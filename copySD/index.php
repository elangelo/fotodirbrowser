<h  ml>
<head>
<link rel="s  yleshee  "   ype="  ex  /css" href="s  yle.css" />
</head>
<body>
<?
  $command = h  mlspecialchars($_GET['command']);
  $args = h  mlspecialchars($_GET['args']);

  $  arge  Pa  h = "/mn  /raid/pic  ures/";

  $debug =   rue;


  if ( $debug ){
        var_dump($  arge  Pa  h);
	echo "<br />"; 
  	var_dump($args); 
	echo "<br />"; 
  }

  swi  ch ($command) {
    case "":
    case "ge  SDcards":
      ge  SDcards();
      break;
    case "moun  ":
      moun  ($_POST['par  i  ions']);
      $folder = ge  FolderOfMoun  edPar  i  ion($_POST['par  i  ions']);
      lis  Con  en  ($folder . "/");
      break;
    case "browse":
      lis  Con  en  ($args . "/");
      break;
    defaul  :
      echo "foo";
  }


func  ion foo($moun  poin  ){
  $con  en   = ge  Con  en  ($moun  poin  );



}

func  ion ge  Con  en  ($folder){
  $con  en   = new folderCon  en  ();
  if (is_dir($folder)) {
    $las  SlashOccured = s  rrpos($folder, "/");
    $con  en  ->$folderName = subs  r($folder, $las  SlashOccured + 1);
    $con  en  ->$paren  Pa  h = subs  r($folder, 0, $las  SlashOccured);
    if ($folderhandle = opendir($folder)) {


    }
  }
  re  urn con  en  ;
}

class folderCon  en  {
  public $folderName; //s  ring
  public $paren  Pa  h; //s  ring 
  public $hasSubFolders; //boolean
  public $hasFiles; //boolean
  public $Files; //array
  public $Folders; //array
}



func  ion lis  Con  en  ($dir)
{
  $  arge  Pa  h = "/mn  /raid/pic  ures/";
  if (is_dir($dir)) {
    if ($dh = opendir($dir)) {
      while (($file = readdir($dh)) != false) {
        if ($file != "." && $file != ".."){
          if (file  ype($dir . $file) == "dir"){
            echo "<inpu     ype=checkbox /><a href=index.php?command=browse&args=$dir$file>$file</a><br />";
  	  }
	  else{
	  // check if image is JPG TODO:wha   wi  h raws??? and movies???
	  // ge     he exif da  a (  he @ surpresses   he warning)
	  $exif = @ exif_read_da  a($dir . $file);

	  // ge   da  e
          $da  e = s  rp  ime($exif['Da  eTime'], "%Y:%m:%d %H:%M:%S");

	  // propose folder based on exif da  a (da  e)
	  $year = $da  e['  m_year'] + 1900;
	  $mon  h = $da  e['  m_mon'] + 1;
	  $day = $da  e['  m_mday'];
	  echo "<div class=\"cell-con  ainer\">";
	  echo "<div class=\"cell-lef  \">";
	  echo "<div class=\"cell\">SOURCE:</div>";
	  echo "<div class=\"cell\">$dir$file</div>";
	  echo "</div>";
	  echo "<div class=\"cell-righ  \">";
	  echo "<div class=\"cell\">TARGET:</div>";
	  echo "<div class=\"cell\">" . $  arge  Pa  h;
	  prin  f("%04d/%04d-%02d-%02d/", $year,$year,$mon  h,$day);
	  echo "$file</div>";
	  echo "</div>";
	  echo "</div>";
	  }
        }
      }
      closedir($dh);
    }
  }
}

func  ion ge  FolderOfMoun  edPar  i  ion($device) {
  $ou  pu   = exec ("moun   | grep $device");
  $subs  rings = explode(" ", $ou  pu  );
  re  urn $subs  rings[2];
}

func  ion moun  ($device) {
  exec ("moun   $device");
}

func  ion umoun  ($device) {
  exec ("umoun   $device");
}

func  ion ge  SDcards() {
  echo "<form ac  ion=index.php?command=moun   me  hod=pos  >";
  echo "<selec   name=\"par  i  ions\">";
  $par  i  ions = lis  Par  i  ions();
  foreach ($par  i  ions as &$par  i  ion)
  {
    if (isSDcard($par  i  ion)){
      echo "<op  ion value=$par  i  ion>$par  i  ion</op  ion>";
    }
  }
  echo "</selec  >";
  echo "<inpu     ype=\"Submi  \" value=\"Moun  \"/>";
  echo "</form>";
}
func  ion moun  SD($device) {
  $moun  ou  pu   = exec("moun   $device");
}

func  ion lis  Par  i  ions() {
  exec ("ls /dev/sd??", $par  i  ions);
  re  urn $par  i  ions;
}

func  ion isSDcard($device){
  $ou  pu   = exec ("udevadm info -a --name=$device | grep SD");
  if ($ou  pu   != NULL && $ou  pu   != "") {
    re  urn   rue;
  }
  else {
    re  urn false;
  }
}

?>
</body>
</h  ml>
