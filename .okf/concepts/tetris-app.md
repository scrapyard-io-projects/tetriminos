---
okf_version: "0.2"
type: Concept
title: Tetriminos
status: stable
---

# Tetriminos (app/Tetris)

Guideline Tetriminos on `scrapyard-io/game-engine`. Same tree on Mac and Jetson — see [Dual-host](dual-host.md).

Run: `./runner tetris [metal|cuda|open-gl|vulkan|sdl3]`.

## Branding

- Title: **TETRIMINOS**
- Footer (bottom center): `Made with ADHD by ProjectSaturnStudios`
- No ScrapyardIO / game-engine subtitle

## Settings (main menu only)

- Phase: `AppPhase::SETTINGS` via Menu **SETTINGS** (not pause)
- Persist: `GameSettings` → Cache store **`redis`**, key `tetriminos.settings` (`SettingsCacheKey::PAYLOAD`)
- Start level **1–10**; Marathon **fixed-goal**: `level = max(startLevel, floor(lines/10)+1)`; HUD lines start at **0**
- Debug submenu: Input HUD, FPS overlay, **VSync**, Target FPS
- **VSync ON**: Target FPS is **30 / 60 / 120 / 180 / 240** (Uncapped hidden). Hardware present lock follows the VSync row on every GPU backend (`metal`, `cuda`, `open-gl`, `vulkan`, `sdl3`). The panel refresh is a floor (this Mac and later Linux 120 Hz). Targets above the panel cannot beat it while VSync is ON; `FramePacer` still caps when the target is *below* refresh.
- **VSync OFF**: Target FPS is **30 / 60 / 120 / 180 / 240 / Uncapped**. Handlers `setVsync(false)` so present does not wait on the display. `FramePacer` still caps numbered rates. **Uncapped must be allowed to exceed the panel refresh** on every GPU backend.
- Handlers: `microscrap/ogx` (GLFW swap interval; Darwin CGL in `setVsync` only), `sdl3-gfx` (`SDL_SetRenderVSync`), `metal-gfx` (`mtl_window_set_display_sync` when ext-metal has it), `vulkan-gfx` (Darwin `CAMetalLayer.displaySyncEnabled` in `setVsync` only; Linux no-op), `cuda-gfx` (GLFW swap interval). Do not poke compositor flags every present.
- App wiring: `DisplaySync` calls `setVsync` on the handler (no brand `instanceof`) then `FramePacer` (Uncapped `hertz()` is `null` → no sleep).
- Verified 2026-08-12 (VSync OFF + Uncapped, 120 Hz panel): present unlocked on all four (`open-gl` ~0.3 ms, `metal` ~0.1 ms, `sdl3` ~0.15 ms, `vulkan` ~2.4 ms). Delivered Hz exceeded 120 on menu (`sdl3` 210–324, `vulkan` 127–169, `open-gl` 133–150, `metal` 121–131). Play-scene OpenGL/Vulkan can still drop from **CPU `fillRect` work** (`tickMs` ~16 ms) — that is not a display lock.
- Renderer label from CLI driver (`RenderBackend`) — display only
- `DebugOverlay` is a permanent `TetrisRoot` sibling (survives phase swaps)
- Bench: `TETRIMINOS_BENCH_FRAMES=180 ./runner tetris open-gl` prints median/mean Hz (same delivered dt as the FPS overlay) and exits. Use this before claiming a pacing fix.

## Signals (showcase)

Scenes **emit**; `TetrisRoot` **connect**s — no `method_exists` duck-typing.

| Emitter | Signal | Args | Root handler |
|---------|--------|------|----------------|
| Logo / Menu / Settings / Play / GameOver | `phase_requested` | `phase: AppPhase` | `onPhaseRequested` |
| PlayScene | `paused` / `resumed` | — | `onPlayPaused` / `onPlayResumed` |
| PlayScene | `game_over` | `score, level, lines` | `onPlayGameOver` → `GAME_OVER` phase |
| TetrisRoot | `phase_changed` | `phase: AppPhase` | (outbound) |

## Phases

`LOGO` → `MENU` → (`SETTINGS` ↔ `MENU`) → `PLAY` → `GAME_OVER` → (`PLAY` retry | `MENU`) → `QUIT`.

## Input focus

`OSWindow::hasInputFocus()` / `WindowHandler::hasInputFocus()` — game-engine `InputServer::pump` skips when blurred; Tetriminos `InputPoller` binds the same check.

- SDL3: `SDL_WINDOW_INPUT_FOCUS`
- Metal: click-to-latch until `mtl_window_is_key()` exists on ext-metal

## Related

- game-engine `HasSignals` / `#[Signal]` / `InputServer`
- tubes `WindowHandler::hasInputFocus`
- `app/Tetris/Timing/DisplaySync.php` / `FramePacer.php`
- FreeSans via `UiTypeface` + `UiText`
- `.env` `CACHE_STORE=redis`
