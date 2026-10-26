<?php

namespace App\Enum;

enum GameStatus: string
{
    case DETENIDA = 'DETENIDA';
    case EN_JUEGO = 'EN_JUEGO';
    case PAUSADA = 'PAUSADA';
    case COMPROBANDO = 'COMPROBANDO';
    case FINALIZADA = 'FINALIZADA';

    public function label(): string
    {
        return match($this) {
            self::DETENIDA => 'Detenida',
            self::EN_JUEGO => 'En Juego',
            self::PAUSADA => 'Pausada',
            self::COMPROBANDO => 'Comprobando',
            self::FINALIZADA => 'Finalizada',
        };
    }
}
