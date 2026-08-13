<?php

namespace App\Tetris\Enums;

enum TimingRule: string
{
    case LOCK_DELAY = 'lock_delay';
    case MAX_LOCK_RESETS = 'max_lock_resets';
    case DAS = 'das';
    case ARR = 'arr';
    case SOFT_DROP_INTERVAL = 'soft_drop_interval';
    case TARGET_FPS = 'target_fps';

    public function floatValue(): float
    {
        return match ($this) {
            self::LOCK_DELAY => 0.5,
            self::DAS => 0.167,
            self::ARR => 0.033,
            // Fixed soft-drop clock (20Hz) — independent of level gravity / renderer FPS.
            self::SOFT_DROP_INTERVAL => 0.05,
            self::MAX_LOCK_RESETS => 15.0,
            self::TARGET_FPS => 60.0,
        };
    }

    public function intValue(): int
    {
        return (int) round($this->floatValue());
    }
}
