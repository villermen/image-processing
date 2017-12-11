<?php

namespace Villermen\ImageProcessing;


use BrianMcdo\ImagePalette\Color;
use BrianMcdo\ImagePalette\ImagePalette;

/**
 * ImagePalette with configurable colors, improved speed and reusability.
 *
 * TODO: Might actually be easier to just implement our own instead of extending that locked up piece of...
 *
 * @package Villermen\ImageProcessing
 */
class ColorProcessor extends ImagePalette
{
    const SAMPLE_RATE = 10;
    const COLOR_PERCENTAGE_MATCH_THRESHOLD = 0.25;

    /** @noinspection PhpMissingParentConstructorInspection */
    /**
     * @param int[] $colorWhitelist An array of color integers. (E.g.: 0x00FF00). A default set of colors will be used if left unspecified.
     */
    public function __construct($colorWhitelist = null)
    {
        $this->precision = self::SAMPLE_RATE;
        $this->paletteLength = 5;
        $this->lib = "GD";

        if ($colorWhitelist === null) {
            $colorWhitelist = $this->whiteList;
        }

        $this->setColorWhitelist($colorWhitelist);
    }

    /**
     * @param resource $image A GD image resource.
     * @return Color[]
     */
    public function processColors($image)
    {
        $this->whiteList = array_fill_keys(array_keys($this->whiteList), 0);

        $this->loadedImage = $image;
        $this->width = imagesx($image);
        $this->height = imagesy($image);

        $this->readPixels();

        // Skip assumed background color
        if ($this->width > 0 && $this->height > 0) {
            $color = $this->getPixelColor(0, 0);
            if (!$color->isTransparent()) {
                $assumedBackgroundColor = $this->getClosestColor($color);

                $this->whiteList[$assumedBackgroundColor] = 0;
            }
        }

        // Sort whitelist by occurrence count
        arsort($this->whiteList);

        $matchedColors = array_keys(array_filter($this->whiteList, function($occurrences) {
            return (100 / ($this->width * $this->height) * $occurrences) * self::SAMPLE_RATE >= self::COLOR_PERCENTAGE_MATCH_THRESHOLD;
        }));

        // Convert to colors
        $this->palette = array_map(function($color) {
            return new Color($color);
        }, $matchedColors);

        return $this->palette;
    }

    /**
     * @param int[] $colorWhitelist An array of color integers. (E.g.: 0x00FF00).
     * @return ColorProcessor
     */
    public function setColorWhitelist($colorWhitelist)
    {
        $this->whiteList = array_fill_keys($colorWhitelist, 0);

        return $this;
    }

    /** @noinspection PhpSignatureMismatchDuringInheritanceInspection */
    /**
     * @return int[]
     */
    public function getColorWhitelist() {
        return array_keys($this->whiteList);
    }
}
