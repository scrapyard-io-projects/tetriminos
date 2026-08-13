# Directory Update Log

## 2026-08-07

* **Initialization**: Created OKF v0.2 bundle for `scrapyard-io/scrapyard-io` skeleton (0.7.x) — orientation package concept. Status `stable` (human-verified for 0.7 publish prep).
* **Convention**: Skeleton `.gitattributes` does **not** `export-ignore` `/.okf` so Composer create-project keeps the bundle; README documents adding `/.okf export-ignore` for target deploy.

## 2026-08-12

* **Tetris app**: Added Guideline Tetris under `app/Tetris` + `resources/tetris` assets; concept `tetris-app`.
* **Engine usage**: Phase swap via `addChild`/`removeChild`; AssetBank colors for playfield; real `GameOverScene`.
* **UI regression fix**: Restored Logo/Menu/PlayHud chrome — do not mount full-bleed `TexturedPanel` children over parent `onDraw` (paint order covers labels).
* **Settings**: Menu-only `SETTINGS` phase; Redis `GameSettings`; fixed-goal start level; Debug overlay (FPS/input HUD/target FPS).
* **VSync**: Debug toggle. On → 30/60/120/180/240 and hardware present lock. Off → Uncapped allowed and present unlocked so delivered Hz can exceed the panel (Mac 120 Hz now, Linux 120 Hz later) on metal / open-gl / vulkan / sdl3. Darwin CGL + CAMetalLayer apply in `setVsync` only.
* **VSync verified**: VSync OFF + Uncapped exceeded 120 Hz on all four GPU backends (present not vblank-locked). Vulkan live packed RGBA8 (`packMs` ~0). OpenGL windowed skips CPU shadow. Play-scene ~60 Hz on OpenGL is `tickMs` CPU fills, not compositor wait.
* **Dual-host**: Same `composer.json` requires metal-gfx **and** cuda-gfx; `game-engine` `^0.7.0` path. Mac `composer install --ignore-platform-req=ext-cuda`; Jetson `--ignore-platform-req=ext-metal` (on the board). Providers skip when the native ext is missing. `DisplaySync` duck-types `setVsync`. Concept [dual-host](concepts/dual-host.md).
