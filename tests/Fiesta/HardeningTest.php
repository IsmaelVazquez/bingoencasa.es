<?php

namespace App\Tests\Fiesta;

use App\Repository\SystemConfigRepository;
use App\Service\SpanishBingoCardGenerator;
use App\Tests\FiestaTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Límites que protegen el pool y las partidas de un uso abusivo.
 */
class HardeningTest extends FiestaTestCase
{
    public function testCannotCreateRoomsWhileFiestaModeIsOff(): void
    {
        static::getContainer()->get(SystemConfigRepository::class)->setValue('modo_fiesta_enabled', '0');

        $this->api($this->host, 'POST', '/api/salas', []);

        self::assertSame(503, $this->statusCode($this->host));
    }

    public function testGameNeedsAtLeastOnePlayer(): void
    {
        $code = $this->createRoom()['salaCodigo'];

        $this->api($this->host, 'POST', "/api/salas/$code/partidas", []);

        self::assertSame(400, $this->statusCode($this->host));
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM game'));
    }

    public function testNewGameClosesTheUnfinishedOneAndReturnsItsCards(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->join($code, 'Ana');
        $this->join($code, 'Luis');

        for ($i = 0; $i < 5; $i++) {
            $this->createGame($code, 2);
        }

        $conn = $this->em()->getConnection();
        // Solo la última partida tiene cartones del pool.
        self::assertSame(4, (int) $conn->fetchOne('SELECT COUNT(*) FROM card_template WHERE NOT is_available'));
        self::assertSame(4, (int) $conn->fetchOne("SELECT COUNT(*) FROM game WHERE status = 'FINALIZADA'"));
    }

    public function testClaimsAreRateLimitedPerPlayer(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        [$ana, $anaId] = $this->join($code, 'Ana');
        $gameId = $this->createGame($code)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");

        for ($i = 0; $i < 4; $i++) {
            $claim = $this->claim($ana, $anaId, $gameId);
            self::assertSame(201, $this->statusCode($ana));
            $this->api($this->host, 'POST', "/api/reclamaciones/{$claim['reclamacionId']}/rechazar");
        }

        $this->claim($ana, $anaId, $gameId);
        self::assertSame(429, $this->statusCode($ana));
        self::assertSame('EN_JUEGO', $this->gameStatus($gameId));
    }

    public function testWrongPinsFromManyAddressesLockTheRoom(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $pin = $this->pinOf($code);
        $wrong = $pin === '0000' ? '1111' : '0000';

        $guest = $this->newBrowser();
        for ($i = 0; $i < 40; $i++) {
            // Cada intento desde una IP distinta: el límite por IP no salta.
            $guest->request('POST', '/fiesta/unirse', ['codigo' => $code, 'pin' => $wrong, 'apodo' => 'X' . $i], server: ['REMOTE_ADDR' => '10.20.0.' . $i]);
        }

        $guest->request('POST', '/fiesta/unirse', ['codigo' => $code, 'pin' => $pin, 'apodo' => 'Ana'], server: ['REMOTE_ADDR' => '10.20.1.1']);

        self::assertFalse($guest->getResponse()->isRedirect());
        self::assertStringContainsString('demasiados intentos', (string) $guest->getResponse()->getContent());
    }

    public function testPlayersAlreadyInTheRoomCanRejoinDuringALockout(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        [$ana, $anaId] = $this->join($code, 'Ana');

        $guest = $this->newBrowser();
        for ($i = 0; $i < 40; $i++) {
            $guest->request('POST', '/fiesta/unirse', ['codigo' => $code, 'pin' => 'xxxx', 'apodo' => 'X' . $i], server: ['REMOTE_ADDR' => '10.20.0.' . $i]);
        }

        [, $again] = $this->join($code, 'Ana', $ana);
        self::assertSame($anaId, $again);
    }

    public function testStateEtagOnlyChangesWithTheGame(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->join($code, 'Ana');
        $gameId = $this->createGame($code)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");

        $this->host->request('GET', "/api/partidas/$gameId/estado");
        $etag = $this->host->getResponse()->headers->get('ETag');
        self::assertSame(['Ana'], array_column(json_decode((string) $this->host->getResponse()->getContent(), true)['jugadores'], 'apodo'));

        $this->host->request('GET', "/api/partidas/$gameId/estado", server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertSame(304, $this->statusCode($this->host));

        $this->api($this->host, 'POST', "/api/partidas/$gameId/sacar-bola");
        $this->host->request('GET', "/api/partidas/$gameId/estado", server: ['HTTP_IF_NONE_MATCH' => $etag]);
        self::assertSame(200, $this->statusCode($this->host));
        self::assertNotSame($etag, $this->host->getResponse()->headers->get('ETag'));
    }

    public function testRegenerateCommandRewritesOnlyFreeCards(): void
    {
        $conn = $this->em()->getConnection();
        $conn->executeStatement("UPDATE card_template SET grid = '[]', flat_numbers = '[]'");
        $conn->executeStatement('UPDATE card_template SET is_available = FALSE WHERE id IN (SELECT id FROM card_template ORDER BY id LIMIT 5)');

        $tester = new CommandTester((new Application(self::$kernel))->find('app:pool:regenerate-cards'));
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $generator = new SpanishBingoCardGenerator();
        foreach ($conn->fetchAllAssociative('SELECT grid, is_available FROM card_template') as $row) {
            $grid = json_decode($row['grid'], true);
            self::assertSame((bool) $row['is_available'], $grid !== [] && $generator->isValid($grid));
        }
    }
}
