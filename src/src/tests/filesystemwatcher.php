#!/usr/bin/env php
<?php

// https://github.com/mkraemer/react-inotify/blob/master/src/MKraemer/ReactInotify/Inotify.php


class FileSystemWatcher
{
    protected string $filePath;
    protected int $eventid;
    protected bool $recurse;
    protected $inotifyHandler;
    public $watchDescriptors = array();

    public function __construct($filePath, $eventid, $recurse)
    {
        $this->filePath = $filePath;
        $this->eventid = $eventid;
        $this->recurse = $recurse;
        $this->inotifyHandler = inotify_init();

        if ($recurse) {
            $this->recursive_add($filePath);
        } else {
            $this->add($filePath);
        }
    }

    public function execute(callable $function)
    {
        while (true) {
            echo "in while loop";
            if (false !== ($events = inotify_read($this->inotifyHandler))) {
                foreach ($events as $event) {
                    var_dump($event);
                    // make sure the watch descriptor assigned to this event is
                    // still valid. removing watch descriptors via 'remove()'
                    // implicitly sends a final event with mask IN_IGNORE set:
                    // http://php.net/manual/en/inotify.constants.php#constant.in-ignored
                    if (isset($this->watchDescriptors[$event['wd']])) {
                        $path = $this->watchDescriptors[$event['wd']]['path'];
                        $function($event['mask'], array($path . $event['name']));
                    }
                }
            }
        }
    }

    private function recursive_add($directory)
    {
        if (is_dir($directory)) {
            $this->add($directory);
            $handle = opendir($directory);
            while (FALSE !== ($entry = readdir($handle))) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }
                $Entry = $directory . "/" . $entry;
                if (is_dir($Entry)) {
                    $this->recursive_add($Entry);
                }
            }
            closedir($handle);
        }
    }

    private function add($path)
    {
        $descriptor = inotify_add_watch($this->inotifyHandler, $path, $this->eventid);
        $this->watchDescriptors[$descriptor] = array('path' => $path);
        return $descriptor;
    }
}
$eventid = 256; //IN_CREATE
$basedir = "/home/samuel/source/fotodirbrowser/docs/example";

$filewatcher = new FileSystemWatcher($basedir, $eventid, true);
var_dump($filewatcher->watchDescriptors);

function megaprint($first, $second)
{
    echo "got an event!";
    var_dump($first);
    var_dump($second);
}

$filewatcher->execute("megaprint");
?>