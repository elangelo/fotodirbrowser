<?php
class Dimension {
    public readonly int $width;
    public readonly int $height;
    public readonly string $orientation; /*HORIZONTAL or VERTICAL*/

    public function __construct(int $width, int $height, string $orientation)
    {
        $this->width = $width;
        $this->height = $height;
        $this->orientation = $orientation;
    }
}
?>