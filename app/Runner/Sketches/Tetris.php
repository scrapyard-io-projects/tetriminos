<?php

namespace App\Runner\Sketches;

use App\Tetris\Enums\AppPhase;
use App\Tetris\Enums\BenchEnv;
use App\Tetris\Enums\RenderBackend;
use App\Tetris\Enums\TimingRule;
use App\Tetris\GameSettings;
use App\Tetris\Input\InputPoller;
use App\Tetris\Rendering\AssetBank;
use App\Tetris\Timing\DisplaySync;
use App\Tetris\Timing\FramePacer;
use App\Tetris\TetrisRoot;
use Fabricate\Contracts\Sketches\Attributes\Sketch as SketchAttribute;
use Fabricate\Contracts\Sketches\SketchLoopResult;
use ScrapyardIO\GameEngine\Game;
use ScrapyardIO\Tubes\Canvas\OSWindow;
use ScrapyardIO\Tubes\Rendering\Renderer2D;
use ScrapyardIO\Tubes\Windows\WindowManager;
use ScrapyardIO\UX\Runner\Sketches\Concerns\ResolvesCanvasWindowOptions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Tetriminos — same tree on Mac and Jetson.
 *
 *   ./runner tetris
 *   ./runner tetris metal      # Mac
 *   ./runner tetris cuda       # Jetson
 *   ./runner tetris sdl3|open-gl|vulkan|ogx
 */
#[SketchAttribute('tetris')]
class Tetris extends Sketch
{
    use ResolvesCanvasWindowOptions;

    protected string $description = 'Tetriminos — logo, menu, hold, SRS, keyboard + gamepad (metal|cuda|sdl3|open-gl|vulkan)';

    /**
     * @var list<string>
     */
    protected array $drivers = ['metal', 'cuda', 'open-gl', 'vulkan', 'sdl3'];

    protected ?Game $game = null;

    protected ?TetrisRoot $root = null;

    protected ?InputPoller $poller = null;

    protected ?GameSettings $settings = null;

    protected FramePacer $framePacer;

    /** Wall-clock stamp for the previous frame (hrtime ns). */
    protected ?int $prevFrameNs = null;

    protected string $activeDriver = 'metal';

    /**
     * Delivered frame times when {@see BenchEnv::FRAMES} is set.
     *
     * @var list<float>
     */
    protected array $benchDts = [];

    public function configureCommand(Command $command): void
    {
        $command->addArgument(
            'driver',
            InputArgument::OPTIONAL,
            'Window driver: metal|cuda|open-gl|ogx|vulkan|sdl3 (default: TUBES_CANVAS / WINDOW_DRIVER)',
        );

        $command->addOption(
            'profile',
            null,
            InputOption::VALUE_REQUIRED,
            'Optional tubes.canvas_profiles.windows.* profile slug',
        );
    }

    public function boot(): void
    {
        $manager = app(WindowManager::class);
        $driver = $this->resolveDriver();
        $this->activeDriver = $driver;

        $window = $manager
            ->driver($driver)
            ->title('Tetriminos')
            ->size(960, 720)
            ->open();

        $this->settings = GameSettings::load();
        $this->framePacer = new FramePacer;
        $bootHz = $this->settings->targetFps()->hertz() ?? TimingRule::TARGET_FPS->floatValue();
        $renderer = $this->resolveRenderer($driver);
        $this->game = new Game($window, $renderer, $bootHz, $bootHz);
        DisplaySync::apply($window, $this->settings);

        $handler = $window->handler();

        if (! method_exists($handler, 'inputHandler')) {
            throw new \RuntimeException("Driver [{$driver}] has no inputHandler().");
        }

        $this->game->input()->bindHandler($handler->inputHandler());

        if ($driver === 'sdl3') {
            $this->ensureSdlGamepadReady();
        }

        if ($window instanceof OSWindow) {
            $window->pollEvents();
        }

        $this->poller = new InputPoller($this->game->input());
        $this->poller->bindFocusCheck(function () use ($window): bool {
            return $window instanceof OSWindow ? $window->hasInputFocus() : true;
        });
        $this->root = new TetrisRoot(
            new AssetBank(base_path('resources/tetris')),
            $this->poller,
            $this->settings,
            RenderBackend::fromDriver($driver),
        );
        $this->game->setRoot($this->root);
        $this->prevFrameNs = hrtime(true);

        $this->info("Tetriminos on [{$driver}] 960x720 — click the window for keyboard focus.");
    }

    public function loop(): SketchLoopResult
    {
        if (is_null($this->game) || is_null($this->root)) {
            return SketchLoopResult::STOP;
        }

        $frameStartNs = hrtime(true);
        $dt = $this->resolveDeltaSeconds($frameStartNs);

        $canvas = $this->game->canvas();

        if ($canvas instanceof OSWindow) {
            $canvas->pollEvents();
        }

        if ($canvas->shouldClose() || $this->root->wantsQuit || $this->root->phase() === AppPhase::QUIT) {
            $this->game->stop();

            return SketchLoopResult::STOP;
        }

        if (! is_null($this->settings)) {
            DisplaySync::apply($canvas, $this->settings);
        }

        // Real wall dt into Game::tick → FiberProcessScheduler → process/physics hooks.
        $this->game->tick($dt);

        $target = $this->settings?->targetFps() ?? $this->root->settings()->targetFps();
        $this->framePacer->pace($frameStartNs, $target, $canvas);

        // Overlay FPS = delivered frame time (work + present + pace), not paint-only.
        $delivered = (hrtime(true) - $frameStartNs) / 1_000_000_000.0;
        $this->root->noteDeliveredFrameDt($delivered);

        $bench = $this->recordBench($delivered);
        if (! is_null($bench)) {
            return $bench;
        }

        return SketchLoopResult::CONTINUE;
    }

    public function shutdown(): void
    {
        $this->game?->canvas()->close();
        $this->game = null;
        $this->root = null;
        $this->poller = null;
        $this->settings = null;
        $this->prevFrameNs = null;
    }

    /**
     * Measured frame delta for process hooks (same contract as UX canvas / BallPhysicsNode).
     * Clamped so hitches don't spiral soft-drop / DAS / gravity.
     */
    protected function resolveDeltaSeconds(int $nowNs): float
    {
        $fps = $this->settings?->targetFps()->hertz() ?? TimingRule::TARGET_FPS->floatValue();
        $fallback = 1.0 / max(1.0, $fps);

        $dt = is_null($this->prevFrameNs)
            ? $fallback
            : ($nowNs - $this->prevFrameNs) / 1_000_000_000.0;

        $this->prevFrameNs = $nowNs;

        return max(1.0 / 240.0, min(0.1, $dt));
    }

    /**
     * SDL3 window boot only inits VIDEO; force GAMEPAD+JOYSTICK before first pad query.
     */
    protected function ensureSdlGamepadReady(): void
    {
        if (! class_exists(\Microscrap\Bindings\SDL3\Init::class)) {
            return;
        }

        $init = \Microscrap\Bindings\SDL3\Init::class;
        $flag = \Microscrap\Bindings\SDL3\Enums\InitFlag::class;

        if ($init::wasInit($flag::SDL_INIT_JOYSTICK) === 0) {
            $init::initSubSystem($flag::SDL_INIT_JOYSTICK);
        }

        if ($init::wasInit($flag::SDL_INIT_GAMEPAD) === 0) {
            $init::initSubSystem($flag::SDL_INIT_GAMEPAD);
        }
    }

    protected function resolveDriver(): string
    {
        $raw = $this->argument('driver');
        $explicit = is_string($raw) && trim($raw) !== '';
        $driver = $explicit ? strtolower(trim($raw)) : '';

        if ($driver === 'ogx' || $driver === 'opengl') {
            $driver = 'open-gl';
        }

        if ($driver === '') {
            $profile = (string) config('tubes.defaults.canvas', '');
            $fromProfile = config('tubes.canvas_profiles.windows.'.$profile.'.driver');
            $driver = is_string($fromProfile) && $fromProfile !== '' ? $fromProfile : $profile;
        }

        if ($driver === 'ogx' || $driver === 'opengl') {
            $driver = 'open-gl';
        }

        $available = app(WindowManager::class)->listWindows();

        if ($driver !== '' && in_array($driver, $this->drivers, true) && in_array($driver, $available, true)) {
            return $driver;
        }

        if ($explicit) {
            throw new \RuntimeException(
                "Driver [{$driver}] is not registered. Available: ".implode(', ', $available)
                .' (allowed: '.implode('|', $this->drivers).'|ogx)'
            );
        }

        foreach ($this->drivers as $candidate) {
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'No Tetriminos window driver is registered. Available: '.implode(', ', $available)
        );
    }

    /**
     * When TETRIMINOS_BENCH_FRAMES is set, sample delivered frame times (same
     * number as the FPS overlay) and stop. Used to verify pacing instead of guessing.
     */
    protected function recordBench(float $delivered): ?SketchLoopResult
    {
        $raw = getenv(BenchEnv::FRAMES->value);

        if ($raw === false || $raw === '') {
            return null;
        }

        $limit = max(1, (int) $raw);
        $this->benchDts[] = $delivered;

        if (count($this->benchDts) < $limit) {
            return null;
        }

        $this->writeBenchReport();
        $this->game?->stop();

        return SketchLoopResult::STOP;
    }

    protected function writeBenchReport(): void
    {
        $dts = $this->benchDts;
        $warmup = min(30, intdiv(count($dts), 5));
        $steady = array_slice($dts, $warmup);
        sort($steady);
        $count = count($steady);
        $mean = array_sum($steady) / max(1, $count);
        $median = $steady[intdiv($count, 2)] ?? $mean;
        $hzMean = $mean > 0.0 ? 1.0 / $mean : 0.0;
        $hzMedian = $median > 0.0 ? 1.0 / $median : 0.0;
        $vsync = $this->settings?->vsync()->value ?? 'unknown';
        $target = $this->settings?->targetFps()->value ?? 'unknown';
        $line = sprintf(
            'TETRIMINOS_BENCH driver=%s vsync=%s target=%s frames=%d warmup=%d mean_hz=%.1f median_hz=%.1f mean_ms=%.2f median_ms=%.2f',
            $this->activeDriver,
            $vsync,
            $target,
            $count,
            $warmup,
            $hzMean,
            $hzMedian,
            $mean * 1000.0,
            $median * 1000.0,
        );
        fwrite(STDERR, $line.PHP_EOL);
        $this->info($line);
    }
}
