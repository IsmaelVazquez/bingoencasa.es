<?php

namespace App\Enum;

enum GameStatus: string
{
    case DETENIDA = 'DETENIDA';
    case EN_JUEGO = 'EN_JUEGO';
    case PAUSADA = 'PAUSADA';
    case COMPROBANDO = 'COMPROBANDO';
    case FINALIZADA = 'FINALIZADA';

    /**
     * Cambios de estado permitidos. COMPROBANDO no se usa en el Modo Fiesta
     * (una reclamación pone la partida en PAUSADA) y queda fuera.
     */
    public function canTransitionTo(self $next): bool
    {
        return match($this) {
            self::DETENIDA => in_array($next, [self::EN_JUEGO, self::FINALIZADA], true),
            self::EN_JUEGO => in_array($next, [self::PAUSADA, self::FINALIZADA], true),
            self::PAUSADA => in_array($next, [self::EN_JUEGO, self::FINALIZADA], true),
            self::COMPROBANDO => in_array($next, [self::EN_JUEGO, self::PAUSADA, self::FINALIZADA], true),
            self::FINALIZADA => false,
        };
    }

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
