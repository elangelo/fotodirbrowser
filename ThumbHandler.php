<?php

// include "includes.inc";
// include "functions.php";

// $debug = false;
// //$debug = true;

// $path = htmlspecialchars($_GET['fileLocation']);
// $path = str_replace("_*_", "&", $path);
// $relativePath = $path;
// $path = $baseDir . '/' . $path;

// $image = imagecreatefromjpeg($path);
// $width = imagesx($image);
// $height = imagesy($image);

// $exif = exif_read_data($path);
// $orientation = $exif['Orientation'];

// if (!$debug) {
//     header('Content-Type: image/jpeg');
// } else {
//     echo "thumbwidth: " . $width . "<br/>";
//     echo "thumbheight: " . $height . "<br/>";
//     echo "orientation:" . $orientation . "<br/>";
// }

// $sqThumb = imagecreatetruecolor($thumbSize, $thumbSize);

// switch ($orientation) {
//     case 1:
//     case 2:
//     case 4:
//         $src_X = ceil(($width - $height) / 2);
//         imagecopyresized($sqThumb, $image, 0, 0, $src_X, 0, $thumbSize, $thumbSize, $height, $height);
//         imagejpeg($sqThumb);
//         imagedestroy($image);
//         imagedestroy($sqThumb);
//         break;
//     case 3:
//         $image = imagerotate($image, 180, 0);
//         $src_X = ceil(($width - $height) / 2);
//         imagecopyresized($sqThumb, $image, 0, 0, $src_X, 0, $thumbSize, $thumbSize, $height, $height);
//         imagejpeg($sqThumb);
//         imagedestroy($image);
//         imagedestroy($sqThumb);
//         break;
//     case 5:
//     case 6:
//     case 7:
//         $image = imagerotate($image, 270, 0);
//         $Bwidth = $width;
//         $width = $height;
//         $height = $Bwidth;
//         $src_Y = ceil(($height - $width) / 2);
//         imagecopyresized($sqThumb, $image, 0, 0, 0, $src_Y, $thumbSize, $thumbSize, $width, $width);
//         imagejpeg($sqThumb);
//         imagedestroy($image);
//         imagedestroy($sqThumb);
//         break;
//     case 8:
//         $image = imagerotate($image, 90, 0);
//         $width = imagesx($image);
//         $height = imagesy($image);
//         $src_Y = ceil(($height - $width) / 2);
//         imagecopyresized($sqThumb, $image, 0, 0, 0, $src_Y, $thumbSize, $thumbSize, $width, $width);
//         imagedestroy($image);
//         imagejpeg($sqThumb);
//         imagedestroy($sqThumb);
//         break;

