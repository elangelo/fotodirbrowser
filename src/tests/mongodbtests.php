#!/usr/bin/env php

<?php
require __DIR__ . '/../../vendor/autoload.php';
$user = "root";
$pwd = 'example';

$client = new MongoDB\Client("mongodb://${user}:${pwd}@localhost:27017");
$collection = $client->demo->beers;

$result = $collection->insertOne( [ 'name' => 'Hinterland', 'brewery' => 'BrewDog' ] );

echo "Inserted with Object ID '{$result->getInsertedId()}'";
?>