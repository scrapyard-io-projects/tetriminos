<?php

namespace App\Tetris\Enums;

enum AppPhase: string
{
    case LOGO = 'logo';
    case MENU = 'menu';
    case SETTINGS = 'settings';
    case PLAY = 'play';
    case GAME_OVER = 'game_over';
    case QUIT = 'quit';
}
