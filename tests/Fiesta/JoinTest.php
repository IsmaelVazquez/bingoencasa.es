<?php

namespace App\Tests\Fiesta;

use App\Tests\FiestaTestCase;

class JoinTest extends FiestaTestCase
{
    public function testNicknameTakenByAnotherDeviceIsRejected(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->join($code, 'Ana');

        $other = $this->newBrowser();
        $other->request('POST', '/fiesta/unirse', ['codigo' => $code, 'pin' => $this->pinOf($code), 'apodo' => 'Ana']);

        self::assertFalse($other->getResponse()->isRedirect());
        self::assertStringContainsString('ya está en uso', (string) $other->getResponse()->getContent());
    }

    public function testFullRoomRejectsNewPlayers(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        $this->em()->getConnection()->executeStatement('UPDATE room SET max_players = 1 WHERE code = ?', [$code]);
        $this->join($code, 'Ana');

        $other = $this->newBrowser();
        $other->request('POST', '/fiesta/unirse', ['codigo' => $code, 'pin' => $this->pinOf($code), 'apodo' => 'Luis']);

        self::assertStringContainsString('La sala está llena', (string) $other->getResponse()->getContent());
    }

    public function testSameDeviceCanRejoinWithItsNickname(): void
    {
        $code = $this->createRoom()['salaCodigo'];
        [$ana, $anaId] = $this->join($code, 'Ana');

        [, $again] = $this->join($code, 'Ana', $ana);

        self::assertSame($anaId, $again);
    }
}
