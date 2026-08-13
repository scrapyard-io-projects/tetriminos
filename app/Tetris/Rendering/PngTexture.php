<?php

namespace App\Tetris\Rendering;

use ScrapyardIO\GameEngine\Resources\ImageTexture;

/**
 * Load PNG/JPEG into ImageTexture (0xRRGGBBAA host words as RGBA byte blob).
 */
final class PngTexture
{
    public static function load(string $path): ImageTexture
    {
        if (! is_file($path)) {
            throw new \InvalidArgumentException("Texture not found: {$path}");
        }

        $raw = @file_get_contents($path);

        if ($raw === false) {
            throw new \RuntimeException("Unable to read texture: {$path}");
        }

        $image = @imagecreatefromstring($raw);

        if ($image === false) {
            throw new \RuntimeException("Unable to decode texture: {$path}");
        }

        imagesavealpha($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        $blob = '';
        $fill = 0xFFFFFFFF;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $c = imagecolorat($image, $x, $y);
                $r = ($c >> 16) & 0xFF;
                $g = ($c >> 8) & 0xFF;
                $b = $c & 0xFF;
                $gdAlpha = ($c & 0x7F000000) >> 24; // 0 opaque … 127 transparent
                $a = (int) round((127 - $gdAlpha) * 255 / 127);
                $blob .= pack('C4', $r, $g, $b, $a);

                if ($a > 200) {
                    $fill = (($r & 0xFF) << 24) | (($g & 0xFF) << 16) | (($b & 0xFF) << 8) | 0xFF;
                }
            }
        }

        imagedestroy($image);

        return new ImageTexture($width, $height, $blob, $fill);
    }
}
