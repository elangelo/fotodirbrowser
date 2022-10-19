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
                        $image = Vips\Image::newFromFile($fullfile);
                        $image->

                        echo $fullfile . '  width: ' . $image->width . '  height: ' . $image->height . "\torientation: " . $image->get('orientation') . "\n";
                    }
                }
            }
        }
    }

    $dircollection->insertOne(['dirname' => $dir]);
}
closedir($handle);



// $result = $collection->insertOne(['name' => 'Hinterland', 'brewery' => 'BrewDog']);

// echo "Inserted with Object ID '{$result->getInsertedId()}'";
?>