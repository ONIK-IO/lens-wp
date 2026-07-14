<?php

namespace OnikImages\Support;

/**
 * Class facade over extractWidthsFromSizes in support.php.
 */
class SizesParser
{
    /**
     * @return int[]
     */
    public static function extract(string $sizesAttribute): array
    {
        return extractWidthsFromSizes($sizesAttribute);
    }
}
