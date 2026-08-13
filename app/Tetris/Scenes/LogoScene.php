<?php

namespace App\Tetris\Scenes;

use App\Tetris\Enums\AppPhase;
use App\Tetris\Enums\UiTypeface;
use App\Tetris\Input\InputPoller;
use App\Tetris\Rendering\AssetBank;
use App\Tetris\Rendering\UiText;
use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\GameEngine\Signals\Signal;
use ScrapyardIO\UX\Core\PaintContext;
use ScrapyardIO\UX\Support\Color;

#[Signal('phase_requested', ['phase' => AppPhase::class])]
final class LogoScene extends Node2D
{
    private float $elapsed = 0.0;

    private float $minTime = 1.2;

    private float $autoAdvance = 2.8;

    private bool $advanced = false;

    public function __construct(
        private readonly AssetBank $assets,
        private readonly InputPoller $input,
    ) {
        parent::__construct('logo_scene');
        $this->width = 960;
        $this->height = 720;
        $this->assets->menuBackground();
    }

    public function process(float $dt): void
    {
        if ($this->advanced) {
            return;
        }

        $this->elapsed += $dt;

        $skip = $this->input->justPressed('confirm') || $this->input->justPressed('pause');

        if (($skip && $this->elapsed >= $this->minTime) || $this->elapsed >= $this->autoAdvance) {
            $this->advanced = true;
            $this->emit('phase_requested', ['phase' => AppPhase::MENU]);
        }
    }

    protected function onDraw(PaintContext $ctx): void
    {
        $ctx->renderer->fillRect(0, 0, 960, 720, Color::fromHex('#0E1524')->pack());

        UiText::printCenteredX($ctx, 480, 260, 'TETRIMINOS', Color::fromHex('#F0C84A')->pack(), UiTypeface::TITLE);

        if ($this->elapsed > $this->minTime) {
            UiText::printCenteredX($ctx, 480, 420, 'Click window, then Enter / A', Color::fromHex('#C8D0E0')->pack(), UiTypeface::BODY);
        }

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
