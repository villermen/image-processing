<?php

namespace Villermen\ImageProcessing;

use Villermen\DataHandling\DataHandling;
use Symfony\Component\Process\Process;

class ImageProcessor
{
    const OUTPUT_TYPE_JPEG = "jpg";
    const OUTPUT_TYPE_PNG = "png";
    const OUTPUT_TYPE_GIF = "gif";
    const OUTPUT_TYPE_ORIGINAL = "original";
    const OUTPUT_TYPES = [
        self::OUTPUT_TYPE_JPEG, self::OUTPUT_TYPE_PNG, self::OUTPUT_TYPE_GIF, self::OUTPUT_TYPE_ORIGINAL
    ];

    /** @var string */
    private $outputDirectory;

    /** @var string */
    private $temporaryDirectory;

    /** @var int[] */
    private $sizes = [];

    /** @var string */
    private $outputType = self::OUTPUT_TYPE_JPEG;

    /** @var ColorProcessor|false */
    private $colorProcessor = false;

    /** @var bool */
    private $gifsicle = false;

    /** @var bool */
    private $overwrite = true;

    /**
     * @param string $outputDirectory
     * @param string|null $temporaryDirectory
     */
    public function __construct($outputDirectory, $temporaryDirectory = null)
    {
        $this->setOutputDirectory($outputDirectory);
        $this->setTemporaryDirectory($temporaryDirectory ?: sys_get_temp_dir());

        $gifsicleProcess = new Process("gifsicle --help");
        $gifsicleProcess->run();
        if ($gifsicleProcess->isSuccessful()) {
            $this->gifsicle = true;
        }
    }

    /**
     * Processes (after optionally downloading) the given image using the processor's settings.
     * Images will be scaled down if they are too large to fit the given size.
     * Images will never be scaled up and will remain their proportions.
     *
     * @param string $imageLocation The URL or file path to an image.
     * @param string $imageName Name that will be used to generate the output file path.
     * @return ProcessedImage
     * @throws ImageProcessorException
     */
    public function processImage($imageLocation, $imageName)
    {
        if (count($this->sizes) === 0) {
            throw new ImageProcessorException("No sizes defined.", 1);
        }

        $imageFilePath = $this->makeTemporaryFile($imageLocation);

        $imageType = exif_imagetype($imageFilePath);

        if (!$imageType) {
            throw new ImageProcessorException("Could not determine image type.", 5);
        }

        $imageData = file_get_contents($imageFilePath);

        $originalImage = @imagecreatefromstring($imageData);

        if ($originalImage === false) {
            throw new ImageProcessorException("Image could not be decoded.", 3);
        }

        $originalWidth = imagesx($originalImage);
        $originalHeight = imagesy($originalImage);

        if ($originalWidth === 0 || $originalHeight === 0) {
            throw new ImageProcessorException("Image has no size.", 4);
        }

        // Create save directory when it doesn't exist
        if (!file_exists($this->getOutputDirectory())) {
            if (!@mkdir($this->getOutputDirectory(), 0755, true)) {
                throw new ImageProcessorException("Could not create output directory.", 5);
            }
        }

        if ($this->outputType === self::OUTPUT_TYPE_GIF ||
            ($imageType === IMAGETYPE_GIF && $this->outputType === self::OUTPUT_TYPE_ORIGINAL)
        ) {
            $outputExtension = "gif";
        } elseif ($this->outputType === self::OUTPUT_TYPE_JPEG ||
            ($imageType === IMAGETYPE_JPEG && $this->outputType === self::OUTPUT_TYPE_ORIGINAL)
        ) {
            $outputExtension = "jpg";
        } elseif ($this->outputType === self::OUTPUT_TYPE_PNG ||
            ($imageType === IMAGETYPE_PNG && $this->outputType === self::OUTPUT_TYPE_ORIGINAL)
        ) {
            $outputExtension = "png";
        } else {
            throw new ImageProcessorException("Could not save image due to unsupported output type.", 104);
        }

        $result = new ProcessedImage();

        // Resize image
        foreach($this->getSizes() as $suffix => $size)
        {
            // Find a filename that's not taken if not overwriting
            $noConflictSuffix = "";
            do {
                $outputFileName = DataHandling::sanitizeUrlParts($imageName .
                    ($noConflictSuffix ? "-" . $noConflictSuffix : "") . ($suffix ? "-" . $suffix : "")
                ) . "." . $outputExtension;
                $outputFilePath = $this->getOutputDirectory() . $outputFileName;

                if (!$noConflictSuffix) {
                    $noConflictSuffix = 2;
                } else {
                    $noConflictSuffix++;
                }
            } while (!$this->isOverwrite() && file_exists($outputFilePath));

            // Resize with Gifsicle
            if ($this->gifsicle && $imageType === IMAGETYPE_GIF &&
                in_array($this->outputType, [self::OUTPUT_TYPE_ORIGINAL, self::OUTPUT_TYPE_GIF])
            ) {
                $gifsicleResize = new Process(sprintf("gifsicle --resize-fit %1\$sx%1\$s --optimize=2 %2\$s --output %3\$s", $size, $imageFilePath, $outputFilePath));
                $gifsicleResize->run();

                if (!$gifsicleResize->isSuccessful()) {
                    throw new ImageProcessorException("Could not resize image using Gifsicle: ".$gifsicleResize->getErrorOutput(), 105);
                }
            } else {
                // Resize with GD
                // Calculate new width and height
                $scaleFactor = min($size / $originalWidth, $size / $originalHeight);
                $scaleFactor = min($scaleFactor, 1);

                $newWidth = ceil($originalWidth * $scaleFactor);
                $newHeight = ceil($originalHeight * $scaleFactor);

                // Create a new image
                $scaledImage = imagecreatetruecolor($newWidth, $newHeight);

                // Make the background transparent or white depending on whether transparency is supported
                if ($this->outputType === self::OUTPUT_TYPE_PNG ||
                    ($this->outputType === self::OUTPUT_TYPE_ORIGINAL && $imageType === IMAGETYPE_PNG)) {
                    imagesavealpha($scaledImage, true);
                    imagefill($scaledImage, 0, 0, imagecolorallocatealpha(
                        $scaledImage, 255, 255, 255, 127));
                } else {
                    imagefill($scaledImage, 0, 0, imagecolorallocate($scaledImage, 255, 255, 255));
                }

                imagecopyresampled($scaledImage, $originalImage, 0, 0, 0, 0, $newWidth, $newHeight,
                    $originalWidth, $originalHeight);

                if ($this->outputType === self::OUTPUT_TYPE_JPEG ||
                    ($this->outputType === self::OUTPUT_TYPE_ORIGINAL && $imageType === IMAGETYPE_JPEG)
                ) {
                    if (!imagejpeg($scaledImage, $outputFilePath, 85)) {
                        throw new ImageProcessorException("Could not save image as jpeg.", 101);
                    }
                } elseif (
                    $this->outputType === self::OUTPUT_TYPE_PNG ||
                    ($this->outputType === self::OUTPUT_TYPE_ORIGINAL && $imageType === IMAGETYPE_PNG)
                ) {
                    if (!imagepng($scaledImage, $outputFilePath, 9, PNG_ALL_FILTERS)) {
                        throw new ImageProcessorException("Could not save image as png.", 102);
                    }
                } elseif (
                    $this->outputType === self::OUTPUT_TYPE_GIF ||
                    ($this->outputType === self::OUTPUT_TYPE_ORIGINAL && $imageType === IMAGETYPE_GIF)
                ) {
                    if (!imagegif($scaledImage, $outputFilePath)) {
                        throw new ImageProcessorException("Could not save image as gif.", 103);
                    }
                }

                imagedestroy($scaledImage);
            }

            $result->addFileName($outputFileName, $suffix);
        }

        // Process colors
        if ($this->colorProcessor) {
            $result->setColors($this->colorProcessor->processColors($originalImage));
        }

        imagedestroy($originalImage);

        return $result;
    }

    /**
     * @return int[]
     */
    public function getSizes()
    {
        return $this->sizes;
    }

    /**
     * @param int[] $sizes Sizes in pixels. Keys can be set to image suffixes for that size.
     * @return ImageProcessor
     */
    public function setSizes($sizes)
    {
        $parsedSizes = [];
        foreach($sizes as $suffix => $size) {
            if (is_int($suffix)) {
                $suffix = $size;
            }

            $parsedSizes[(string)$suffix] = $size;
        }

        $this->sizes = $parsedSizes;

        return $this;
    }

    /**
     * @return string
     */
    public function getOutputDirectory()
    {
        return $this->outputDirectory;
    }

    /**
     * @param string $outputDirectory
     * @return ImageProcessor
     */
    public function setOutputDirectory($outputDirectory)
    {
        $this->outputDirectory = rtrim(trim($outputDirectory), "/")."/";

        return $this;
    }

    /**
     * @return string
     */
    public function getOutputType()
    {
        return $this->outputType;
    }

    /**
     * @param string $type
     * @return ImageProcessor
     * @throws \Villermen\DataHandling\DataHandlingException
     */
    public function setOutputType($type)
    {
        DataHandling::validateInArray($type, self::OUTPUT_TYPES);

        $this->outputType = $type;

        return $this;
    }

    /**
     * @return ColorProcessor|false
     */
    public function getColorProcessor()
    {
        return $this->colorProcessor;
    }

    /**
     * @param ColorProcessor|false $colorProcessor
     * @return ImageProcessor
     */
    public function setColorProcessor($colorProcessor)
    {
        $this->colorProcessor = $colorProcessor;

        return $this;
    }

    /**
     * @return string
     */
    public function getTemporaryDirectory()
    {
        return $this->temporaryDirectory;
    }

    /**
     * @param string $temporaryDirectory
     * @return ImageProcessor
     */
    public function setTemporaryDirectory($temporaryDirectory)
    {
        $this->temporaryDirectory = rtrim(trim($temporaryDirectory), "/")."/";

        return $this;
    }

    /**
     * Copies the given file to a temporary file and returns its path.
     *
     * @param $imageLocation
     * @return string
     * @throws ImageProcessorException
     */
    private function makeTemporaryFile($imageLocation)
    {
        $imageFile = @fopen($imageLocation, "r");

        if (!$imageFile) {
            throw new ImageProcessorException("Could not open image location for reading: ".error_get_last()["message"], 7);
        }

        $temporaryFilePath = $this->temporaryDirectory . "imageProcessorCache" . md5($imageLocation);

        $temporaryFile = @fopen($temporaryFilePath, "w");

        if (!$temporaryFile) {
            fclose($imageFile);
            throw new ImageProcessorException("Could not create temporary image file: ".error_get_last()["message"], 8);
        }

        $copyResult = @stream_copy_to_stream($imageFile, $temporaryFile);

        fclose($imageFile);
        fclose($temporaryFile);

        if ($copyResult === false) {
            throw new ImageProcessorException("Could not copy image to temporary file: ".error_get_last()["message"], 9);
        }

        if (!$copyResult) {
            throw new ImageProcessorException("The image file is empty.", 2);
        }

        return $temporaryFilePath;
    }

    /**
     * @return bool
     */
    public function isOverwrite()
    {
        return $this->overwrite;
    }

    /**
     * @param bool $overwrite
     *
     * @return ImageProcessor
     */
    public function setOverwrite($overwrite)
    {
        $this->overwrite = $overwrite;

        return $this;
    }
}
