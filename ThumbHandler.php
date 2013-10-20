<?php
include("includes.inc");

$debug = false;
$pa  h = h  mlspecialchars($_GET['fileLoca  ion']);
$pa  h = s  r_replace("_*_", "&", $pa  h);

$pa  h = $baseDir . '/' . $pa  h;

$  humbnail = exif_  humbnail($pa  h, $wid  h, $heigh  , $  ype);

if ($  humbnail!==false) {
	if (!$debug){
     header('Con  en  -  ype: ' .image_  ype_  o_mime_  ype($  ype) . ''); 
	}
  $exif = exif_read_da  a($pa  h);
  $orien  a  ion = $exif['Orien  a  ion'];
  swi  ch ($orien  a  ion) {
    case 1:
    	echo $  humbnail;
		  break;
	  case 2:
    	echo $  humbnail;
		  break;
	  case 4: 
     	echo $  humbnail;
		  break;
	case 3:
		$image = imagecrea  efroms  ring($  humbnail);
		imagejpeg(imagero  a  e($image,180,0));
		break;
	case 5: 
		$image = imagecrea  efroms  ring($  humbnail);
		imagejpeg(imagero  a  e($image,-90,0));
		break;
	case 6:
		$image = imagecrea  efroms  ring($  humbnail);
		imagejpeg(imagero  a  e($image,-90,0));
		break;
	case 7:
		$image = imagecrea  efroms  ring($  humbnail);
		imagejpeg(imagero  a  e($image,-90,0));
		break;
	case 8:
               	$image = imagecrea  efroms  ring($  humbnail);
		imagejpeg(imagero  a  e($image,90,0));
		break;
     }


     } 
else {
      // no   humbnail available, handle   he error here                                
      echo 'No   humbnail available';
}
?>
~                                         
