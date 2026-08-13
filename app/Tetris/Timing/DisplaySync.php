<?php

namespace App\Tetris\Timing;

use App\Tetris\Enums\TargetFpsOption;
use App\Tetris\GameSettings;
use ScrapyardIO\Tubes\Canvas\OSWindow;

/**
 * Push Tetriminos VSync onto the live window handler.
 *
 * Hardware vsync follows the Settings VSync row on every GPU backend
 * (metal / cuda / open-gl / vulkan / sdl3). It is a floor at the panel refresh —
 * sleep in {@see FramePacer} can only cap.
 * VSync OFF + Uncapped must be allowed to exceed the panel.
 *
 * Apply only when the computed flag changes — setVsync on every loop is a GPU sync
 * (OpenGL CGL / glfwSwapInterval) and collapses 60fps to ~45.
 *
 * Handlers expose setVsync(bool): static. No brand instanceof — CUDA and future
 * companions work without Tetris imports.
 */
final class DisplaySync
{
    private static ?int $lastHandlerId = null;

    private static ?bool $lastVsync = null;

    public static function apply(mixed $canvas, GameSettings $settings): void
    {
        if (! $canvas instanceof OSWindow) {
            return;
        }

        $handler = $canvas->handler();

        if (! method_exists($handler, 'setVsync')) {
            return;
        }

        $vsync = self::hardwareEnabled($settings->vsyncEnabled(), $settings->targetFps());
        $handlerId = spl_object_id($handler);

        if (self::$lastHandlerId === $handlerId && self::$lastVsync === $vsync) {
            return;
        }

        $handler->setVsync($vsync);
        self::$lastHandlerId = $handlerId;
        self::$lastVsync = $vsync;
    }

    /**
     * Hardware present lock = Settings VSync. Numbered targets above the panel cannot
     * beat refresh while VSync is ON; VSync OFF leaves present unlocked so Uncapped
     * (and software-capped 120+) can exceed the display.
     */
    public static function hardwareEnabled(bool $vsyncOn, TargetFpsOption $target): bool
    {
        return $vsyncOn && ! is_null($target->hertz());
    }
}
