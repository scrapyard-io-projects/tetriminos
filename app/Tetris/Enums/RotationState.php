<?php

namespace App\Tetris\Enums;

/**
 * SRS rotation indices: 0 spawn, 1 = R (CW), 2 = 180, 3 = L (CCW).
 */
enum RotationState: int
{
    case SPAWN = 0;
    case RIGHT = 1;
    case FLIP = 2;
    case LEFT = 3;

    public function clockwise(): self
    {
        return self::from(($this->value + 1) % 4);
    }

    public function counterClockwise(): self
    {
        return self::from(($this->value + 3) % 4);
    }

    public function key(): string
    {
        return match ($this) {
            self::SPAWN => '0',
            self::RIGHT => 'R',
            self::FLIP => '2',
            self::LEFT => 'L',
        };
    }
}
