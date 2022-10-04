<?php
class Media
{
    public readonly string $directory;
    public readonly string $fullFilename;
    public readonly string $fileName;
    public readonly string $extension;
    public readonly string $type;
    public readonly string $saveFilename;

    public function __construct(string $directory, string $fileName)
    {
        $this->directory = $directory;
        $this->fileName = $fileName;

        $this->fullFilename = $directory . '/' . $fileName;
        $tmp = explode('.', $this->fileName);
        $this->extension = strtolower(end($tmp));

        $this->saveFilename = str_replace("&", "_*_", $this->fullFilename);

        switch ($this->extension) {
            case "mp4":
                $this->type = 'video';
                break;
            case "jpg":
                $this->type = 'image';
                break;
            default:
                break;
        }
    }

    /* This is the static comparing function: */
    static function cmp_obj($a, $b)
    {
        return strtolower($a->fileName) <=> strtolower($b->fileName);
    }
}
