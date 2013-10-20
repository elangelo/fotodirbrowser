<?php
  include("includes.inc");

  $debug = false;
  $path = htmlspecialchars($_GET['fileLocation']);
  $path = str_replace("_*_", "&", $path);
  $path = $baseDir . '/' . $path;

  $thumbnail = exif_thumbnail($path, $width, $height, $type);

  if ($thumbnail!==false) 
  {
    if (!$debug)
    {
      header('Content-type: ' .image_type_to_mime_type($type) . ''); 
    }
    $exif = exif_read_data($path);
    $orientation = $exif['Orientation'];
    switch ($orientation)
    {
      case 1:
        echo $thumbnail;
        break;
      case 2:
        echo $thumbnail;
        break;
      case 4: 
        echo $thumbnail;
        break;
      case 3:
        $image = imagecreatefromstring($thumbnail);
        imagejpeg(imagerotate($image,180,0));
        break;
      case 5: 
        $image = imagecreatefromstring($thumbnail);
        imagejpeg(imagerotate($image,-90,0));
        break;
      case 6:
        $image = imagecreatefromstring($thumbnail);
        imagejpeg(imagerotate($image,-90,0));
        break;
      case 7:
        $image = imagecreatefromstring($thumbnail);
        imagejpeg(imagerotate($image,-90,0));
        break;
      case 8:
        $image = imagecreatefromstring($thumbnail);
        imagejpeg(imagerotate($image,90,0));
        break;
    }
  } 
  else 
  {
     // no thumbnail available, handle the error here                                
     echo 'No thumbnail available';
  }
?>
~                                         
