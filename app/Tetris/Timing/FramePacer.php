<?php

namespace App\Tetris\Timing;

use App\Tetris\Enums\TargetFpsOption;
use ScrapyardIO\Tubes\Canvas\OSWindow;

/**
 * Software frame cap (same contract as tubes {@see FramePaceNode}).
 *
 * Sleeps the remainder of the frame budget after paint/present so Target FPS
 * applies on every window driver — including Metal (vsync only pads up to the
 * display; we still sleep when the budget is larger than present waited).
 */
final class FramePacer
{
    public function pace(int $frameStartNs, TargetFpsOption $target, mixed $canvas = null): void
    {
        $hz = $target->hertz();

        if (is_null($hz)) {
            return;
        }

        $budgetNs = intdiv(1_000_000_000, max(1, (int) round($hz)));
        $deadlineNs = $frameStartNs + $budgetNs;

        while (true) {
            $remainingNs = $deadlineNs - hrtime(true);

            if ($remainingNs <= 0) {
                return;
            }

            // Slice sleeps so HID stays warm (SDL3 / GLFW) without overshooting the deadline.
            $sliceUs = (int) min(2000, max(1, intdiv($remainingNs, 1000)));
            usleep($sliceUs);

            if ($canvas instanceof OSWindow) {
                $canvas->pollEvents();
            }
        }
    }
}
