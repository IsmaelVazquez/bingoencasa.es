<?php

namespace App\Tests\Fiesta;

use App\Service\RoomReleaser;
use App\Tests\FiestaTestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Liberar una sala la deja como nueva para el siguiente anfitrión, sin tocar
 * lo que estén usando otras salas.
 */
class RoomReleaseTest extends FiestaTestCase
{
    public function testReleasedRoomComesBackEmpty(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        [$ana] = $this->join($code, 'Ana');
        $this->createGame($code);

        $this->api($this->host, 'POST', "/api/salas/$code/liberar");
        self::assertSame(200, $this->statusCode($this->host));

        $conn = $this->em()->getConnection();
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM player'));
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM game'));
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM card_template WHERE NOT is_available'));

        // El jugador antiguo ya no ve la sala.
        $this->api($ana, 'GET', "/api/salas/$code/lobby");
        self::assertSame(404, $this->statusCode($ana));
    }

    public function testReleasingDoesNotFreeCardsReusedByAnotherRoom(): void
    {
        // Pool de un solo cartón: la segunda sala recibe el que soltó la primera.
        $this->resetDatabase(rooms: 2, cardTemplates: 1);

        $codeA = $this->createRoom()['salaCodigo'];
        [$ana, $anaId] = $this->join($codeA, 'Ana');
        $gameA = $this->createGame($codeA)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameA/comenzar");
        $claim = $this->claim($ana, $anaId, $gameA);
        $this->api($this->host, 'POST', "/api/reclamaciones/{$claim['reclamacionId']}/aprobar");

        $hostB = $this->newBrowser();
        $codeB = $this->api($hostB, 'POST', '/api/salas', [])['salaCodigo'];
        $this->join($codeB, 'Luis');
        $this->api($hostB, 'POST', "/api/salas/$codeB/partidas", []);
        self::assertSame(201, $this->statusCode($hostB));

        // La sala A se libera con su partida ya terminada: el cartón sigue en uso por B.
        $this->api($this->host, 'POST', "/api/salas/$codeA/liberar");
        self::assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM card_template WHERE is_available'));
    }

    public function testNotEnoughCardsRollsBack(): void
    {
        $this->resetDatabase(rooms: 1, cardTemplates: 3);
        $code = $this->createRoom()['salaCodigo'];
        $this->join($code, 'Ana');
        $this->join($code, 'Luis');

        $this->api($this->host, 'POST', "/api/salas/$code/partidas", ['cartonesPorJugador' => 2]);
        self::assertSame(503, $this->statusCode($this->host));

        $conn = $this->em()->getConnection();
        self::assertSame(3, (int) $conn->fetchOne('SELECT COUNT(*) FROM card_template WHERE is_available'));
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM game'));
    }

    public function testStaleRoomsCommandOnlyReleasesInactiveRooms(): void
    {
        $old = $this->createRoom()['salaCodigo'];
        $this->join($old, 'Ana');
        $this->createGame($old);

        $recentHost = $this->newBrowser();
        $recent = $this->api($recentHost, 'POST', '/api/salas', [])['salaCodigo'];

        $this->em()->getConnection()->executeStatement(
            "UPDATE room SET last_used_at = NOW() - INTERVAL '13 hours' WHERE code = ?",
            [$old]
        );

        $tester = new CommandTester((new Application(self::$kernel))->find('app:rooms:release-stale'));
        $tester->execute(['--hours' => 12]);
        $tester->assertCommandIsSuccessful();

        $conn = $this->em()->getConnection();
        self::assertTrue((bool) $conn->fetchOne('SELECT is_available FROM room WHERE code = ?', [$old]));
        self::assertFalse((bool) $conn->fetchOne('SELECT is_available FROM room WHERE code = ?', [$recent]));
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM player'));
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM game'));
    }

    public function testActivityKeepsRoomAlive(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->em()->getConnection()->executeStatement(
            "UPDATE room SET last_used_at = NOW() - INTERVAL '13 hours' WHERE code = ?",
            [$code]
        );

        $this->join($code, 'Ana');

        $released = static::getContainer()->get(RoomReleaser::class)->releaseStale(new \DateTimeImmutable('-12 hours'));
        self::assertSame(0, $released);
    }

    public function testReleaseAllCleansEveryRoom(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->join($code, 'Ana');
        $this->createGame($code);

        $released = static::getContainer()->get(RoomReleaser::class)->releaseAll();

        self::assertSame(1, $released);
        $conn = $this->em()->getConnection();
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM room WHERE NOT is_available'));
        self::assertSame(0, (int) $conn->fetchOne('SELECT COUNT(*) FROM player'));
    }
}
