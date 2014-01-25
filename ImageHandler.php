<?php
  include("includes.inc");
  include("functions.php");

  $fileLocation = htmlspecialchars($_GET['fileLocation']);
  $fileLocation = str_replace("_*_", "&", $fileLocation);
  $size = (int)$_GET['size'];

  $resizedImage = resizeImageFromPath($fileLocation, $size);
  trace ("resimg: " . $resizedImage);

  //header('Content-Description: File Transfer');
  //header('Content-Type: application/octet-stream');
  header('Content-Type: image/jpeg');
  header('Content-Disposition: attachment; filename='.basename($resizedImage));
  //header('Expires: 0');
  //header('Cache-Control: must-revalidate');
  //header('Pragma: public');
  //header('Content-Length: ' . filesize($resizedImage));
  ob_clean();
  flush();
  readfile($resizedImage);
  exit;
?>
