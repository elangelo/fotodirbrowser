<!-- need this: https://www.mongodb.com/docs/php-library/current/tutorial/custom-types/
https://www.php.net/manual/en/function.mongodb.bson-tophp.php -->


<?php
require __DIR__ . '/../vendor/autoload.php';

include 'VideoEngine.php';
include 'ImageEngine.php';
include 'Image.php';
include 'Video.php';
// include 'Directory.php';

// use FFMpeg\FFProbe; 
// use FFMpeg\Media\Video;
// use Jcupitt\Vips;

class Media
{
    public readonly string $fileName;
    public readonly string $directoryName;
    public readonly string $fullPath;

    public readonly string $extension;
    public readonly string $type;
    public readonly string $saveFilename;

    public readonly string $md5sum;
    public readonly int $size;

    public readonly int $creationTime;
    public readonly string $creationDate;

    public readonly bool $deleted;

    // public readonly array $record;

    public static int $maxImgSize = 960;

    public static function withDirAndFilename(string $directoryName, string $fileName)
    {
        $fullPath = "$directoryName/$fileName";
        $instance = new self();
        if (is_dir($fullPath)) {
            $type = 'directory';
            $extension = '';
            $instance = Directory::withDirAndFilename($directoryName, $fileName);
            $md5sum = '';
            $size = 0;
            $creationTime = filectime($fullPath);
            $creationDate = date('Y-m-d', $creationTime);
        } else {
            $type = 'file';
            $md5sum = md5_file($fullPath);
            $size = filesize($fullPath);
            $tmp = explode('.', $fileName);
            $extension = strtolower(end($tmp));
            switch ($extension) {
                case 'mp4':
                    $instance = Video::withDirAndFilename($directoryName, $fileName);
                    $creationTime = $instance->metadata['creationTime'];
                    $creationDate = date('Y-m-d', $creationTime);
                    break;

                case 'jpg':
                    $instance = Image::withDirAndFilename($directoryName, $fileName);
                    $creationTime = $instance->metadata['FileDateTime'];
                    $creationDate = date('Y-m-d', $creationTime);
                    break;
            }
        }
        $instance->fileName = $fileName;
        $instance->directoryName = $directoryName;
        $instance->fullPath = $fullPath;
        $instance->extension = $extension;
        $instance->type = $type;

        $instance->saveFilename = str_replace("&", "_*_", $instance->fullPath);

        $instance->md5sum = $md5sum;
        $instance->size = $size;

        $instance->creationDate = $creationDate;
        $instance->creationTime = $creationTime;

        $instance->deleted = false;

        return $instance;
    }

    public static function withBSONDoc(array $array)
    {
        $instance = new self();
        $instance->record = $array;
        return $instance;
    }

    /* This is the static comparing function: */
    static function cmp_obj($a, $b)
    {
        return strtolower($a->fileName) <=> strtolower($b->fileName);
    }
}
