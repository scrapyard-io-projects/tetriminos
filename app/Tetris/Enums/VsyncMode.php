<?php

namespace App\Tetris\Enums;

/**
 * Display sync for Tetriminos. Off unlocks Uncapped (and lets 120 exceed a 60 Hz lock).
 */
enum VsyncMode: string
{
    case ON = 'on';
    case OFF = 'off';

    public function enabled(): bool
    {
        return $this === self::ON;
    }

    public function label(): string
    {
        return match ($this) {
            self::ON => 'ON',
            self::OFF => 'OFF',
        };
    }

    public function toggled(): self
    {
        return $this === self::ON ? self::OFF : self::ON;
    }
}
