<?php

namespace App\Tetris\Rendering;

use App\Tetris\Enums\UiTypeface;
use ScrapyardIO\UX\Core\PaintContext;

/**
 * Draw Montserrat UI copy with real glyph bounds (printLocal is left + baseline anchored).
 */
final class UiText
{
    /**
     * Center text inside an axis-aligned box (button / panel header / title band).
     */
    public static function printInBox(
        PaintContext $ctx,
        int $boxX,
        int $boxY,
        int $boxW,
        int $boxH,
        string $text,
        int $fg,
        UiTypeface $face = UiTypeface::BODY,
        ?int $bg = null,
    ): void {
        if ($text === '') {
            return;
        }

        [$cursorX, $cursorY] = self::cursorForBox($ctx, $boxX, $boxY, $boxW, $boxH, $text, $face);
        self::printAt($ctx, $cursorX, $cursorY, $text, $fg, $face, $bg);
    }

    /**
     * Horizontally center on an absolute x; $y is the top of the ink box.
     */
    public static function printCenteredX(
        PaintContext $ctx,
        int $centerX,
        int $y,
        string $text,
        int $fg,
        UiTypeface $face = UiTypeface::BODY,
        ?int $bg = null,
    ): void {
        if ($text === '') {
            return;
        }

        $bounds = self::measure($ctx, $text, $face);
        $cursorX = $centerX - intdiv($bounds['w'], 2) - $bounds['x1'];
        $cursorY = $y - $bounds['y1'];
        self::printAt($ctx, $cursorX, $cursorY, $text, $fg, $face, $bg);
    }

    /**
     * Left-aligned run (hints / debug) using a UI face.
     */
    public static function print(
        PaintContext $ctx,
        int $x,
        int $y,
        string $text,
        int $fg,
        UiTypeface $face = UiTypeface::CAPTION,
        ?int $bg = null,
    ): void {
        if ($text === '') {
            return;
        }

        $bounds = self::measure($ctx, $text, $face);
        self::printAt($ctx, $x - $bounds['x1'], $y - $bounds['y1'], $text, $fg, $face, $bg);
    }

    /**
     * @return array{x1: int, y1: int, w: int, h: int}
     */
    public static function measure(PaintContext $ctx, string $text, UiTypeface $face): array
    {
        $renderer = $ctx->renderer;
        $renderer->setFont($face->font())->setTextSize(1)->setTextWrap(false);
        $bounds = $renderer->getTextBounds($text, 0, 0);
        $renderer->setFont(null);

        return [
            'x1' => (int) $bounds['x1'],
            'y1' => (int) $bounds['y1'],
            'w' => max(0, (int) $bounds['w']),
            'h' => max(0, (int) $bounds['h']),
        ];
    }

    /**
     * @return array{0: int, 1: int} local cursor x/y
     */
    private static function cursorForBox(
        PaintContext $ctx,
        int $boxX,
        int $boxY,
        int $boxW,
        int $boxH,
        string $text,
        UiTypeface $face,
    ): array {
        $bounds = self::measure($ctx, $text, $face);
        $cursorX = $boxX + intdiv($boxW - $bounds['w'], 2) - $bounds['x1'];
        $cursorY = $boxY + intdiv($boxH - $bounds['h'], 2) - $bounds['y1'];

        return [$cursorX, $cursorY];
    }

    private static function printAt(
        PaintContext $ctx,
        int $localX,
        int $localY,
        string $text,
        int $fg,
        UiTypeface $face,
        ?int $bg,
    ): void {
        $buffer = $ctx->localToBuffer($localX, $localY);
        $ctx->renderer
            ->setFont($face->font())
            ->setTextSize(1)
            ->setTextWrap(false)
            ->setTextColor($fg, $bg)
            ->setCursor($buffer->x, $buffer->y)
            ->print($text)
            ->setFont(null);
    }
}
