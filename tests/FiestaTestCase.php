<?php

namespace App\Tests;

use App\Entity\CardTemplate;
use App\Entity\Room;
use App\Service\SpanishBingoCardGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base de los tests del Modo Fiesta: base de datos de test vacía, un pool
 * pequeño de salas y cartones, y un navegador por persona (cada uno con su
 * propia sesión, que es lo que identifica al anfitrión y a los jugadores).
 */
abstract class FiestaTestCase extends WebTestCase
{
    protected const PIN = '1234';

    protected KernelBrowser $host;

    protected function setUp(): void
    {
        $this->host = static::createClient();
        $this->resetDatabase();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function resetDatabase(int $rooms = 5, int $cardTemplates = 60): void
    {
        $this->em()->getConnection()->executeStatement(
            'TRUNCATE claim, draw, card, player, game, room, card_template RESTART IDENTITY CASCADE'
        );
        $this->createPool($rooms, $cardTemplates);
    }

    protected function createPool(int $rooms, int $cardTemplates): void
    {
        $em = $this->em();
        for ($i = 1; $i <= $rooms; $i++) {
            $room = new Room();
            $room->setName('Sala de Bingo');
            $room->setCode('SALA' . $i);
            $room->setPin(self::PIN);
            $em->persist($room);
        }

        $generator = new SpanishBingoCardGenerator();
        for ($i = 0; $i < $cardTemplates; $i++) {
            $data = $generator->generateCard();
            $template = new CardTemplate();
            $template->setGrid($data['grid']);
            $template->setFlatNumbers($data['flatNumbers']);
            $em->persist($template);
        }

        $em->flush();
        $em->clear();
    }

    /** Navegador nuevo, con su propia sesión, sobre el mismo kernel. */
    protected function newBrowser(): KernelBrowser
    {
        return static::getContainer()->get('test.client');
    }

    /** @return array<string, mixed> */
    protected function api(KernelBrowser $browser, string $method, string $uri, array $body = []): array
    {
        $browser->request($method, $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body));

        return json_decode((string) $browser->getResponse()->getContent(), true) ?? [];
    }

    protected function statusCode(KernelBrowser $browser): int
    {
        return $browser->getResponse()->getStatusCode();
    }

    /** @return array<string, mixed> datos de la sala creada */
    protected function createRoom(): array
    {
        $data = $this->api($this->host, 'POST', '/api/salas', ['nombreSala' => 'Test']);
        self::assertSame(201, $this->statusCode($this->host), json_encode($data));

        return $data;
    }

    /** Une un jugador nuevo a la sala y devuelve [navegador, jugadorId]. */
    protected function join(string $code, string $nickname, ?KernelBrowser $browser = null): array
    {
        $browser ??= $this->newBrowser();
        $browser->request('POST', '/fiesta/unirse', ['codigo' => $code, 'pin' => $this->pinOf($code), 'apodo' => $nickname]);
        $response = $browser->getResponse();
        self::assertTrue($response->isRedirect(), 'No se pudo unir ' . $nickname . ': ' . strip_tags((string) $response->getContent()));
        preg_match('~/jugar/([0-9A-Z]+)$~', (string) $response->headers->get('Location'), $m);

        return [$browser, $m[1]];
    }

    protected function pinOf(string $code): string
    {
        return (string) $this->em()->getConnection()->fetchOne('SELECT pin FROM room WHERE code = ?', [$code]);
    }

    /** @return array<string, mixed> */
    protected function createGame(string $code, int $cardsPerPlayer = 1): array
    {
        $data = $this->api($this->host, 'POST', "/api/salas/$code/partidas", ['cartonesPorJugador' => $cardsPerPlayer]);
        self::assertSame(201, $this->statusCode($this->host), json_encode($data));

        return $data;
    }

    protected function firstCardOf(KernelBrowser $player, string $playerId, string $gameId): string
    {
        $data = $this->api($player, 'GET', "/api/jugadores/$playerId/cartones?partidaId=$gameId");

        return $data['cartones'][0]['cartonId'];
    }

    protected function claim(KernelBrowser $player, string $playerId, string $gameId, string $type = 'BINGO'): array
    {
        return $this->api($player, 'POST', '/api/reclamaciones', [
            'partidaId' => $gameId,
            'jugadorId' => $playerId,
            'cartonId' => $this->firstCardOf($player, $playerId, $gameId),
            'tipo' => $type,
        ]);
    }

    protected function gameStatus(string $gameId): string
    {
        return $this->api($this->host, 'GET', "/api/partidas/$gameId/estado")['estadoPartida'];
    }

    protected function sessionId(KernelBrowser $browser): string
    {
        return (string) $browser->getCookieJar()->get('MOCKSESSID')?->getValue();
    }
}
