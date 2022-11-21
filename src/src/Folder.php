<?php
require __DIR__ . '/../vendor/autoload.php';

class Folder extends Media implements MongoDB\BSON\Persistable
{
    public bool $scanned;
    public array $tags;

    public static function withRelativeDirAndFilename($directoryName, $fileName)
    {
        $mediaDir = self::getMediaDir();

        $instance = new self();
        $instance->scanned = false;
        $instance->tags = self::getTags("$mediaDir/$directoryName");
        return $instance;
    }

    private static function getTags($directoryName)
    {
        $tagFilename = $directoryName . '/tags';
        if (is_file($tagFilename)) {
            $tagsText = file_get_contents($tagFilename);
            $tags = explode(';', $tagsText);
        } else {
            $tags = array();
        }

        return $tags;
    }

    public string $thumbSize = "250";

    public function getThumbUrl(int $counter)
    {
        $tagstring = "";
        foreach ($this->tags as $dirTag) {
            if (!empty($dirTag)) {
                $tagstring .= "<div class=\"thumbtag\">" . $dirTag . '</div>';
            }
        }

        $html = "
         <a class=\"baseNavigation\" href=\"DirHandler.php?fileLocation=$this->relativePath\">
         <div class=\"tagcloud\">
        $tagstring
        </div>
        <div class=\"thumbimg\">
        <img height=\"" . $this->thumbSize . "\" width=\"" . $this->thumbSize . "\" src=\"assets/folder_200.png\">
        </div>
        <div class=\"thumblabel\">
        $this->fileName<br />
        </div>
        </a>";
        return $html;
    }

    public function getPreviewUrl()
    {
    }

    public function bsonSerialize()
    {
        $media = parent::bsonSerialize();
        $media['scanned'] = $this->scanned;
        $media['tags'] = $this->tags;
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
        $this->scanned = $data['scanned'];
        $this->tags = (array)$data['tags'];
    }
}
