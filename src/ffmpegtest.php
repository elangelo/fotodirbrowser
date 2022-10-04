#!/usr/bin/env php

<?php
//https://github.com/PHP-FFMpeg/PHP-FFMpeg

require __DIR__ . '/../vendor/autoload.php';

$ffmpeg = FFMpeg\FFMpeg::create();

$video = $ffmpeg->open( __DIR__ . '/../docs/example/2020/2020-09-17/VID_20200917_154600.mp4');
$frame = $video->frame(FFMpeg\Coordinate\TimeCode::fromSeconds(2));
$frame->save('VID_20200917_154600.jpg');
