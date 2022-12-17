<?php

use MongoDB\Operation\Count;

require __DIR__ . '/../vendor/autoload.php';
require_once('functions.php');

//documentation: https://www.mongodb.com/docs/php-library/current/reference/method/MongoDBCollection-deleteMany/

class Dal
{
    public readonly MongoDB\Client $client;
    private readonly MongoDB\Collection $mediacollection;
    private readonly MongoDB\Collection $logscollection;
    private readonly string $dbname;

    public function __construct()
    {
        $mongourl = getenv('MONGO_URL');
        $this->dbname = getenv('MONGO_DB');
        $this->client = new MongoDB\Client($mongourl);
        $this->mediacollection = $this->client->{$this->dbname}->media;
        $this->logscollection = $this->client->{$this->dbname}->logs;
    }

    public static function waitUntilOnline($mongourl)
    {
        printf("wating until mongo is online\r\n");
        $i = 0;
        while ($i < 100) {
            $i++;
            printf($i . "\r\n");
            try {
                $client = new MongoDB\Client($mongourl);
                $dbs = iterator_to_array($client->listDatabases());
                printf("connection established after " . $i . " tries\r\n");
                break;
            } catch (Exception $ex) {
                sleep(5);
            }
        }
    }

    public function mediaCollectionExists()
    {
        $dbs = $this->client->listDatabases();
        $dbnames = iterator_to_array($dbs);
        // var_dump($dbnames);
        foreach ($dbnames as $db) {
            if ($db['name'] == $this->dbname) {
                return true;
            }
        }
        return false;
    }

    public function dirScanned($directoryName)
    {
        if ($directoryName == "/") {
            return true;
        }
        $existsalready = $this->mediacollection->countDocuments(['relativePath' => $directoryName, 'deleted' => false]);
        return $existsalready > 0;
    }

    public function getPreviousAndNextDirectory($directoryName)
    {
        $currentDirectory = $this->mediacollection->findOne(['relativePath' => $directoryName]);
        $parentDirectory = $currentDirectory->directoryName;
        $currentDirectory = $currentDirectory->fileName;

        $filter = ['directoryName' => $parentDirectory, 'type' => 'folder', 'deleted' => false];

        $options = ['sort' => ['fileName' => 1], 'projection' => ['fileName' => true, 'relativePath' => true, '_id' => false]];

        $siblings = $this->mediacollection->find($filter, $options)->toArray();
        $indexOfCurrentDir = $this->getIndexInSortedArray($siblings, 'fileName', $currentDirectory);
        // var_dump($indexOfCurrentDir);
        $previousDir = "";
        $nextDir = "";
        if ($indexOfCurrentDir > 0) {
            $previousDir = ($siblings[$indexOfCurrentDir - 1])->relativePath;
        }
        if ($indexOfCurrentDir + 1 < sizeof($siblings)) {
            $nextDir = ($siblings[$indexOfCurrentDir + 1])->relativePath;
        }
        return [$previousDir, $nextDir];
    }

    private function getIndexInSortedArray($array, $prop, $searchedValue)
    {
        for ($i = 0; $i < sizeof($array); $i++) {
            $currentItem = $array[$i];
            if ($currentItem[$prop] == $searchedValue) {
                return $i;
            }
        }
    }

    public function getMediaForDirectory($directoryName)
    {
        return $this->mediacollection->find(['directoryName' => $directoryName, 'deleted' => false])->toArray();
    }

    public function getMedia($fileName)
    {
        return $this->mediacollection->findOne(['fullPath' => $fileName], ['showRecordId' => true]);
    }

    public function insertRecords($media)
    {
        $this->mediacollection->insertMany($media);
    }

    public function setScanned($directoryName)
    {
        $this->mediacollection->updateOne(['relativePath' => $directoryName], ['$set' => ['scanned' => 'true']]);
    }

    public function delete($fileLocation)
    {
        file_put_contents('php://stdout', "deleting $fileLocation\n");
        //protection against eternal loop...
        $media = $this->getMedia($fileLocation);
        if ($media != null) {
            $this->mediacollection->updateOne(
                ['fullPath' => $fileLocation],
                ['$set' => ['deleted' => true]]
            );

            $spltInfo = new SplFileInfo($fileLocation);
            $folder = $spltInfo->getPath();
            $relativeFolder = "/" . relativePath(getMediaDir(), $folder);
            file_put_contents('php://stdout', "get item count for folder $relativeFolder\n");
            $itemsInFolder = $this->getMediaForDirectory($relativeFolder);
            $count = count($itemsInFolder);
            file_put_contents('php://stdout', "get item count for folder $relativeFolder: $count\n");
            if ($count == 0) {
                file_put_contents('php://stdout', "deleting folder $folder\n");
                $this->delete($folder);
            }

            $this->logscollection->insertOne(
                [
                    'date' => time(),
                    'path' => $fileLocation,
                    'operation' => 'DELETE'
                ]
            );
        }
    }

    public function drop()
    {
        $this->mediacollection->drop();
    }
}
