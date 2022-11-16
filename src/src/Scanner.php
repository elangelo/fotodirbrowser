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


Dal::waitUntilOnline(getenv('MONGO_URL'));

$dal = new Dal();
// $dal->waitUntilOnline();
if ($dal->mediaCollectionExists()) {
    printf("Db exists already don't know how to update....");
    exit();
}

if (array_key_exists("dropdb", $options)  && !$options["dropdb"]) {
    print "Dropping db\r\n";
    $dal->drop();
}

$baseDir = getenv('BASEDIR');
$thumbDir = getenv('THUMBDIR');

if ($handle = opendir($baseDir)) {
    // $root[] = Media::withAbsoluteDirAndFilename($baseDir, '/');
    // $dal->insertRecords($root);
    getChildren($baseDir, $dal);
}

closedir($handle);

function getChildren($dir, $dal)
{
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
    if (is_array($files)) {
        $dal->insertRecords($files);

        foreach ($files as $child) {
            if ($child->type == 'folder') {
                $files[] = getChildren($child->fullPath, $dal);
            }
        }
    }
}
