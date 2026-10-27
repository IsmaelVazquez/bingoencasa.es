<?php

namespace App\Tests\Service;

use App\Service\SpanishBingoCardGenerator;
use PHPUnit\Framework\TestCase;

class SpanishBingoCardGeneratorTest extends TestCase
{
    public function testEveryCardFollowsTheSpanishFormat(): void
    {
        $generator = new SpanishBingoCardGenerator();
        $columnCounts = [];

        for ($i = 0; $i < 2000; $i++) {
            $card = $generator->generateCard();
            $grid = $card['grid'];

            self::assertTrue($generator->isValid($grid));
            self::assertCount(15, $card['flatNumbers']);
            self::assertCount(15, array_unique($card['flatNumbers']));
            foreach ($grid as $row) {
                self::assertCount(5, array_filter($row, fn($v) => $v !== null));
            }
            for ($col = 0; $col < 9; $col++) {
                $columnCounts[count(array_filter(array_column($grid, $col), fn($v) => $v !== null))] = true;
            }
        }

        // Salen columnas de 1, 2 y 3 números, y nunca vacías.
        ksort($columnCounts);
        self::assertSame([1, 2, 3], array_keys($columnCounts));
    }

    public function testRejectsAnEmptyColumn(): void
    {
        $grid = [
            [1, 10, 20, 30, 40, null, null, null, null],
            [2, 11, 21, 31, 41, null, null, null, null],
            [3, 12, 22, 32, 42, null, null, null, null],
        ];

        self::assertFalse((new SpanishBingoCardGenerator())->isValid($grid));
    }
}
