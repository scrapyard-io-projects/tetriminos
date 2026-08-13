<?php

namespace App\Tetris\Logic;

use App\Tetris\Enums\BoardDim;
use App\Tetris\Enums\TetrominoType;

/**
 * 10×22 matrix (2 hidden rows on top). null = empty.
 */
final class BoardMatrix
{
    /** @var list<list<TetrominoType|null>> */
    private array $cells;

    public function __construct()
    {
        $this->clear();
    }

    public function clear(): void
    {
        $this->cells = [];
        $height = BoardDim::HEIGHT->value;

        for ($y = 0; $y < $height; $y++) {
            $this->cells[$y] = array_fill(0, BoardDim::WIDTH->value, null);
        }
    }

    public function get(int $x, int $y): ?TetrominoType
    {
        if (! $this->inBounds($x, $y)) {
            return null;
        }

        return $this->cells[$y][$x];
    }

    public function set(int $x, int $y, ?TetrominoType $type): void
    {
        if (! $this->inBounds($x, $y)) {
            return;
        }

        $this->cells[$y][$x] = $type;
    }

    public function inBounds(int $x, int $y): bool
    {
        return $x >= 0 && $x < BoardDim::WIDTH->value && $y >= 0 && $y < BoardDim::HEIGHT->value;
    }

    public function isEmpty(int $x, int $y): bool
    {
        return $this->inBounds($x, $y) && is_null($this->cells[$y][$x]);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $cells
     */
    public function fits(array $cells): bool
    {
        foreach ($cells as [$x, $y]) {
            if (! $this->inBounds($x, $y) || ! is_null($this->cells[$y][$x])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array{0: int, 1: int}>  $cells
     */
    public function lock(array $cells, TetrominoType $type): void
    {
        foreach ($cells as [$x, $y]) {
            $this->set($x, $y, $type);
        }
    }

    /**
     * @return list<int> cleared row indices (top → bottom)
     */
    public function clearFullLines(): array
    {
        $cleared = [];
        $height = BoardDim::HEIGHT->value;
        $width = BoardDim::WIDTH->value;

        for ($y = 0; $y < $height; $y++) {
            $full = true;

            for ($x = 0; $x < $width; $x++) {
                if (is_null($this->cells[$y][$x])) {
                    $full = false;
                    break;
                }
            }

            if ($full) {
                $cleared[] = $y;
            }
        }

        if ($cleared === []) {
            return [];
        }

        $remaining = [];

        for ($y = 0; $y < $height; $y++) {
            if (! in_array($y, $cleared, true)) {
                $remaining[] = $this->cells[$y];
            }
        }

        $pad = $height - count($remaining);
        $this->cells = [];

        for ($i = 0; $i < $pad; $i++) {
            $this->cells[] = array_fill(0, $width, null);
        }

        foreach ($remaining as $row) {
            $this->cells[] = $row;
        }

        return $cleared;
    }

    /**
     * @return list<list<TetrominoType|null>>
     */
    public function rows(): array
    {
        return $this->cells;
    }
}
