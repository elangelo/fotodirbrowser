<?php

// function resizeThumb($thumb, $old_width, $old_height, $new_width, $new_height)
// {
//     $thumbnail = imagecreatefromstring($thumb);
//     return resizeImage($thumbnail, $old_width, $old_height, $new_width, $new_height);
// }

// function resizeImage($image, $old_width, $old_height, $new_width, $new_height)
// {
//     $newThumb = imagecreatetruecolor($new_width, $new_height);
//     imagecopyresampled($newThumb, $image, 0, 0, 0, 0, $new_width, $new_height, $old_width, $old_height);
//     return imagejpeg($newThumb);
// }

function trace($message)
{
    $debug = false;
    //$debug = true;
    if ($debug) {
        print $message . "<br/>";
    }
}

function getSize($imagePath)
{
    include "includes.inc";
    $fileLocation = $imagePath;
    trace("fileLocation: " . $fileLocation);
    $picBaseDir = $baseDir;
    $fullImagePath = $picBaseDir . '/' . $fileLocation;
    $imageinfo = getimagesize($fullImagePath);

    return [
        'x' => $imageinfo[0],
        'y' => $imageinfo[1],
    ];
}

function resizeImageFromPath($imagePath, $maxDimension)
{
    include "includes.inc";
    $size = $maxDimension;
    $fileLocation = $imagePath;
    trace("fileLocation: " . $fileLocation);
    trace("maxDimension: " . $maxDimension);
    $picBaseDir = $baseDir;
    $fullImagePath = $picBaseDir . '/' . $fileLocation;
    if ($size == 0) {
        return $fullImagePath;
    } else {
        $thumbBaseDir = $thumbBaseDir . '/' . $size . '/';
        $fullThumbPath = $thumbBaseDir . $fileLocation;
        $fileNameArray = explode('/', $fileLocation);
        $relativePath = '';
        for ($i = 0; $i < sizeof($fileNameArray) - 1; $i++) {
            $relativePath = $relativePath . $fileNameArray[$i] . '/';
        }

        $fileName = $fileNameArray[sizeof($fileNameArray) - 1];

        if (!file_exists($thumbBaseDir . '/' . $relativePath)) {
            mkdir($thumbBaseDir . '/' . $relativePath, 0770, true);
        }

        if (!file_exists($fullThumbPath)) {
            trace($fullThumbPath . "does not exist, make it");
            trace("full image path: " . $fullImagePath);

            $image_info = getimagesize($fullImagePath);

            $image_type = $image_info[2];
            $exif = exif_read_data($fullImagePath);
            $orientation = $exif['Orientation'];
            $image = imagecreatefromjpeg($fullImagePath);
            $oWidth = imagesx($image);
            $oHeight = imagesy($image);

            if ($oWidth > $oHeight) {
                $ratio = $oHeight / $oWidth;
                $newWidth = $size;
                $newHeight = $ratio * $newWidth;
            } else {
                $ratio = $oWidth / $oHeight;
                $newHeight = $size;
                $newWidth = $ratio * $newHeight;
            }
            trace($newWidth . " " . $newHeight);
            $newImage = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $oWidth, $oHeight);
            switch ($orientation) {
                case 1:
                    break;
                case 2:
                    break;
                case 3:
                    $newImage = imagerotate($newImage, 180, 0);
                    break;
                case 4:
                    break;
                case 5:
                    $newImage = imagerotate($newImage, -90, 0);
                    break;
                case 6:
                    $newImage = imagerotate($newImage, -90, 0);
                    break;
                case 7:
                    $newImage = imagerotate($newImage, -90, 0);
                    break;
                case 8:
                    $newImage = imagerotate($newImage, 90, 0);
                    break;
            }
            imagejpeg($newImage, $fullThumbPath, 75);
        }
        return $fullThumbPath;
    }
}
