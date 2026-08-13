<?php

namespace App\Tetris\Enums;

enum BoardDim: int
{
    case WIDTH = 10;
    case HEIGHT = 22;
    case HIDDEN_ROWS = 2;
    case CELL_PX = 32;
}
