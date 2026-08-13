<?php

namespace App\Tetris\Enums;

use ScrapyardIO\Fonts\FreeSans\Bold\FreeSans12PtBold;
use ScrapyardIO\Fonts\FreeSans\Bold\FreeSans9PtBold;
use ScrapyardIO\Fonts\FreeSans\FreeSans18Pt;
use ScrapyardIO\Fonts\FreeSans\FreeSans9Pt;
use ScrapyardIO\Tubes\Contracts\Fonts\GFXFont;

/**
 * Tetris UI type roles → FreeSans (Adafruit 1bpp) from scrapyard-io/autopen.
 *
 * Montserrat LVGL faces are in autopen too, but their yOffsets + the old tubes
 * cap-height bias produced broken Q/g/y glyphs. FreeSans matches DrawsText’s
 * Adafruit baseline path.
 */
enum UiTypeface: string
{
    case TITLE = 'title';
    case HEADING = 'heading';
    case BODY = 'body';
    case CAPTION = 'caption';

    public function font(): GFXFont
    {
        return match ($this) {
            self::TITLE => self::cached(FreeSans18Pt::class),
            self::HEADING => self::cached(FreeSans12PtBold::class),
            self::BODY => self::cached(FreeSans9PtBold::class),
            self::CAPTION => self::cached(FreeSans9Pt::class),
        };
    }

    /**
     * @param  class-string<GFXFont>  $class
     */
    private static function cached(string $class): GFXFont
    {
        static $fonts = [];

        if (! isset($fonts[$class])) {
            $fonts[$class] = new $class;
        }

        return $fonts[$class];
    }
}
