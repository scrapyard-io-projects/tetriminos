<?php

namespace App\Tetris\Logic;

use App\Tetris\Enums\TetrominoType;

/**
 * Guideline 7-bag randomizer.
 */
final class SevenBag
{
    /** @var list<TetrominoType> */
    private array $bag = [];

    /** @var list<TetrominoType> */
    private array $queue = [];

    public function __construct(
        private readonly int $previewCount = 5,
    ) {
        $this->refillQueue();
    }

    public function peek(int $index = 0): TetrominoType
    {
        $this->ensure($index + 1);

        return $this->queue[$index];
    }

    /**
     * @return list<TetrominoType>
     */
    public function preview(): array
    {
        $this->ensure($this->previewCount);

        return array_slice($this->queue, 0, $this->previewCount);
    }

    public function take(): TetrominoType
    {
        $this->ensure(1);
        $next = array_shift($this->queue);
        $this->ensure($this->previewCount);

        return $next;
    }

    private function ensure(int $count): void
    {
        while (count($this->queue) < $count) {
            $this->queue = array_merge($this->queue, $this->drawBag());
        }
    }

    private function refillQueue(): void
    {
        $this->queue = [];
        $this->ensure($this->previewCount);
    }

    /**
     * @return list<TetrominoType>
     */
    private function drawBag(): array
    {
        $pieces = TetrominoType::bagOrder();
        shuffle($pieces);

        return $pieces;
    }
}
