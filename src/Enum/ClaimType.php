<?php

namespace App\Enum;

enum ClaimType: string
{
    case LINEA = 'LINEA';
    case BINGO = 'BINGO';

    public function label(): string
    {
        return match($this) {
            self::LINEA => 'Línea',
            self::BINGO => 'Bingo',
        };
    }
}
