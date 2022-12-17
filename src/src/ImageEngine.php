<?php
require __DIR__ . '/../vendor/autoload.php';

use Jcupitt\Vips;

class ImageEngine
{
    static function resizeImageFromPath($fileLocation, $size)
    {
        include __DIR__ . '/../includes.inc';
        $splFileInfo = new SplFileInfo($fileLocation);
        $fileName = $splFileInfo->getFilename();

        // $fullImagePath = $baseDir . '/' . $fileLocation;
        if ($size == 0) {
            return $fileLocation;
        } else {
            $thumbBaseDir = $thumbBaseDir . '/' . $size . '/';
            $basePath = $splFileInfo->getPath();

            $fullThumbPath = $thumbBaseDir . $basePath . '/' . $fileName;
            $splfileInfo = new SplFileInfo($fullThumbPath);

            if (!file_exists($fullThumbPath)) {
                $thumbfolder = $splfileInfo->getPath();
                if (!file_exists($thumbfolder)) {
                    mkdir($thumbfolder, 0770, true);
                }

                $im = Vips\Image::thumbnail($fileLocation, $size);
                $im->writeToFile($fullThumbPath);
            }
            return $fullThumbPath;
        }
    }

    static function getMetaData($directoryName, $fileName)
    {
        $fullpath = "$directoryName/$fileName";
        $exif_properties = ["FileDateTime", "MimeType", "FileSize", "Make", "ExifImageWidth", "ExifImageLength", "Model", "Orientation", "ExposureTime", "ISOSpeedRatings", "ShutterSpeedValue", "ApertureValue", "LightSource", "Flash", "FocalLengthIn35mmFilm"];
        $exif = exif_read_data($fullpath);
        $metadata = array();
        foreach ($exif_properties as $key) {
            $metadata[$key] = $exif[$key] ?? NULL;
        }

        $orientation = self::getOrientation($metadata['Orientation']);
        //if vertical, swap orientation!
        if ($orientation == 'PORTRAIT') {
            $height = $metadata['ExifImageWidth'];
            $width = $metadata['ExifImageLength'];
            $metadata['ExifImageWidth'] = $width;
            $metadata['ExifImageLength'] = $height;
        }

        $metadata['orientation'] = $orientation;
        return $metadata;
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
