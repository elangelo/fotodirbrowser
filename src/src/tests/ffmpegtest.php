#!/usr/bin/env php

<?php
//https://github.com/PHP-FFMpeg/PHP-FFMpeg

require __DIR__ . '/../../vendor/autoload.php';

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Format;
use FFMpeg\Coordinate;

$ffmpeg = FFMpeg::create();

// $video = $ffmpeg->open( __DIR__ . '/../../docs/example/2020/2020-09-17/VID_20200917_154600.mp4');
$video == $ffmpeg->open(__DIR__ . '/../../docs/example/2020/2020-09-17/VID_20200917_163649.mp4');
$frame = $video->frame(Coordinate\TimeCode::fromSeconds(2));
$frame->save('VID_20200917_154600.jpg');

$format = new Format\Video\X264();
// $format->setInitialParameters(array(/*'-movflags',*/ '+faststart', '-preset', 'superfast'));

$maxDimension = new Coordinate\Dimension(720,720);

$video = $ffmpeg->open( __DIR__ . '/../../docs/example/2020/2020-09-17/VID_20200917_154600.mp4');
$video
 ->filters()
 ->resize($maxDimension, 'inset')
 ->synchronize();

 $video
 ->save($format, 'test.mp4');
 //  new FFMpeg\Coordinate\Dimension(720,720))



$ffprobe = FFProbe::create();
$videostream = $ffprobe
    ->streams(__DIR__ . '/../../docs/example/2020/2020-09-17/VID_20200917_163649.mp4')
    ->videos()
    ->first();

$videostream-get()

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