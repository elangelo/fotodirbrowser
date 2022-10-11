<?php
require __DIR__ . '/../vendor/autoload.php';

include 'Dimension.php';

use FFMpeg\FFProbe;
use Jcupitt\Vips;

class Media
{
    public readonly string $directory;
    public readonly string $fullFilename;
    public readonly string $fileName;
    public readonly string $extension;
    public readonly string $type;
    public readonly string $saveFilename;

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
                break;
            case "jpg":
                $this->type = 'image';
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

                // $image = imagecreatefromjpeg($this->fullfilename);
                // $oWidth = imagesx($image);
                // $oHeight = imagesy($image);
                // if ($oWidth > $oHeight) {
                //     $width = $maxsize;
                //     $height = $oHeight * $maxsize / $oWidth;
                // } else {
                //     $height = $maxsize;
                //     $width = $oWidth * $maxsize / $oHeight;
                // }

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
        include __DIR__ . '/../includes.inc';
        $im = Vips\Image::newFromFile($baseDir . '/' . $this->fullFilename);
        $orientation = 'HORIZONTAL';
        $width = $im->width;
        $height = $im->height;
        if ($im->getType('orientation') != 0) {
            $or = $im->get('orientation');
            switch ($or) {
                case 1:
                case 2:
                case 4:
                    break;
                case 3:
                case 5:
                case 6:
                case 7:
                case 8:
                    $orientation = 'VERTICAL';
                    $width = $im->height;
                    $height = $im->width;
                    break;
            }
        }

        return new Dimension($width, $height, $orientation);
    }

    private function getVideoDimension()
    {
        include __DIR__ . '/../includes.inc';

        $ffprobe = FFMpeg\FFProbe::create();
        $videostream = $ffprobe
            ->streams($baseDir . '/' . $this->fullFilename)
            ->videos()
            ->first();

        $ratio = $videostream->get('display_aspect_ratio');
        $tmp = explode(':', $ratio);

        if ($tmp[0] / $tmp[1] > 1) {
            $orientation = 'HORIZONTAL';
        } else {
            $orientation = 'VERTICAL';
        }

        $width = $videostream->get('width');
        $height = $videostream->get('height');

        return new Dimension($width, $height, $orientation);
    }
}
