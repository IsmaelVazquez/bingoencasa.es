<?php

namespace App\Controller;

use App\Entity\Player;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\RoomRepository;
use App\Service\FiestaAccess;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

/**
 * Páginas web del Modo Fiesta orientadas al jugador.
 *
 * El anfitrión vive íntegramente en la app React (entrada en `/`). El flujo
 * antiguo de anfitrión en Twig (index/crear/lobby/partida_host) se retiró para
 * dejar un único anfitrión. Aquí solo quedan las pantallas del invitado:
 * unirse a una sala (destino del QR) y jugar.
 */
#[Route('/fiesta', name: 'fiesta_')]
class FiestaWebController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoomRepository $roomRepository,
        private PlayerRepository $playerRepository,
        private GameRepository $gameRepository,
        private FiestaAccess $access,
    ) {}

    #[Route('/unirse', name: 'unirse', methods: ['GET', 'POST'])]
    public function unirse(Request $request, RateLimiterFactoryInterface $fiestaPinFallidoLimiter): Response
    {
        $error = null;
        $codigoFromQr = $request->query->get('codigo', '');
        $pinFromQr = $request->query->get('pin', '');

        if ($request->isMethod('POST')) {
            $codigo = strtoupper(trim((string) $request->request->get('codigo', '')));
            $pin = trim((string) $request->request->get('pin', ''));
            $apodo = Player::normalizeNickname($request->request->get('apodo'));
            $limiter = $fiestaPinFallidoLimiter->create($request->getClientIp() . '|' . $codigo);

            if ($codigo === '' || $pin === '' || $apodo === null) {
                $error = 'Por favor, introduce el código de sala, PIN y tu apodo.';
            } elseif ($limiter->consume(0)->getRemainingTokens() === 0) {
                $error = 'Demasiados intentos fallidos. Espera unos minutos y vuelve a probar.';
            } else {
                $room = $this->roomRepository->findOneBy(['code' => $codigo]);

                if (!$room || $room->isAvailable() || !hash_equals((string) $room->getPin(), $pin)) {
                    $limiter->consume();
                    $error = 'Código de sala o PIN incorrectos.';
                } else {
                    // Con la sala bloqueada, dos invitados a la vez no pueden coger
                    // el mismo apodo ni pasarse del aforo.
                    $resultado = $this->em->wrapInTransaction(function () use ($room, $apodo, $pin): Player|string {
                        $this->em->refresh($room, LockMode::PESSIMISTIC_WRITE);
                        if ($room->isAvailable() || !hash_equals((string) $room->getPin(), $pin)) {
                            // La sala se ha liberado mientras tanto.
                            return 'Código de sala o PIN incorrectos.';
                        }

                        $existingPlayer = $this->playerRepository->findOneBy([
                            'room' => $room,
                            'nickname' => $apodo,
                        ]);

                        if ($existingPlayer && !$this->access->isPlayer($existingPlayer)) {
                            // El apodo es de otro dispositivo: no se le puede suplantar.
                            return 'Ese apodo ya está en uso en esta sala. Elige otro.';
                        }
                        if ($existingPlayer) {
                            return $existingPlayer;
                        }
                        if ($room->isFull()) {
                            return 'La sala está llena (máximo ' . $room->getMaxPlayers() . ' jugadores).';
                        }

                        $player = new Player();
                        $player->setNickname($apodo);
                        $room->addPlayer($player);
                        $room->touch();
                        $this->em->persist($player);

                        return $player;
                    });

                    if (is_string($resultado)) {
                        $error = $resultado;
                    } else {
                        $this->access->grantPlayer($resultado);

                        return $this->redirectToRoute('fiesta_jugar', [
                            'codigo' => $room->getCode(),
                            'jugadorId' => $resultado->getId()->toBase32(),
                        ]);
                    }
                }
            }
        }

        return $this->render('fiesta/unirse.html.twig', [
            'error' => $error,
            'codigoFromQr' => $codigoFromQr,
            'pinFromQr' => $pinFromQr,
        ]);
    }

    #[Route('/sala/{codigo}/jugar/{jugadorId}', name: 'jugar', methods: ['GET'])]
    public function jugar(string $codigo, string $jugadorId): Response
    {
        $room = $this->roomRepository->findOneBy(['code' => $codigo]);
        $player = $this->findPlayerById($jugadorId);

        if (!$room || !$player || $player->getRoom() !== $room || !$this->access->isPlayer($player)) {
            // Sin sesión de este jugador (otro dispositivo, enlace compartido…): que vuelva a unirse.
            return $this->redirectToRoute('fiesta_unirse', ['codigo' => $codigo]);
        }

        $game = $this->gameRepository->findOneBy(['room' => $room], ['createdAt' => 'DESC']);

        return $this->render('fiesta/jugar.html.twig', [
            'room' => $room,
            'player' => $player,
            'game' => $game,
        ]);
    }

    private function findPlayerById(string $id): ?Player
    {
        if (!Ulid::isValid($id)) {
            return null;
        }

        return $this->playerRepository->find(Ulid::fromString($id));
    }
}
