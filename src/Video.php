<?php
class Video extends Media
{
    public readonly string $orientation;
    public readonly array $metadata;
    public readonly int $width;
    public readonly int $height;


    public static function withDirAndFilename($directoryName, $fileName)
    {
        $instance = new self();
        $metadata = VideoEngine::getMetaData($directoryName, $fileName);
        $instance->metadata = $metadata;
        $instance->orientation = $metadata['orientation'] ?? 'LANDSCAPE';
        $instance->width = $metadata['video']['width'] ?? 1920;
        $instance->height = $metadata['video']['height'] ?? 1080;

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
        return "<div class=\"mySlides\"><video width=\"" . $width . "\" height=\"" . $height . "\" controls><source src=\"VideoHandler.php?fileLocation=" . $this->saveFilename . "\" /></video></div>";
    }

    public function getThumbUrl(int $counter)
    {
        return "<img class=\"grid\" src=\"VideoHandler.php?fileLocation=" . $this->saveFilename . "&size=300\"  onclick=\"openModal();currentSlide(" . $counter + 1 . ")\" />";
    }
}
