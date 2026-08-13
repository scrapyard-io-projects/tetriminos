<?php

namespace App\Tetris\Scenes;

use App\Tetris\Enums\AppPhase;
use App\Tetris\Enums\GameOverMenuOption;
use App\Tetris\Enums\UiTypeface;
use App\Tetris\Input\InputPoller;
use App\Tetris\Rendering\AssetBank;
use App\Tetris\Rendering\UiText;
use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\GameEngine\Signals\Signal;
use ScrapyardIO\UX\Core\PaintContext;
use ScrapyardIO\UX\Support\Color;

#[Signal('phase_requested', ['phase' => AppPhase::class])]
final class GameOverScene extends Node2D
{
    private GameOverMenuOption $selected = GameOverMenuOption::RETRY;

    public function __construct(
        private readonly AssetBank $assets,
        private readonly InputPoller $input,
        private readonly int $score,
        private readonly int $level,
        private readonly int $lines,
    ) {
        parent::__construct('game_over_scene');
        $this->width = 960;
        $this->height = 720;
        $this->assets->menuBackground();
    }

    public function process(float $dt): void
    {
        if ($this->input->justPressed('menu_up') || $this->input->justPressed('move_left')) {
            $this->selected = GameOverMenuOption::RETRY;
        }

        if ($this->input->justPressed('menu_down') || $this->input->justPressed('move_right') || $this->input->justPressed('soft_drop')) {
            $this->selected = GameOverMenuOption::MENU;
        }

        if ($this->input->justPressed('confirm')) {
            $this->emit('phase_requested', [
                'phase' => $this->selected === GameOverMenuOption::RETRY ? AppPhase::PLAY : AppPhase::MENU,
            ]);
        }

        if ($this->input->justPressed('pause')) {
            $this->emit('phase_requested', ['phase' => AppPhase::MENU]);
        }
    }

    protected function onDraw(PaintContext $ctx): void
    {
        $ctx->renderer->fillRect(0, 0, 960, 720, Color::fromHex('#0E1524')->pack());

        $ink = Color::fromHex('#E8EEF8')->pack();
        $muted = Color::fromHex('#8FA3C0')->pack();
        $accent = Color::fromHex('#F0C84A')->pack();
        $idle = Color::fromHex('#3D5A80')->pack();
        $panel = Color::fromHex('#162033')->pack();
        $dim = Color::fromHex('#6A7A90')->pack();

        UiText::printCenteredX($ctx, 480, 110, 'GAME OVER', Color::fromHex('#FF6688')->pack(), UiTypeface::TITLE);

        $ctx->renderer->fillRect(280, 220, 400, 160, $panel);
        $ctx->renderer->drawRect(280, 220, 400, 160, $idle);
        UiText::printCenteredX($ctx, 480, 240, 'SCORE  '.$this->score, $ink, UiTypeface::HEADING);
        UiText::printCenteredX($ctx, 480, 285, 'LEVEL  '.$this->level, Color::fromHex('#FFD27A')->pack(), UiTypeface::BODY);
        UiText::printCenteredX($ctx, 480, 325, 'LINES  '.$this->lines, Color::fromHex('#7DFFB0')->pack(), UiTypeface::BODY);

        $retryOn = $this->selected === GameOverMenuOption::RETRY;
        $menuOn = $this->selected === GameOverMenuOption::MENU;

        $ctx->renderer->fillRect(330, 420, 300, 64, $panel);
        $ctx->renderer->drawRect(330, 420, 300, 64, $retryOn ? $accent : $idle);
        UiText::printInBox($ctx, 330, 420, 300, 64, 'RETRY', $retryOn ? $ink : $dim, UiTypeface::HEADING);

        $ctx->renderer->fillRect(330, 510, 300, 64, $panel);
        $ctx->renderer->drawRect(330, 510, 300, 64, $menuOn ? $accent : $idle);
        UiText::printInBox($ctx, 330, 510, 300, 64, 'MENU', $menuOn ? $ink : $dim, UiTypeface::HEADING);

        UiText::printCenteredX($ctx, 480, 600, 'Up/Down select  Enter confirm  Esc menu', $muted, UiTypeface::CAPTION);

        UiText::printCenteredX(
            $ctx,
            480,
            680,
            'Made with ADHD by ProjectSaturnStudios',
            Color::fromHex('#8FA3C0')->pack(),
            UiTypeface::CAPTION,
        );
    }
}
