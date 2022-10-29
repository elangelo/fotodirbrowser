<?php
require __DIR__ . '/../vendor/autoload.php';

class Dal
{
    private $user = "root";
    private $pwd = "example";

    public readonly MongoDB\Client $client;
    private readonly MongoDB\Collection $dircollection;
    private readonly MongoDB\Collection $filecollection;

    public function __construct()
    {
        $user = $this->user;
        $pwd = $this->pwd;
        $this->client = new MongoDB\Client("mongodb://${user}:${pwd}@localhost:27017");
        $this->dircollection = $this->client->fotodir->dirs;
        $this->filecollection = $this->client->fotodir->files;
    }
    public function dirScanned($dirpath)
    {
        $existsalready = $this->dircollection->countDocuments(['dirname' => $dirpath]);
        return $existsalready > 0;
    }

    public function getRecord($filepath)
    {
        return $this->filecollection->findOne(['path' => $filepath]);
    }

    public function insertRecords($records)
    {
        $this->filecollection->insertMany($records);
    }

}
