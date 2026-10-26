<?php

namespace App\Service;

use App\Repository\RoomRepository;

class RoomCodeGenerator
{
    private const WORDS = [
        'SOL', 'LUNA', 'PLAYA', 'FIESTA', 'BOLA', 'SUERTE', 'PREMIO', 'LINEA',
        'CANTO', 'BOMBO', 'CARTON', 'NUMERO', 'MESA', 'NOCHE', 'DIA', 'ROJO',
        'AZUL', 'VERDE', 'ORO', 'PLATA', 'COPA', 'REY', 'REINA', 'TORRE',
        'FELIZ', 'ALEGRE', 'BRAVO', 'FUEGO', 'AGUA', 'TIERRA', 'VIENTO', 'RAYO',
        'NUBE', 'ARCO', 'FLOR', 'ROSA', 'LIMON', 'FRESA', 'MENTA', 'COCO',
        'TIGRE', 'LEON', 'OSO', 'LOBO', 'GATO', 'PERRO', 'PATO', 'TORO',
        'CAFE', 'VINO', 'SALSA', 'RITMO'
    ];

    public function __construct(
        private RoomRepository $roomRepository
    ) {}

    public function generateUniqueCode(): string
    {
        $maxAttempts = 100;
        $attempt = 0;
        
        do {
            $word = self::WORDS[array_rand(self::WORDS)];
            $number = random_int(10, 99);
            $code = $word . $number;
            $attempt++;
            
            if ($attempt >= $maxAttempts) {
                $code = $word . random_int(100, 999);
            }
        } while ($this->roomRepository->findOneBy(['code' => $code]) !== null && $attempt < $maxAttempts + 10);
        
        return strtoupper($code);
    }

    public function generatePin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
