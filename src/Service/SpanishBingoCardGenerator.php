<?php

namespace App\Service;

/**
 * Cartón de bingo español de 90 bolas: 3 filas y 9 columnas, 15 números,
 * 5 por fila y de 1 a 3 por columna. La columna 1 lleva del 1 al 9, la 2 del
 * 10 al 19… y la última del 80 al 90. Dentro de cada columna los números
 * crecen de arriba abajo.
 */
class SpanishBingoCardGenerator
{
    private const COLUMNS = 9;
    private const ROWS = 3;
    private const NUMBERS_PER_ROW = 5;
    private const MIN_PER_COLUMN = 1;
    private const MAX_PER_COLUMN = 3;
    private const TOTAL_NUMBERS = 15;

    /** @return array{grid: array<int, array<int, ?int>>, flatNumbers: int[]} */
    public function generateCard(): array
    {
        $grid = $this->placeNumbers($this->layout($this->columnCounts()));

        if (!$this->isValid($grid)) {
            // No debería pasar nunca: el reparto de abajo siempre es posible.
            throw new \LogicException('Cartón de bingo inválido');
        }

        return [
            'grid' => $grid,
            'flatNumbers' => $this->extractFlatNumbers($grid),
        ];
    }

    /** @return array<int, array{grid: array<int, array<int, ?int>>, flatNumbers: int[]}> */
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

    /**
     * Cuántos números lleva cada columna: uno en cada una y los 6 restantes
     * repartidos al azar, sin pasar de 3 por columna.
     *
     * @return int[]
     */
    private function columnCounts(): array
    {
        $counts = array_fill(0, self::COLUMNS, self::MIN_PER_COLUMN);

        for ($left = self::TOTAL_NUMBERS - self::COLUMNS; $left > 0; $left--) {
            $open = array_keys(array_filter($counts, fn(int $c) => $c < self::MAX_PER_COLUMN));
            $counts[$open[random_int(0, count($open) - 1)]]++;
        }

        return $counts;
    }

    /**
     * Qué celdas llevan número. Se colocan primero las columnas más llenas y
     * cada una en las filas a las que más números les faltan; así todas las
     * filas acaban con 5.
     *
     * @param int[] $counts
     * @return array<int, array<int, bool>>
     */
    private function layout(array $counts): array
    {
        $filled = array_fill(0, self::ROWS, array_fill(0, self::COLUMNS, false));
        $missing = array_fill(0, self::ROWS, self::NUMBERS_PER_ROW);

        $columns = range(0, self::COLUMNS - 1);
        shuffle($columns);
        usort($columns, fn(int $a, int $b) => $counts[$b] <=> $counts[$a]);

        foreach ($columns as $col) {
            $rows = range(0, self::ROWS - 1);
            shuffle($rows);
            usort($rows, fn(int $a, int $b) => $missing[$b] <=> $missing[$a]);

            foreach (array_slice($rows, 0, $counts[$col]) as $row) {
                $filled[$row][$col] = true;
                $missing[$row]--;
            }
        }

        return $filled;
    }

    /**
     * @param array<int, array<int, bool>> $filled
     * @return array<int, array<int, ?int>>
     */
    private function placeNumbers(array $filled): array
    {
        $grid = array_fill(0, self::ROWS, array_fill(0, self::COLUMNS, null));

        for ($col = 0; $col < self::COLUMNS; $col++) {
            $rows = array_keys(array_filter(array_column($filled, $col)));
            $numbers = $this->columnRange($col);
            shuffle($numbers);
            $numbers = array_slice($numbers, 0, count($rows));
            sort($numbers);

            foreach ($rows as $i => $row) {
                $grid[$row][$col] = $numbers[$i];
            }
        }

        return $grid;
    }

    /** @return int[] */
    private function columnRange(int $col): array
    {
        return match ($col) {
            0 => range(1, 9),
            self::COLUMNS - 1 => range(80, 90),
            default => range($col * 10, $col * 10 + 9),
        };
    }

    /** @param array<int, array<int, ?int>> $grid */
    public function isValid(array $grid): bool
    {
        if (count($grid) !== self::ROWS) {
            return false;
        }

        foreach ($grid as $row) {
            if (count($row) !== self::COLUMNS || count(array_filter($row, fn($v) => $v !== null)) !== self::NUMBERS_PER_ROW) {
                return false;
            }
        }

        for ($col = 0; $col < self::COLUMNS; $col++) {
            $values = array_values(array_filter(array_column($grid, $col), fn($v) => $v !== null));
            if (count($values) < self::MIN_PER_COLUMN || count($values) > self::MAX_PER_COLUMN) {
                return false;
            }
            $sorted = $values;
            sort($sorted);
            if ($sorted !== $values || count(array_unique($values)) !== count($values)) {
                return false;
            }
            if (array_diff($values, $this->columnRange($col)) !== []) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, array<int, ?int>> $grid
     * @return int[]
     */
    private function extractFlatNumbers(array $grid): array
    {
        $numbers = array_values(array_filter(array_merge(...$grid), fn($v) => $v !== null));
        sort($numbers);

        return $numbers;
    }
}
