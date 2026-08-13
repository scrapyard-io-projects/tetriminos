<?php

namespace App\Tetris;

use App\Tetris\Enums\SettingsBound;
use App\Tetris\Enums\SettingsCacheKey;
use App\Tetris\Enums\TargetFpsOption;
use App\Tetris\Enums\VsyncMode;
use Fabricate\Core\MagicAliases\Cache;

/**
 * Session + Redis-backed Tetriminos settings (Cache store: redis).
 */
final class GameSettings
{
    private int $startLevel = 1;

    private bool $showInputDebug = false;

    private bool $showFps = false;

    private VsyncMode $vsync = VsyncMode::ON;

    private TargetFpsOption $targetFps = TargetFpsOption::FPS_60;

    public static function load(): self
    {
        $settings = new self;
        $payload = Cache::store('redis')->get(SettingsCacheKey::PAYLOAD->value);

        if (is_array($payload)) {
            $settings->applyPayload($payload);
        }

        return $settings;
    }

    public function persist(): void
    {
        Cache::store('redis')->forever(SettingsCacheKey::PAYLOAD->value, $this->toPayload());
    }

    public function startLevel(): int
    {
        return $this->startLevel;
    }

    public function setStartLevel(int $level): void
    {
        $this->startLevel = max(
            SettingsBound::MIN_START_LEVEL->value,
            min(SettingsBound::MAX_START_LEVEL->value, $level),
        );
        $this->persist();
    }

    public function bumpStartLevel(int $delta): void
    {
        $this->setStartLevel($this->startLevel + $delta);
    }

    public function showInputDebug(): bool
    {
        return $this->showInputDebug;
    }

    public function setShowInputDebug(bool $on): void
    {
        $this->showInputDebug = $on;
        $this->persist();
    }

    public function toggleShowInputDebug(): void
    {
        $this->setShowInputDebug(! $this->showInputDebug);
    }

    public function showFps(): bool
    {
        return $this->showFps;
    }

    public function setShowFps(bool $on): void
    {
        $this->showFps = $on;
        $this->persist();
    }

    public function toggleShowFps(): void
    {
        $this->setShowFps(! $this->showFps);
    }

    public function vsync(): VsyncMode
    {
        return $this->vsync;
    }

    public function vsyncEnabled(): bool
    {
        return $this->vsync->enabled();
    }

    public function setVsync(VsyncMode $mode): void
    {
        $this->vsync = $mode;
        $this->clampTargetFps();
        $this->persist();
    }

    public function toggleVsync(): void
    {
        $this->setVsync($this->vsync->toggled());
    }

    public function targetFps(): TargetFpsOption
    {
        return $this->targetFps;
    }

    public function setTargetFps(TargetFpsOption $option): void
    {
        $this->targetFps = $option;
        $this->clampTargetFps();
        $this->persist();
    }

    public function cycleTargetFps(int $direction): void
    {
        $vsyncOn = $this->vsyncEnabled();
        $this->setTargetFps(
            $direction >= 0
                ? $this->targetFps->nextAllowed($vsyncOn)
                : $this->targetFps->previousAllowed($vsyncOn),
        );
    }

    /**
     * @return array{start_level: int, show_input_debug: bool, show_fps: bool, vsync: string, target_fps: string}
     */
    private function toPayload(): array
    {
        return [
            'start_level' => $this->startLevel,
            'show_input_debug' => $this->showInputDebug,
            'show_fps' => $this->showFps,
            'vsync' => $this->vsync->value,
            'target_fps' => $this->targetFps->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyPayload(array $payload): void
    {
        if (isset($payload['start_level']) && is_numeric($payload['start_level'])) {
            $this->startLevel = max(
                SettingsBound::MIN_START_LEVEL->value,
                min(SettingsBound::MAX_START_LEVEL->value, (int) $payload['start_level']),
            );
        }

        if (array_key_exists('show_input_debug', $payload)) {
            $this->showInputDebug = (bool) $payload['show_input_debug'];
        }

        if (array_key_exists('show_fps', $payload)) {
            $this->showFps = (bool) $payload['show_fps'];
        }

        if (isset($payload['vsync']) && is_string($payload['vsync'])) {
            $mode = VsyncMode::tryFrom($payload['vsync']);

            if (! is_null($mode)) {
                $this->vsync = $mode;
            }
        }

        if (isset($payload['target_fps']) && is_string($payload['target_fps'])) {
            $option = TargetFpsOption::tryFrom($payload['target_fps']);

            if (! is_null($option)) {
                $this->targetFps = $option;
            }
        }

        $this->clampTargetFps();
    }

    private function clampTargetFps(): void
    {
        if (! $this->targetFps->allowedWhenVsync($this->vsyncEnabled())) {
            $this->targetFps = TargetFpsOption::FPS_60;
        }
    }
}
