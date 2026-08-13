<?php

namespace App\Tetris\Rendering;

use ScrapyardIO\GameEngine\Nodes\Node2D\Sprite2D;
use ScrapyardIO\GameEngine\Resources\ImageTexture;
use ScrapyardIO\GameEngine\Resources\Texture;
use ScrapyardIO\UX\Core\PaintContext;

/**
 * Sprite2D that actually blits ImageTexture RGBA via Renderer2D::drawPixels.
 */
class TexturedSprite extends Sprite2D
{
    /** @var list<array{0: int, 1: int, 2: int}>|null */
    private ?array $pixelCache = null;

    private string $cacheKey = '';

    public function setTexture(?Texture $texture): static
    {
        $this->pixelCache = null;
        $this->cacheKey = '';

        return parent::setTexture($texture);
    }

    protected function onDraw(PaintContext $ctx): void
    {
        $texture = $this->texture();

        if (is_null($texture) || ! ($texture instanceof ImageTexture)) {
            parent::onDraw($ctx);

            return;
        }

        $rgba = $texture->rgba();
        $width = $texture->width();
        $height = $texture->height();

        if ($rgba === '' || $width <= 0 || $height <= 0) {
            parent::onDraw($ctx);

            return;
        }

        $originX = (int) round($this->globalX());
        $originY = (int) round($this->globalY());
        $key = $originX.'|'.$originY.'|'.$width.'|'.$height.'|'.strlen($rgba);

        if ($this->cacheKey !== $key || is_null($this->pixelCache)) {
            $this->pixelCache = $this->buildPixels($rgba, $width, $height, $originX, $originY);
            $this->cacheKey = $key;
        }

        if ($this->pixelCache !== []) {
            $ctx->renderer->drawPixels($this->pixelCache);
        }
    }

    /**
     * @return list<array{0: int, 1: int, 2: int}>
     */
    private function buildPixels(string $rgba, int $width, int $height, int $originX, int $originY): array
    {
        $pixels = [];
        $expected = $width * $height * 4;

        if (strlen($rgba) < $expected) {
            return $pixels;
        }

        $offset = 0;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $r = ord($rgba[$offset]);
                $g = ord($rgba[$offset + 1]);
                $b = ord($rgba[$offset + 2]);
                $a = ord($rgba[$offset + 3]);
                $offset += 4;

                if ($a < 16) {
                    continue;
                }

                $color = ($r << 24) | ($g << 16) | ($b << 8) | $a;
                $pixels[] = [$originX + $x, $originY + $y, $color];
            }
        }

        return $pixels;
    }
}
