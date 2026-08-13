<?php

namespace App\Tetris\Enums;

/**
 * Pacing targets for the Tetriminos sketch (Settings → Debug).
 *
 * Uncapped is only available when VSync is off.
 */
enum TargetFpsOption: string
{
    case FPS_30 = '30';
    case FPS_60 = '60';
    case FPS_120 = '120';
    case FPS_180 = '180';
    case FPS_240 = '240';
    case UNCAPPED = 'uncapped';

    public function label(): string
    {
        return match ($this) {
            self::FPS_30 => '30',
            self::FPS_60 => '60',
            self::FPS_120 => '120',
            self::FPS_180 => '180',
            self::FPS_240 => '240',
            self::UNCAPPED => 'Uncapped',
        };
    }

    /**
     * Null means do not software-pace the frame loop.
     */
    public function hertz(): ?float
    {
        return match ($this) {
            self::FPS_30 => 30.0,
            self::FPS_60 => 60.0,
            self::FPS_120 => 120.0,
            self::FPS_180 => 180.0,
            self::FPS_240 => 240.0,
            self::UNCAPPED => null,
        };
    }

    public function allowedWhenVsync(bool $vsyncOn): bool
    {
        return $this !== self::UNCAPPED || ! $vsyncOn;
    }

    /**
     * @return list<self>
     */
    public static function allowed(bool $vsyncOn): array
    {
        $out = [];

        foreach (self::cases() as $option) {
            if ($option->allowedWhenVsync($vsyncOn)) {
                $out[] = $option;
            }
        }

        return $out;
    }

    public function nextAllowed(bool $vsyncOn): self
    {
        return $this->stepAllowed($vsyncOn, 1);
    }

    public function previousAllowed(bool $vsyncOn): self
    {
        return $this->stepAllowed($vsyncOn, -1);
    }

    public function next(): self
    {
        return $this->nextAllowed(false);
    }

    public function previous(): self
    {
        return $this->previousAllowed(false);
    }

    private function stepAllowed(bool $vsyncOn, int $delta): self
    {
        $opts = self::allowed($vsyncOn);
        $index = array_search($this, $opts, true);

        if ($index === false) {
            return $opts[0];
        }

        $count = count($opts);

        return $opts[($index + $delta + $count) % $count];
    }
}
