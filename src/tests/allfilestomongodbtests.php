#!/usr/bin/env php

<?php
require __DIR__ . '/../../vendor/autoload.php';

use Jcupitt\Vips;

$user = "root";
$pwd = 'example';

$client = new MongoDB\Client("mongodb://${user}:${pwd}@localhost:27017");
$dircollection = $client->fotodir->dirs;
$filecollection = $client->fotodir->files;

$dir = __DIR__ . '/../../docs/example/2020/2020-09-17';
//check if we scanned this directory before
$existsalready = $dircollection->count(['dirname' => $dir]);
if ($existsalready == 0) {
    // scan files.
    if ($handle = opendir($dir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                $fullfile = $dir . '/' . $file;
                if (is_dir($fullfile)) {
                } else {
                    $tmp = explode('.', $file);
                    $extension = strtoupper(end($tmp));
                    if ($extension == "JPG") {
                        $cursor = $filecollection->find(['path' => $fullfile]);
                        if (!$cursor->isDead()) {
                            // var_dump($cursor);
                            $record = $cursor->toArray()[0];
                            var_dump($record);
                        } else {
                            $exif_properties = ["FileDateTime", "MimeType", "FileSize", "Make", "ImageWidth", "ImageLength", "Model", "Orientation", "ExposureTime", "ISOSpeedRatings", "ShutterSpeedValue", "ApertureValue", "LightSource", "Flash", "FocalLengthIn35mmFilm"];
                            $exif = exif_read_data($fullfile);
                            $metadata = array();
                            foreach ($exif_properties as $key) {
                                $metadata[$key] = $exif[$key];
                            }
                            $record = [
                                'filename' => $file,
                                'path' => $fullfile,
                                'md5sum' => '',
                                'metadata' => $metadata,
                                'size' => $metadata["FileSize"],
                                'date' => $metadata["FileDateTime"],
                                'deleted' => false
                            ];

                            var_dump($record);

                            $filecollection->insertOne($record);
                        }
                    }
                }
            }
        }
        closedir($handle);
    }

    $dircollection->insertOne(['dirname' => $dir]);
}




// $result = $collection->insertOne(['name' => 'Hinterland', 'brewery' => 'BrewDog']);

// echo "Inserted with Object ID '{$result->getInsertedId()}'";
?>