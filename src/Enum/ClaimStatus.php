<?php

namespace App\Enum;

enum ClaimStatus: string
{
    case PENDIENTE = 'PENDIENTE';
    case APROBADA = 'APROBADA';
    case RECHAZADA = 'RECHAZADA';

    public function label(): string
    {
        return match($this) {
            self::PENDIENTE => 'Pendiente',
            self::APROBADA => 'Aprobada',
            self::RECHAZADA => 'Rechazada',
        };
    }
}
