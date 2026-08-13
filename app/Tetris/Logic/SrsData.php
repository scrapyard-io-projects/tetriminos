<?php

namespace App\Tetris\Logic;

use App\Tetris\Enums\RotationState;
use App\Tetris\Enums\TetrominoType;

/**
 * SRS shapes (Y+) and wall-kick tables (wiki Y+ up → stored as board Y+ down).
 *
 * Shape offsets adapted from lgn21st/godot-tetris (Guideline-oriented).
 * Kick tables from https://harddrop.com/wiki/SRS (Y negated for downward boards).
 */
final class SrsData
{
    /**
     * @return list<array{0: int, 1: int}>
     */
    public static function cells(TetrominoType $type, RotationState $rotation): array
    {
        return self::shapes()[$type->value][$rotation->value];
    }

    /**
     * @return list<array{0: int, 1: int}> list of (dx, dy) kicks, Y+ down
     */
    public static function kicks(TetrominoType $type, RotationState $from, RotationState $to): array
    {
        if ($type === TetrominoType::O) {
            return [[0, 0]];
        }

        $table = $type === TetrominoType::I ? self::iKicks() : self::jltszKicks();
        $key = $from->key().'->'.$to->key();

        return $table[$key] ?? [[0, 0]];
    }

    /**
     * @return array<string, list<list<array{0: int, 1: int}>>>
     */
    private static function shapes(): array
    {
        return [
            'I' => [
                [[-1, 0], [0, 0], [1, 0], [2, 0]],
                [[1, -1], [1, 0], [1, 1], [1, 2]],
                [[-1, 1], [0, 1], [1, 1], [2, 1]],
                [[0, -1], [0, 0], [0, 1], [0, 2]],
            ],
            'O' => [
                [[0, 0], [1, 0], [0, 1], [1, 1]],
                [[0, 0], [1, 0], [0, 1], [1, 1]],
                [[0, 0], [1, 0], [0, 1], [1, 1]],
                [[0, 0], [1, 0], [0, 1], [1, 1]],
            ],
            'T' => [
                [[-1, 0], [0, 0], [1, 0], [0, 1]],
                [[0, -1], [0, 0], [1, 0], [0, 1]],
                [[-1, 0], [0, 0], [1, 0], [0, -1]],
                [[0, -1], [-1, 0], [0, 0], [0, 1]],
            ],
            'S' => [
                [[0, 0], [1, 0], [-1, 1], [0, 1]],
                [[0, -1], [0, 0], [1, 0], [1, 1]],
                [[0, 0], [1, 0], [-1, 1], [0, 1]],
                [[0, -1], [0, 0], [1, 0], [1, 1]],
            ],
            'Z' => [
                [[-1, 0], [0, 0], [0, 1], [1, 1]],
                [[1, -1], [0, 0], [1, 0], [0, 1]],
                [[-1, 0], [0, 0], [0, 1], [1, 1]],
                [[1, -1], [0, 0], [1, 0], [0, 1]],
            ],
            'J' => [
                [[-1, 0], [0, 0], [1, 0], [-1, 1]],
                [[0, -1], [0, 0], [0, 1], [1, 1]],
                [[-1, 0], [0, 0], [1, 0], [1, -1]],
                [[0, -1], [0, 0], [0, 1], [-1, -1]],
            ],
            'L' => [
                [[-1, 0], [0, 0], [1, 0], [1, 1]],
                [[0, -1], [0, 0], [0, 1], [1, -1]],
                [[-1, 0], [0, 0], [1, 0], [-1, -1]],
                [[0, -1], [0, 0], [0, 1], [-1, 1]],
            ],
        ];
    }

    /**
     * @return array<string, list<array{0: int, 1: int}>>
     */
    private static function jltszKicks(): array
    {
        // Wiki Y+ up → negated Y for board.
        return [
            '0->R' => [[0, 0], [-1, 0], [-1, -1], [0, 2], [-1, 2]],
            'R->0' => [[0, 0], [1, 0], [1, 1], [0, -2], [1, -2]],
            'R->2' => [[0, 0], [1, 0], [1, 1], [0, -2], [1, -2]],
            '2->R' => [[0, 0], [-1, 0], [-1, -1], [0, 2], [-1, 2]],
            '2->L' => [[0, 0], [1, 0], [1, -1], [0, 2], [1, 2]],
            'L->2' => [[0, 0], [-1, 0], [-1, 1], [0, -2], [-1, -2]],
            'L->0' => [[0, 0], [-1, 0], [-1, 1], [0, -2], [-1, -2]],
            '0->L' => [[0, 0], [1, 0], [1, -1], [0, 2], [1, 2]],
        ];
    }

    /**
     * @return array<string, list<array{0: int, 1: int}>>
     */
    private static function iKicks(): array
    {
        return [
            '0->R' => [[0, 0], [-2, 0], [1, 0], [-2, 1], [1, -2]],
            'R->0' => [[0, 0], [2, 0], [-1, 0], [2, -1], [-1, 2]],
            'R->2' => [[0, 0], [-1, 0], [2, 0], [-1, -2], [2, 1]],
            '2->R' => [[0, 0], [1, 0], [-2, 0], [1, 2], [-2, -1]],
            '2->L' => [[0, 0], [2, 0], [-1, 0], [2, -1], [-1, 2]],
            'L->2' => [[0, 0], [-2, 0], [1, 0], [-2, 1], [1, -2]],
            'L->0' => [[0, 0], [1, 0], [-2, 0], [1, 2], [-2, -1]],
            '0->L' => [[0, 0], [-1, 0], [2, 0], [-1, -2], [2, 1]],
        ];
    }
}
