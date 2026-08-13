<?php

namespace App\Tetris\Rendering;

use App\Tetris\Enums\BoardDim;
use App\Tetris\Enums\TetrominoType;
use App\Tetris\Logic\PlayController;
use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\UX\Core\PaintContext;
use ScrapyardIO\UX\Support\Color;

/**
 * Draws the matrix with fillRect bevels (works on Metal / OGX / Vulkan).
 * Guideline colors come from AssetBank mino / ghost textures (Bitlytic Resources pattern).
 */
final class PlayfieldView extends Node2D
{
    private ?PlayController $game = null;

    /** @var array<string, int> 0xRRGGBBAA */
    private array $colors = [];

    /** @var array<string, array{border: int, mid: int, hi: int, sh: int}> */
    private array $bevels = [];

    private int $wellFill = 0;

    private int $wellStroke = 0;

    public function __construct(
        private readonly AssetBank $assets,
        string $name = 'playfield',
    ) {
        parent::__construct($name);
        $cell = BoardDim::CELL_PX->value;
        $this->width = BoardDim::WIDTH->value * $cell;
        $this->height = (BoardDim::HEIGHT->value - BoardDim::HIDDEN_ROWS->value) * $cell;
        $this->warmColors();
    }

    public function bind(PlayController $game): static
    {
        $this->game = $game;

        return $this;
    }

    protected function onDraw(PaintContext $ctx): void
    {
        if (is_null($this->game)) {
            return;
        }

        $ox = (int) round($this->globalX());
        $oy = (int) round($this->globalY());
        $hidden = BoardDim::HIDDEN_ROWS->value;
        $cell = BoardDim::CELL_PX->value;
        $height = BoardDim::HEIGHT->value;
        $width = BoardDim::WIDTH->value;

        // Well backdrop
        $ctx->renderer->fillRect($ox - 4, $oy - 4, $this->width + 8, $this->height + 8, $this->wellFill);
        $ctx->renderer->drawRect($ox - 4, $oy - 4, $this->width + 8, $this->height + 8, $this->wellStroke);

        for ($y = $hidden; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $px = $ox + $x * $cell;
                $py = $oy + ($y - $hidden) * $cell;
                $type = $this->game->board->get($x, $y);
                $this->drawCell($ctx, $px, $py, $cell, is_null($type) ? 'empty' : $type->value);
            }
        }

        if (! is_null($this->game->active)) {
            foreach ($this->game->ghostCells() as [$x, $y]) {
                if ($y < $hidden) {
                    continue;
                }
                $this->drawCell(
                    $ctx,
                    $ox + $x * $cell,
                    $oy + ($y - $hidden) * $cell,
                    $cell,
                    'ghost_'.$this->game->active->type->value,
                );
            }

            foreach ($this->game->active->cells() as [$x, $y]) {
                if ($y < $hidden) {
                    continue;
                }
                $this->drawCell(
                    $ctx,
                    $ox + $x * $cell,
                    $oy + ($y - $hidden) * $cell,
                    $cell,
                    $this->game->active->type->value,
                );
            }
        }
    }

    public function drawMiniPiece(PaintContext $ctx, int $ox, int $oy, ?TetrominoType $type, int $cell = 16): void
    {
        if (is_null($type)) {
            return;
        }

        $cells = \App\Tetris\Logic\SrsData::cells($type, \App\Tetris\Enums\RotationState::SPAWN);
        $minX = 0;
        $minY = 0;
        $maxX = 0;
        $maxY = 0;

        foreach ($cells as [$x, $y]) {
            $minX = min($minX, $x);
            $minY = min($minY, $y);
            $maxX = max($maxX, $x);
            $maxY = max($maxY, $y);
        }

        $w = ($maxX - $minX + 1) * $cell;
        $h = ($maxY - $minY + 1) * $cell;
        $sx = $ox - intdiv($w, 2);
        $sy = $oy - intdiv($h, 2);

        foreach ($cells as [$x, $y]) {
            $this->drawCell(
                $ctx,
                $sx + ($x - $minX) * $cell,
                $sy + ($y - $minY) * $cell,
                $cell,
                $type->value,
            );
        }
    }

    private function drawCell(PaintContext $ctx, int $px, int $py, int $cell, string $key): void
    {
        $bevel = $this->bevels[$key] ?? $this->bevels['empty'];

        $ctx->renderer->fillRect($px, $py, $cell, $cell, $bevel['border']);
        $inset = max(1, intdiv($cell, 10));
        $ctx->renderer->fillRect($px + $inset, $py + $inset, $cell - 2 * $inset, $cell - 2 * $inset, $bevel['mid']);
        $ctx->renderer->fillRect($px + $inset, $py + $inset, $cell - 2 * $inset, max(1, intdiv($cell, 8)), $bevel['hi']);
        $ctx->renderer->fillRect($px + $inset, $py + $inset, max(1, intdiv($cell, 8)), $cell - 2 * $inset, $bevel['hi']);
        $ctx->renderer->fillRect($px + $cell - $inset - 1, $py + $inset, 1, $cell - 2 * $inset, $bevel['sh']);
        $ctx->renderer->fillRect($px + $inset, $py + $cell - $inset - 1, $cell - 2 * $inset, 1, $bevel['sh']);
    }

    private function warmColors(): void
    {
        foreach (TetrominoType::cases() as $type) {
            $this->colors[$type->value] = $this->assets->mino($type)->fillColor();
            $this->colors['ghost_'.$type->value] = $this->assets->ghost($type)->fillColor();
        }

        $this->colors['empty'] = $this->assets->emptyCell()->fillColor();

        foreach ($this->colors as $key => $mid) {
            $this->bevels[$key] = $this->bevelFor($mid);
        }

        $this->wellFill = Color::fromHex('#0B1220')->pack();
        $this->wellStroke = Color::fromHex('#3D5A80')->pack();
    }

    /**
     * @return array{border: int, mid: int, hi: int, sh: int}
     */
    private function bevelFor(int $mid): array
    {
        $r = ($mid >> 24) & 0xFF;
        $g = ($mid >> 16) & 0xFF;
        $b = ($mid >> 8) & 0xFF;
        $a = $mid & 0xFF;

        return [
            'border' => $this->packRgb(max(0, $r - 70), max(0, $g - 70), max(0, $b - 70), $a),
            'mid' => $mid,
            'hi' => $this->packRgb(min(255, $r + 70), min(255, $g + 70), min(255, $b + 70), $a),
            'sh' => $this->packRgb(max(0, $r - 45), max(0, $g - 45), max(0, $b - 45), $a),
        ];
    }

    private function packRgb(int $r, int $g, int $b, int $a = 255): int
    {
        return (($r & 0xFF) << 24) | (($g & 0xFF) << 16) | (($b & 0xFF) << 8) | ($a & 0xFF);
    }
}
