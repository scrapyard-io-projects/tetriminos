<?php

namespace App\Tetris\Enums;

/**
 * Window/driver backends Tetriminos can report in Settings.
 */
enum RenderBackend: string
{
    case METAL = 'metal';
    case OPEN_GL = 'open-gl';
    case VULKAN = 'vulkan';
    case SDL3 = 'sdl3';
    case CUDA = 'cuda';

    public function label(): string
    {
        return match ($this) {
            self::METAL => 'Metal',
            self::OPEN_GL => 'OpenGL',
            self::VULKAN => 'Vulkan',
            self::SDL3 => 'SDL3',
            self::CUDA => 'CUDA',
        };
    }

    public static function fromDriver(string $driver): self
    {
        $normalized = strtolower(trim($driver));

        if ($normalized === 'ogx' || $normalized === 'opengl') {
            $normalized = self::OPEN_GL->value;
        }

        return self::tryFrom($normalized) ?? self::METAL;
    }
}
