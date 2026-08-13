<?php

namespace App\Tetris\Rendering;

use App\Tetris\Enums\UiTypeface;
use App\Tetris\GameSettings;
use App\Tetris\Input\InputPoller;
use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\UX\Core\PaintContext;
use ScrapyardIO\UX\Support\Color;

/**
 * Permanent HUD for optional FPS (green, upper-right) and input debug lines.
 */
final class DebugOverlay extends Node2D
{
    private float $frameDt = 1.0 / 60.0;

    public function __construct(
        private readonly GameSettings $settings,
        private readonly InputPoller $input,
    ) {
        parent::__construct('debug_overlay');
        $this->width = 960;
        $this->height = 720;
    }

    public function noteFrameDt(float $dt): void
    {
        if ($dt > 0.0) {
            $this->frameDt = $dt;
        }
    }

    protected function onDraw(PaintContext $ctx): void
    {
        if ($this->settings->showFps()) {
            $fps = 1.0 / max(0.0001, $this->frameDt);
            UiText::print(
                $ctx,
                820,
                16,
                sprintf('FPS %.0f', $fps),
                Color::fromHex('#33FF66')->pack(),
                UiTypeface::BODY,
            );
        }

        if (! $this->settings->showInputDebug()) {
            return;
        }

        $muted = Color::fromHex('#6A7A90')->pack();

        foreach ($this->input->debugLines() as $i => $line) {
            UiText::print($ctx, 24, 640 + $i * 20, $line, $muted, UiTypeface::CAPTION);
        }
    }
}
