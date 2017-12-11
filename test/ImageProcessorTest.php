<?php

use Villermen\ImageProcessing\ColorProcessor;
use Villermen\ImageProcessing\ImageProcessor;
use BrianMcdo\ImagePalette\Color;

class ImageProcessorTest extends PHPUnit_Framework_TestCase
{
    /** @var ImageProcessor */
    private $imageProcessor;

    public function setUp()
    {
        $this->imageProcessor = new ImageProcessor(sys_get_temp_dir());
    }

    public function testProcess()
    {
        $outputDirectory = "test/out/";

        // Clear output directory
        foreach(glob($outputDirectory."*") as $filePath) {
            unlink($filePath);
        }

        if (file_exists($outputDirectory)) {
            rmdir($outputDirectory);
        }

        $this->imageProcessor
            ->setOutputDirectory($outputDirectory)
            ->setSizes([ "thumb" => 300, "large" => 500])
            ->setColorProcessor(new ColorProcessor());

        $processedImage = $this->imageProcessor->processImage("test/fixtures/1000_1000.png", "png image");
        self::assertEquals(
            ["thumb" => "png-image-thumb.jpg", "large" => "png-image-large.jpg"],
            $processedImage->getFileNames()
        );

        // Green and red shouldn't be here (green = not enough, red = background)
        $hexColors = array_filter($processedImage->getColors(), function(Color $color) {
            return $color->toHexString();
        });

        self::assertEquals(["#cccccc", "#333399", "#cc6633", "#993399", "#ffff00"], $hexColors);

        $this->imageProcessor->setColorProcessor(false);

        $this->imageProcessor->processImage("test/fixtures/800_400.jpg", "jpg image");
        self::assertFileExists($outputDirectory."jpg-image-thumb.jpg");
        self::assertFileExists($outputDirectory."jpg-image-large.jpg");
        $thumbSize = getimagesize($outputDirectory."jpg-image-thumb.jpg");
        self::assertEquals(300, $thumbSize[0]);
        self::assertEquals(150, $thumbSize[1]);
        self::assertEquals("image/jpeg", $thumbSize["mime"]);
        $largeSize = getimagesize($outputDirectory."jpg-image-large.jpg");
        self::assertEquals(500, $largeSize[0]);
        self::assertEquals(250, $largeSize[1]);

        $this->imageProcessor->processImage("test/fixtures/400_800.gif", "gif image");
        self::assertFileExists($outputDirectory."gif-image-thumb.jpg");
        $thumbSize = getimagesize($outputDirectory."gif-image-thumb.jpg");
        self::assertEquals(150, $thumbSize[0]);
        self::assertEquals(300, $thumbSize[1]);

        $this->imageProcessor->setOutputType(ImageProcessor::OUTPUT_TYPE_ORIGINAL);
        $this->imageProcessor->processImage("test/fixtures/1000_1000.png", "png image");
        self::assertFileExists($outputDirectory."png-image-thumb.png");
        self::assertFileExists($outputDirectory."png-image-large.png");
        $largeSize = getimagesize($outputDirectory."png-image-large.png");
        self::assertEquals("image/png", $largeSize["mime"]);

        $this->imageProcessor->setColorProcessor(new ColorProcessor());
        $processedImage = $this->imageProcessor->processImage("test/fixtures/animated.gif", "animated gif");
        self::assertFileExists($outputDirectory."animated-gif-thumb.gif");
        self::assertFileExists($outputDirectory."animated-gif-large.gif");
        $largeSize = getimagesize($outputDirectory."animated-gif-large.gif");
        self::assertEquals("image/gif", $largeSize["mime"]);
        $hexColors = array_filter($processedImage->getColors(), function(Color $color) {
            return $color->toHexString();
        });
        self::assertEquals(["#000000", "#333399"], $hexColors);

        // Test if no conflict suffix 2 is generated in no overwrite mode
        $this->imageProcessor
            ->setColorProcessor(false)
            ->setOverwrite(false);

        $this->imageProcessor->setOutputType(ImageProcessor::OUTPUT_TYPE_PNG);
        $this->imageProcessor->processImage("test/fixtures/1000_1000.png", "png image");
        self::assertFileExists($outputDirectory."png-image-2-thumb.png");
        self::assertFileExists($outputDirectory."png-image-2-large.png");

        echo "\nManual verification of images in \"{$outputDirectory}\" is recommended.\n";
    }

    public function testSizes()
    {
        $this->imageProcessor->setSizes([
            "thumb" => 300,
            20,
            23 => 80
        ]);

        self::assertEquals(
            ["thumb" => 300, "20" => 20, "80" => 80],
            $this->imageProcessor->getSizes()
        );
    }
}
