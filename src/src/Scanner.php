#!/usr/bin/env php

<?php
$shortopts = "";
$longopts = array(
    "dropdb"
);

$options = getopt($shortopts, $longopts);
// var_dump($options);

require __DIR__ . '/../vendor/autoload.php';

require_once('Dal.php');
require_once('Media.php');
require_once('DirWatcher.php');

Dal::waitUntilOnline(getenv('MONGO_URL'));

$dal = new Dal();
// if ($dal->mediaCollectionExists()) {
//     printf("Db exists already don't know how to update....");
//     exit();
// }

if (array_key_exists("dropdb", $options)  && !$options["dropdb"]) {
    print "Dropping db\r\n";
    $dal->drop();
}

if (!$dal->mediaCollectionExists()) {
    $baseDir = getenv('BASEDIR');
    $thumbDir = getenv('THUMBDIR');

    if ($handle = opendir($baseDir)) {
        getChildren($baseDir, $dal);
    }

    closedir($handle);
}

function getChildren($dir, $dal)
{
    echo "scanning directory: ${dir}\r\n";
    if ($handle = opendir($dir)) {
        while (false !== ($file = readdir($handle))) {
            if ($file != "." && $file != "..") {
                $media = Media::withAbsoluteDirAndFilename($dir, $file);
                if ($media != null) {
                    $files[] = $media;
                }
            }
        }
    }
    closedir($handle);
    if ($files != null && is_array($files)) {
        $dal->insertRecords($files);

        foreach ($files as $child) {
            if ($child->type == 'folder') {
                $files[] = getChildren($child->fullPath, $dal);
            }
        }
    }
}

function addFileToMongodb($event, $fullpath)
{
    require_once('Dal.php');
    $dal = new Dal();
    $pathinfo = pathinfo($fullpath);
    $pathinfo = pathinfo($fullpath);
    $dir = $pathinfo['dirname'];
    $file = $pathinfo['filename'];
    $media = Media::withAbsoluteDirAndFilename($dir, $file);
    if ($media != null) {
        $files[] = $media;
        $dal->insertRecords($files);
    }
}
echo ("starting watcher\n");

$dirwatcher = new DirWatcher();
$dirwatcher->add($baseDir);
$dirwatcher->wait("addFileToMongodb");

echo ("done...\n");
