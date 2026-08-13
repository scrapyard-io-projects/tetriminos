<?php

namespace App\Tetris\Rendering;

use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\GameEngine\Resources\ImageTexture;
use ScrapyardIO\UX\Core\PaintContext;

/**
 * Fast textured quad: precomputes horizontal runs and draws via fillRect.
 * Suitable for backgrounds / large UI panels (textures, not solid primitives).
 */
class TexturedPanel extends Node2D
{
    private ?ImageTexture $texture = null;

    /** @var list<array{0: int, 1: int, 2: int, 3: int}> x,y,w,color local runs */
    private array $runs = [];

    public function setTexture(?ImageTexture $texture): static
    {
        $this->texture = $texture;
        $this->runs = [];

        if (! is_null($texture)) {
            $this->width = $texture->width();
            $this->height = $texture->height();
            $this->runs = self::buildRuns($texture);
        }

        return $this;
    }

    public function texture(): ?ImageTexture
    {
        return $this->texture;
    }

    protected function onDraw(PaintContext $ctx): void
    {
        if ($this->runs === []) {
            return;
        }

        $ox = (int) round($this->globalX());
        $oy = (int) round($this->globalY());

        foreach ($this->runs as [$x, $y, $w, $color]) {
            $ctx->renderer->fillRect($ox + $x, $oy + $y, $w, 1, $color);
        }
    }

    /**
     * @return list<array{0: int, 1: int, 2: int, 3: int}>
     */
    public static function buildRuns(ImageTexture $texture): array
    {
        $rgba = $texture->rgba();
        $width = $texture->width();
        $height = $texture->height();
        $runs = [];
        $offset = 0;

        for ($y = 0; $y < $height; $y++) {
            $runX = 0;
            $runW = 0;
            $runColor = 0;
            $active = false;

            for ($x = 0; $x < $width; $x++) {
                $r = ord($rgba[$offset]);
                $g = ord($rgba[$offset + 1]);
                $b = ord($rgba[$offset + 2]);
                $a = ord($rgba[$offset + 3]);
                $offset += 4;

                if ($a < 16) {
                    if ($active) {
                        $runs[] = [$runX, $y, $runW, $runColor];
                        $active = false;
                    }

                    continue;
                }

                // Quantize alpha for opaque-ish blit via fillRect (no blend).
                $color = ($r << 24) | ($g << 16) | ($b << 8) | 0xFF;

                if (! $active) {
                    $active = true;
                    $runX = $x;
                    $runW = 1;
                    $runColor = $color;
                } elseif ($color === $runColor) {
                    $runW++;
                } else {
                    $runs[] = [$runX, $y, $runW, $runColor];
                    $runX = $x;
                    $runW = 1;
                    $runColor = $color;
                }
            }

            if ($active) {
                $runs[] = [$runX, $y, $runW, $runColor];
            }
        }

        return $runs;
    }
}
