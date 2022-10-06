<?php
require __DIR__ . '/../vendor/autoload.php';

include 'Dimension.php';
include __DIR__ . '/../includes.inc';

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
                return "video";
                break;
        }
    }

    public function getImgUrl()
    {
        switch ($this->type) {
            case 'image':
                $dimension = $this->getDimension();
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

                echo "<div class=\"mySlides\" style=\"width:" . $width . "px;\">";
                echo "<img src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=" . self::$maxImgSize . "\" width=\"" . $width . "\" height=\"" . $height . "\"/>";
                echo "</div>";
                break;
            case 'video':
                return "video";
                break;
        }
    }

    private function getDimension()
    {
        $im = Vips\Image::newFromFile( $baseDir . '/' . $this->fullFilename);
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
}
