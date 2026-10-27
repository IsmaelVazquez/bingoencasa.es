<?php

namespace App\Service;

/**
 * Pistas para el anfitrión: si un cartón tiene línea o bingo con las bolas
 * cantadas. Los cartones se generan en SpanishBingoCardGenerator.
 */
class BingoCardGenerator
{
    /**
     * Validates if a line (row) is complete given the drawn numbers.
     */
    public function validateLinea(array $grid, array $bolasSacadas): bool
    {
        foreach ($grid as $row) {
            $numbersInRow = array_filter($row, fn($n) => $n !== null);
            if (count($numbersInRow) > 0 && count(array_diff($numbersInRow, $bolasSacadas)) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validates if bingo is complete (all numbers marked).
     */
    public function validateBingo(array $flatNumbers, array $bolasSacadas): bool
    {
        return count(array_diff($flatNumbers, $bolasSacadas)) === 0;
    }
}
