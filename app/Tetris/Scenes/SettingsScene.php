<?php

namespace App\Tetris\Scenes;

use App\Tetris\Enums\AppPhase;
use App\Tetris\Enums\DebugMenuOption;
use App\Tetris\Enums\RenderBackend;
use App\Tetris\Enums\SettingsMenuOption;
use App\Tetris\Enums\UiTypeface;
use App\Tetris\GameSettings;
use App\Tetris\Input\InputPoller;
use App\Tetris\Rendering\AssetBank;
use App\Tetris\Rendering\UiText;
use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\GameEngine\Signals\Signal;
use ScrapyardIO\UX\Core\PaintContext;
use ScrapyardIO\UX\Support\Color;

#[Signal('phase_requested', ['phase' => AppPhase::class])]
final class SettingsScene extends Node2D
{
    private SettingsMenuOption $selected = SettingsMenuOption::START_LEVEL;

    private DebugMenuOption $debugSelected = DebugMenuOption::INPUT_HUD;

    private bool $inDebug = false;

    public function __construct(
        private readonly AssetBank $assets,
        private readonly InputPoller $input,
        private readonly GameSettings $settings,
        private readonly RenderBackend $renderer,
    ) {
        parent::__construct('settings_scene');
        $this->width = 960;
        $this->height = 720;
        $this->assets->menuBackground();
    }

    public function process(float $dt): void
    {
        if ($this->inDebug) {
            $this->processDebug();

            return;
        }

        if ($this->input->justPressed('menu_up')) {
            $this->selected = match ($this->selected) {
                SettingsMenuOption::START_LEVEL => SettingsMenuOption::BACK,
                SettingsMenuOption::DEBUG => SettingsMenuOption::START_LEVEL,
                SettingsMenuOption::BACK => SettingsMenuOption::DEBUG,
            };
        }

        if ($this->input->justPressed('menu_down') || $this->input->justPressed('soft_drop')) {
            $this->selected = match ($this->selected) {
                SettingsMenuOption::START_LEVEL => SettingsMenuOption::DEBUG,
                SettingsMenuOption::DEBUG => SettingsMenuOption::BACK,
                SettingsMenuOption::BACK => SettingsMenuOption::START_LEVEL,
            };
        }

        if ($this->selected === SettingsMenuOption::START_LEVEL) {
            if ($this->input->justPressed('move_left')) {
                $this->settings->bumpStartLevel(-1);
            }

            if ($this->input->justPressed('move_right')) {
                $this->settings->bumpStartLevel(1);
            }
        }

        if ($this->input->justPressed('confirm')) {
            match ($this->selected) {
                SettingsMenuOption::DEBUG => $this->inDebug = true,
                SettingsMenuOption::BACK => $this->emit('phase_requested', ['phase' => AppPhase::MENU]),
                SettingsMenuOption::START_LEVEL => null,
            };
        }

        if ($this->input->justPressed('pause')) {
            $this->emit('phase_requested', ['phase' => AppPhase::MENU]);
        }
    }

    private function processDebug(): void
    {
        if ($this->input->justPressed('menu_up')) {
            $this->debugSelected = $this->stepDebug(-1);
        }

        if ($this->input->justPressed('menu_down') || $this->input->justPressed('soft_drop')) {
            $this->debugSelected = $this->stepDebug(1);
        }

        if ($this->debugSelected === DebugMenuOption::TARGET_FPS) {
            if ($this->input->justPressed('move_left')) {
                $this->settings->cycleTargetFps(-1);
            }

            if ($this->input->justPressed('move_right')) {
                $this->settings->cycleTargetFps(1);
            }
        }

        if ($this->debugSelected === DebugMenuOption::VSYNC) {
            if ($this->input->justPressed('move_left') || $this->input->justPressed('move_right')) {
                $this->settings->toggleVsync();
            }
        }

        if ($this->input->justPressed('confirm')) {
            match ($this->debugSelected) {
                DebugMenuOption::INPUT_HUD => $this->settings->toggleShowInputDebug(),
                DebugMenuOption::FPS_OVERLAY => $this->settings->toggleShowFps(),
                DebugMenuOption::VSYNC => $this->settings->toggleVsync(),
                DebugMenuOption::TARGET_FPS => $this->settings->cycleTargetFps(1),
                DebugMenuOption::BACK => $this->inDebug = false,
            };
        }

        if ($this->input->justPressed('pause')) {
            $this->inDebug = false;
        }
    }

    private function stepDebug(int $delta): DebugMenuOption
    {
        $cases = DebugMenuOption::cases();
        $count = count($cases);
        $index = ($this->debugSelected->value + $delta + $count) % $count;

        return $cases[$index];
    }

    protected function onDraw(PaintContext $ctx): void
    {
        $ctx->renderer->fillRect(0, 0, 960, 720, Color::fromHex('#0E1524')->pack());

        if ($this->inDebug) {
            $this->drawDebug($ctx);

            return;
        }

        $this->drawRoot($ctx);
    }

    private function drawRoot(PaintContext $ctx): void
    {
        $ink = Color::fromHex('#E8EEF8')->pack();
        $muted = Color::fromHex('#8FA3C0')->pack();
        $accent = Color::fromHex('#F0C84A')->pack();
        $idle = Color::fromHex('#3D5A80')->pack();
        $panel = Color::fromHex('#162033')->pack();
        $dim = Color::fromHex('#6A7A90')->pack();

        UiText::printCenteredX($ctx, 480, 70, 'SETTINGS', $accent, UiTypeface::TITLE);
        UiText::printCenteredX(
            $ctx,
            480,
            130,
            'Renderer: '.$this->renderer->label(),
            $muted,
            UiTypeface::BODY,
        );

        $this->drawRow($ctx, 220, 'Start level  < '.$this->settings->startLevel().' >', $this->selected === SettingsMenuOption::START_LEVEL, $panel, $accent, $idle, $ink, $dim);
        $this->drawRow($ctx, 300, 'Debug…', $this->selected === SettingsMenuOption::DEBUG, $panel, $accent, $idle, $ink, $dim);
        $this->drawRow($ctx, 380, 'Back', $this->selected === SettingsMenuOption::BACK, $panel, $accent, $idle, $ink, $dim);

        UiText::printCenteredX($ctx, 480, 500, 'Up/Down select  Left/Right adjust  Enter confirm  Esc back', $muted, UiTypeface::CAPTION);
        UiText::printCenteredX($ctx, 480, 680, 'Made with ADHD by ProjectSaturnStudios', $muted, UiTypeface::CAPTION);
    }

    private function drawDebug(PaintContext $ctx): void
    {
        $ink = Color::fromHex('#E8EEF8')->pack();
        $muted = Color::fromHex('#8FA3C0')->pack();
        $accent = Color::fromHex('#F0C84A')->pack();
        $idle = Color::fromHex('#3D5A80')->pack();
        $panel = Color::fromHex('#162033')->pack();
        $dim = Color::fromHex('#6A7A90')->pack();

        UiText::printCenteredX($ctx, 480, 70, 'DEBUG', $accent, UiTypeface::TITLE);

        $inputLabel = 'Input HUD  '.($this->settings->showInputDebug() ? '[ON]' : '[OFF]');
        $fpsLabel = 'FPS overlay  '.($this->settings->showFps() ? '[ON]' : '[OFF]');
        $vsyncLabel = 'VSync  ['.$this->settings->vsync()->label().']';
        $targetLabel = 'Target FPS  < '.$this->settings->targetFps()->label().' >';

        $this->drawRow($ctx, 180, $inputLabel, $this->debugSelected === DebugMenuOption::INPUT_HUD, $panel, $accent, $idle, $ink, $dim);
        $this->drawRow($ctx, 250, $fpsLabel, $this->debugSelected === DebugMenuOption::FPS_OVERLAY, $panel, $accent, $idle, $ink, $dim);
        $this->drawRow($ctx, 320, $vsyncLabel, $this->debugSelected === DebugMenuOption::VSYNC, $panel, $accent, $idle, $ink, $dim);
        $this->drawRow($ctx, 390, $targetLabel, $this->debugSelected === DebugMenuOption::TARGET_FPS, $panel, $accent, $idle, $ink, $dim);
        $this->drawRow($ctx, 460, 'Back', $this->debugSelected === DebugMenuOption::BACK, $panel, $accent, $idle, $ink, $dim);

        $hint = $this->settings->vsyncEnabled()
            ? 'VSync on: 30 / 60 / 120 / 180 / 240   Uncapped requires VSync off'
            : 'VSync off: 30 / 60 / 120 / 180 / 240 / Uncapped';
        UiText::printCenteredX($ctx, 480, 540, $hint, $muted, UiTypeface::CAPTION);
        UiText::printCenteredX($ctx, 480, 570, 'Enter toggles  Left/Right cycles  Esc back', $muted, UiTypeface::CAPTION);
        UiText::printCenteredX($ctx, 480, 680, 'Made with ADHD by ProjectSaturnStudios', $muted, UiTypeface::CAPTION);
    }

    private function drawRow(
        PaintContext $ctx,
        int $y,
        string $label,
        bool $on,
        int $panel,
        int $accent,
        int $idle,
        int $ink,
        int $dim,
    ): void {
        $ctx->renderer->fillRect(200, $y, 560, 56, $panel);
        $ctx->renderer->drawRect(200, $y, 560, 56, $on ? $accent : $idle);
        UiText::printInBox($ctx, 200, $y, 560, 56, $label, $on ? $ink : $dim, UiTypeface::BODY);
    }
}
