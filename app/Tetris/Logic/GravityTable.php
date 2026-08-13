<?php

namespace App\Tetris\Logic;

final class GravityTable
{
    /**
     * Seconds per automatic gravity step by level (Tetris Worlds / Guideline style).
     * Indexed from level 1.
     *
     * @var list<float>
     */
    private const TABLE = [
        1.0, 0.793, 0.6178, 0.4727, 0.3552,
        0.262, 0.1897, 0.1347, 0.0939, 0.0642,
        0.0429, 0.0281, 0.018, 0.0113, 0.0069,
        0.0041, 0.0024, 0.0014, 0.0008, 0.0005,
    ];

    public static function intervalForLevel(int $level): float
    {
        $index = max(0, min(count(self::TABLE) - 1, $level - 1));

        return self::TABLE[$index];
    }
}
