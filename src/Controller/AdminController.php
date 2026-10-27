<?php

namespace App\Controller;

use App\Repository\CardRepository;
use App\Repository\CardTemplateRepository;
use App\Repository\ClaimRepository;
use App\Repository\GameRepository;
use App\Repository\PlayerRepository;
use App\Repository\RoomRepository;
use App\Repository\SystemConfigRepository;
use App\Service\RoomCodeGenerator;
use App\Service\RoomReleaser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Ulid;

#[IsGranted('ROLE_ADMIN')]
#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoomCodeGenerator $codeGenerator,
    ) {}

    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(
        RoomRepository $roomRepository,
        GameRepository $gameRepository,
        PlayerRepository $playerRepository,
        CardRepository $cardRepository,
        ClaimRepository $claimRepository,
        CardTemplateRepository $cardTemplateRepository,
    ): Response {
        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'rooms' => $roomRepository->count([]),
                'roomsAvailable' => $roomRepository->countAvailable(),
                'games' => $gameRepository->count([]),
                'players' => $playerRepository->count([]),
                'cards' => $cardRepository->count([]),
                'cardTemplates' => $cardTemplateRepository->count([]),
                'cardTemplatesAvailable' => $cardTemplateRepository->countAvailable(),
                'claims' => $claimRepository->count([]),
            ],
            'recentClaims' => $claimRepository->findBy([], ['createdAt' => 'DESC'], 5),
        ]);
    }

    #[Route('/rooms', name: 'app_admin_rooms')]
    public function rooms(RoomRepository $roomRepository): Response
    {
        return $this->render('admin/rooms.html.twig', [
            'rooms' => $roomRepository->findBy([], ['isAvailable' => 'ASC', 'lastUsedAt' => 'DESC'], 50),
            'totalRooms' => $roomRepository->count([]),
            'availableRooms' => $roomRepository->countAvailable(),
        ]);
    }

    #[Route('/pool', name: 'app_admin_pool')]
    public function pool(
        RoomRepository $roomRepository,
        CardTemplateRepository $cardTemplateRepository,
    ): Response {
        return $this->render('admin/pool.html.twig', [
            'totalRooms' => $roomRepository->count([]),
            'availableRooms' => $roomRepository->countAvailable(),
            'usedRooms' => $roomRepository->count([]) - $roomRepository->countAvailable(),
            'totalCardTemplates' => $cardTemplateRepository->count([]),
            'availableCardTemplates' => $cardTemplateRepository->countAvailable(),
            'usedCardTemplates' => $cardTemplateRepository->count([]) - $cardTemplateRepository->countAvailable(),
            'activeRooms' => $roomRepository->findBy(['isAvailable' => false], ['lastUsedAt' => 'DESC'], 20),
        ]);
    }

    #[Route('/pool/room/{id}/release', name: 'app_admin_pool_release_room', methods: ['POST'])]
    public function releaseRoom(string $id, RoomRepository $roomRepository, RoomReleaser $roomReleaser): JsonResponse
    {
        $room = Ulid::isValid($id) ? $roomRepository->find($id) : null;
        if (!$room) {
            return $this->json(['exito' => false, 'error' => 'Sala no encontrada'], 404);
        }

        $cartonesLiberados = $roomReleaser->release($room);
        $this->em->flush();

        return $this->json([
            'exito' => true,
            'cartonesLiberados' => $cartonesLiberados,
            'mensaje' => 'Sala liberada correctamente',
        ]);
    }

    #[Route('/pool/room/{id}/reset-pin', name: 'app_admin_pool_reset_pin', methods: ['POST'])]
    public function resetPin(string $id, RoomRepository $roomRepository): JsonResponse
    {
        $room = Ulid::isValid($id) ? $roomRepository->find($id) : null;
        if (!$room) {
            return $this->json(['exito' => false, 'error' => 'Sala no encontrada'], 404);
        }

        $newPin = $this->codeGenerator->generatePin();
        $room->setPin($newPin);
        $this->em->flush();

        return $this->json([
            'exito' => true,
            'nuevoPin' => $newPin,
            'mensaje' => 'PIN actualizado correctamente',
        ]);
    }

    #[Route('/pool/release-all', name: 'app_admin_pool_release_all', methods: ['POST'])]
    public function releaseAll(
        RoomReleaser $roomReleaser,
        CardTemplateRepository $cardTemplateRepository,
    ): JsonResponse {
        $roomsReleased = $roomReleaser->releaseAll();
        $cardsReleased = $cardTemplateRepository->releaseAll();

        return $this->json([
            'exito' => true,
            'salasLiberadas' => $roomsReleased,
            'cartonesLiberados' => $cardsReleased,
            'mensaje' => 'Todo el pool ha sido liberado',
        ]);
    }

    #[Route('/pool/release-stale', name: 'app_admin_pool_release_stale', methods: ['POST'])]
    public function releaseStale(
        Request $request,
        RoomReleaser $roomReleaser,
    ): JsonResponse {
        $hours = max(1, (int) ($request->request->get('hours') ?? 24));
        $olderThan = new \DateTimeImmutable("-{$hours} hours");

        $roomsReleased = $roomReleaser->releaseStale($olderThan);

        return $this->json([
            'exito' => true,
            'salasLiberadas' => $roomsReleased,
            'mensaje' => "Salas inactivas por más de {$hours} horas liberadas",
        ]);
    }

    #[Route('/claims', name: 'app_admin_claims')]
    public function claims(ClaimRepository $claimRepository): Response
    {
        return $this->render('admin/claims.html.twig', [
            'claims' => $claimRepository->findBy([], ['createdAt' => 'DESC'], 25),
        ]);
    }

    #[Route('/settings', name: 'app_admin_settings')]
    public function settings(SystemConfigRepository $configRepository): Response
    {
        return $this->render('admin/settings.html.twig', [
            'modoBombo' => $configRepository->isEnabled('modo_bombo_enabled', true),
            'modoFiesta' => $configRepository->isEnabled('modo_fiesta_enabled', true),
        ]);
    }

    #[Route('/settings/toggle', name: 'app_admin_settings_toggle', methods: ['POST'])]
    public function toggleSetting(Request $request, SystemConfigRepository $configRepository): JsonResponse
    {
        $key = $request->request->get('key');
        $enabled = $request->request->get('enabled') === '1';

        $allowedKeys = ['modo_bombo_enabled', 'modo_fiesta_enabled'];
        if (!in_array($key, $allowedKeys, true)) {
            return $this->json(['exito' => false, 'error' => 'Clave no permitida'], 400);
        }

        $configRepository->setValue($key, $enabled ? '1' : '0');

        return $this->json([
            'exito' => true,
            'clave' => $key,
            'habilitado' => $enabled,
        ]);
    }
}
