<?php

namespace App\Tetris\Enums;

enum TetrominoType: string
{
    case I = 'I';
    case O = 'O';
    case T = 'T';
    case S = 'S';
    case Z = 'Z';
    case J = 'J';
    case L = 'L';

    /**
     * @return list<self>
     */
    public static function bagOrder(): array
    {
        return [
            self::I,
            self::O,
            self::T,
            self::S,
            self::Z,
            self::J,
            self::L,
        ];
    }
}
