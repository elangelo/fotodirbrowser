<?php

require __DIR__ . '/../vendor/autoload.php';

use FFMpeg\FFProbe;

function trace($message)
{
    $debug = false;
    if ($debug) {
        print $message . "<br/>";
    }
}

function getStillFromVideo($fileLocation, $size)
{
    trace('fileLocationFromUrl : ' . $fileLocation);
    include __DIR__ . '/../includes.inc';
    $fullPath = $baseDir . '/' . $fileLocation;
    trace('fullPath' . $fullPath);
    $fullThumbPath = $thumbBaseDir . '/' . $size . '/' . $fileLocation . '.jpg';
    $splfileInfo = new SplFileInfo($fullThumbPath);
    $thumbfolder = $splfileInfo->getPath();
    if (!file_exists($thumbfolder)) {
        mkdir($thumbfoler, 0770, true);
    }

    if (!file_exists($fullThumbPath)) {
        trace('fullThumbPath' . $fullThumbPath);
        $ffmpeg = FFMpeg\FFMpeg::create();
        $video = $ffmpeg->open($fullPath);
        $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(2));
        $frame->save($fullThumbPath);
    }

    return $fullThumbPath;
}
