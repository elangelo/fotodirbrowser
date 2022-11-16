<?php
require_once('functions.php');

class Image extends Media implements MongoDB\BSON\Persistable
{
    public string $orientation;
    public array $metadata;
    public int $width;
    public int $height;

    public static function withRelativeDirAndFilename($directoryName, $fileName)
    {
        $instance = new self();
        $metadata = ImageEngine::getMetaData($directoryName, $fileName);
        $instance->metadata = $metadata;
        $instance->orientation = $metadata['orientation'] ?? 'LANDSCAPE';
        $instance->width = $metadata['ExifImageWidth'] ?? 1000;
        $instance->height = $metadata['ExifImageLength'] ?? 1000;

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
        return "<div class=\"mySlides\" style=\"width:" . $width . "px;\"><img loading=\"lazy\" src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=" . self::$maxImgSize . "\" width=\"" . $width . "\" height=\"" . $height . "\"/></div>";
    }

    public function getThumbUrl(int $counter)
    {
        return "<img class=\"grid\" src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=300\"  onclick=\"openModal();currentSlide(" . $counter + 1 . ")\" />";
    }

    public function bsonSerialize()
    {
        $media = parent::bsonSerialize();
        $media['orientation'] = $this->orientation;
        $media['metadata'] = $this->metadata;
        $media['width'] = $this->width;
        $media['height'] = $this->height;
        return $media;
    }

    public function bsonUnserialize(array $data)
    {
        $this->fileName = $data['fileName'];
        $this->directoryName = $data['directoryName'];
        $this->fullPath = $data['fullPath'];
        $this->relativePath = $data['relativePath'];
        $this->extension = $data['extension'];
        $this->type = $data['type'];
        $this->saveFilename = $data['saveFilename'];
        $this->md5sum = $data['md5sum'];
        $this->size = $data['size'];
        $this->creationTime = $data['creationTime'];
        $this->creationDate = $data['creationDate'];
        $this->delete = $data['deleted'];
        $this->orientation = $data['orientation'];
        $this->metadata=(array)$data['metadata'];
        $this->width = $data['width'];
        $this->height = $data['height'];
    }
}
