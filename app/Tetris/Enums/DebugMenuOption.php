<?php

namespace App\Tetris\Enums;

enum DebugMenuOption: int
{
    case INPUT_HUD = 0;
    case FPS_OVERLAY = 1;
    case VSYNC = 2;
    case TARGET_FPS = 3;
    case BACK = 4;
}
