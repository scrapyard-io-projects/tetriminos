<?php

namespace App\Tetris\Logic;

use App\Tetris\Enums\RotationState;
use App\Tetris\Enums\TetrominoType;

final class ActivePiece
{
    public function __construct(
        public TetrominoType $type,
        public int $x,
        public int $y,
        public RotationState $rotation = RotationState::SPAWN,
    ) {}

    /**
     * @return list<array{0: int, 1: int}>
     */
    public function cells(): array
    {
        $out = [];

        foreach (SrsData::cells($this->type, $this->rotation) as [$ox, $oy]) {
            $out[] = [$this->x + $ox, $this->y + $oy];
        }

        return $out;
    }

    public function withOffset(int $dx, int $dy): self
    {
        return new self($this->type, $this->x + $dx, $this->y + $dy, $this->rotation);
    }

    public function withRotation(RotationState $rotation): self
    {
        return new self($this->type, $this->x, $this->y, $rotation);
    }
}
