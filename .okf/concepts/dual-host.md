---
okf_version: "0.2"
type: Concept
title: Dual-host Tetriminos
status: draft
---

# Dual-host Tetriminos (Mac + Jetson)

One source tree. Two machines. Same `app/`, same `microscrap/*`, same `composer.json`.

| Host | Native gfx | Shared gfx |
|------|------------|------------|
| Mac (`tetris-mac`) | `metal` (`ext-metal`) | `sdl3`, `open-gl`, `vulkan` |
| Jetson (`game` / tetris-cuda) | `cuda` (`ext-cuda`) | `sdl3`, `open-gl`, `vulkan` |

Metal and CUDA PHP extensions are **not** rebuilt on the other host. Shared extensions (`sdl3`, `open-gl`, `vulkan`, `glfw`) are rebuilt on **both** machines when their `.zep` changes, then `ext/` is reset so Angel can commit.

## Composer

`composer.json` requires **both** `microscrap/metal-gfx` and `microscrap/cuda-gfx`. Path repo `microscrap/*` (symlink). `scrapyard-io/game-engine` is `^0.7.0` so the local `microscrap/game-engine` path wins (do not pin `0.7.x-dev` dist).

```bash
# Mac (cuda-gfx also asks for ext-glfw ^0.7; Herd may still be 0.5 — ignore for install, CUDA will not boot)
composer install --ignore-platform-req=ext-cuda --ignore-platform-req=ext-glfw
# or: composer run install-mac

# Jetson (run ON the board, not via Mountain Duck)
composer install --ignore-platform-req=ext-metal
# or: composer run install-jetson
```

Providers skip `WindowFactory::extend` when the native extension is missing (`ext-metal` / `ext-cuda`). Opening the wrong driver still fails at `bootNative()`.

## Default driver

`.env` `WINDOW_DRIVER` / `TUBES_CANVAS` selects the default (`config/tubes.php` `defaults.canvas`). Mac: `metal`. Jetson: `cuda`. CLI `./runner tetris sdl3` overrides. If the configured driver is not registered, Tetriminos picks the first available from `metal|cuda|open-gl|vulkan|sdl3`.

Do **not** sync `.env` or `vendor/` between hosts. Sync `app/`, `microscrap/`, `config/`, `composer.json`. Jetson vendor must symlink `microscrap/*` after composer on the board — a Mountain Duck copy of `vendor/` will point at Mac paths.

## php-io-extensions-dev

Workspace fold: `/Users/angelgonzalez/Development/PHP/OfficialScrapyardIO/php-io-extensions-dev`.

- Change `metal/` or `cuda/` → rebuild only on that host.
- Change `sdl3/`, `open-gl/`, `vulkan/`, `glfw/` → rebuild on Mac **and** Jetson, then reset `ext/` for commit.
- Do not commit php-io-extensions web remotes from this Tetris work; track in the local package git.

## Related

- [Tetriminos](tetris-app.md)
- tubes `WindowHandler` + `Renderer2D`
