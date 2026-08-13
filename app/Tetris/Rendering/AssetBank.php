<?php

namespace App\Tetris\Rendering;

use App\Tetris\Enums\TetrominoType;
use ScrapyardIO\GameEngine\Resources\ImageTexture;

final class AssetBank
{
    private string $root;

    /** @var array<string, ImageTexture> */
    private array $cache = [];

    public function __construct(?string $root = null)
    {
        $this->root = $root ?? dirname(__DIR__, 3).'/resources/tetris';
    }

    public function mino(TetrominoType $type): ImageTexture
    {
        return $this->get('minos/'.$type->value.'.png');
    }

    public function ghost(TetrominoType $type): ImageTexture
    {
        return $this->get('minos/ghost_'.$type->value.'.png');
    }

    public function emptyCell(): ImageTexture
    {
        return $this->get('minos/empty.png');
    }

    public function menuBackground(): ImageTexture
    {
        return $this->get('bg/menu.png');
    }

    public function boardFrame(): ImageTexture
    {
        return $this->get('bg/board_frame.png');
    }

    public function logo(): ImageTexture
    {
        if (is_file($this->root.'/ui/logo_scene.png')) {
            return $this->get('ui/logo_scene.png');
        }

        // Pack ships title art; dedicated logo_scene is optional.
        return $this->title();
    }

    public function title(): ImageTexture
    {
        return $this->get('ui/title.png');
    }

    public function playButton(): ImageTexture
    {
        return $this->get('ui/btn_play.png');
    }

    public function pauseIcon(): ImageTexture
    {
        return $this->get('ui/pause.png');
    }

    public function sidePanel(): ImageTexture
    {
        return $this->get('ui/side_panel.png');
    }

    public function nextPanel(): ImageTexture
    {
        return $this->get('ui/next_panel.png');
    }

    public function pieceArt(TetrominoType $type): ImageTexture
    {
        $path = $this->root.'/pieces/'.$type->value.'.png';

        if (is_file($path)) {
            return $this->get('pieces/'.$type->value.'.png');
        }

        return $this->mino($type);
    }

    public function get(string $relative): ImageTexture
    {
        if (isset($this->cache[$relative])) {
            return $this->cache[$relative];
        }

        $absolute = $this->root.'/'.$relative;
        $texture = PngTexture::load($absolute);
        $this->cache[$relative] = $texture;

        return $texture;
    }
}
