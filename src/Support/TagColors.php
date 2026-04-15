<?php

namespace Kantui\Support;

class TagColors
{
    private const PALETTE = [
        [0, 150, 255],    // Blue
        [46, 197, 70],    // Green
        [255, 193, 7],    // Yellow
        [220, 53, 69],    // Red
        [138, 43, 226],   // Purple
        [255, 127, 80],   // Coral
        [32, 178, 170],   // Teal
        [255, 105, 180],  // Pink
    ];

    /**
     * Get an RGB color for a tag by hashing its name.
     *
     * @return array RGB color array
     */
    public static function forTag(string $tag): array
    {
        $hash = crc32($tag);
        $index = abs($hash) % count(self::PALETTE);

        return self::PALETTE[$index];
    }

    /**
     * Get an RGB color by index, cycling through the palette.
     *
     * @return array RGB color array
     */
    public static function byIndex(int $index): array
    {
        return self::PALETTE[$index % count(self::PALETTE)];
    }
}
