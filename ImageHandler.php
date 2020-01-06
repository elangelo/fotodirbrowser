<?php
  include("includes.inc");
  include("functions.php");
  
  if (array_key_exists('fileLocation', $_GET))
  {
    $fileLocation = htmlspecialchars($_GET['fileLocation']);
    $fileLocation = str_replace("_*_", "&", $fileLocation);
    $size = (int)$_GET['size'];
  
    $resizedImage = resizeImageFromPath($fileLocation, $size);
    trace ("resimg: " . $resizedImage);
  
    $headers = apache_request_headers();
    // Checking if the client is validating his cache and if it is current.
    if (isset($headers['If-Modified-Since']) && (strtotime($headers['If-Modified-Since']) == filemtime($resizedImage))) {
          // Client's cache IS current, so we just respond '304 Not Modified'.
          header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($resizedImage)).' GMT', true, 304);
    } else {
          // Image not cached or cache outdated, we respond '200 OK' and output the image.
          header('Last-Modified: '.gmdate('D, d M Y H:i:s', filemtime($resizedImage)).' GMT', true, 200);
          header('Content-Length: '.filesize($resizedImage));
          header('Content-Type: image/jpeg');
          $basename = basename($resizedImage);
          $fileParts = explode(".", $basename);
          $newfilename = basename($fileParts[0].'_'.$size.'.'.$fileParts[1]);
          header('Content-Disposition: attachment; filename='.$newfilename);
    }
   
    if (ob_get_contents()) 
    { 
      ob_clean();
    }
    flush();
    readfile($resizedImage);
  }
  exit;
?>