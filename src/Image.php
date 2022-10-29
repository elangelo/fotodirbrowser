<?php

class Image extends Media
{
    public readonly string $orientation;
    public readonly array $metadata;
    public readonly int $width;
    public readonly int $height;


    public static function withDirAndFilename($directoryName, $fileName)
    {
        $instance = new self();
        $metadata = ImageEngine::getMetaData($directoryName, $fileName);
        $instance->metadata = $metadata;
        $instance->orientation = $metadata['orientation'] ?? 'LANDSCAPE';
        $instance->width = $metadata['ImageWidth'] ?? 1000;
        $instance->height = $metadata['ImageLength'] ?? 1000;

        return $instance;
    }

    public function getPreviewUrl()
    {
        switch ($this->orientation) {
            case 'LANDSCAPE':
                $width = self::$maxImgSize;
                $height = $this->height * self::$maxImgSize / $this->width;
                break;
            case 'PORTRAIT':
                $height = self::$maxImgSize;
                $width = $this->width * self::$maxImgSize / $this->height;
                break;
        }
        return "<div class=\"mySlides\" style=\"width:" . $width . "px;\"><img src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=" . self::$maxImgSize . "\" width=\"" . $width . "\" height=\"" . $height . "\"/></div>";
    }

    public function getThumbUrl(int $counter)
    {
        return "<img class=\"grid\" src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=300\"  onclick=\"openModal();currentSlide(" . $counter + 1 . ")\" />";
    }
}
