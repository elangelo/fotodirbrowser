<?php

// need this: https://www.mongodb.com/docs/php-library/current/tutorial/custom-types/
// https://www.php.net/manual/en/function.mongodb.bson-tophp.php

require __DIR__ . '/../vendor/autoload.php';

require_once('VideoEngine.php');
require_once('ImageEngine.php');
require_once('Image.php');
require_once('Video.php');
require_once('Folder.php');
require_once('functions.php');

// use FFMpeg\FFProbe; 
// use FFMpeg\Media\Video;
// use Jcupitt\Vips;

class Media
{
    public string $id;
    public string $fileName;
    //relative directory vs media directory
    public string $directoryName;

    //full path to file
    public string $fullPath;

    //relative path to file vs media directory
    public string $relativePath;

    public string $extension;
    public string $type;
    public string $saveFilename;

    public string $md5sum;
    public int $size;

    public int $creationTime;
    public string $creationDate;

    public bool $deleted;

    // public readonly array $record;

    public static int $maxPreviewSize = 960;
    public static int $maxThumbSize = 300;

    public static function withAbsoluteDirAndFilename(string $directoryName, string $fileName)
    {
        $mediadir = getMediaDir();
        $fullPath = path_join($directoryName, $fileName);
        $instance = new self();
        $index = false;
        try {
            if (is_dir($fullPath)) {
                $type = 'folder';
                $extension = '';
                $instance = Folder::withRelativeDirAndFilename($directoryName, $fileName);
                $md5sum = '';
                $size = 0;
                $creationTime = filectime($fullPath);
                $creationDate = date('Y-m-d', $creationTime);
                $index = true;
            } else {
                $type = 'file';
                $md5sum = md5_file($fullPath);
                $size = filesize($fullPath);
                $tmp = explode('.', $fileName);
                $extension = strtolower(end($tmp));
                switch ($extension) {
                    case 'mp4':
                    case 'm4v':
                        $instance = Video::withRelativeDirAndFilename($directoryName, $fileName);
                        $creationTime = $instance->metadata['creationTime'];
                        $creationDate = date('Y-m-d', $creationTime);
                        $index = true;
                        break;

                    case 'jpg':
                        $instance = Image::withRelativeDirAndFilename($directoryName, $fileName);
                        $creationTime = $instance->metadata['FileDateTime'];
                        $creationDate = date('Y-m-d', $creationTime);
                        $index = true;
                        break;
                }
            }
            if ($index && $creationTime != null) {
                $instance->fileName = $fileName;
                $parentPath = "/" . relativePath($mediadir, $directoryName);
                $instance->directoryName = $parentPath;
                $instance->fullPath = $fullPath;
                $instance->extension = $extension;
                $instance->type = $type;

                $relativePath = "/" . relativePath($mediadir, $fullPath);

                $instance->relativePath = $relativePath; // path_join($directoryName, $fileName);

                $instance->saveFilename = str_replace("&", "_*_", $instance->fullPath);

                $instance->md5sum = $md5sum;
                $instance->size = $size;

                $instance->creationDate = $creationDate;
                $instance->creationTime = $creationTime;

                $instance->deleted = false;

                return $instance;
            }
        } catch (Exception $ex) {
            echo "scanning {$directoryName}/{$fileName} failed, exception occurred:\r\n{$ex}";
        }
        return null;
    }

    public static function withBSONDoc(array $array)
    {
        $instance = new self();
        $instance->record = $array;
        return $instance;
    }

    public function getResizedDimension($maxDimension)
    {
        switch ($this->orientation) {
            case 'LANDSCAPE':
                $width = $maxDimension;
                $height = $this->height * $maxDimension / $this->width;
                break;
            case 'PORTRAIT':
                $height = $maxDimension;
                $width = $this->width * $maxDimension / $this->height;
                break;
        }

        return [$width, $height];
    }

    public function bsonSerialize()
    {
        return [
            'fileName' => $this->fileName,
            'directoryName' => $this->directoryName,
            'fullPath' => $this->fullPath,
            'relativePath' => $this->relativePath,
            'extension' => $this->extension,
            'type' => $this->type,
            'saveFilename' => $this->saveFilename,
            'md5sum' => $this->md5sum,
            'size' => $this->size,
            'creationTime' => $this->creationTime,
            'creationDate' => $this->creationDate,
            'deleted' => $this->deleted
        ];
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
    }

    /* This is the static comparing function: */
    static function cmp_obj($a, $b)
    {
        return strtolower($a->fileName) <=> strtolower($b->fileName);
    }
}
