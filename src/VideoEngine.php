<?php

require __DIR__ . '/../vendor/autoload.php';

use FFMpeg\FFProbe;

class VideoEngine
{
    static function getStillFromVideo($fileLocation, $size)
    {
        include __DIR__ . '/../includes.inc';
        $splFileInfo = new SplFileInfo($fileLocation);
        $fileName = $splFileInfo->getFilename();

        $fullThumbPath = $thumbBaseDir . '/' . $size . '/' . $fileName . '.jpg';
        $splfileInfo = new SplFileInfo($fullThumbPath);
        $thumbfolder = $splfileInfo->getPath();
        if (!file_exists($thumbfolder)) {
            mkdir($thumbfoler, 0770, true);
        }

        if (!file_exists($fullThumbPath)) {
            $ffmpeg = FFMpeg\FFMpeg::create();
            $video = $ffmpeg->open($fileLocation);
            $frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(2));
            $frame->save($fullThumbPath);
        }

        return $fullThumbPath;
    }

    static function getMetaData($directoryName, $fileName)
    {
        $fullPath = "$directoryName/$fileName";

        $ffprobe = FFProbe::create();
        $videoinfo = $ffprobe->format($fullPath);
        $duration =  (float)$videoinfo->get('duration');
        $tags = $videoinfo->get('tags');
        $datetime = $tags['creation_time'] ?? filemtime($directoryName);
        $unixtime = strtotime($datetime);
        $streams = $ffprobe->streams($fullPath);
        $videostream = $streams->videos()->first();
        $audiostream = $streams->audios()->first();
        $videoprops = ["codec_name", "width", "height", "display_aspect_ratio", "avg_frame_rate", "duration"];

        $videometadata = array();
        foreach ($videoprops as $prop) {
            $videometadata[$prop] = $videostream->get($prop);
        }
        $orientation = self::getOrientation($videometadata['display_aspect_ratio']);

        //if vertical, swap orientation!
        if ($orientation == 'PORTRAIT') {
            $height = $videometadata['width'];
            $width = $videometadata['height'];
            $videometadata['width'] = $width;
            $videometadata['height'] = $height;
        }

        $audioprops = ["codec_name", "sample_rate", "channels", "duration", "bit_rate"];
        $audiometadata = array();
        foreach ($audioprops as $prop) {
            $audiometadata[$prop] = $audiostream->get($prop);
        }

        $metadata = ([
            'creationTime' => $unixtime,
            'duration' => $duration,
            'orientation' => $orientation,
            'video' => $videometadata,
            'audio' => $audiometadata
        ]);

        return $metadata;
    }

    static function getOrientation($ratio)
    {
        $tmp = explode(':', $ratio);

        if ((int)$tmp[0] / (int)$tmp[1] > 1) {
            return 'LANDSCAPE';
        } else {
            return 'PORTRAIT';
        }
    }
}
