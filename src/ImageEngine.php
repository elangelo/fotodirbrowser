<?php
require __DIR__ . '/../vendor/autoload.php';

use Jcupitt\Vips;

class ImageEngine
{
    static function trace($message)
    {
        $debug = false;
        if ($debug) {
            print $message . "<br/>";
        }
    }

    static function resizeImageFromPath($fileLocation, $size)
    {
        include __DIR__ . '/../includes.inc';
        trace("fileLocation: " . $fileLocation);
        trace("maxDimension: " . $size);
        $fullImagePath = $baseDir . '/' . $fileLocation;
        if ($size == 0) {
            return $fullImagePath;
        } else {
            $thumbBaseDir = $thumbBaseDir . '/' . $size . '/';
            $fullThumbPath = $thumbBaseDir . $fileLocation;

            $splfileInfo = new SplFileInfo($fullThumbPath);
            $thumbfolder = $splfileInfo->getPath();

            if (!file_exists($thumbfolder)) {
                mkdir($thumbfolder, 0770, true);
            }

            if (!file_exists($fullThumbPath)) {
                $im = Vips\Image::thumbnail($fullImagePath, $size);
                $im->writeToFile($fullThumbPath);
            }
            return $fullThumbPath;
        }
    }

    static function getMetaData($fileName, $fullFilePath)
    {
        $exif_properties = ["FileDateTime", "MimeType", "FileSize", "Make", "ImageWidth", "ImageLength", "Model", "Orientation", "ExposureTime", "ISOSpeedRatings", "ShutterSpeedValue", "ApertureValue", "LightSource", "Flash", "FocalLengthIn35mmFilm"];
        $exif = exif_read_data($fullFilePath);
        $metadata = array();
        foreach ($exif_properties as $key) {
            $metadata[$key] = $exif[$key];
        }

        $orientation = self::getOrientation($metadata['Orientation']);
        //if vertical, swap orientation!
        if ($orientation == 'PORTRAIT') {
            $height = $metadata['ImageWidth'];
            $width = $metadata['ImageLength'];
            $metadata['ImageWidth'] = $width;
            $metadata['ImageHeight'] = $height;
        }

        $record = ([
            'filename' => $fileName,
            'path' => $fullFilePath,
            'type' => 'file',
            'md5sum' => md5_file($fullFilePath),
            'metadata' => $metadata,
            'size' => $metadata["FileSize"],
            'orientation' => $orientation,
            'date' => $metadata["FileDateTime"],
            'deleted' => false
        ]);

        return $record;
    }

    static function getOrientation($orientation)
    {
        switch ($orientation) {
            case 1:
            case 2:
            case 4:
                return 'LANDSCAPE';
            case 3:
            case 5:
            case 6:
            case 7:
            case 8:
                return 'PORTRAIT';
        }
    }
}
