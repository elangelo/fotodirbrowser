<?php
  include("includes.inc");
  include("functions.php");

  $debug = false;

  $path = htmlspecialchars($_GET['fileLocation']);
  $path = str_replace("_*_", "&", $path);
  $relativePath = $path;
  $path = $baseDir . '/' . $path;

  $thumbnail = exif_thumbnail($path, $width, $height);

  $needtoresize= ($width > $thumbSize || $height > $thumbSize);

  if ($thumbnail!==false) 
  {
    if (! $debug)
    {
      header('Content-type: ' .image_type_to_mime_type($type) . ''); 
    }
    $exif = exif_read_data($path);
    $orientation = $exif['Orientation'];

    if( $debug )
    {
      echo "thumbwidth: " . $width  . "<br/>" ;
      echo "thumbheight: " . $height. "<br/>" ;
      echo "orientation:" . $orientation . "<br/>";
      echo "needtoresiz:" . $needtoresize . "<br/>";
      echo "original:<br/>" . $thumbnail . "<br/>";
    }

    switch ($orientation)
    {
      case 1:
        if (! $needtoresize )
        {
          echo $thumbnail;
        }
        else
        {
          $dst_width = $thumbSize;
          $dst_height = round($thumbSize * $height / $width);

          echo resizeThumb($thumbnail, $width, $height, $dst_width, $dst_height);
        }
        break;
      case 2:
        if (! $needtoresize )
        {
          echo $thumbnail;
        }
        else
        {
          $dst_width = $thumbSize;
          $dst_height = round($thumbSize * $height / $width);

          echo resizeThumb($thumbnail, $width, $height, $dst_width, $dst_height);
        }
        break;
      case 4: 
        if (! $needtoresize )
        {
          echo $thumbnail;
        }
        else
        {
          $dst_width = $thumbSize;
          $dst_height = round($thumbSize * $height / $width);

          echo resizeThumb($thumbnail, $width, $height, $dst_width, $dst_height);
        }
        break;
      case 3:
        $image = imagecreatefromstring($thumbnail);
        if (! $needtoresize )
        {
          imagejpeg(imagerotate($image,180,0));
        }
        else
        {
          $dst_height = $thumbSize;
          $dst_width = round($thumbSize * $width / $height);
          echo resizeThumb($image, $width, $height, $dst_width, $dst_height);
        }
        break;
      case 5: 
        $image = imagecreatefromstring($thumbnail);
        if (! $needtoresize )
        {
          imagejpeg(imagerotate($image,-90,0));
        }
        else
        {
          $dst_height = $thumbSize;
          $dst_width = round($thumbSize * $width / $height);
          echo resizeThumb($image, $width, $height, $dst_width, $dst_height);
        }
        break;
      case 6:
        $image = imagecreatefromstring($thumbnail);
        $image = imagerotate($image, -90, 0);
        if (! $needtoresize )
        {
          imagejpeg($image);
        }
        else
        {
           $dst_height = $thumbSize;
           $dst_width = round($thumbSize * $height / $width);
           
           echo resizeImage($image, $height, $width, $dst_width, $dst_height);
        }
        break;
      case 7:
        $image = imagecreatefromstring($thumbnail);
        $image = imagerotate($image, -90, 0);
        if (! $needtoresize )
        {
          imagejpeg($image);
        }
        else
        {
           $dst_height = $thumbSize;
           $dst_width = round($thumbSize * $height / $width);
           
           echo resizeImage($image, $height, $width, $dst_width, $dst_height);
        }
        break;
      case 8:
        $image = imagecreatefromstring($thumbnail);
        $image = imagerotate($image,90,0);
        if (! $needtoresize )
        {
          imagejpeg($image);
        }
        else
        {
           $dst_height = $thumbSize;
           $dst_width = round($thumbSize * $height / $width);
           
           echo resizeImage($image, $height, $width, $dst_width, $dst_height);
        }
    }
  } 
  else 
  {
    trace("path: " . $relativePath);
    trace("size: " . $thumbSize);
    $resizedImage = resizeImageFromPath($relativePath, $thumbSize);
    readfile($resizedImage);
    exit;
     // no thumbnail available, handle the error here                                
     //echo 'No thumbnail available';
  }
?>
~                                         
