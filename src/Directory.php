<?php
class Directory extends Media
{
    private readonly bool $scanned;

    public static function withDirAndFilename($directoryName, $fileName)
    {
        $instance = new self();
        $instance->scanned = false;
        return $instance;
    }
}
