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

function getMetaData($fileName, $fullFilePath)
{
    $ffprobe = FFProbe::create();
    $videoinfo = $ffprobe->format($fullFilePath);
    // var_dump($videoinfo);
    $duration =  (float)$videoinfo->get('duration');
    $tags = $videoinfo->get('tags');
    $datetime = $tags['creation_time'];
    $unixtime = strtotime($datetime);
    $streams = $ffprobe->streams($fullFilePath);
    $videostream = $streams->videos()->first();
    $audiostream = $streams->audios()->first();
    $videoprops = ["codec_name", "width", "height", "display_aspect_ratio", "avg_frame_rate", "duration"];
    $videometadata = array();
    foreach ($videoprops as $prop) {
        $videometadata[$prop] = $videostream->get($prop);
    }

    $audioprops = ["codec_name", "sample_rate", "channels", "duration", "bit_rate"];
    $audiometadata = array();
    foreach ($audioprops as $prop) {
        $audiometadata[$prop] = $audiostream->get($prop);
    }

    $metadata = ([
        'duration' => $duration,
        'video' => $videometadata,
        'audio' => $audiometadata
    ]);

    $record = ([
        'filename' => $fileName,
        'path' => $fullFilePath,
        'type' => 'file',
        'md5sum' => md5_file($fullFilePath),
        'metadata' => $metadata,
        'size' => filesize($fullFilePath),
        'date' => $unixtime,
        'deleted' => false
    ]);

    return $record;
}
