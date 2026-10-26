<?php

namespace App\Controller\Api;

use App\Repository\CardTemplateRepository;
use Doctrine\DBAL\ParameterType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/cartones')]
class CardTemplatePrintController extends AbstractController
{
    #[Route('/plantillas', name: 'api_cartones_plantillas', methods: ['GET'])]
    public function getPlantillas(Request $request, CardTemplateRepository $repo): JsonResponse
    {
        $cantidad = min(200, max(1, (int) $request->query->get('cantidad', 12)));
        
        $conn = $repo->getEntityManager()->getConnection();
        $sql = 'SELECT id, grid, flat_numbers FROM card_template ORDER BY RANDOM() LIMIT :limit';
        $result = $conn->executeQuery(
            $sql,
            ['limit' => $cantidad],
            ['limit' => ParameterType::INTEGER],
        );
        $rows = $result->fetchAllAssociative();
        
        if (empty($rows)) {
            return $this->json([
                'error' => 'No se encontraron cartones',
                'mensaje' => 'No hay cartones disponibles en el sistema'
            ], 404);
        }
        
        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => $row['id'],
                'numero' => sprintf('#%04d', $row['id']),
                'grid' => self::decodeJson($row['grid']),
                'flatNumbers' => self::decodeJson($row['flat_numbers'])
            ];
        }
        
        return $this->json([
            'cartones' => $data,
            'total' => count($data)
        ]);
    }

    #[Route('/validar/{id}', name: 'api_cartones_validar', methods: ['GET'])]
    public function validarCarton(int $id, CardTemplateRepository $repo): JsonResponse
    {
        $template = $repo->find($id);
        
        if (!$template) {
            return $this->json([
                'existe' => false,
                'error' => 'Cartón no encontrado',
                'mensaje' => "El cartón #$id no existe en el sistema"
            ], 404);
        }
        
        return $this->json([
            'existe' => true,
            'id' => $template->getId(),
            'numero' => sprintf('#%04d', $template->getId()),
            'grid' => $template->getGrid(),
            'flatNumbers' => $template->getFlatNumbers()
        ]);
    }

    private static function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
