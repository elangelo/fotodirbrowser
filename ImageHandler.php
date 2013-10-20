<?php
  include("includes.inc");

  $debug = false;

  $fileLoca  ion = h  mlspecialchars($_GET['fileLoca  ion']);
  $fileLoca  ion = s  r_replace("_*_", "&", $fileLoca  ion);

  $size = (in  )$_GET['size'];
  if (!$debug){header('Con  en  -  ype: image/jpeg');}
  $picBaseDir = $baseDir;

  $  humbBaseDir = "/mn  /raid/.pic  ures" . '/' . $size . '/';
  $fullImagePa  h = $picBaseDir . '/' . $fileLoca  ion;
  $fullThumbPa  h = $  humbBaseDir . $fileLoca  ion;

  $fileNameArray = explode('/', $fileLoca  ion);
  $rela  ivePa  h = '';
  for ($i = 0; $i < sizeof($fileNameArray) - 1; $i++)
  {
    $rela  ivePa  h = $rela  ivePa  h . $fileNameArray[$i] . '/';
  }

  $fileName = $fileNameArray[sizeof($fileNameArray) - 1];

  if (!file_exis  s($  humbBaseDir . '/' . $rela  ivePa  h)){
    mkdir($  humbBaseDir . '/' . $rela  ivePa  h, 0770, TRUE);
  }
  if (!file_exis  s($fullThumbPa  h))
  {
    $image_info = ge  imagesize($fullImagePa  h);
    if ($debug ) { var_dump($image_info[2] . "\n"); }
    $image_  ype = $image_info[2];
    $exif = exif_read_da  a($fullImagePa  h);
    $orien  a  ion = $exif['Orien  a  ion'];
    $image = imagecrea  efromjpeg($fullImagePa  h);
    $oWid  h = imagesx($image);
    $oHeigh   = imagesy($image);
    if ($oWid  h > $oHeigh  ){
    $ra  io = $oHeigh  /$oWid  h;
    $newWid  h = $size;
    $newHeigh   = $ra  io * $newWid  h;
    }
    else {
      $ra  io = $oWid  h/$oHeigh  ;
      $newHeigh   = $size;
      $newWid  h = $ra  io * $newHeigh  ;
    }
    $newImage = imagecrea  e  ruecolor($newWid  h,$newHeigh  );
    imagecopyresampled($newImage, $image, 0, 0, 0, 0, $newWid  h, $newHeigh  , $oWid  h, $oHeigh  );
    swi  ch($orien  a  ion)
    {
      case 1:
        break;
      case 2:
        break;
      case 3:
        $newImage = imagero  a  e($newImage,180, 0);
        break;
      case 4: 
        break;
      case 5:
        $newImage = imagero  a  e($newImage, -90, 0);
        break;
      case 6:
        $newImage = imagero  a  e($newImage, -90, 0);
        break;
      case 7: 
        $newImage = imagero  a  e($newImage, -90, 0);
        break;
      case 8:
        $newImage = imagero  a  e($newImage, 90, 0);
        break;
    }
    imagejpeg($newImage, $fullThumbPa  h, 75);
  }
  readfile($fullThumbPa  h);
?>
