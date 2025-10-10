#!/usr/bin/env php


<?php
require __DIR__ . '/../../vendor/autoload.php';

include '../Media.php';
include '../Dal.php';

$_ENV['MEDIADIR'] = '/home/samuel/source/fotodirbrowser/docs/example';
$_ENV['MONGO_HOST'] = 'localhost';
$_ENV['MONGO_PASSWORD'] = 'example';
$_ENV['MONGO_USER'] = 'root';
$_ENV['MONGO_DB'] = 'test';

$test = Media::withRelativeDirAndFilename("2020/2020-09-17", "IMG_20200917_141104.jpg");
print("Original test object \r\n");
var_dump($test);
$jsonmetadata = json_encode($test->metadata);

print("json : $jsonmetadata \r\n\r\n");
$base64encoded = base64_encode($jsonmetadata);
print("base64: $base64encoded \r\n\r\n");


$serialized = MongoDB\BSON\fromPHP($test);
print("Serialized test object \r\n");
var_dump($serialized);

print("\r\n\r\n");

$unserialized = MongoDB\BSON\toPHP($serialized);
print("Unserialized test object \r\n");
var_dump($unserialized);

// $dal = new Dal();
// $dal->delete(['fileName' => 'IMG_20200917_141104.jpg']);

// $dal->insertRecords([$test]);

// $result = $dal->getMediaForDirectory("2020/2020-09-17");
// var_dump($result);
