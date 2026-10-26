<?php

namespace App\Service;

class BingoCardGenerator
{
    /**
     * Generates a Spanish standard bingo card (15 numbers, max 2 per column, 5 per row).
     */
    public function generateBingoCard(int $seed): array
    {
        $state = $seed;
        $rand = static function () use (&$state): float {
            $state = ($state * 9301 + 49297) % 233280;
            return $state / 233280;
        };

        $shuffle = static function (array $arr) use (&$rand): array {
            $result = $arr;
            for ($i = count($result) - 1; $i > 0; $i--) {
                $j = (int) floor($rand() * ($i + 1));
                [$result[$i], $result[$j]] = [$result[$j], $result[$i]];
            }
            return $result;
        };

        $colRanges = [
            [1, 9], [10, 19], [20, 29], [30, 39], [40, 49], [50, 59], [60, 69], [70, 79], [80, 90],
        ];

        // 6 columns with 2 numbers + 3 columns with 1 number = 15 total
        $colCounts = [2, 2, 2, 2, 2, 2, 1, 1, 1];
        $shuffledCounts = $shuffle($colCounts);

        // Generate numbers for each column
        $cols = [];
        for ($c = 0; $c < 9; $c++) {
            [$min, $max] = $colRanges[$c];
            $count = $shuffledCounts[$c];
            $colNums = [];
            while (count($colNums) < $count) {
                $num = (int) floor($rand() * ($max - $min + 1)) + $min;
                if (!in_array($num, $colNums, true)) {
                    $colNums[] = $num;
                }
            }
            sort($colNums);
            $cols[] = $colNums;
        }

        // Initialize grid (3 rows x 9 columns)
        $grid = array_fill(0, 3, array_fill(0, 9, null));
        $rowCounts = [0, 0, 0];

        // Separate columns by count
        $twoNumCols = [];
        $oneNumCols = [];
        for ($c = 0; $c < 9; $c++) {
            if (count($cols[$c]) === 2) {
                $twoNumCols[] = $c;
            } else {
                $oneNumCols[] = $c;
            }
        }

        // Row pairs for 2-number columns
        $rowPairs = [[0, 1], [0, 2], [1, 2]];

        // Place 2-number columns first
        foreach ($shuffle($twoNumCols) as $c) {
            $validPairs = array_filter($rowPairs, fn($pair) => $rowCounts[$pair[0]] < 5 && $rowCounts[$pair[1]] < 5);
            if (empty($validPairs)) {
                continue;
            }
            $validPairs = array_values($validPairs);
            $pair = $validPairs[(int) floor($rand() * count($validPairs))];
            $grid[$pair[0]][$c] = $cols[$c][0];
            $grid[$pair[1]][$c] = $cols[$c][1];
            $rowCounts[$pair[0]]++;
            $rowCounts[$pair[1]]++;
        }

        // Place 1-number columns
        foreach ($shuffle($oneNumCols) as $c) {
            $validRows = array_filter([0, 1, 2], fn($r) => $rowCounts[$r] < 5);
            if (empty($validRows)) {
                continue;
            }
            $validRows = array_values($validRows);
            $row = $validRows[(int) floor($rand() * count($validRows))];
            $grid[$row][$c] = $cols[$c][0];
            $rowCounts[$row]++;
        }

        // Generate flat list
        $flat = [];
        foreach ($cols as $col) {
            foreach ($col as $value) {
                $flat[] = $value;
            }
        }
        sort($flat);

        return [
            'id' => $seed,
            'grid' => $grid,
            'flat' => $flat,
        ];
    }

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
