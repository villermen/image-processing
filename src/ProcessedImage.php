<?php

namespace Villermen\ImageProcessing;


use BrianMcdo\ImagePalette\Color;

class ProcessedImage
{
    /** @var string[] */
    private $fileNames = [];

    /** @var Color[] Colors in order or occurrence. */
    private $colors = [];

    /**
     * @return string[]
     */
    public function getFileNames()
    {
        return $this->fileNames;
    }

    /**
     * @param string $fileName
     * @param string $suffix
     */
    public function addFileName($fileName, $suffix)
    {
        $this->fileNames[$suffix] = $fileName;
    }

    /**
     * @return Color[]
     */
    public function getColors()
    {
        return $this->colors;
    }

    /**
     * @param Color[] $colors
     * @return ProcessedImage
     */
    public function setColors($colors)
    {
        $this->colors = $colors;
        return $this;
    }
}
