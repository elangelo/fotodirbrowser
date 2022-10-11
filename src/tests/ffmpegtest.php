#!/usr/bin/env php

<?php
//https://github.com/PHP-FFMpeg/PHP-FFMpeg

require __DIR__ . '/../../vendor/autoload.php';
use FFMpeg\FFProbe;

$ffmpeg = FFMpeg\FFMpeg::create();

$video = $ffmpeg->open( __DIR__ . '/../../docs/example/2020/2020-09-17/VID_20200917_154600.mp4');
$frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(2));
$frame->save('VID_20200917_154600.jpg');


$ffprobe = FFMpeg\FFProbe::create();
$videostream = $ffprobe
    ->streams(__DIR__ . '/../../docs/example/2020/2020-09-17/VID_20200917_154600.mp4')
    ->videos()
    ->first();

$ratio = $videostream->get('display_aspect_ratio')         ;   

// print ($dimensions);

$width = $videostream->get('width');
$height = $videostream->get('height');

print 'width : '. $width . '\n';
print 'height : ' . $height . '\n';
print 'ratio :' . $ratio . '\n';

 $tmp = explode(':', $ratio);

if ($tmp[0] / $tmp[1] > 1) {
    $orientation = 'horizontal';
} else {
    $orientation = 'vertical';
}

print 'orientation: ' . $orientation;