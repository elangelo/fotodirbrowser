<?php
require __DIR__ . '/../vendor/autoload.php';


//documentation: https://www.mongodb.com/docs/php-library/current/reference/method/MongoDBCollection-deleteMany/

class Dal
{
    public readonly MongoDB\Client $client;
    private readonly MongoDB\Collection $mediacollection;
    private readonly string $dbname;

    public function __construct()
    {
        $mongourl = getenv('MONGO_URL');
        $this->dbname = getenv('MONGO_DB');
        $this->client = new MongoDB\Client($mongourl);
        $this->mediacollection = $this->client->{$this->dbname}->media;
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
        var_dump($dbnames);
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
        $existsalready = $this->mediacollection->countDocuments(['relativePath' => $directoryName]);
        return $existsalready > 0;
    }

    public function getMediaForDirectory($directoryName)
    {
        return $this->mediacollection->find(['directoryName' => $directoryName])->toArray();
    }

    public function insertRecords($media)
    {
        $this->mediacollection->insertMany($media);
    }

    public function setScanned($directoryName)
    {
        $this->mediacollection->updateOne(['relativePath' => $directoryName], ['$set' => ['scanned' => 'true']]);
    }

    public function delete($filter)
    {
        $this->mediacollection->deleteMany($filter);
    }

    public function drop()
    {
        $this->mediacollection->drop();
    }
}
