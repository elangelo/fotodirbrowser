<?php
require __DIR__ . '/../vendor/autoload.php';


//documentation: https://www.mongodb.com/docs/php-library/current/reference/method/MongoDBCollection-deleteMany/

class Dal
{
    public readonly MongoDB\Client $client;
    private readonly MongoDB\Collection $mediacollection;

    public function __construct()
    {
        $mongourl = getenv('MONGO_URL');
        printf($mongourl);
        $db = getenv('MONGO_DB');
        $this->client = new MongoDB\Client($mongourl);
        $this->mediacollection = $this->client->{$db}->media;
    }
    public function dirScanned($directoryName)
    {
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
