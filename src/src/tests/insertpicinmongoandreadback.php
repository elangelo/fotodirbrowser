#!/usr/bin/env php


<?php
require __DIR__ . '/../../vendor/autoload.php';
include __DIR__ . '/../Dal.php';
include __DIR__ . '/../Media.php';

$file1= '/home/samuel/source/fotodirbrowser/docs/example/2020/2020-09-17/IMG_20200917_141104.jpg';

$media = Media::withAbsoluteDirAndFilename('/home/samuel/source/fotodirbrowser/docs/example/2020/2020-09-17','IMG_20200917_141104.jpg');

$dal = new Dal();
$records = array();
$records[] = $media;

$dal->insertRecords($records);

$read = $dal->getMedia($file1);
var_dump ($read);
?>