<?php
require __DIR__ . '/../vendor/autoload.php';


//documentation: https://www.mongodb.com/docs/php-library/current/reference/method/MongoDBCollection-deleteMany/

class Dal
{
    public readonly MongoDB\Client $client;
    private readonly MongoDB\Collection $mediacollection;

    public function __construct()
    {
        $user = $_ENV['MONGO_USER'];
        $pwd = $_ENV['MONGO_PASSWORD'];
        $host = $_ENV['MONGO_HOST'];
        $db = $_ENV['MONGO_DB'];
        $this->client = new MongoDB\Client("mongodb://${user}:${pwd}@$host:27017");
        $this->mediacollection = $this->client->{$db}->media;

        // $this->mediacollection = $this->client->fotodir->media;
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
        // $this->mediacollection->insertMany($media);
        $this->mediacollection->updateMany($media, $media, ['upsert' => true]);
    }

    public function setScanned($directoryName)
    {
        $this->mediacollection->updateOne(['relativePath' => $directoryName], ['$set' => ['scanned' => 'true']]);
    }

    public function delete($filter)
    {
        $this->mediacollection->deleteMany($filter);
    }
}
