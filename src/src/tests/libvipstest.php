#!/usr/bin/env php

<?php
//https://github.com/libvips/php-vips

require __DIR__ . '/../../vendor/autoload.php';

use Jcupitt\Vips;

unlink('inverted.jpg');
// unlink('tiny.jpg');

echo "yes\n";

// fast thumbnail generator
// $image = Vips\Image::thumbnail( __DIR__ . '/../../docs/example/2020/2020-09-17/IMG_20200917_141104.jpg', 200);
// $image->writeToFile('tiny.jpg');

// load an image, get fields, process, save
// $filename = __DIR__ . '/../../../docs/example/2010/2010-11-01/IMGP9389.JPG';
$filename = __DIR__ . '/../../../docs/example/2020/2020-09-17/IMG_20200917_141104.jpg';
$image = Vips\Image::newFromFile($filename);
echo "width = $image->width\n";
$image = $image->invert();

$image->writeToFile('inverted.jpg');

// $data = $image->get('exif-data');
// var_dump($data);
$exif = exif_read_data($filename);
$exif_ifd0 = exif_read_data($filename, 'IFD0', 0);
$exif_exif = exif_read_data($filename, 'EXIF', 0);
// echo "EXIF_EXIF\n";
// var_dump($exif_exif);
// echo "EXIF_IFD0\n";
// var_dump($exif_ifd0);

$exif_properties = ["FileDateTime", "MimeType", "FileSize", "Make", "ImageWidth", "ImageLength", "Model", "Orientation", "ExposureTime", "ISOSpeedRatings", "ShutterSpeedValue", "ApertureValue", "LightSource", "Flash", "FocalLengthIn35mmFilm"];

foreach($exif_properties as $key) {
    echo "${key} : ${exif[$key]}\n";
}
var_dump($exif);



// foreach ($exif_exif as $key => $value) {
//     if (!is_array($value)) {
//         echo $key . ":" . $value . "\n";
//     }
// }

// var_dump($exif);
// // foreach ($exif as $key => $section) {
// //     echo "$key";
// //     foreach ($section as $name => $val) {
// //         echo "$key.$name: $val\n";
// //     }
// // }
