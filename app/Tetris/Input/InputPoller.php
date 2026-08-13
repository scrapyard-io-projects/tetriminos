<?php

namespace App\Tetris\Input;

use App\Tetris\Enums\InputTuning;
use App\Tetris\Enums\TimingRule;
use ScrapyardIO\GameEngine\Input\InputServer;
use ScrapyardIO\Tubes\HumanInput\EngineInput;
use ScrapyardIO\Tubes\HumanInput\Keyboard;

/**
 * Reads Metal/SDL keyboard + USB gamepads directly (same approach as UX color menu).
 *
 * Does not rely on InputMap/actionPressed — those break when multiple keys share an
 * action and were also sampled before AppKit poll in Game::tick.
 */
final class InputPoller
{
    /** @var array<string, bool> */
    private array $prev = [];

    /** @var array<string, bool> */
    private array $down = [];

    /** @var array<string, bool> */
    private array $pressed = [];

    private float $dasTimer = 0.0;

    private int $dasDir = 0;

    private bool $dasCharged = false;

    private float $arrTimer = 0.0;

    private readonly float $das;

    private readonly float $arr;

    private readonly float $stickDeadzone;

    /** @var list<string> */
    private array $debugLines = [];

    /** @var (callable(): bool)|null */
    private $focusCheck = null;

    public function __construct(
        private readonly InputServer $input,
        ?float $das = null,
        ?float $arr = null,
    ) {
        $this->das = $das ?? TimingRule::DAS->floatValue();
        $this->arr = $arr ?? TimingRule::ARR->floatValue();
        $this->stickDeadzone = InputTuning::STICK_DEADZONE->floatValue();
    }

    /**
     * When the callback returns false, HID is ignored (blurred OS window).
     *
     * @param  callable(): bool  $focusCheck
     */
    public function bindFocusCheck(callable $focusCheck): static
    {
        $this->focusCheck = $focusCheck;

        return $this;
    }

    public function sync(float $dt): void
    {
        $engine = $this->input->engineInput();

        // Caller already ran OSWindow::pollEvents() (and Game::tick pumps EngineInput).
        // A third handler poll() allocated another Mouse snapshot every process().

        if (! $this->windowHasFocus()) {
            $this->clearTransientInput();
            $this->debugLines = [
                'focus=no',
                'keys: -',
                'actions: -',
                'pads: 0',
            ];

            return;
        }

        $now = [
            'move_left' => false,
            'move_right' => false,
            'soft_drop' => false,
            'hard_drop' => false,
            'rotate_cw' => false,
            'rotate_ccw' => false,
            'hold' => false,
            'pause' => false,
            'confirm' => false,
            'menu_up' => false,
            'menu_down' => false,
        ];

        $keysSeen = [];
        $padCount = 0;

        if (! is_null($engine)) {
            $keyboard = $engine->keyboard();

            if (! is_null($keyboard)) {
                // Keyboard (requested): Space CW, L-Shift CCW, Up hard drop, E hold.
                $now['rotate_cw'] = $this->keyDown($keyboard, $keysSeen, 'SPACE', 'Space', 'space');
                $now['rotate_ccw'] = $this->keyDown(
                    $keyboard,
                    $keysSeen,
                    'LEFT_SHIFT', 'LeftShift', 'left_shift', 'LSHIFT', 'Shift',
                );
                $now['hard_drop'] = $this->keyDown($keyboard, $keysSeen, 'UP_ARROW', 'Up', 'ArrowUp', 'up');
                $now['hold'] = $this->keyDown($keyboard, $keysSeen, 'E', 'e');
                $now['pause'] = $this->keyDown($keyboard, $keysSeen, 'ESCAPE', 'Escape', 'Esc', 'escape', 'P', 'p');
                $now['confirm'] = $this->keyDown($keyboard, $keysSeen, 'RETURN', 'Return', 'Enter', 'return', 'enter', 'kp_enter');
                $now['menu_up'] = $this->keyDown($keyboard, $keysSeen, 'UP_ARROW', 'Up', 'ArrowUp', 'up', 'W', 'w');
                $now['menu_down'] = $this->keyDown($keyboard, $keysSeen, 'DOWN_ARROW', 'Down', 'ArrowDown', 'down', 'S', 's');
                $now['move_left'] = $this->keyDown($keyboard, $keysSeen, 'LEFT_ARROW', 'Left', 'ArrowLeft', 'left', 'A', 'a');
                $now['move_right'] = $this->keyDown($keyboard, $keysSeen, 'RIGHT_ARROW', 'Right', 'ArrowRight', 'right', 'D', 'd');
                $now['soft_drop'] = $this->keyDown($keyboard, $keysSeen, 'DOWN_ARROW', 'Down', 'ArrowDown', 'down', 'S', 's');
            }

            $padCount = $this->mergeGamepad($engine, $now);
        }

        $this->pressed = [];

        foreach ($now as $action => $isDown) {
            $was = $this->prev[$action] ?? false;
            $this->down[$action] = $isDown;

            if ($isDown && ! $was) {
                $this->pressed[$action] = true;
            }
        }

        $this->prev = $now;
        $this->updateDas($dt);

        $active = [];

        foreach ($now as $action => $isDown) {
            if ($isDown) {
                $active[] = $action;
            }
        }

        $this->debugLines = [
            'focus=yes',
            'keys: '.(count($keysSeen) > 0 ? implode(',', array_unique($keysSeen)) : '-'),
            'actions: '.(count($active) > 0 ? implode(',', $active) : '-'),
            'pads: '.$padCount,
        ];
    }

    /**
     * @return list<string>
     */
    public function debugLines(): array
    {
        return $this->debugLines;
    }

    private function windowHasFocus(): bool
    {
        if (is_null($this->focusCheck)) {
            return true;
        }

        return (bool) ($this->focusCheck)();
    }

    private function clearTransientInput(): void
    {
        $this->pressed = [];
        $this->down = [];
        $this->prev = [];
        $this->dasTimer = 0.0;
        $this->dasDir = 0;
        $this->dasCharged = false;
        $this->arrTimer = 0.0;
    }

    public function down(string $action): bool
    {
        return $this->down[$action] ?? false;
    }

    public function justPressed(string $action): bool
    {
        return $this->pressed[$action] ?? false;
    }

    /**
     * @return list<int>
     */
    public function horizontalShifts(): array
    {
        $shifts = [];

        if ($this->justPressed('move_left')) {
            $shifts[] = -1;
        }

        if ($this->justPressed('move_right')) {
            $shifts[] = 1;
        }

        if ($this->dasDir !== 0 && $this->dasCharged && $this->arrTimer <= 0.0) {
            $shifts[] = $this->dasDir;
            $this->arrTimer = $this->arr;
        }

        return $shifts;
    }

    private function updateDas(float $dt): void
    {
        $left = $this->down('move_left');
        $right = $this->down('move_right');
        $dir = 0;

        if ($left && ! $right) {
            $dir = -1;
        } elseif ($right && ! $left) {
            $dir = 1;
        }

        if ($dir === 0) {
            $this->dasDir = 0;
            $this->dasTimer = 0.0;
            $this->dasCharged = false;
            $this->arrTimer = 0.0;

            return;
        }

        if ($dir !== $this->dasDir) {
            $this->dasDir = $dir;
            $this->dasTimer = 0.0;
            $this->dasCharged = false;
            $this->arrTimer = 0.0;

            return;
        }

        if (! $this->dasCharged) {
            $this->dasTimer += $dt;

            if ($this->dasTimer >= $this->das) {
                $this->dasCharged = true;
                $this->arrTimer = 0.0;
            }

            return;
        }

        $this->arrTimer -= $dt;
    }

    /**
     * @param  list<string>  $keysSeen
     */
    private function keyDown(Keyboard $keyboard, array &$keysSeen, string ...$aliases): bool
    {
        foreach ($aliases as $alias) {
            if ($keyboard->isDown($alias)) {
                $keysSeen[] = $alias;

                return true;
            }
        }

        $want = [];

        foreach ($aliases as $alias) {
            $want[strtolower($alias)] = true;
        }

        foreach ($keyboard->keys() as $name => $down) {
            if ($down === true && isset($want[strtolower((string) $name)])) {
                $keysSeen[] = (string) $name;

                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, bool>  $now
     */
    private function mergeGamepad(EngineInput $engine, array &$now): int
    {
        $count = 0;

        foreach ($engine->gameControllers() as $pad) {
            $count++;
            $this->mergeDigitalPadButtons($pad->digitalButtons(), $now);
            $this->mergeLeftStick($pad->sticks(), $now);
        }

        // SDL3 may expose unmapped HID devices as GamePad (hats / button_N) only.
        foreach ($engine->gamePads() as $pad) {
            $count++;
            $this->mergeDigitalPadButtons($pad->digitalButtons(), $now);
        }

        return $count;
    }

    /**
     * @param  iterable<object>  $buttons
     * @param  array<string, bool>  $now
     */
    private function mergeDigitalPadButtons(iterable $buttons, array &$now): void
    {
        foreach ($buttons as $btn) {
            if (! method_exists($btn, 'isPressed') || ! $btn->isPressed()) {
                continue;
            }

            if (! method_exists($btn, 'name')) {
                continue;
            }

            $name = $this->normalizePadButton((string) $btn->name());

            match ($name) {
                'DPAD_LEFT', 'LEFT', 'HAT0_LEFT', 'HAT_LEFT' => $now['move_left'] = true,
                'DPAD_RIGHT', 'RIGHT', 'HAT0_RIGHT', 'HAT_RIGHT' => $now['move_right'] = true,
                'DPAD_DOWN', 'DOWN', 'HAT0_DOWN', 'HAT_DOWN' => $now['soft_drop'] = $now['menu_down'] = true,
                'DPAD_UP', 'UP', 'HAT0_UP', 'HAT_UP' => $now['hard_drop'] = $now['menu_up'] = true,
                'A', 'CROSS', 'SOUTH' => $now['rotate_cw'] = $now['confirm'] = true,
                'B', 'CIRCLE', 'EAST' => $now['rotate_ccw'] = true,
                'X', 'SQUARE', 'WEST' => $now['hold'] = true,
                'Y', 'TRIANGLE', 'NORTH' => $now['hard_drop'] = true,
                'LEFT_SHOULDER', 'LEFT_BUMPER', 'L1' => $now['hold'] = true,
                'RIGHT_SHOULDER', 'RIGHT_BUMPER', 'R1' => $now['hard_drop'] = true,
                'START', 'OPTIONS' => $now['pause'] = $now['confirm'] = true,
                'BACK', 'SELECT', 'GUIDE' => $now['pause'] = true,
                default => null,
            };
        }
    }

    /**
     * @param  iterable<object>  $sticks
     * @param  array<string, bool>  $now
     */
    private function mergeLeftStick(iterable $sticks, array &$now): void
    {
        foreach ($sticks as $stick) {
            if (! method_exists($stick, 'name') || ! method_exists($stick, 'x') || ! method_exists($stick, 'y')) {
                continue;
            }

            if (strtoupper((string) $stick->name()) !== 'LEFT') {
                continue;
            }

            $x = (float) $stick->x();
            $y = (float) $stick->y();

            if ($x <= -$this->stickDeadzone) {
                $now['move_left'] = true;
            }

            if ($x >= $this->stickDeadzone) {
                $now['move_right'] = true;
            }

            if ($y >= $this->stickDeadzone) {
                $now['soft_drop'] = true;
                $now['menu_down'] = true;
            }

            // Stick-up is menu only — hard drop from stick was too easy to misfire.
            if ($y <= -$this->stickDeadzone) {
                $now['menu_up'] = true;
            }
        }
    }

    /**
     * Normalize Metal / GLFW / SDL3 gamepad button strings to a common token set.
     */
    private function normalizePadButton(string $raw): string
    {
        $name = strtoupper(str_replace(['-', ' '], '_', $raw));
        $name = str_replace([
            'SDL_GAMEPAD_BUTTON_',
            'GLFW_GAMEPAD_BUTTON_',
            'GAMEPAD_BUTTON_',
        ], '', $name);

        // SDL_GetGamepadStringForButton short names (no separators).
        return match ($name) {
            'DPUP' => 'DPAD_UP',
            'DPDOWN' => 'DPAD_DOWN',
            'DPLEFT' => 'DPAD_LEFT',
            'DPRIGHT' => 'DPAD_RIGHT',
            'LEFTSHOULDER' => 'LEFT_SHOULDER',
            'RIGHTSHOULDER' => 'RIGHT_SHOULDER',
            'LEFTSTICK' => 'LEFT_STICK',
            'RIGHTSTICK' => 'RIGHT_STICK',
            default => $name,
        };
    }
}
