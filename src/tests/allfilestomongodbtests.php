#!/usr/bin/env php

<?php
require __DIR__ . '/../../vendor/autoload.php';

use FFMpeg\FFProbe;
use Jcupitt\Vips;

$user = "root";
$pwd = 'example';

$client = new MongoDB\Client("mongodb://${user}:${pwd}@localhost:27017");
$dircollection = $client->fotodir->dirs;
$filecollection = $client->fotodir->files;

$dir = __DIR__ . '/../../docs/example/2020/2020-09-17';
//check if we scanned this directory before
$existsalready = $dircollection->count(['dirname' => $dir]);
// if ($existsalready == 0) {
// scan files.
if ($handle = opendir($dir)) {
    $records = array();
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != "..") {
            $fullfile = $dir . '/' . $file;
            if (is_dir($fullfile)) {
                $record = ([
                    'filename' => $file,
                    'path' => $fullfile,
                    'type' => 'directory',
                    'deleted' => false
                ]);
                $records[] = $record;
            } else {
                $tmp = explode('.', $file);
                $extension = strtoupper(end($tmp));
                if ($extension == "JPG") {
                    $cursor = $filecollection->find(['path' => $fullfile]);
                    if (!$cursor->isDead()) {
                        // var_dump($cursor);
                        $record = $cursor->toArray()[0];
                        // var_dump($record);
                    } else {
                        $exif_properties = ["FileDateTime", "MimeType", "FileSize", "Make", "ImageWidth", "ImageLength", "Model", "Orientation", "ExposureTime", "ISOSpeedRatings", "ShutterSpeedValue", "ApertureValue", "LightSource", "Flash", "FocalLengthIn35mmFilm"];
                        $exif = exif_read_data($fullfile);
                        $metadata = array();
                        foreach ($exif_properties as $key) {
                            $metadata[$key] = $exif[$key];
                        }
                        $record = ([
                            'filename' => $file,
                            'path' => $fullfile,
                            'type' => 'file',
                            'md5sum' => '',
                            'metadata' => $metadata,
                            'size' => $metadata["FileSize"],
                            'date' => $metadata["FileDateTime"],
                            'deleted' => false
                        ]);
                        $records[] = $record;
                        // var_dump($record);

                        // $filecollection->insertOne($record);
                    }
                } else if ($extension = 'MP4') {
                    $ffprobe = FFProbe::create();
                    $videoinfo = $ffprobe->format($fullfile);
                    // var_dump($videoinfo);
                    $duration =  (double)$videoinfo->get('duration');
                    $tags = $videoinfo->get('tags');
                    $datetime = $tags['creation_time'];
                    $unixtime = strtotime($datetime);
                    $streams = $ffprobe->streams($fullfile);
                    $videostream = $streams->videos()->first();
                    $audiostream = $streams->audios()->first();
                    $videoprops = ["codec_name", "width", "height", "display_aspect_ratio", "avg_frame_rate", "duration"];
                    $videometadata = array();
                    foreach ($videoprops as $prop) {
                        $videometadata[$prop] = $videostream->get($prop);
                    }

                    $audioprops = ["codec_name", "sample_rate", "channels", "duration", "bit_rate"];
                    $audiometadata = array();
                    foreach ($audioprops as $prop) {
                        $audiometadata[$prop] = $audiostream->get($prop);
                    }

                    $metadata = ([
                        'duration' => $duration,
                        'video' => $videometadata,
                        'audio' => $audiometadata
                    ]);

                    $record = ([
                        'filename' => $file,
                        'path' => $fullfile,
                        'type' => 'file',
                        'md5sum' => '',
                        'metadata' => $metadata,
                        'size' => filesize($fullfile),
                        'date' => $unixtime,
                        'deleted' => false
                    ]);

                    $records[] = $record;
                }
            }
        }
    }
    // var_dump($records);
    if (count($records) > 0) {
        $filecollection->insertMany($records);
    }
    closedir($handle);
}

$dircollection->insertOne(['dirname' => $dir]);
// } else {
//     echo "directory ${dir} exists already\n";
// }




// $result = $collection->insertOne(['name' => 'Hinterland', 'brewery' => 'BrewDog']);

// echo "Inserted with Object ID '{$result->getInsertedId()}'";
?>