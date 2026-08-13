<?php

namespace App\Tetris;

use App\Tetris\Enums\AppPhase;
use App\Tetris\Enums\RenderBackend;
use App\Tetris\Input\InputPoller;
use App\Tetris\Rendering\AssetBank;
use App\Tetris\Rendering\DebugOverlay;
use App\Tetris\Scenes\GameOverScene;
use App\Tetris\Scenes\LogoScene;
use App\Tetris\Scenes\MenuScene;
use App\Tetris\Scenes\PlayScene;
use App\Tetris\Scenes\SettingsScene;
use ScrapyardIO\GameEngine\Nodes\Node;
use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\GameEngine\Signals\Signal;
use ScrapyardIO\UX\Core\Node as UxNode;

/**
 * Owns scene phase transitions for the Tetris sketch.
 *
 * Child scenes request navigation via {@see Signal} `phase_requested`.
 * PlayScene emits `paused` / `resumed` / `game_over`; root opens GameOverScene.
 * Phase swaps use SceneTree addChild/removeChild only.
 * {@see DebugOverlay} is a permanent sibling so FPS/input HUD survive phase swaps.
 */
#[Signal('phase_changed', ['phase' => AppPhase::class])]
final class TetrisRoot extends Node
{
    private AppPhase $phase = AppPhase::LOGO;

    private ?AppPhase $pendingPhase = null;

    private ?UxNode $current = null;

    private DebugOverlay $overlay;

    private int $lastScore = 0;

    private int $lastLevel = 1;

    private int $lastLines = 0;

    public bool $wantsQuit = false;

    public function __construct(
        private readonly AssetBank $assets,
        private readonly InputPoller $input,
        private readonly GameSettings $settings,
        private readonly RenderBackend $renderer,
    ) {
        parent::__construct('tetris_root');
        $this->overlay = new DebugOverlay($this->settings, $this->input);
        $this->addChild($this->overlay);
        $this->applyPhase(AppPhase::LOGO);
    }

    public function settings(): GameSettings
    {
        return $this->settings;
    }

    public function phase(): AppPhase
    {
        return $this->phase;
    }

    public function onPhaseRequested(AppPhase $phase): void
    {
        $this->pendingPhase = $phase;
    }

    public function onPlayPaused(): void
    {
        //
    }

    public function onPlayResumed(): void
    {
        //
    }

    public function onPlayGameOver(int $score, int $level, int $lines): void
    {
        $this->lastScore = $score;
        $this->lastLevel = $level;
        $this->lastLines = $lines;
        $this->pendingPhase = AppPhase::GAME_OVER;
    }

    public function process(float $dt): void
    {
        $this->input->sync($dt);

        if (! is_null($this->pendingPhase)) {
            $next = $this->pendingPhase;
            $this->pendingPhase = null;
            $this->applyPhase($next);
        }
    }

    /**
     * Delivered frame duration after paint/present/pace — drives the FPS overlay.
     */
    public function noteDeliveredFrameDt(float $dt): void
    {
        $this->overlay->noteFrameDt($dt);
    }

    private function applyPhase(AppPhase $phase): void
    {
        if (! is_null($this->current)) {
            $this->removeChild($this->current);
            $this->current = null;
        }

        $this->phase = $phase;

        if ($phase === AppPhase::QUIT) {
            $this->wantsQuit = true;
            $this->emit('phase_changed', ['phase' => $phase]);

            return;
        }

        $this->current = match ($phase) {
            AppPhase::LOGO => new LogoScene($this->assets, $this->input),
            AppPhase::MENU => new MenuScene($this->assets, $this->input),
            AppPhase::SETTINGS => new SettingsScene(
                $this->assets,
                $this->input,
                $this->settings,
                $this->renderer,
            ),
            AppPhase::PLAY => new PlayScene($this->assets, $this->input, $this->settings),
            AppPhase::GAME_OVER => new GameOverScene(
                $this->assets,
                $this->input,
                $this->lastScore,
                $this->lastLevel,
                $this->lastLines,
            ),
        };

        $this->wireScene($this->current);
        $this->addChild($this->current);
        // Keep overlay last in child order so it paints above the active scene.
        $this->removeChild($this->overlay);
        $this->addChild($this->overlay);
        $this->emit('phase_changed', ['phase' => $phase]);
    }

    private function wireScene(UxNode $scene): void
    {
        if (! $scene instanceof Node2D) {
            return;
        }

        $scene->connect('phase_requested', $this, 'onPhaseRequested');

        if ($scene instanceof PlayScene) {
            $scene->connect('paused', $this, 'onPlayPaused');
            $scene->connect('resumed', $this, 'onPlayResumed');
            $scene->connect('game_over', $this, 'onPlayGameOver');
        }
    }
}
