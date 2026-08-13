<?php

use App\Tetris\Enums\RotationState;
use App\Tetris\Enums\TargetFpsOption;
use App\Tetris\Enums\TetrominoType;
use App\Tetris\Logic\ActivePiece;
use App\Tetris\Logic\BoardMatrix;
use App\Tetris\Logic\PlayController;
use App\Tetris\Logic\SevenBag;
use App\Tetris\Logic\SrsData;
use App\Tetris\Timing\DisplaySync;

it('deals each tetromino once per bag', function () {
    $bag = new SevenBag(5);
    $seen = [];

    for ($i = 0; $i < 7; $i++) {
        $seen[] = $bag->take()->value;
    }

    sort($seen);
    expect($seen)->toBe(['I', 'J', 'L', 'O', 'S', 'T', 'Z']);
});

it('keeps a five-piece preview queue', function () {
    $bag = new SevenBag(5);
    expect($bag->preview())->toHaveCount(5);
});

it('applies SRS wall kicks for JLTSZ', function () {
    $kicks = SrsData::kicks(TetrominoType::T, RotationState::SPAWN, RotationState::RIGHT);
    expect($kicks[0])->toBe([0, 0]);
    expect($kicks)->toHaveCount(5);
});

it('locks pieces and clears full lines', function () {
    $board = new BoardMatrix;
    $width = \App\Tetris\Enums\BoardDim::WIDTH->value;
    $bottom = \App\Tetris\Enums\BoardDim::HEIGHT->value - 1;

    for ($x = 0; $x < $width; $x++) {
        $board->set($x, $bottom, TetrominoType::O);
    }

    $cleared = $board->clearFullLines();
    expect($cleared)->toHaveCount(1);
    expect($board->get(0, $bottom))->toBeNull();
});

it('awards tetris score with back-to-back bonus', function () {
    $scoring = new \App\Tetris\Logic\Scoring;
    $first = $scoring->awardLineClear(4);
    expect($first['isTetris'])->toBeTrue();
    expect($scoring->score)->toBe(800);

    $second = $scoring->awardLineClear(4);
    // 800 * 1.5 B2B + 50 combo * level = 1250
    expect($second['points'])->toBe(1250);
});

it('uses fixed-goal marathon start level without preloading lines', function () {
    $scoring = new \App\Tetris\Logic\Scoring(5);
    expect($scoring->startLevel)->toBe(5);
    expect($scoring->level)->toBe(5);
    expect($scoring->lines)->toBe(0);

    for ($i = 0; $i < 10; $i++) {
        $scoring->awardLineClear(4);
    }

    expect($scoring->lines)->toBe(40);
    expect($scoring->level)->toBe(5);

    $scoring->awardLineClear(4);
    $scoring->awardLineClear(4);
    $scoring->awardLineClear(2);

    expect($scoring->lines)->toBe(50);
    expect($scoring->level)->toBe(6);
});

it('passes start level into play controller scoring', function () {
    $game = new PlayController(7);
    expect($game->scoring->startLevel)->toBe(7);
    expect($game->scoring->level)->toBe(7);
    expect($game->scoring->lines)->toBe(0);
});

it('stops resetting lock delay after fifteen moves', function () {
    $game = new PlayController;
    $ref = new ReflectionClass($game);
    $onGround = $ref->getProperty('onGround');
    $onGround->setAccessible(true);
    $onGround->setValue($game, true);
    $resets = $ref->getProperty('lockResets');
    $resets->setAccessible(true);
    $resets->setValue($game, 15);
    $timer = $ref->getProperty('lockTimer');
    $timer->setAccessible(true);
    $timer->setValue($game, 0.25);

    // Force grounded collision so registerMovement takes the lock-reset branch.
    $active = $game->active;
    expect($active)->not->toBeNull();
    // Drop piece onto floor in matrix by filling below it
    foreach ($active->withOffset(0, 1)->cells() as [$x, $y]) {
        if ($game->board->inBounds($x, $y)) {
            $game->board->set($x, $y, TetrominoType::O);
        }
    }
    $onGround->setValue($game, true);

    $method = $ref->getMethod('registerMovement');
    $method->setAccessible(true);
    $method->invoke($game);

    expect($timer->getValue($game))->toBe(0.25);
    expect($resets->getValue($game))->toBe(15);
});

it('supports hold swapping once per piece', function () {
    $game = new PlayController;
    $first = $game->active?->type;
    expect($first)->not->toBeNull();

    expect($game->holdPiece())->toBeTrue();
    expect($game->hold)->toBe($first);
    expect($game->holdPiece())->toBeFalse();
});

it('hides uncapped when vsync is on', function () {
    expect(TargetFpsOption::UNCAPPED->allowedWhenVsync(true))->toBeFalse();
    expect(TargetFpsOption::FPS_240->allowedWhenVsync(true))->toBeTrue();
    expect(TargetFpsOption::allowed(true))->toBe([
        TargetFpsOption::FPS_30,
        TargetFpsOption::FPS_60,
        TargetFpsOption::FPS_120,
        TargetFpsOption::FPS_180,
        TargetFpsOption::FPS_240,
    ]);
});

it('cycles target fps without uncapped while vsync is on', function () {
    expect(TargetFpsOption::FPS_240->nextAllowed(true))->toBe(TargetFpsOption::FPS_30);
    expect(TargetFpsOption::FPS_30->previousAllowed(true))->toBe(TargetFpsOption::FPS_240);
});

it('includes uncapped when vsync is off', function () {
    expect(TargetFpsOption::FPS_240->nextAllowed(false))->toBe(TargetFpsOption::UNCAPPED);
});

it('locks hardware vsync only for 30 and 60', function () {
    expect(DisplaySync::hardwareEnabled(true, TargetFpsOption::FPS_30))->toBeTrue();
    expect(DisplaySync::hardwareEnabled(true, TargetFpsOption::FPS_60))->toBeTrue();
    expect(DisplaySync::hardwareEnabled(true, TargetFpsOption::FPS_120))->toBeFalse();
    expect(DisplaySync::hardwareEnabled(true, TargetFpsOption::FPS_180))->toBeFalse();
    expect(DisplaySync::hardwareEnabled(true, TargetFpsOption::FPS_240))->toBeFalse();
    expect(DisplaySync::hardwareEnabled(false, TargetFpsOption::FPS_60))->toBeFalse();
    expect(DisplaySync::hardwareEnabled(false, TargetFpsOption::UNCAPPED))->toBeFalse();
    expect(DisplaySync::hardwareEnabled(true, TargetFpsOption::UNCAPPED))->toBeFalse();
});

it('rejects illegal moves into filled cells', function () {
    $board = new BoardMatrix;
    $board->set(4, 10, TetrominoType::I);
    $piece = new ActivePiece(TetrominoType::O, 4, 10);
    expect($board->fits($piece->cells()))->toBeFalse();
});
