<?php
class DirWatcher
{
    private $fd;
    private $dirs;
    function __construct()
    {
        $this->fd = inotify_init();
        $this->dirs = array();
    }

    function add($dirname)
    {
        if (is_dir($dirname)) {
            if (substr($dirname, -1) != DIRECTORY_SEPARATOR) {
                $dirname = $dirname . DIRECTORY_SEPARATOR;
            }
            // $watch_descriptor = inotify_add_watch($this->fd, $dirname, IN_ALL_EVENTS);
            // IN_MOVED_TO: last step of rsync that creates final file
            // IN_CLOSE_WRITE: if you just use cp file to target/
            // IN_CREATE: detect creation of directories so we can add them to the watcher
            $watch_descriptor = inotify_add_watch($this->fd, $dirname, IN_MOVED_TO | IN_CLOSE_WRITE | IN_CREATE);
            $this->dirs += array($watch_descriptor => $dirname);

            $handle = opendir($dirname);
            while (FALSE !== ($entry = readdir($handle))) {
                if ($entry == '.' || $entry == '..') {
                    continue;
                }

                $entryfullpath = $dirname . $entry;
                if (is_dir($entryfullpath)) {
                    $this->add($entryfullpath);
                }
            }
            closedir($handle);
        }
    }

    function wait(callable $function)
    {
        while (true) {
            if (false !== ($events = inotify_read($this->fd))) {
                foreach ($events as $event) {
                    if ($event['name'] != "") {
                        $fullpath = $this->dirs[$event['wd']] . $event['name'];
                    } else {
                        $fullpath = $this->dirs[$event['wd']];
                    }
                    $dirname = $this->dirs[$event['wd']];
                    $filename = $event['name'];

                    if ($event['mask'] & IN_CREATE) {
                        if (is_dir($fullpath)) {
                            echo "adding new created directory " . $fullpath . " to watch\n";
                            $this->add($fullpath);
                            $function($event['mask'], $dirname, $filename);
                        }
                    } else if ($event['mask'] & IN_CLOSE_WRITE || $event['mask'] & IN_MOVED_TO) {
                        $function($event['mask'], $dirname, $filename);
                    }
                }
            }
        }
    }
}
