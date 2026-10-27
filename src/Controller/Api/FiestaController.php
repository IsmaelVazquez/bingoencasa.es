<?php

namespace App\Controller\Api;

use App\Entity\Card;
use App\Entity\CardTemplate;
use App\Entity\Claim;
use App\Entity\Draw;
use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Room;
use App\Enum\ClaimStatus;
use App\Enum\ClaimType;
use App\Enum\GameStatus;
use App\Repository\CardRepository;
use App\Repository\CardTemplateRepository;
use App\Repository\ClaimRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\RoomRepository;
use App\Repository\SystemConfigRepository;
use App\Service\BingoCardGenerator;
use App\Service\FiestaAccess;
use App\Service\RoomCodeGenerator;
use App\Service\RoomReleaser;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Ulid;

#[Route('/api', name: 'api_fiesta_')]
class FiestaController extends AbstractController
{
    private const MAX_CARTONES_POR_JUGADOR = 6;

    public function __construct(
        private EntityManagerInterface $em,
        private RoomRepository $roomRepository,
        private GameRepository $gameRepository,
        private PlayerRepository $playerRepository,
        private CardRepository $cardRepository,
        private CardTemplateRepository $cardTemplateRepository,
        private ClaimRepository $claimRepository,
        private SystemConfigRepository $configRepository,
        private BingoCardGenerator $cardGenerator,
        private RoomCodeGenerator $codeGenerator,
        private FiestaAccess $access,
        private RoomReleaser $roomReleaser,
    ) {}

    // ==================== ESTADO DEL SISTEMA ====================

    #[Route('/estado', name: 'estado_sistema', methods: ['GET'])]
    public function estadoSistema(): JsonResponse
    {
        $salasDisponibles = $this->roomRepository->countAvailable();
        $totalSalas = $this->roomRepository->count([]);

        return $this->json([
            'modoBombo' => $this->configRepository->isEnabled('modo_bombo_enabled', true),
            'modoFiesta' => $this->configRepository->isEnabled('modo_fiesta_enabled', true),
            'salasDisponibles' => $salasDisponibles,
            'totalSalas' => $totalSalas,
            'fiestaLleno' => $salasDisponibles === 0,
        ]);
    }

    // ==================== SALAS ====================

    #[Route('/salas', name: 'crear_sala', methods: ['POST'])]
    public function crearSala(Request $request, RateLimiterFactoryInterface $fiestaCrearSalaLimiter): JsonResponse
    {
        if (!$this->configRepository->isEnabled('modo_fiesta_enabled', true)) {
            return $this->json([
                'exito' => false,
                'error' => 'El Modo Fiesta está desactivado en este momento.',
            ], 503);
        }
        if (!$fiestaCrearSalaLimiter->create($request->getClientIp())->consume()->isAccepted()) {
            return $this->json([
                'exito' => false,
                'error' => 'Has creado demasiadas salas seguidas. Espera un rato.',
            ], 429);
        }

        $data = $this->jsonBody($request);
        $nombreSala = is_string($data['nombreSala'] ?? null) ? trim(mb_substr($data['nombreSala'], 0, 60)) : '';
        $nombreSala = $nombreSala !== '' ? $nombreSala : 'Sala Bingo';

        $room = $this->roomRepository->acquireAvailableRoom();

        if (!$room) {
            return $this->json([
                'exito' => false,
                'error' => 'No hay salas disponibles en este momento. Inténtalo de nuevo más tarde.',
            ], 503);
        }

        $room->setName($nombreSala);
        $room->setPin($this->codeGenerator->generatePin());
        $this->access->grantHost($room);

        $this->em->flush();

        return $this->json([
            'exito' => true,
            'salaId' => $room->getId()->toBase32(),
            'salaCodigo' => $room->getCode(),
            'salaPin' => $room->getPin(),
            'nombreSala' => $room->getName(),
            'maxJugadores' => $room->getMaxPlayers(),
            'creadaEn' => $room->getCreatedAt()->format('Y-m-d H:i:s'),
        ], 201);
    }

    #[Route('/salas/{salaCodigo}/lobby', name: 'lobby_sala', methods: ['GET'])]
    public function lobbySala(string $salaCodigo, Request $request): JsonResponse
    {
        $room = $this->roomRepository->findOneBy(['code' => strtoupper($salaCodigo)]);
        if (!$room || !$this->access->canSeeRoom($room)) {
            return $this->json(['exito' => false, 'error' => 'Sala no encontrada'], 404);
        }

        $jugadores = [];
        foreach ($room->getPlayers() as $player) {
            $jugadores[] = [
                'jugadorId' => $player->getId()->toBase32(),
                'apodo' => $player->getNickname(),
            ];
        }

        $game = $this->gameRepository->findOneBy(['room' => $room], ['createdAt' => 'DESC']);

        $partidaActiva = null;
        if ($game) {
            $partidaActiva = [
                'id' => $game->getId()->toBase32(),
                'estado' => $game->getStatus(),
                'premioObjetivo' => $game->getPrizeTarget(),
            ];
        }

        // Generate ETag based on full player list and game state
        $playerIds = array_map(fn($j) => $j['jugadorId'], $jugadores);
        sort($playerIds);
        $etag = md5(json_encode([
            implode(',', $playerIds),
            $game?->getStatus(),
            $game?->getId()?->toBase32()
        ]));
        
        // Check If-None-Match header
        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304);
        }

        $response = $this->json([
            'exito' => true,
            'salaCodigo' => $room->getCode(),
            'nombreSala' => $room->getName(),
            'jugadores' => $jugadores,
            'totalJugadores' => count($jugadores),
            'estado' => $game ? $game->getStatus() : 'ESPERANDO',
            'partidaId' => $game?->getId()?->toBase32(),
            'partidaActiva' => $partidaActiva,
        ]);
        
        // Add cache headers
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'private, max-age=1');
        
        return $response;
    }

    // ==================== PARTIDAS ====================

    #[Route('/salas/{salaCodigo}/partidas', name: 'crear_partida', methods: ['POST'])]
    public function crearPartida(string $salaCodigo, Request $request): JsonResponse
    {
        $room = $this->roomRepository->findOneBy(['code' => strtoupper($salaCodigo)]);
        if (!$room) {
            return $this->json(['exito' => false, 'error' => 'Sala no encontrada'], 404);
        }
        if (!$this->access->isHost($room)) {
            return $this->forbidden();
        }

        $data = $this->jsonBody($request);
        $seed = random_int(1, 1000000);
        $cartonesPorJugador = max(1, min(self::MAX_CARTONES_POR_JUGADOR, (int) ($data['cartonesPorJugador'] ?? 1)));
        $premioObjetivo = ($data['premioObjetivo'] ?? 'LINEA') === 'BINGO' ? 'BINGO' : 'LINEA';
        $precioCarton = max(0.0, min(1000.0, (float) ($data['precioCarton'] ?? 0)));
        $porcentajeLinea = max(0, min(100, (int) ($data['porcentajeLinea'] ?? 30)));
        $porcentajeBingo = max(0, min(100 - $porcentajeLinea, (int) ($data['porcentajeBingo'] ?? 70)));

        try {
            [$game, $totalCartones] = $this->em->wrapInTransaction(
                function () use ($room, $seed, $cartonesPorJugador, $premioObjetivo, $precioCarton, $porcentajeLinea, $porcentajeBingo): array {
                    // Con la sala bloqueada nadie entra mientras se reparten los cartones.
                    $this->em->refresh($room, LockMode::PESSIMISTIC_WRITE);

                    if ($room->getPlayers()->isEmpty()) {
                        throw new \LengthException('Espera a que se una al menos un jugador.');
                    }

                    // Una partida sin terminar de esta sala (un reintento, una
                    // pestaña vieja) se cierra y devuelve sus cartones: si no,
                    // cada intento se quedaría con un lote más del pool.
                    foreach ($this->gameRepository->findBy(['room' => $room]) as $previous) {
                        if ($previous->getStatus() !== GameStatus::FINALIZADA->value) {
                            $this->lockGame($previous);
                            $this->finalizeGame($previous);
                        }
                    }

                    $totalCartonesNecesarios = $room->getPlayers()->count() * $cartonesPorJugador;
                    $cardTemplates = $this->cardTemplateRepository->getAvailableCards($totalCartonesNecesarios);
                    if (count($cardTemplates) < $totalCartonesNecesarios) {
                        // Se deshace la transacción y los cartones cogidos vuelven al pool.
                        throw new \OverflowException((string) $totalCartonesNecesarios);
                    }

                    $game = new Game();
                    $game->setRoom($room);
                    $game->setSeed($seed);
                    $game->setStatus(GameStatus::DETENIDA);
                    $game->setPrizeTarget($premioObjetivo);
                    $game->setCardPrice($precioCarton);
                    $game->setLineaPrizePercent($porcentajeLinea);
                    $game->setBingoPrizePercent($porcentajeBingo);
                    $this->em->persist($game);

                    $templateIndex = 0;
                    foreach ($room->getPlayers() as $player) {
                        for ($i = 0; $i < $cartonesPorJugador; $i++) {
                            $template = $cardTemplates[$templateIndex++];

                            $card = new Card();
                            $card->setPlayer($player);
                            $card->setGame($game);
                            $card->setSeed($template->getId());
                            $card->setGrid($template->getGrid());
                            $card->setFlatNumbers($template->getFlatNumbers());
                            $this->em->persist($card);
                        }
                    }

                    $room->touch();

                    return [$game, $templateIndex];
                }
            );
        } catch (\LengthException $e) {
            return $this->json(['exito' => false, 'error' => $e->getMessage()], 400);
        } catch (\OverflowException $e) {
            return $this->json([
                'exito' => false,
                'error' => 'No hay suficientes cartones disponibles. Disponibles: ' . $this->cardTemplateRepository->countAvailable() . ', Necesarios: ' . $e->getMessage(),
            ], 503);
        }

        $boteTotal = $totalCartones * $precioCarton;
        $premioLinea = $boteTotal * ($porcentajeLinea / 100);
        $premioBingo = $boteTotal * ($porcentajeBingo / 100);

        return $this->json([
            'exito' => true,
            'partidaId' => $game->getId()->toBase32(),
            'estadoPartida' => $game->getStatus(),
            'premioObjetivo' => $game->getPrizeTarget(),
            'totalCartones' => $totalCartones,
            'precioCarton' => $precioCarton,
            'boteTotal' => $boteTotal,
            'premioLinea' => $premioLinea,
            'premioBingo' => $premioBingo,
        ], 201);
    }

    #[Route('/partidas/{partidaId}/comenzar', name: 'comenzar_partida', methods: ['POST'])]
    public function comenzarPartida(string $partidaId): JsonResponse
    {
        $game = $this->findGameById($partidaId);
        if (!$game) {
            return $this->json(['exito' => false, 'error' => 'Partida no encontrada'], 404);
        }
        if (!$this->access->isHost($game->getRoom())) {
            return $this->forbidden();
        }

        return $this->em->wrapInTransaction(function () use ($game): JsonResponse {
            $this->lockGame($game);
            if ($game->getStatusEnum() !== GameStatus::DETENIDA) {
                return $this->invalidState($game);
            }

            $game->setStatus(GameStatus::EN_JUEGO);
            $game->setStartedAt(new \DateTimeImmutable());
            $game->getRoom()->touch();

            return $this->json([
                'exito' => true,
                'estadoPartida' => $game->getStatus(),
                'mensaje' => '¡La partida ha comenzado!',
            ]);
        });
    }

    #[Route('/partidas/{partidaId}/pausar', name: 'pausar_partida', methods: ['POST'])]
    public function pausarPartida(string $partidaId): JsonResponse
    {
        $game = $this->findGameById($partidaId);
        if (!$game) {
            return $this->json(['exito' => false, 'error' => 'Partida no encontrada'], 404);
        }
        if (!$this->access->isHost($game->getRoom())) {
            return $this->forbidden();
        }

        return $this->em->wrapInTransaction(function () use ($game): JsonResponse {
            $this->lockGame($game);
            if (!$game->canTransitionTo(GameStatus::PAUSADA)) {
                return $this->invalidState($game);
            }

            $game->setStatus(GameStatus::PAUSADA);

            return $this->json([
                'exito' => true,
                'estadoPartida' => $game->getStatus(),
                'mensaje' => 'Partida pausada',
            ]);
        });
    }

    #[Route('/partidas/{partidaId}/reanudar', name: 'reanudar_partida', methods: ['POST'])]
    public function reanudarPartida(string $partidaId): JsonResponse
    {
        $game = $this->findGameById($partidaId);
        if (!$game) {
            return $this->json(['exito' => false, 'error' => 'Partida no encontrada'], 404);
        }
        if (!$this->access->isHost($game->getRoom())) {
            return $this->forbidden();
        }

        return $this->em->wrapInTransaction(function () use ($game): JsonResponse {
            $this->lockGame($game);
            if ($game->getStatusEnum() !== GameStatus::PAUSADA) {
                return $this->invalidState($game);
            }
            if ($game->hasPendingClaim()) {
                return $this->json([
                    'exito' => false,
                    'error' => 'Hay una reclamación sin resolver. Apruébala o recházala antes de seguir.',
                ], 409);
            }

            $game->setStatus(GameStatus::EN_JUEGO);
            $game->getRoom()->touch();

            return $this->json([
                'exito' => true,
                'estadoPartida' => $game->getStatus(),
                'mensaje' => '¡Partida reanudada!',
            ]);
        });
    }

    #[Route('/partidas/{partidaId}/finalizar', name: 'finalizar_partida', methods: ['POST'])]
    public function finalizarPartida(string $partidaId, Request $request): JsonResponse
    {
        $game = $this->findGameById($partidaId);
        if (!$game) {
            return $this->json(['exito' => false, 'error' => 'Partida no encontrada'], 404);
        }
        if (!$this->access->isHost($game->getRoom())) {
            return $this->forbidden();
        }

        $data = $this->jsonBody($request);
        $liberarSala = ($data['liberarSala'] ?? false) === true;

        [$cartonesLiberados, $salaLiberada] = $this->em->wrapInTransaction(function () use ($game, $liberarSala): array {
            $this->lockGame($game);
            $cartonesLiberados = $this->finalizeGame($game);

            $room = $game->getRoom();
            if ($liberarSala && $room) {
                $this->em->refresh($room, LockMode::PESSIMISTIC_WRITE);
                $this->roomReleaser->release($room);

                return [$cartonesLiberados, true];
            }

            return [$cartonesLiberados, false];
        });

        return $this->json([
            'exito' => true,
            'estadoPartida' => $game->getStatus(),
            'cartonesLiberados' => $cartonesLiberados,
            'salaLiberada' => $salaLiberada,
            'mensaje' => $salaLiberada ? 'Partida finalizada y sala liberada' : 'Partida finalizada',
        ]);
    }

    #[Route('/salas/{salaCodigo}/liberar', name: 'liberar_sala', methods: ['POST'])]
    public function liberarSala(string $salaCodigo): JsonResponse
    {
        $room = $this->roomRepository->findOneBy(['code' => strtoupper($salaCodigo)]);
        if (!$room) {
            return $this->json(['exito' => false, 'error' => 'Sala no encontrada'], 404);
        }
        if (!$this->access->isHost($room)) {
            return $this->forbidden();
        }

        $cartonesLiberados = $this->em->wrapInTransaction(function () use ($room): int {
            $this->em->refresh($room, LockMode::PESSIMISTIC_WRITE);

            return $this->roomReleaser->release($room);
        });

        return $this->json([
            'exito' => true,
            'salaCodigo' => $room->getCode(),
            'cartonesLiberados' => $cartonesLiberados,
            'mensaje' => 'Sala liberada correctamente. Ahora está disponible para reutilizar.',
        ]);
    }

    #[Route('/partidas/{partidaId}/sacar-bola', name: 'sacar_bola', methods: ['POST'])]
    public function sacarBola(string $partidaId): JsonResponse
    {
        $game = $this->findGameById($partidaId);
        if (!$game) {
            return $this->json(['exito' => false, 'error' => 'Partida no encontrada'], 404);
        }
        if (!$this->access->isHost($game->getRoom())) {
            return $this->forbidden();
        }

        // Con la partida bloqueada, un doble clic o dos pestañas del anfitrión
        // no pueden sacar la misma bola dos veces.
        $resultado = $this->em->wrapInTransaction(function () use ($game): JsonResponse|int {
            $this->lockGame($game);

            if ($game->getStatus() !== GameStatus::EN_JUEGO->value) {
                return $this->json([
                    'exito' => false,
                    'error' => 'La partida no está en juego',
                ], 400);
            }

            $bolasSacadas = array_map(fn(Draw $d) => $d->getNumber(), $game->getDraws()->toArray());

            if (count($bolasSacadas) >= 90) {
                return $this->json([
                    'exito' => false,
                    'error' => 'Ya se han sacado todas las bolas',
                ], 400);
            }

            $disponibles = array_values(array_diff(range(1, 90), $bolasSacadas));
            $nuevaBola = $disponibles[random_int(0, count($disponibles) - 1)];

            $draw = new Draw();
            $draw->setNumber($nuevaBola);
            $game->addDraw($draw);
            $this->em->persist($draw);
            $game->getRoom()->touch();

            return $nuevaBola;
        });

        if ($resultado instanceof JsonResponse) {
            return $resultado;
        }
        $nuevaBola = $resultado;

        $bolasSacadasActualizadas = $this->drawnNumbersNewestFirst($game);

        return $this->json([
            'exito' => true,
            'bolaActual' => $nuevaBola,
            'historial' => array_slice($bolasSacadasActualizadas, 0, 5),
            'bolasSacadas' => $bolasSacadasActualizadas,
            'totalSacadas' => count($bolasSacadasActualizadas),
            'restantes' => 90 - count($bolasSacadasActualizadas),
        ]);
    }

    #[Route('/partidas/{partidaId}/estado', name: 'estado_partida', methods: ['GET'])]
    public function estadoPartida(string $partidaId, Request $request): JsonResponse
    {
        $game = $this->findGameById($partidaId);
        if (!$game || !$this->access->canSeeRoom($game->getRoom())) {
            return $this->json(['exito' => false, 'error' => 'Partida no encontrada'], 404);
        }

        // Los móviles preguntan cada segundo y medio y casi siempre no ha
        // cambiado nada: el ETag sale de una sola consulta, sin cargar bolas
        // ni reclamaciones.
        $etag = '"' . $this->gameRepository->pollSignature($game) . '"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, 304);
        }

        $bolasSacadas = $this->drawnNumbersNewestFirst($game);
        $bolaActual = count($bolasSacadas) > 0 ? $bolasSacadas[0] : null;

        $jugadores = array_map(fn(Player $p) => [
            'jugadorId' => $p->getId()->toBase32(),
            'apodo' => $p->getNickname(),
        ], $this->playerRepository->findWithCardsInGame($game));

        // Reclamación activa (pendiente) y ganador (bingo aprobado).
        $reclamacionActiva = null;
        $ganador = null;
        $reclamacionesPendientes = 0;
        foreach ($game->getClaims() as $claim) {
            if ($claim->getStatus() === ClaimStatus::PENDIENTE->value) {
                $reclamacionesPendientes++;
                if ($reclamacionActiva === null) {
                    $reclamacionActiva = $this->serializeClaim($claim, $bolasSacadas);
                }
            }
            if ($claim->getStatus() === ClaimStatus::APROBADA->value
                && $claim->getType() === ClaimType::BINGO->value) {
                $ganador = [
                    'apodo' => $claim->getPlayer()->getNickname(),
                    'jugadorId' => $claim->getPlayer()->getId()->toBase32(),
                    'tipo' => 'BINGO',
                    'premio' => $game->getBingoPrize(),
                ];
            }
        }

        // Determine target prize
        $premioObjetivo = $this->determinarPremioObjetivo($game);

        $response = $this->json([
            'exito' => true,
            'partidaId' => $game->getId()->toBase32(),
            'estadoPartida' => $game->getStatus(),
            'bolaActual' => $bolaActual,
            'historial' => array_slice($bolasSacadas, 0, 5),
            'bolasSacadas' => $bolasSacadas,
            'totalSacadas' => count($bolasSacadas),
            'restantes' => 90 - count($bolasSacadas),
            'premioObjetivo' => $premioObjetivo,
            'jugadores' => $jugadores,
            'reclamacionesPendientes' => $reclamacionesPendientes,
            'reclamacionActiva' => $reclamacionActiva,
            'ganador' => $ganador,
            'precioCarton' => $game->getCardPrice(),
            'boteTotal' => $game->getTotalPot(),
            'premioLinea' => $game->getLineaPrize(),
            'premioBingo' => $game->getBingoPrize(),
        ]);
        
        // Add cache headers
        $response->headers->set('ETag', $etag);
        $response->headers->set('Cache-Control', 'private, max-age=1');
        
        return $response;
    }

    // ==================== JUGADORES Y CARTONES ====================

    #[Route('/jugadores/{jugadorId}/cartones', name: 'cartones_jugador', methods: ['GET'])]
    public function cartonesJugador(string $jugadorId, Request $request): JsonResponse
    {
        $player = $this->findPlayerById($jugadorId);
        if (!$player || !$this->access->isPlayer($player)) {
            return $this->json(['exito' => false, 'error' => 'Jugador no encontrado'], 404);
        }

        $partidaId = $request->query->get('partidaId');
        $game = is_string($partidaId) ? $this->findGameById($partidaId) : null;

        $cartones = [];
        foreach ($player->getCards() as $card) {
            if ($game && $card->getGame()->getId()->toBase32() !== $game->getId()->toBase32()) {
                continue;
            }
            $cartones[] = [
                'cartonId' => $card->getId()->toBase32(),
                'semilla' => $card->getSeed(),
                'cuadricula' => $card->getGrid(),
                'numeros' => $card->getFlatNumbers(),
            ];
        }

        return $this->json([
            'exito' => true,
            'jugadorId' => $player->getId()->toBase32(),
            'apodo' => $player->getNickname(),
            'cartones' => $cartones,
            'totalCartones' => count($cartones),
        ]);
    }

    // ==================== RECLAMACIONES ====================

    #[Route('/reclamaciones', name: 'crear_reclamacion', methods: ['POST'])]
    public function crearReclamacion(Request $request, RateLimiterFactoryInterface $fiestaReclamacionLimiter): JsonResponse
    {
        $data = $this->jsonBody($request);

        $partidaId = $data['partidaId'] ?? null;
        $jugadorId = $data['jugadorId'] ?? null;
        $cartonId = $data['cartonId'] ?? null;
        $tipo = ($data['tipo'] ?? 'BINGO') === 'LINEA' ? 'LINEA' : 'BINGO';

        if (!is_string($partidaId) || !is_string($jugadorId) || !is_string($cartonId)) {
            return $this->json(['exito' => false, 'error' => 'Faltan datos obligatorios'], 400);
        }

        $game = $this->findGameById($partidaId);
        $player = $this->findPlayerById($jugadorId);
        $card = $this->findCardById($cartonId);

        if (!$game || !$player || !$card) {
            return $this->json(['exito' => false, 'error' => 'Datos no válidos'], 404);
        }
        // Solo puede reclamar el propio jugador, con un cartón suyo de esta partida.
        if (!$this->access->isPlayer($player)
            || $card->getPlayer() !== $player
            || $card->getGame() !== $game) {
            return $this->forbidden();
        }
        if (!$fiestaReclamacionLimiter->create($player->getId()->toBase32())->consume()->isAccepted()) {
            return $this->json([
                'exito' => false,
                'error' => 'Has cantado muchas veces seguidas. Espera un par de minutos.',
            ], 429);
        }
        // Con la partida bloqueada, dos reclamaciones simultáneas no pueden
        // quedar las dos pendientes.
        $claim = $this->em->wrapInTransaction(function () use ($game, $player, $card, $tipo): JsonResponse|Claim {
            $this->lockGame($game);

            if ($game->getStatus() === GameStatus::FINALIZADA->value) {
                return $this->json(['exito' => false, 'error' => 'La partida ya ha terminado'], 409);
            }

            // Solo se admite una reclamación en curso por partida.
            if ($game->hasPendingClaim()) {
                return $this->json([
                    'exito' => false,
                    'error' => 'Ya hay una reclamación en curso. Espera a que el anfitrión la resuelva.',
                ], 409);
            }

            if ($game->getStatus() !== GameStatus::EN_JUEGO->value) {
                return $this->json(['exito' => false, 'error' => 'La partida no está en juego'], 409);
            }

            // Pista de validación para el anfitrión (la decisión final es humana).
            $bolasSacadas = array_map(fn(Draw $d) => $d->getNumber(), $game->getDraws()->toArray());
            $sugerenciaValida = $tipo === 'LINEA'
                ? $this->cardGenerator->validateLinea($card->getGrid(), $bolasSacadas)
                : $this->cardGenerator->validateBingo($card->getFlatNumbers(), $bolasSacadas);

            $claim = new Claim();
            $claim->setGame($game);
            $claim->setPlayer($player);
            $claim->setCard($card);
            $claim->setType($tipo === 'LINEA' ? ClaimType::LINEA : ClaimType::BINGO);
            $claim->setStatus(ClaimStatus::PENDIENTE);
            $claim->setNote($sugerenciaValida ? 'sugerencia:valida' : 'sugerencia:invalida');
            $game->addClaim($claim);

            // La partida se pausa hasta que el anfitrión verifique la reclamación.
            $game->setStatus(GameStatus::PAUSADA);
            $game->getRoom()->touch();

            $this->em->persist($claim);

            return $claim;
        });

        if ($claim instanceof JsonResponse) {
            return $claim;
        }

        return $this->json([
            'exito' => true,
            'estado' => 'PENDIENTE',
            'reclamacionId' => $claim->getId()->toBase32(),
            'tipo' => $tipo,
            'mensaje' => 'Reclamación enviada. El anfitrión está revisando tu cartón.',
        ], 201);
    }

    #[Route('/reclamaciones/{reclamacionId}/aprobar', name: 'aprobar_reclamacion', methods: ['POST'])]
    public function aprobarReclamacion(string $reclamacionId): JsonResponse
    {
        $claim = Ulid::isValid($reclamacionId) ? $this->claimRepository->find($reclamacionId) : null;
        if (!$claim) {
            return $this->json(['exito' => false, 'error' => 'Reclamación no encontrada'], 404);
        }
        if (!$this->access->isHost($claim->getGame()->getRoom())) {
            return $this->forbidden();
        }
        $game = $claim->getGame();
        $esBingo = $claim->getType() === ClaimType::BINGO->value;

        $error = $this->em->wrapInTransaction(function () use ($claim, $game, $esBingo): ?JsonResponse {
            if ($error = $this->lockClaim($claim, $game)) {
                return $error;
            }

            $claim->setStatus(ClaimStatus::APROBADA);

            if ($esBingo) {
                // Bingo válido: la partida termina y se libera el cartonaje.
                $this->finalizeGame($game);
            } else {
                // Línea válida: el objetivo pasa a BINGO y la partida se reanuda.
                $game->setPrizeTarget('BINGO');
                $this->resumeAfterClaim($game);
            }
            $game->getRoom()->touch();

            return null;
        });
        if ($error) {
            return $error;
        }

        return $this->json([
            'exito' => true,
            'resultado' => 'APROBADA',
            'tipo' => $claim->getType(),
            'estadoPartida' => $game->getStatus(),
            'mensaje' => $esBingo ? '¡BINGO aprobado! Partida finalizada.' : '¡Línea aprobada! Seguimos para bingo.',
        ]);
    }

    #[Route('/reclamaciones/{reclamacionId}/rechazar', name: 'rechazar_reclamacion', methods: ['POST'])]
    public function rechazarReclamacion(string $reclamacionId): JsonResponse
    {
        $claim = Ulid::isValid($reclamacionId) ? $this->claimRepository->find($reclamacionId) : null;
        if (!$claim) {
            return $this->json(['exito' => false, 'error' => 'Reclamación no encontrada'], 404);
        }
        if (!$this->access->isHost($claim->getGame()->getRoom())) {
            return $this->forbidden();
        }
        $game = $claim->getGame();

        $error = $this->em->wrapInTransaction(function () use ($claim, $game): ?JsonResponse {
            if ($error = $this->lockClaim($claim, $game)) {
                return $error;
            }

            $claim->setStatus(ClaimStatus::RECHAZADA);
            // Falsa alarma: la partida se reanuda.
            $this->resumeAfterClaim($game);
            $game->getRoom()->touch();

            return null;
        });
        if ($error) {
            return $error;
        }

        return $this->json([
            'exito' => true,
            'resultado' => 'RECHAZADA',
            'tipo' => $claim->getType(),
            'estadoPartida' => $game->getStatus(),
            'mensaje' => 'Reclamación rechazada. Seguimos jugando.',
        ]);
    }

    // ==================== HELPERS ====================

    /**
     * Bloquea la fila de la partida hasta el final de la transacción y la
     * recarga. Todas las acciones que cambian una partida pasan por aquí, así
     * que se ejecutan de una en una y cada una ve lo que hizo la anterior.
     * Hay que llamarla antes de leer las bolas o las reclamaciones.
     */
    private function lockGame(Game $game): void
    {
        $this->em->refresh($game, LockMode::PESSIMISTIC_WRITE);
    }

    /** Bloquea la partida y comprueba que la reclamación sigue pendiente. */
    private function lockClaim(Claim $claim, Game $game): ?JsonResponse
    {
        $this->lockGame($game);
        $this->em->refresh($claim);

        if ($claim->getStatus() !== ClaimStatus::PENDIENTE->value) {
            return $this->json(['exito' => false, 'error' => 'La reclamación ya fue resuelta'], 409);
        }
        if ($game->getStatus() === GameStatus::FINALIZADA->value) {
            return $this->json(['exito' => false, 'error' => 'La partida ya ha terminado'], 409);
        }

        return null;
    }

    /** Tras resolver una reclamación, la partida vuelve a estar en juego si estaba pausada por ella. */
    private function resumeAfterClaim(Game $game): void
    {
        if ($game->getStatusEnum() === GameStatus::PAUSADA) {
            $game->setStatus(GameStatus::EN_JUEGO);
        }
    }

    private function invalidState(Game $game): JsonResponse
    {
        return $this->json([
            'exito' => false,
            'error' => 'No se puede hacer eso con la partida en estado ' . mb_strtolower($game->getStatusEnum()->label()) . '.',
            'estadoPartida' => $game->getStatus(),
        ], 409);
    }

    /**
     * Bolas de la partida, de la más reciente a la más antigua. Se ordena por
     * el ULID, que guarda el instante con milisegundos: created_at solo llega
     * al segundo y dos bolas del mismo segundo podían salir desordenadas.
     *
     * @return int[]
     */
    private function drawnNumbersNewestFirst(Game $game): array
    {
        $draws = $game->getDraws()->toArray();
        usort($draws, fn(Draw $a, Draw $b) => strcmp((string) $b->getId(), (string) $a->getId()));

        return array_map(fn(Draw $d) => $d->getNumber(), $draws);
    }

    private function findGameById(string $id): ?Game
    {
        return Ulid::isValid($id) ? $this->gameRepository->find($id) : null;
    }

    private function findPlayerById(string $id): ?Player
    {
        return Ulid::isValid($id) ? $this->playerRepository->find($id) : null;
    }

    private function findCardById(string $id): ?Card
    {
        return Ulid::isValid($id) ? $this->cardRepository->find($id) : null;
    }

    /** @return array<string, mixed> */
    private function jsonBody(Request $request): array
    {
        $data = json_decode($request->getContent(), true);

        return is_array($data) ? $data : [];
    }

    private function forbidden(): JsonResponse
    {
        return $this->json(['exito' => false, 'error' => 'No tienes permiso para hacer esto en esta sala'], 403);
    }

    /**
     * Finaliza la partida: marca FINALIZADA, fecha de fin y libera el cartonaje.
     * No toca la sala ni los jugadores (eso se hace al liberar la sala).
     * La partida tiene que estar bloqueada con lockGame().
     *
     * @return int número de cartones liberados
     */
    private function finalizeGame(Game $game): int
    {
        // Una partida ya finalizada devolvió sus cartones, que quizá use ya
        // otra sala: no se vuelven a liberar.
        if ($game->getStatus() === GameStatus::FINALIZADA->value) {
            return 0;
        }

        $game->setStatus(GameStatus::FINALIZADA);
        $game->setFinishedAt(new \DateTimeImmutable());

        $templateIds = [];
        foreach ($game->getCards() as $card) {
            $templateIds[] = $card->getSeed();
        }

        if (!empty($templateIds)) {
            $this->cardTemplateRepository->releaseByIds($templateIds);
        }

        return count($templateIds);
    }

    /**
     * Serializa una reclamación para el panel del anfitrión.
     *
     * @param int[] $bolasSacadas
     * @return array<string, mixed>
     */
    private function serializeClaim(Claim $claim, array $bolasSacadas): array
    {
        $card = $claim->getCard();

        return [
            'reclamacionId' => $claim->getId()->toBase32(),
            'jugadorId' => $claim->getPlayer()->getId()->toBase32(),
            'apodo' => $claim->getPlayer()->getNickname(),
            'tipo' => $claim->getType(),
            'cartonId' => $card->getId()->toBase32(),
            'cuadricula' => $card->getGrid(),
            'numerosMarcados' => array_values(array_intersect($card->getFlatNumbers(), $bolasSacadas)),
            'sugerenciaValida' => $claim->getNote() === 'sugerencia:valida',
        ];
    }

    private function determinarPremioObjetivo(?Game $game): string
    {
        if (!$game) {
            return 'LINEA';
        }

        foreach ($game->getClaims() as $claim) {
            if ($claim->getType() === ClaimType::LINEA->value && 
                $claim->getStatus() === ClaimStatus::APROBADA->value) {
                return 'BINGO';
            }
        }

        return 'LINEA';
    }
}
