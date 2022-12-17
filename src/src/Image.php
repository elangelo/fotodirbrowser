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
        return "<img class=\"demo\" id=\"preview_" . $this->id . "\" loading=\"lazy\" src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=" . self::$maxPreviewSize . "\" alt=\"" . $this->fileName . "\"/>";
    }

    public function getThumbUrl(int $counter)
    {
        $newDimensions = $this->getResizedDimension(self::$maxThumbSize);
        return "<img class=\"grid\" id=\"thumb_" . $this->id . "\" src=\"ImageHandler.php?fileLocation=" . $this->saveFilename . "&size=" . self::$maxThumbSize . "\" width=\"" . $newDimensions[0] . "\" height=\"" . $newDimensions[1] . "\"   onclick=\"openModal();currentSlide(" . $counter + 1 . ")\" />";
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
        $this->id = $data["_id"];
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
        $this->metadata = (array)$data['metadata'];
        $this->width = $data['width'];
        $this->height = $data['height'];
    }
}
