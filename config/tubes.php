<?php

return [

    /*
    |--------------------------------------------------------------------------
    | MagicAlias default drivers
    |--------------------------------------------------------------------------
    |
    | Written by scrapyard-io/installer for the selected Tubes graphics provider.
    |
    */

    'defaults' => [
        'canvas' => env('TUBES_CANVAS', env('WINDOW_DRIVER', 'metal')),
        'framebuffer' => env('FRAMEBUFFER_DRIVER', 'full'),
        'font' => env('FONT_DRIVER', 'classic'),
    ],

    'canvas_profiles' => [

        'windows' => [
            'metal' => [
                'driver' => 'metal',
                'title' => 'Tetriminos',
                'width' => 960,
                'height' => 720,
            ],
            'sdl3' => [
                'driver' => 'sdl3',
                'title' => 'Tetriminos',
                'width' => 960,
                'height' => 720,
            ],
            'open-gl' => [
                'driver' => 'open-gl',
                'title' => 'Tetriminos',
                'width' => 960,
                'height' => 720,
            ],
            'vulkan' => [
                'driver' => 'vulkan',
                'title' => 'Tetriminos',
                'width' => 960,
                'height' => 720,
            ],
            'cuda' => [
                'driver' => 'cuda',
                'title' => 'Tetriminos',
                'width' => 960,
                'height' => 720,
            ],
        ],

        'panels' => [
        ],

    ],

];
