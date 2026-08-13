<?php

namespace App\Tetris\Scenes;

use App\Tetris\Enums\AppPhase;
use App\Tetris\Enums\MenuOption;
use App\Tetris\Enums\UiTypeface;
use App\Tetris\Input\InputPoller;
use App\Tetris\Rendering\AssetBank;
use App\Tetris\Rendering\UiText;
use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\GameEngine\Signals\Signal;
use ScrapyardIO\UX\Core\PaintContext;
use ScrapyardIO\UX\Support\Color;

#[Signal('phase_requested', ['phase' => AppPhase::class])]
final class MenuScene extends Node2D
{
    private MenuOption $selected = MenuOption::PLAY;

    public function __construct(
        private readonly AssetBank $assets,
        private readonly InputPoller $input,
    ) {
        parent::__construct('menu_scene');
        $this->width = 960;
        $this->height = 720;
        $this->assets->menuBackground();
        $this->assets->title();
        $this->assets->playButton();
    }

    public function process(float $dt): void
    {
        if ($this->input->justPressed('menu_up') || $this->input->justPressed('move_left')) {
            $this->selected = match ($this->selected) {
                MenuOption::PLAY => MenuOption::QUIT,
                MenuOption::SETTINGS => MenuOption::PLAY,
                MenuOption::QUIT => MenuOption::SETTINGS,
            };
        }

        if ($this->input->justPressed('menu_down') || $this->input->justPressed('move_right') || $this->input->justPressed('soft_drop')) {
            $this->selected = match ($this->selected) {
                MenuOption::PLAY => MenuOption::SETTINGS,
                MenuOption::SETTINGS => MenuOption::QUIT,
                MenuOption::QUIT => MenuOption::PLAY,
            };
        }

        if ($this->input->justPressed('confirm')) {
            $this->emit('phase_requested', [
                'phase' => match ($this->selected) {
                    MenuOption::PLAY => AppPhase::PLAY,
                    MenuOption::SETTINGS => AppPhase::SETTINGS,
                    MenuOption::QUIT => AppPhase::QUIT,
                },
            ]);
        }

        if ($this->input->justPressed('pause')) {
            $this->emit('phase_requested', ['phase' => AppPhase::QUIT]);
        }
    }

    protected function onDraw(PaintContext $ctx): void
    {
        $ctx->renderer->fillRect(0, 0, 960, 720, Color::fromHex('#0E1524')->pack());

        UiText::printCenteredX($ctx, 480, 100, 'TETRIMINOS', Color::fromHex('#F0C84A')->pack(), UiTypeface::TITLE);

        $inkOn = Color::fromHex('#FFFFFF')->pack();
        $inkOff = Color::fromHex('#6A7A90')->pack();
        $accent = Color::fromHex('#F0C84A')->pack();
        $idle = Color::fromHex('#3D5A80')->pack();
        $panel = Color::fromHex('#162033')->pack();

        $this->drawButton($ctx, 250, 'PLAY', $this->selected === MenuOption::PLAY, $panel, $accent, $idle, $inkOn, $inkOff);
        $this->drawButton($ctx, 330, 'SETTINGS', $this->selected === MenuOption::SETTINGS, $panel, $accent, $idle, $inkOn, $inkOff);
        $this->drawButton($ctx, 410, 'QUIT', $this->selected === MenuOption::QUIT, $panel, $accent, $idle, $inkOn, $inkOff);

        UiText::printCenteredX($ctx, 480, 520, 'Click the window once for keyboard focus.', Color::fromHex('#FFE08A')->pack(), UiTypeface::BODY);
        UiText::printCenteredX($ctx, 480, 560, 'Keyboard: Arrows  Enter confirm', Color::fromHex('#8FA3C0')->pack(), UiTypeface::CAPTION);
        UiText::printCenteredX($ctx, 480, 590, 'Pad: D-pad  A confirm  Start quit', Color::fromHex('#8FA3C0')->pack(), UiTypeface::CAPTION);

        UiText::printCenteredX(
            $ctx,
            480,
            680,
            'Made with ADHD by ProjectSaturnStudios',
            Color::fromHex('#8FA3C0')->pack(),
            UiTypeface::CAPTION,
        );
    }

    private function drawButton(
        PaintContext $ctx,
        int $y,
        string $label,
        bool $on,
        int $panel,
        int $accent,
        int $idle,
        int $inkOn,
        int $inkOff,
    ): void {
        $ctx->renderer->fillRect(330, $y, 300, 64, $panel);
        $ctx->renderer->drawRect(330, $y, 300, 64, $on ? $accent : $idle);
        UiText::printInBox($ctx, 330, $y, 300, 64, $label, $on ? $inkOn : $inkOff, UiTypeface::HEADING);
    }
}
