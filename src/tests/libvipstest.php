#!/usr/bin/env php

<?php
//https://github.com/libvips/php-vips

require __DIR__ . '/../vendor/autoload.php';
use Jcupitt\Vips;

unlink('inverted.jpg');
unlink('tiny.jpg');

echo "yes\n";

// fast thumbnail generator
$image = Vips\Image::thumbnail( __DIR__ . '/../docs/example/2020/2020-09-17/IMG_20200917_141104.jpg', 200);
$image->writeToFile('tiny.jpg');

// load an image, get fields, process, save
$image = Vips\Image::newFromFile(__DIR__ . '/../docs/example/2020/2020-09-17/IMG_20200917_141104.jpg');
echo "width = $image->width\n";
$image = $image->invert();

$image->writeToFile('inverted.jpg');