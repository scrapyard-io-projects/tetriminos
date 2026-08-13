<?php

namespace App\Tetris\Logic;

final class Scoring
{
    public int $score = 0;

    public int $lines = 0;

    public int $level = 1;

    public int $startLevel = 1;

    public int $combo = -1;

    public bool $backToBack = false;

    public function __construct(int $startLevel = 1)
    {
        $this->startLevel = max(1, $startLevel);
        $this->level = $this->startLevel;
    }

    public function softDrop(int $cells): void
    {
        $this->score += max(0, $cells);
    }

    public function hardDrop(int $cells): void
    {
        $this->score += max(0, $cells) * 2;
    }

    /**
     * Guideline fixed-goal Marathon: level = max(startLevel, floor(lines/10)+1).
     *
     * @return array{lines: int, points: int, isTetris: bool}
     */
    public function awardLineClear(int $cleared): array
    {
        if ($cleared <= 0) {
            $this->combo = -1;

            return ['lines' => 0, 'points' => 0, 'isTetris' => false];
        }

        $this->combo++;
        $isTetris = $cleared >= 4;
        $base = match ($cleared) {
            1 => 100,
            2 => 300,
            3 => 500,
            default => 800,
        };

        $points = $base * $this->level;

        if ($isTetris && $this->backToBack) {
            $points = (int) round($points * 1.5);
        }

        if ($this->combo > 0) {
            $points += 50 * $this->combo * $this->level;
        }

        $this->score += $points;
        $this->lines += $cleared;
        $this->backToBack = $isTetris;
        $this->level = max($this->startLevel, intdiv($this->lines, 10) + 1);

        return ['lines' => $cleared, 'points' => $points, 'isTetris' => $isTetris];
    }
}
