<?php

namespace App\Tetris\Scenes;

use App\Tetris\Enums\AppPhase;
use App\Tetris\Enums\PauseMenuOption;
use App\Tetris\Enums\TetrominoType;
use App\Tetris\Enums\UiTypeface;
use App\Tetris\GameSettings;
use App\Tetris\Input\InputPoller;
use App\Tetris\Logic\PlayController;
use App\Tetris\Rendering\AssetBank;
use App\Tetris\Rendering\PlayfieldView;
use App\Tetris\Rendering\UiText;
use ScrapyardIO\GameEngine\Nodes\Node2D;
use ScrapyardIO\GameEngine\Signals\Signal;
use ScrapyardIO\UX\Core\PaintContext;
use ScrapyardIO\UX\Support\Color;

/**
 * Paint order: parent draws first, then children. PlayfieldView then PlayHud.
 *
 * Navigation and pause lifecycle go through signals — not duck-typed root calls.
 * Game-over transitions to AppPhase::GAME_OVER via root (dedicated scene).
 */
#[Signal('phase_requested', ['phase' => AppPhase::class])]
#[Signal('paused')]
#[Signal('resumed')]
#[Signal('game_over', ['score' => 'int', 'level' => 'int', 'lines' => 'int'])]
final class PlayScene extends Node2D
{
    private PlayController $game;

    private PlayfieldView $playfield;

    private PlayHud $hud;

    private bool $gameOverEmitted = false;

    private PauseMenuOption $pauseSelection = PauseMenuOption::RESUME;

    public function __construct(
        private readonly AssetBank $assets,
        private readonly InputPoller $input,
        private readonly GameSettings $settings,
    ) {
        parent::__construct('play_scene');
        $this->width = 960;
        $this->height = 720;
        $this->game = new PlayController($this->settings->startLevel());

        $this->playfield = (new PlayfieldView($this->assets))->bind($this->game);
        $this->playfield->setPosition(80, 40);

        $this->hud = new PlayHud($this->game, $this->playfield, $this->input);
        $this->hud->setPosition(0, 0);

        $this->addChild($this->playfield);
        $this->addChild($this->hud);
    }

    public function process(float $dt): void
    {
        if ($this->input->justPressed('pause')) {
            if ($this->game->gameOver) {
                return;
            }

            $this->setPaused(! $this->game->paused);

            return;
        }

        if ($this->game->paused) {
            $this->processPauseMenu();

            return;
        }

        $this->hud->pauseSelection = null;

        if ($this->game->gameOver) {
            $this->emitGameOverOnce();

            return;
        }

        foreach ($this->input->horizontalShifts() as $dir) {
            $this->game->moveHorizontal($dir);
        }

        if ($this->input->justPressed('rotate_cw')) {
            $this->game->rotate(true);
        }

        if ($this->input->justPressed('rotate_ccw')) {
            $this->game->rotate(false);
        }

        if ($this->input->justPressed('hard_drop')) {
            $this->game->hardDrop();
        }

        if ($this->input->justPressed('hold')) {
            $this->game->holdPiece();
        }

        if ($this->input->justPressed('soft_drop')) {
            $this->game->softDropStep();
        }

        $this->game->tick($dt, $this->input->down('soft_drop'));
        $this->emitGameOverOnce();
    }

    private function processPauseMenu(): void
    {
        if ($this->input->justPressed('menu_up') || $this->input->justPressed('move_left')) {
            $this->pauseSelection = PauseMenuOption::RESUME;
        }

        if ($this->input->justPressed('menu_down') || $this->input->justPressed('move_right') || $this->input->justPressed('soft_drop')) {
            $this->pauseSelection = PauseMenuOption::QUIT_TO_MENU;
        }

        $this->hud->pauseSelection = $this->pauseSelection;

        if (! $this->input->justPressed('confirm')) {
            return;
        }

        if ($this->pauseSelection === PauseMenuOption::RESUME) {
            $this->setPaused(false);

            return;
        }

        $this->setPaused(false);
        $this->emit('phase_requested', ['phase' => AppPhase::MENU]);
    }

    private function setPaused(bool $paused): void
    {
        if ($this->game->gameOver || $this->game->paused === $paused) {
            return;
        }

        $this->game->paused = $paused;

        if ($paused) {
            $this->pauseSelection = PauseMenuOption::RESUME;
            $this->hud->pauseSelection = $this->pauseSelection;
            $this->emit('paused');
        } else {
            $this->hud->pauseSelection = null;
            $this->emit('resumed');
        }
    }

    private function emitGameOverOnce(): void
    {
        if (! $this->game->gameOver || $this->gameOverEmitted) {
            return;
        }

        $this->gameOverEmitted = true;
        $s = $this->game->scoring;
        $this->emit('game_over', [
            'score' => $s->score,
            'level' => $s->level,
            'lines' => $s->lines,
        ]);
    }

    protected function onDraw(PaintContext $ctx): void
    {
        $ctx->renderer->fillRect(0, 0, 960, 720, Color::fromHex('#0E1524')->pack());
    }
}

/**
 * Drawn after the playfield so text/panels sit on top.
 * Panels are drawn in onDraw (not as covering texture children).
 */
final class PlayHud extends Node2D
{
    public ?PauseMenuOption $pauseSelection = null;

    public function __construct(
        private readonly PlayController $game,
        private readonly PlayfieldView $playfield,
        private readonly InputPoller $input,
    ) {
        parent::__construct('play_hud');
        $this->width = 960;
        $this->height = 720;
    }

    protected function onDraw(PaintContext $ctx): void
    {
        $panel = Color::fromHex('#162033')->pack();
        $border = Color::fromHex('#3D5A80')->pack();
        $ink = Color::fromHex('#E8EEF8')->pack();
        $muted = Color::fromHex('#8FA3C0')->pack();

        $ctx->renderer->fillRect(440, 40, 200, 150, $panel);
        $ctx->renderer->drawRect(440, 40, 200, 150, $border);
        UiText::printInBox($ctx, 440, 45, 200, 28, 'HOLD', $muted, UiTypeface::BODY);
        $this->playfield->drawMiniPiece($ctx, 540, 115, $this->game->hold, 18);

        $ctx->renderer->fillRect(440, 210, 200, 340, $panel);
        $ctx->renderer->drawRect(440, 210, 200, 340, $border);
        UiText::printInBox($ctx, 440, 215, 200, 28, 'NEXT', $muted, UiTypeface::BODY);

        $preview = $this->game->bag->preview();
        foreach (array_slice($preview, 0, 5) as $i => $type) {
            if ($type instanceof TetrominoType) {
                $this->playfield->drawMiniPiece($ctx, 540, 270 + $i * 55, $type, 14);
            }
        }

        $ctx->renderer->fillRect(660, 40, 260, 160, $panel);
        $ctx->renderer->drawRect(660, 40, 260, 160, $border);
        $s = $this->game->scoring;
        UiText::printInBox($ctx, 660, 45, 260, 28, 'SCORE', $muted, UiTypeface::BODY);
        UiText::printInBox($ctx, 660, 75, 260, 40, (string) $s->score, $ink, UiTypeface::HEADING);
        UiText::printInBox($ctx, 660, 118, 260, 28, 'LEVEL '.$s->level, Color::fromHex('#FFD27A')->pack(), UiTypeface::BODY);
        UiText::printInBox($ctx, 660, 146, 260, 28, 'LINES '.$s->lines, Color::fromHex('#7DFFB0')->pack(), UiTypeface::BODY);

        UiText::printCenteredX(
            $ctx,
            480,
            688,
            'Arrows move/soft  Up hard  Space CW  L-Shift CCW  E hold  Esc pause',
            $muted,
            UiTypeface::CAPTION,
        );

        if ($this->game->paused && ! is_null($this->pauseSelection)) {
            $this->drawPauseMenu($ctx, $ink, $muted);
        }
    }

    private function drawPauseMenu(PaintContext $ctx, int $ink, int $muted): void
    {
        $panel = Color::fromHex('#0A101C')->pack();
        $border = Color::fromHex('#F0C84A')->pack();
        $idle = Color::fromHex('#3D5A80')->pack();
        $dim = Color::fromHex('#6A7A90')->pack();

        // Keep chrome + hint fully inside the frame (FreeSans caption has real glyph extent).
        $boxX = 90;
        $boxY = 200;
        $boxW = 340;
        $boxH = 260;

        $ctx->renderer->fillRect($boxX, $boxY, $boxW, $boxH, $panel);
        $ctx->renderer->drawRect($boxX, $boxY, $boxW, $boxH, $border);
        UiText::printInBox($ctx, $boxX, $boxY + 12, $boxW, 40, 'PAUSED', $ink, UiTypeface::HEADING);

        $resumeOn = $this->pauseSelection === PauseMenuOption::RESUME;
        $quitOn = $this->pauseSelection === PauseMenuOption::QUIT_TO_MENU;

        $btnX = $boxX + 30;
        $btnW = $boxW - 60;

        $ctx->renderer->fillRect($btnX, $boxY + 70, $btnW, 48, Color::fromHex('#162033')->pack());
        $ctx->renderer->drawRect($btnX, $boxY + 70, $btnW, 48, $resumeOn ? $border : $idle);
        UiText::printInBox($ctx, $btnX, $boxY + 70, $btnW, 48, 'RESUME', $resumeOn ? $ink : $dim, UiTypeface::BODY);

        $ctx->renderer->fillRect($btnX, $boxY + 130, $btnW, 48, Color::fromHex('#162033')->pack());
        $ctx->renderer->drawRect($btnX, $boxY + 130, $btnW, 48, $quitOn ? $border : $idle);
        UiText::printInBox($ctx, $btnX, $boxY + 130, $btnW, 48, 'QUIT TO MENU', $quitOn ? $ink : $dim, UiTypeface::BODY);

        UiText::printInBox(
            $ctx,
            $boxX + 12,
            $boxY + $boxH - 44,
            $boxW - 24,
            28,
            'Up/Down select  Enter confirm',
            $muted,
            UiTypeface::CAPTION,
        );
    }
}
