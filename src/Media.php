<!-- need this: https://www.mongodb.com/docs/php-library/current/tutorial/custom-types/
https://www.php.net/manual/en/function.mongodb.bson-tophp.php -->


<?php
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

    public static int $maxImgSize = 960;

    public static function getMediaDir()
    {
        return $_ENV['MEDIADIR'];
    }

    public static function withRelativeDirAndFilename(string $directoryName, string $fileName)
    {
        $mediadir = self::getMediaDir();
        $fullPath = path_join(path_join($mediadir, $directoryName), $fileName);
        $instance = new self();
        if (is_dir($fullPath)) {

            // if (is_dir($baseDir . $dir . '/' . $file)) {
            //     $dirs[] = $saveDirName . '/' . $file;
            //     $tagFilename = $baseDir . $dir . '/' . $file . '/tags';
            //     if (is_file($tagFilename)) {
            //         $tags = file_get_contents($tagFilename);
            //     } else {
            //         $tags = "";
            //     }
            //     $dirtags[$saveDirName . '/' . $file] = $tags;
            // } else {
            //     $files[] = Media::withDirAndFilename("$baseDir/$dir", $file);
            // }

            $type = 'folder';
            $extension = '';
            $instance = Folder::withRelativeDirAndFilename($directoryName, $fileName);
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
                    $instance = Video::withRelativeDirAndFilename($directoryName, $fileName);
                    $creationTime = $instance->metadata['creationTime'];
                    $creationDate = date('Y-m-d', $creationTime);
                    break;

                case 'jpg':
                    $instance = Image::withRelativeDirAndFilename($directoryName, $fileName);
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
        $instance->relativePath = path_join($directoryName, $fileName);

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
