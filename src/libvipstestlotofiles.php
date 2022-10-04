#!/usr/bin/env php

<?php
//https://github.com/libvips/php-vips

require __DIR__ . '/../vendor/autoload.php';
use Jcupitt\Vips;

$dir = __DIR__ . '/../docs/example/2020/2020-09-17';
if ($handle = opendir ($dir)) {
    while (false !== ($file = readdir($handle))){
        if ($file != "." && $file != "..") {
            $fullfile = $dir . '/' . $file;
            if (is_dir($fullfile)){

            } else {
                $tmp = explode ('.', $file);
                $extension = end($tmp);
                if ($extension == "JPG" || $extension == "jpg") {
                    $image = Vips\Image::thumbnail( $fullfile, 200);
                    $image->writeToFile('tiny'.$file);
                }
            }
        }
    }
}
closedir($handle);


// // fast thumbnail generator
// $image = Vips\Image::thumbnail( __DIR__ . '/../docs/example/2020/2020-09-17/IMG_20200917_141104.jpg', 200);
// $image->writeToFile('tiny.jpg');

// // load an image, get fields, process, save
// $image = Vips\Image::newFromFile(__DIR__ . '/../docs/example/2020/2020-09-17/IMG_20200917_141104.jpg');
// echo "width = $image->width\n";
// $image = $image->invert();

// $image->writeToFile('inverted.jpg');