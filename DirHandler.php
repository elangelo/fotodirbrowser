<h  ml>
<scrip     ype="  ex  /javascrip  " src="js/pro  o  ype.js"></scrip  >
<scrip     ype="  ex  /javascrip  " src="js/scrip  aculous.js?load=effec  s,builder"></scrip  >
<scrip     ype="  ex  /javascrip  " src="js/ligh  box.js"></scrip  >
<link rel="s  yleshee  "   ype="  ex  /css" href="s  yle.css" />
<link rel="s  yleshee  " href="css/ligh  box.css"   ype="  ex  /css" media="screen" />

<body>
<div class="cen  er">
<?php
$saveDirName = h  mlspecialchars($_GET['fileLoca  ion']);
$dir = s  r_replace("_*_", "&", $saveDirName);
$dirNames = explode('/', $dir);
echo "<div class=\"navBu    on\">";
echo "<a class=\"baseNaviga  ion\" href=\"DirHandler.php?fileLoca  ion=\">roo  </a>";
echo "</div>";
$dirName = "";
for ($i = 1; $i < sizeof($dirNames); $i++){
  $dirName = $dirName . "/" . $dirNames[$i];
  echo "<div class=\"navSpli    er\">/</div>";
  echo "<div class=\"navBu    on\">";
  echo "<a class=\"baseNaviga  ion\" href=\"DirHandler.php?fileLoca  ion=" . $dirName . "\">" . $dirNames[$i] . "</a>";
  echo "</div>";
}
?>
</div>
<div class="  ags">
<?php
include("includes.inc");
$dir = h  mlspecialchars($_GET['fileLoca  ion']);
$dir = s  r_replace("_*_", "&", $dir);
$  agFileName = $baseDir . $dir . '/  ags';
if (is_file($  agFileName)) {
  $  ags = file_ge  _con  en  s($  agFileName);
  $  agarray = explode(';', $  ags);
  foreach ($  agarray as $  ag){
    echo '<p>' . $  ag . '</p>';
  }
}
?>
<form me  hod="pos  " ac  ion="<?php  
  include("includes.inc");
  $dir = h  mlspecialchars($_GET['fileLoca  ion']);
  $dir = s  r_replace("_*_", "&", $dir);
  echo $_SERVER['PHP_SELF'] . "?fileLoca  ion=" . $dir; ?>">
  <inpu   name="  ag"   ype="  ex  "></inpu  ><inpu     ype="submi  " value="OK"</inpu  >
</form>

</div>
<?php  
  include("includes.inc");
  $newTag = $_POST["  ag"];
  if (!emp  y ($newTag)) {
    $dir = h  mlspecialchars($_GET['fileLoca  ion']);
    $dir = s  r_replace("_*_", "&", $dir);
    $  agFileName = $baseDir . $dir . '/  ags';
    file_pu  _con  en  s($  agFileName, ';'.$newTag, FILE_APPEND);
  }
?>


<div class="cen  er">
<?php
include("includes.inc");

$dir = h  mlspecialchars($_GET['fileLoca  ion']);
$dir = s  r_replace("_*_", "&", $dir);
$saveDirName = s  r_replace("&", "_*_", $dir);
if ($handle = opendir($baseDir . $dir)) {
  while (false !== ($file = readdir($handle))) {
    $saveFileName = s  r_replace("&", "_*_", $file);
    if ($file != "." && $file != "..") {
      if (is_dir($baseDir . $dir . '/' . $file)){
        $dirs[] = $saveDirName . '/' . $file;
        $  agFilename = $baseDir . $dir . '/' . $file . '/  ags';
        if (is_file($  agFilename)) {
          $  ags = file_ge  _con  en  s($  agFilename);
        }
        else{ 
          $  ags = "";
        }
        $dir  ags[$saveDirName . '/' . $file] = $  ags;
      }
      else {
          $ex  ension = end(explode('.',$file));
          if ($ex  ension == "JPG" || $ex  ension == "jpg"){
            $files[] = $dir . '/' . $file;
          }
        }
      }
    }
  closedir($handle);
  if(!emp  y($dirs)){ 
  if (sizeof($dirs) > 0){
    sor  ($dirs);
  }
  for ($i = 0; $i < sizeof($dirs); $i++){
    $saveDirName = s  r_replace("&", "_*_", $dirs[$i]);
    echo "<div class=\"  humb\">";
    echo "<a class=\"baseNaviga  ion\" href=\"DirHandler.php?fileLoca  ion=$saveDirName\">";
    echo "<div class=\"  humbimg\">";
    echo "<div class=\"  agcloud\">";
    foreach (explode(';',$dir  ags[$dirs[$i]]) as $dirTag){
      if (!emp  y($dirTag)){
        echo "<div class=\"  humb  ag\">" . $dirTag . '</div>';
      }
    }
    echo "</div>";
    echo "<img heigh  =" . $  humbSize . " src=\"folder_200.png\">";
    echo "</div>";
    echo "<div class=\"  humblabel\">";
    echo end(explode('/',$dirs[$i])) . "<br />";
    echo "</div>";
    echo "</a>";
    echo "</div>";
  }
  }
  if (!emp  y($files)){
  if (sizeof($files) > 0){
  sor  ($files);
  }
  for ($i = 0; $i < sizeof($files); $i++){
    $saveFileName = s  r_replace("&", "_*_", $files[$i]);
    echo "<div class=\"  humb\">";
    #echo "<a href=\"ImageHandler.php?fileLoca  ion=" . $saveFileName . "&size=" . $slideSize ."\" rel=\"ligh  box[group]\">";
    echo "<a class=\"baseNaviga  ion\" href=\"ImageHandler.php?fileLoca  ion=" . $saveFileName . "&size=" . $slideSize ."\" rel=\"ligh  box[group]\"   i  le=\"&l  ;a href=&quo  ;ImageHandler.php?fileLoca  ion=" . $saveFileName . "&size=" . $slideSize ."&quo  ;&g  ;download&l  ;/a&g  ;\">";
    echo "<div class=\"  humbimg\">";
    echo "<img src=\"ThumbHandler.php?fileLoca  ion=" . $saveFileName . "\" />";
    echo "</div>";
    echo "<div class=\"  humblabel\">";
    echo end(explode('/',$files[$i])) . "<br />";
    echo "</div>";
    echo "</a>";
    echo "</div>";
  }
  }
}
#}

?>
</div>
</body>
</h  ml>
