<?php

namespace App\Tetris\Logic;

use App\Tetris\Enums\RotationState;
use App\Tetris\Enums\TetrominoType;
use App\Tetris\Enums\TimingRule;

/**
 * Guideline-oriented playfield controller: gravity, DAS/ARR consumers,
 * SRS rotate, hold, lock delay (move-reset, max 15), ghost, next queue.
 */
final class PlayController
{
    public BoardMatrix $board;

    public SevenBag $bag;

    public Scoring $scoring;

    public ?ActivePiece $active = null;

    public ?TetrominoType $hold = null;

    public bool $holdUsedThisPiece = false;

    public bool $gameOver = false;

    public bool $paused = false;

    private float $gravityAccum = 0.0;

    private float $softDropAccum = 0.0;

    private float $lockTimer = 0.0;

    private int $lockResets = 0;

    private bool $onGround = false;

    public function __construct(int $startLevel = 1)
    {
        $this->board = new BoardMatrix;
        $this->bag = new SevenBag(5);
        $this->scoring = new Scoring($startLevel);
        $this->spawnNext();
    }

    public function isPaused(): bool
    {
        return $this->paused;
    }

    public function tick(float $dt, bool $softDropping): void
    {
        if ($this->paused || $this->gameOver || is_null($this->active)) {
            return;
        }

        $dt = max(0.0, min($dt, 0.1));

        if ($softDropping) {
            $this->runSoftDrop($dt);
        } else {
            $this->softDropAccum = 0.0;
            $this->runGravity($dt);
        }

        $this->onGround = is_null($this->active)
            ? false
            : $this->wouldCollide($this->active->withOffset(0, 1));

        if ($this->onGround) {
            $this->lockTimer += $dt;

            if ($this->lockTimer >= TimingRule::LOCK_DELAY->floatValue()) {
                $this->lockActive();
            }
        } else {
            $this->lockTimer = 0.0;
        }
    }

    private function runSoftDrop(float $dt): void
    {
        $interval = TimingRule::SOFT_DROP_INTERVAL->floatValue();
        $this->softDropAccum += $dt;
        // Cap steps so a hitch can't teleport the piece through the stack.
        $guard = 0;

        while ($this->softDropAccum >= $interval && $guard < 8) {
            $this->softDropAccum -= $interval;
            $guard++;

            if (! $this->tryMove(0, 1)) {
                $this->onGround = true;
                $this->softDropAccum = 0.0;

                return;
            }

            $this->scoring->softDrop(1);
        }
    }

    private function runGravity(float $dt): void
    {
        $interval = GravityTable::intervalForLevel($this->scoring->level);
        $this->gravityAccum += $dt;
        $guard = 0;

        while ($this->gravityAccum >= $interval && $guard < 8) {
            $this->gravityAccum -= $interval;
            $guard++;

            if (! $this->tryMove(0, 1)) {
                $this->onGround = true;

                return;
            }
        }
    }

    public function moveHorizontal(int $dir): bool
    {
        if ($this->paused || $this->gameOver || $dir === 0) {
            return false;
        }

        $moved = $this->tryMove($dir, 0);

        if ($moved) {
            $this->registerMovement();
        }

        return $moved;
    }

    public function softDropStep(): bool
    {
        if ($this->paused || $this->gameOver) {
            return false;
        }

        if ($this->tryMove(0, 1)) {
            $this->scoring->softDrop(1);
            $this->registerMovement();

            return true;
        }

        $this->onGround = true;

        return false;
    }

    public function hardDrop(): void
    {
        if ($this->paused || $this->gameOver || is_null($this->active)) {
            return;
        }

        $distance = 0;

        while ($this->tryMove(0, 1)) {
            $distance++;
        }

        $this->scoring->hardDrop($distance);
        $this->lockActive();
    }

    public function rotate(bool $clockwise): bool
    {
        if ($this->paused || $this->gameOver || is_null($this->active)) {
            return false;
        }

        $from = $this->active->rotation;
        $to = $clockwise ? $from->clockwise() : $from->counterClockwise();

        foreach (SrsData::kicks($this->active->type, $from, $to) as [$dx, $dy]) {
            $candidate = $this->active->withRotation($to)->withOffset($dx, $dy);

            if (! $this->wouldCollide($candidate)) {
                $this->active = $candidate;
                $this->registerMovement();

                return true;
            }
        }

        return false;
    }

    public function holdPiece(): bool
    {
        if ($this->paused || $this->gameOver || $this->holdUsedThisPiece || is_null($this->active)) {
            return false;
        }

        $current = $this->active->type;
        $this->holdUsedThisPiece = true;

        if (is_null($this->hold)) {
            $this->hold = $current;
            $this->spawnNext();
        } else {
            $swap = $this->hold;
            $this->hold = $current;
            $this->spawnType($swap);
        }

        $this->resetLockState();

        return true;
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    public function ghostCells(): array
    {
        if (is_null($this->active)) {
            return [];
        }

        $ghost = $this->active;

        while (! $this->wouldCollide($ghost->withOffset(0, 1))) {
            $ghost = $ghost->withOffset(0, 1);
        }

        return $ghost->cells();
    }

    private function registerMovement(): void
    {
        if (! $this->onGround && (is_null($this->active) || ! $this->wouldCollide($this->active->withOffset(0, 1)))) {
            $this->lockTimer = 0.0;
            $this->onGround = false;

            return;
        }

        if ($this->lockResets < TimingRule::MAX_LOCK_RESETS->intValue()) {
            $this->lockTimer = 0.0;
            $this->lockResets++;
        }

        $this->onGround = is_null($this->active)
            ? false
            : $this->wouldCollide($this->active->withOffset(0, 1));
    }

    private function tryMove(int $dx, int $dy): bool
    {
        if (is_null($this->active)) {
            return false;
        }

        $candidate = $this->active->withOffset($dx, $dy);

        if ($this->wouldCollide($candidate)) {
            return false;
        }

        $this->active = $candidate;

        return true;
    }

    private function wouldCollide(ActivePiece $piece): bool
    {
        return ! $this->board->fits($piece->cells());
    }

    private function lockActive(): void
    {
        if (is_null($this->active)) {
            return;
        }

        $this->board->lock($this->active->cells(), $this->active->type);
        $cleared = $this->board->clearFullLines();
        $this->scoring->awardLineClear(count($cleared));
        $this->active = null;
        $this->holdUsedThisPiece = false;
        $this->resetLockState();
        $this->spawnNext();
    }

    private function spawnNext(): void
    {
        $this->spawnType($this->bag->take());
    }

    private function spawnType(TetrominoType $type): void
    {
        // Guideline-ish spawn: centered, in hidden rows.
        $x = $type === TetrominoType::O ? 4 : 4;
        $y = 1;
        $piece = new ActivePiece($type, $x, $y, RotationState::SPAWN);

        if ($this->wouldCollide($piece)) {
            $this->active = $piece;
            $this->gameOver = true;

            return;
        }

        $this->active = $piece;
        $this->gravityAccum = 0.0;
        $this->softDropAccum = 0.0;
        $this->resetLockState();
    }

    private function resetLockState(): void
    {
        $this->lockTimer = 0.0;
        $this->lockResets = 0;
        $this->onGround = is_null($this->active)
            ? false
            : $this->wouldCollide($this->active->withOffset(0, 1));
    }
}
