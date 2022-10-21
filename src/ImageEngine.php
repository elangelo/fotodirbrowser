<?php
require __DIR__ . '/../vendor/autoload.php';

use Jcupitt\Vips;

function trace($message)
{
    $debug = false;
    if ($debug) {
        print $message . "<br/>";
    }
}

function resizeImageFromPath($fileLocation, $size)
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

function getMetaData($fileName, $fullFilePath)
{
    $exif_properties = ["FileDateTime", "MimeType", "FileSize", "Make", "ImageWidth", "ImageLength", "Model", "Orientation", "ExposureTime", "ISOSpeedRatings", "ShutterSpeedValue", "ApertureValue", "LightSource", "Flash", "FocalLengthIn35mmFilm"];
    $exif = exif_read_data($fullFilePath);
    $metadata = array();
    foreach ($exif_properties as $key) {
        $metadata[$key] = $exif[$key];
    }
    $record = ([
        'filename' => $fileName,
        'path' => $fullFilePath,
        'type' => 'file',
        'md5sum' => md5_file($fullFilePath),
        'metadata' => $metadata,
        'size' => $metadata["FileSize"],
        'date' => $metadata["FileDateTime"],
        'deleted' => false
    ]);

    return $record;
}
