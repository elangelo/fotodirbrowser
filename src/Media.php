<?php
require __DIR__ . '/../vendor/autoload.php';

include 'Dimension.php';
include 'VideoEngine.php';
include 'ImageEngine.php';

use FFMpeg\FFProbe;
use Jcupitt\Vips;

class Media
{
    private readonly string $directory;
    private readonly string $fullFilename;
    private readonly string $fileName;
    private readonly string $extension;
    private readonly string $type;
    private readonly string $saveFilename;

    private readonly array $record;

    public static int $maxImgSize = 960;

    public function __construct(string $directory, string $fileName)
    {
        $this->directory = $directory;
        $this->fileName = $fileName;

        $this->fullFilename = $directory . '/' . $fileName;
        $tmp = explode('.', $this->fileName);
        $this->extension = strtolower(end($tmp));

        $this->saveFilename = str_replace("&", "_*_", $this->fullFilename);

        switch ($this->extension) {
            case "mp4":
                $this->type = 'video';
                $this->record = VideoEngine::getMetaData($this->fileName, $this->fullFilename);
                break;
            case "jpg":
                $this->type = 'image';
                $this->record = ImageEngine::getMetaData($this->fileName, $this->fullFilename);
                break;
            default:
                break;
        }
    }

    /* This is the static comparing function: */
    static function cmp_obj($a, $b)
    {
        return strtolower($a->fileName) <=> strtolower($b->fileName);
    }

    public function getThumbUrl(int $counter)
    {
        switch ($this->type) {
            case 'image':
                return "<img class=\"grid\" src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=300\"  onclick=\"openModal();currentSlide(" . $counter + 1 . ")\" />";
                break;
            case 'video':
                return "<img class=\"grid\" src=\"VideoHandler.php?fileLocation=" . $this->saveFilename . "&size=300\"  onclick=\"openModal();currentSlide(" . $counter + 1 . ")\" />";
                break;
        }
    }

    public function getPreviewUrl()
    {
        switch ($this->type) {
            case 'image':
                $dimension = $this->getImageDimension();
                switch ($dimension->orientation) {
                    case 'HORIZONTAL':
                        $width = self::$maxImgSize;
                        $height = $dimension->height * self::$maxImgSize / $dimension->width;
                        break;
                    case 'VERTICAL':
                        $height = self::$maxImgSize;
                        $width = $dimension->width * self::$maxImgSize / $dimension->height;
                        break;
                }
                return "<div class=\"mySlides\" style=\"width:" . $width . "px;\"><img src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=" . self::$maxImgSize . "\" width=\"" . $width . "\" height=\"" . $height . "\"/></div>";
            case 'video':
                $dimension = $this->getVideoDimension();
                switch ($dimension->orientation) {
                    case 'HORIZONTAL':
                        $width = self::$maxImgSize;
                        $height = $dimension->height * self::$maxImgSize / $dimension->width;
                        break;
                    case 'VERTICAL':
                        $height = self::$maxImgSize;
                        $width = $dimension->width * self::$maxImgSize / $dimension->height;
                        break;
                }
                return "<div class=\"mySlides\"><video width=\"" . $width . "\" height=\"" . $height . "\" controls><source src=\"VideoHandler.php?fileLocation=" . $this->saveFilename . "\" /></video></div>";
                break;
        }
    }

    private function getImageDimension()
    {
        $width = $this->record['metadata']['ImageWidth'];
        $height = $this->record['metadata']['ImageLength'];
        $orientation = $this->record['orientation'];

        return new Dimension($width, $height, $orientation);
    }

    private function getVideoDimension()
    {
        include __DIR__ . '/../includes.inc';

        $orientation = $this->record['orientation'];
        $width = $this->record['metadata']['video']['width'];
        $height = $this->record['metadata']['video']['height'];

        return new Dimension($width, $height, $orientation);
    }
}
