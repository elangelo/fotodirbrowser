<?php
  include("includes.inc");

  $debug = false;

  $fileLocation = htmlspecialchars($_GET['fileLocation']);
  $fileLocation = str_replace("_*_", "&", $fileLocation);

  $size = (int)$_GET['size'];
  if (!$debug){header('Content-type: image/jpeg');}
  $picBaseDir = $baseDir;

  $thumbBaseDir = "/mnt/raid/.pictures" . '/' . $size . '/';
  $fullImagePath = $picBaseDir . '/' . $fileLocation;
  $fullThumbPath = $thumbBaseDir . $fileLocation;

  $fileNameArray = explode('/', $fileLocation);
  $relativePath = '';
  for ($i = 0; $i < sizeof($fileNameArray) - 1; $i++)
  {
    $relativePath = $relativePath . $fileNameArray[$i] . '/';
  }

  $fileName = $fileNameArray[sizeof($fileNameArray) - 1];

  if (!file_exists($thumbBaseDir . '/' . $relativePath)){
    mkdir($thumbBaseDir . '/' . $relativePath, 0770, TRUE);
  }
  if (!file_exists($fullThumbPath))
  {
    $image_info = getimagesize($fullImagePath);
    if ($debug ) { var_dump($image_info[2] . "\n"); }
    $image_type = $image_info[2];
    $exif = exif_read_data($fullImagePath);
    $orientation = $exif['Orientation'];
    $image = imagecreatefromjpeg($fullImagePath);
    $oWidth = imagesx($image);
    $oHeight = imagesy($image);
    if ($oWidth > $oHeight){
    $ratio = $oHeight/$oWidth;
    $newWidth = $size;
    $newHeight = $ratio * $newWidth;
    }
    else {
      $ratio = $oWidth/$oHeight;
      $newHeight = $size;
      $newWidth = $ratio * $newHeight;
    }
    $newImage = imagecreatetruecolor($newWidth,$newHeight);
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $oWidth, $oHeight);
    switch($orientation)
    {
      case 1:
        break;
      case 2:
        break;
      case 3:
        $newImage = imagerotate($newImage,180, 0);
        break;
      case 4: 
        break;
      case 5:
        $newImage = imagerotate($newImage, -90, 0);
        break;
      case 6:
        $newImage = imagerotate($newImage, -90, 0);
        break;
      case 7: 
        $newImage = imagerotate($newImage, -90, 0);
        break;
      case 8:
        $newImage = imagerotate($newImage, 90, 0);
        break;
    }
    imagejpeg($newImage, $fullThumbPath, 75);
  }
  readfile($fullThumbPath);
?>
