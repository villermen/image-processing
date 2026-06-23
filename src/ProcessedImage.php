<?php

namespace Villermen\ImageProcessing;


use BrianMcdo\ImagePalette\Color;

class ProcessedImage
{
    /** @var string[] */
    private array $fileNames = [];

    /** @var Color[] Colors in order or occurrence. */
    private array $colors = [];

    /**
     * @return string[]
     */
    public function getFileNames(): array
    {
        return $this->fileNames;
    }

    /**
     * @param string $fileName
     * @param string $suffix
     */
    public function addFileName(string $fileName, string $suffix): void
    {
        $this->fileNames[$suffix] = $fileName;
    }

    /**
     * @return Color[]
     */
    public function getColors(): array
    {
        return $this->colors;
    }

    public function setColors(array $colors): self
    {
        $this->colors = $colors;
        return $this;
    }
}
