<?php

namespace App\Service;

class SpanishBingoCardGenerator
{
    private const COLUMNS = 9;
    private const ROWS = 3;
    private const NUMBERS_PER_ROW = 5;
    private const MAX_PER_COLUMN = 2;
    private const TOTAL_NUMBERS = 15;

    public function generateCard(): array
    {
        $grid = $this->createValidGrid();
        $flatNumbers = $this->extractFlatNumbers($grid);

        return [
            'grid' => $grid,
            'flatNumbers' => $flatNumbers,
        ];
    }

    public function generateMultipleCards(int $count): array
    {
        $cards = [];
        $seenGrids = [];

        while (count($cards) < $count) {
            $card = $this->generateCard();
            $key = implode(',', $card['flatNumbers']);

            if (!isset($seenGrids[$key])) {
                $seenGrids[$key] = true;
                $cards[] = $card;
            }
        }

        return $cards;
    }

    private function createValidGrid(): array
    {
        $maxAttempts = 1000;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $grid = $this->attemptCreateGrid();

            if ($grid !== null && $this->validateGrid($grid)) {
                return $grid;
            }
        }

        throw new \RuntimeException('Could not generate valid bingo card after ' . $maxAttempts . ' attempts');
    }

    private function attemptCreateGrid(): ?array
    {
        $grid = array_fill(0, self::ROWS, array_fill(0, self::COLUMNS, null));
        $columnNumbers = $this->getColumnNumberRanges();

        for ($col = 0; $col < self::COLUMNS; $col++) {
            $availableNumbers = $columnNumbers[$col];
            shuffle($availableNumbers);

            $numbersInColumn = rand(0, min(self::MAX_PER_COLUMN, count($availableNumbers)));

            if ($numbersInColumn > 0) {
                $selectedNumbers = array_slice($availableNumbers, 0, $numbersInColumn);
                sort($selectedNumbers);

                $availableRows = [0, 1, 2];
                shuffle($availableRows);
                $selectedRows = array_slice($availableRows, 0, $numbersInColumn);
                sort($selectedRows);

                foreach ($selectedRows as $i => $row) {
                    $grid[$row][$col] = $selectedNumbers[$i];
                }
            }
        }

        for ($row = 0; $row < self::ROWS; $row++) {
            $filledCount = count(array_filter($grid[$row], fn($v) => $v !== null));

            while ($filledCount < self::NUMBERS_PER_ROW) {
                $emptyColumns = [];
                for ($col = 0; $col < self::COLUMNS; $col++) {
                    if ($grid[$row][$col] === null) {
                        $columnCount = $this->countInColumn($grid, $col);
                        if ($columnCount < self::MAX_PER_COLUMN) {
                            $emptyColumns[] = $col;
                        }
                    }
                }

                if (empty($emptyColumns)) {
                    return null;
                }

                $col = $emptyColumns[array_rand($emptyColumns)];
                $availableNumbers = $this->getAvailableNumbersForCell($grid, $row, $col, $columnNumbers[$col]);

                if (empty($availableNumbers)) {
                    return null;
                }

                $grid[$row][$col] = $availableNumbers[array_rand($availableNumbers)];
                $filledCount++;
            }

            while ($filledCount > self::NUMBERS_PER_ROW) {
                $filledColumns = [];
                for ($col = 0; $col < self::COLUMNS; $col++) {
                    if ($grid[$row][$col] !== null) {
                        $filledColumns[] = $col;
                    }
                }

                $col = $filledColumns[array_rand($filledColumns)];
                $grid[$row][$col] = null;
                $filledCount--;
            }
        }

        $this->ensureColumnOrder($grid);

        return $grid;
    }

    private function getColumnNumberRanges(): array
    {
        return [
            0 => range(1, 9),
            1 => range(10, 19),
            2 => range(20, 29),
            3 => range(30, 39),
            4 => range(40, 49),
            5 => range(50, 59),
            6 => range(60, 69),
            7 => range(70, 79),
            8 => range(80, 90),
        ];
    }

    private function countInColumn(array $grid, int $col): int
    {
        $count = 0;
        for ($row = 0; $row < self::ROWS; $row++) {
            if ($grid[$row][$col] !== null) {
                $count++;
            }
        }
        return $count;
    }

    private function getAvailableNumbersForCell(array $grid, int $row, int $col, array $columnRange): array
    {
        $usedInColumn = [];
        for ($r = 0; $r < self::ROWS; $r++) {
            if ($grid[$r][$col] !== null) {
                $usedInColumn[] = $grid[$r][$col];
            }
        }

        $available = array_diff($columnRange, $usedInColumn);

        if ($row > 0) {
            $above = $grid[$row - 1][$col];
            if ($above !== null) {
                $available = array_filter($available, fn($n) => $n > $above);
            }
        }

        if ($row < self::ROWS - 1) {
            $below = $grid[$row + 1][$col];
            if ($below !== null) {
                $available = array_filter($available, fn($n) => $n < $below);
            }
        }

        return array_values($available);
    }

    private function ensureColumnOrder(array &$grid): void
    {
        for ($col = 0; $col < self::COLUMNS; $col++) {
            $numbers = [];
            $rows = [];

            for ($row = 0; $row < self::ROWS; $row++) {
                if ($grid[$row][$col] !== null) {
                    $numbers[] = $grid[$row][$col];
                    $rows[] = $row;
                }
            }

            if (count($numbers) > 1) {
                sort($numbers);
                foreach ($rows as $i => $row) {
                    $grid[$row][$col] = $numbers[$i];
                }
            }
        }
    }

    private function validateGrid(array $grid): bool
    {
        $totalNumbers = 0;

        for ($row = 0; $row < self::ROWS; $row++) {
            $rowCount = count(array_filter($grid[$row], fn($v) => $v !== null));
            if ($rowCount !== self::NUMBERS_PER_ROW) {
                return false;
            }
            $totalNumbers += $rowCount;
        }

        if ($totalNumbers !== self::TOTAL_NUMBERS) {
            return false;
        }

        for ($col = 0; $col < self::COLUMNS; $col++) {
            $colCount = $this->countInColumn($grid, $col);
            if ($colCount > self::MAX_PER_COLUMN) {
                return false;
            }
        }

        $columnRanges = $this->getColumnNumberRanges();
        for ($col = 0; $col < self::COLUMNS; $col++) {
            $prevValue = null;
            for ($row = 0; $row < self::ROWS; $row++) {
                $value = $grid[$row][$col];
                if ($value !== null) {
                    if (!in_array($value, $columnRanges[$col])) {
                        return false;
                    }
                    if ($prevValue !== null && $value <= $prevValue) {
                        return false;
                    }
                    $prevValue = $value;
                }
            }
        }

        return true;
    }

    private function extractFlatNumbers(array $grid): array
    {
        $numbers = [];
        foreach ($grid as $row) {
            foreach ($row as $cell) {
                if ($cell !== null) {
                    $numbers[] = $cell;
                }
            }
        }
        sort($numbers);
        return $numbers;
    }
}
