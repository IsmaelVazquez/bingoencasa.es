<?php

namespace App\Tests\Fiesta;

use App\Tests\FiestaTestCase;

/**
 * Ciclo de vida de una partida: qué cambios de estado se permiten y que el
 * sorteo y las reclamaciones dejan la partida coherente.
 */
class GameFlowTest extends FiestaTestCase
{
    public function testDrawsAllNinetyBallsWithoutRepeating(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->join($code, 'Ana');
        $gameId = $this->createGame($code)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");

        $drawn = [];
        for ($i = 0; $i < 90; $i++) {
            $data = $this->api($this->host, 'POST', "/api/partidas/$gameId/sacar-bola");
            self::assertTrue($data['exito']);
            self::assertSame($data['bolaActual'], $data['bolasSacadas'][0], 'La bola nueva va la primera');
            $drawn[] = $data['bolaActual'];
        }

        sort($drawn);
        self::assertSame(range(1, 90), $drawn);

        $this->api($this->host, 'POST', "/api/partidas/$gameId/sacar-bola");
        self::assertSame(400, $this->statusCode($this->host));
    }

    public function testCannotDrawBeforeStartOrStartTwice(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->join($code, 'Ana');
        $gameId = $this->createGame($code)['partidaId'];

        $this->api($this->host, 'POST', "/api/partidas/$gameId/sacar-bola");
        self::assertSame(400, $this->statusCode($this->host));

        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");
        self::assertSame(200, $this->statusCode($this->host));
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");
        self::assertSame(409, $this->statusCode($this->host));
    }

    public function testClaimBeforeStartIsRejectedAndDoesNotStartTheGame(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        [$ana, $anaId] = $this->join($code, 'Ana');
        $gameId = $this->createGame($code)['partidaId'];

        $this->claim($ana, $anaId, $gameId);

        self::assertSame(409, $this->statusCode($ana));
        self::assertSame('DETENIDA', $this->gameStatus($gameId));
    }

    public function testOnlyOneClaimAtATimeAndNoResumeWhilePending(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        [$ana, $anaId] = $this->join($code, 'Ana');
        [$luis, $luisId] = $this->join($code, 'Luis');
        $gameId = $this->createGame($code)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");
        $this->api($this->host, 'POST', "/api/partidas/$gameId/sacar-bola");

        $claim = $this->claim($ana, $anaId, $gameId, 'LINEA');
        self::assertSame(201, $this->statusCode($ana));
        self::assertSame('PAUSADA', $this->gameStatus($gameId));

        $this->claim($luis, $luisId, $gameId);
        self::assertSame(409, $this->statusCode($luis));

        $this->api($this->host, 'POST', "/api/partidas/$gameId/reanudar");
        self::assertSame(409, $this->statusCode($this->host));

        $this->api($this->host, 'POST', "/api/reclamaciones/{$claim['reclamacionId']}/rechazar");
        self::assertSame(200, $this->statusCode($this->host));
        self::assertSame('EN_JUEGO', $this->gameStatus($gameId));

        // Ya resuelta: no se puede aprobar después.
        $this->api($this->host, 'POST', "/api/reclamaciones/{$claim['reclamacionId']}/aprobar");
        self::assertSame(409, $this->statusCode($this->host));
    }

    public function testFinishedGameCannotBeRevived(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        [$ana, $anaId] = $this->join($code, 'Ana');
        $gameId = $this->createGame($code)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");

        $claim = $this->claim($ana, $anaId, $gameId);
        $this->api($this->host, 'POST', "/api/reclamaciones/{$claim['reclamacionId']}/aprobar");
        self::assertSame('FINALIZADA', $this->gameStatus($gameId));

        foreach (['comenzar', 'reanudar', 'pausar'] as $action) {
            $this->api($this->host, 'POST', "/api/partidas/$gameId/$action");
            self::assertSame(409, $this->statusCode($this->host), $action);
        }
        self::assertSame('FINALIZADA', $this->gameStatus($gameId));

        // Finalizar otra vez no devuelve los cartones al pool por segunda vez.
        $data = $this->api($this->host, 'POST', "/api/partidas/$gameId/finalizar");
        self::assertSame(0, $data['cartonesLiberados']);
    }

    public function testClaimOnFinishedGameCannotBeApproved(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        [$ana, $anaId] = $this->join($code, 'Ana');
        $gameId = $this->createGame($code)['partidaId'];
        $this->api($this->host, 'POST', "/api/partidas/$gameId/comenzar");

        $claim = $this->claim($ana, $anaId, $gameId);
        $this->api($this->host, 'POST', "/api/partidas/$gameId/finalizar");

        $this->api($this->host, 'POST', "/api/reclamaciones/{$claim['reclamacionId']}/aprobar");
        self::assertSame(409, $this->statusCode($this->host));
    }
}
