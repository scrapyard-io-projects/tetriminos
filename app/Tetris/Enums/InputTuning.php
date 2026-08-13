<?php

namespace App\Tetris\Enums;

enum InputTuning: string
{
    case STICK_DEADZONE = 'stick_deadzone';

    public function floatValue(): float
    {
        return match ($this) {
            self::STICK_DEADZONE => 0.7,
        };
    }
}
