<?php

namespace App\Tests\Concurrency;

use App\Tests\FiestaTestCase;
use Symfony\Component\Process\Process;

/**
 * Peticiones simultáneas de verdad: cada una en su propio proceso PHP, con su
 * propia conexión a Postgres, y todas soltadas a la vez con un fichero barrera.
 * Sin los bloqueos, estos tests fallan de forma intermitente; con ellos, nunca.
 */
class ConcurrencyTest extends FiestaTestCase
{
    public function testSimultaneousHostsNeverShareARoom(): void
    {
        $this->resetDatabase(rooms: 4, cardTemplates: 0);

        $results = $this->runAtOnce(array_fill(0, 8, ['acquire-room']));

        $rooms = array_filter($results);
        self::assertCount(4, $rooms, 'Las 4 salas se reparten y los otros 4 se quedan sin sala');
        self::assertCount(4, array_unique($rooms), 'Ninguna sala se entrega dos veces');
    }

    public function testSimultaneousGamesNeverShareCards(): void
    {
        $this->resetDatabase(rooms: 1, cardTemplates: 20);

        $results = $this->runAtOnce(array_fill(0, 6, ['take-cards', '5']));

        $ids = array_merge(...array_map(fn ($r) => $r === '' ? [] : explode(',', $r), $results));
        self::assertCount(20, $ids);
        self::assertCount(20, array_unique($ids), 'Ningún cartón se entrega dos veces');
    }

    public function testDoubleClickNeverDrawsTheSameBallTwice(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->join($code, 'Ana');
        $gameId = $this->createGame($code)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");

        $sid = $this->sessionId($this->host);
        $results = $this->runAtOnce(array_fill(0, 10, ['json', $sid, 'POST', "/api/partidas/$gameId/sacar-bola", '{}']));

        self::assertSame(array_fill(0, 10, '200'), $results);
        $conn = $this->em()->getConnection();
        self::assertSame(10, (int) $conn->fetchOne('SELECT COUNT(DISTINCT number) FROM draw'));
        self::assertSame(10, (int) $conn->fetchOne('SELECT COUNT(*) FROM draw'));
    }

    public function testSimultaneousClaimsLeaveOnlyOnePending(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $players = [];
        for ($i = 1; $i <= 6; $i++) {
            $players[] = $this->join($code, 'Jugador ' . $i);
        }
        $gameId = $this->createGame($code)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");

        $jobs = [];
        foreach ($players as [$browser, $playerId]) {
            $body = json_encode([
                'partidaId' => $gameId,
                'jugadorId' => $playerId,
                'cartonId' => $this->firstCardOf($browser, $playerId, $gameId),
                'tipo' => 'BINGO',
            ]);
            $jobs[] = ['json', $this->sessionId($browser), 'POST', '/api/reclamaciones', $body];
        }

        $results = $this->runAtOnce($jobs);

        sort($results);
        self::assertSame(['201', '409', '409', '409', '409', '409'], $results);
        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne("SELECT COUNT(*) FROM claim WHERE status = 'PENDIENTE'"));
    }

    public function testSimultaneousJoinsRespectRoomCapacity(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $conn = $this->em()->getConnection();
        $conn->executeStatement('UPDATE room SET max_players = 3 WHERE code = ?', [$code]);
        $pin = $this->pinOf($code);

        $jobs = [];
        for ($i = 1; $i <= 8; $i++) {
            $jobs[] = ['form', '', '/fiesta/unirse', json_encode(['codigo' => $code, 'pin' => $pin, 'apodo' => 'Jugador ' . $i])];
        }
        $this->runAtOnce($jobs);

        self::assertSame(3, (int) $conn->fetchOne('SELECT COUNT(*) FROM player'));
    }

    public function testSameNicknameFromTwoDevicesAtOnceCreatesOnePlayer(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $pin = $this->pinOf($code);

        $job = ['form', '', '/fiesta/unirse', json_encode(['codigo' => $code, 'pin' => $pin, 'apodo' => 'Ana'])];
        $this->runAtOnce(array_fill(0, 6, $job));

        self::assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM player'));
    }

    /**
     * Lanza un proceso por trabajo, espera a que todos hayan arrancado y los
     * suelta a la vez. Devuelve la última línea que imprime cada uno.
     *
     * @param list<list<string>> $jobs
     * @return list<string>
     */
    private function runAtOnce(array $jobs): array
    {
        // Nada abierto en este proceso que pueda bloquear a los hijos.
        $this->em()->clear();

        $barrier = sys_get_temp_dir() . '/bingo-barrier-' . bin2hex(random_bytes(6));
        $processes = [];
        foreach ($jobs as $job) {
            $process = new Process(['php', __DIR__ . '/worker.php', $barrier, ...$job], env: ['APP_ENV' => 'test', 'XDEBUG_MODE' => 'off']);
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }

        foreach ($processes as $process) {
            $process->waitUntil(fn ($type, $out) => str_contains($process->getOutput(), "READY\n") || !$process->isRunning());
        }
        touch($barrier);

        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            self::assertTrue($process->isSuccessful(), $process->getErrorOutput() . $process->getOutput());
            $lines = explode("\n", trim($process->getOutput()));
            $results[] = $lines[1] ?? '';
        }
        unlink($barrier);

        return $results;
    }
}
