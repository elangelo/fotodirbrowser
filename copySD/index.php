/*probably not working*/

<html>
<head>
<link rel="stylesheet" type="text/css" href="style.css" />
</head>
<body>
<?
  $command = htmlspecialchars($_GET['command']);
  $args = htmlspecialchars($_GET['args']);

  $targetPath = "/mnt/raid/pictures/";

  $debug = true;

  if ( $debug )
  {
    var_dump($targetPath);
    echo "<br />"; 
    var_dump($args); 
    echo "<br />"; 
  }

  switch ($command) 
  {
    case "":
    case "getSDcards":
      getSDcards();
      break;
    case "mount":
      mount($_POST['partitions']);
      $folder = getFolderOfMountedPartition($_POST['partitions']);
      listContent($folder . "/");
      break;
    case "browse":
      listContent($args . "/");
      break;
    default:
      echo "foo";
  }

  function foo($mountpoint)
  {
    $content = getContent($mountpoint);
  }

  function getContent($folder)
  {
    $content = new folderContent();
    if (is_dir($folder)) 
    {
      $lastSlashOccured = strrpos($folder, "/");
      $content->$folderName = substr($folder, $lastSlashOccured + 1);
      $content->$parentPath = substr($folder, 0, $lastSlashOccured);
      if ($folderhandle = opendir($folder))
      {


      } 
    }
    return content;
  }

class folderContent
{
  public $folderName; //string
  public $parentPath; //string 
  public $hasSubFolders; //boolean
  public $hasFiles; //boolean
  public $Files; //array
  public $Folders; //array
}



function listContent($dir)
{
  $targetPath = "/mnt/raid/pictures/";
  if (is_dir($dir)) 
  {
    if ($dh = opendir($dir)) 
    {
      while (($file = readdir($dh)) != false) 
      {
        if ($file != "." && $file != "..")
        {
          if (filetype($dir . $file) == "dir")
          {
            echo "<input type=checkbox /><a href=index.php?command=browse&args=$dir$file>$file</a><br />";
          }
          else
          {
            // check if image is JPG TODO:what with raws??? and movies???
            // get the exif data (the @ surpresses the warning)
            $exif = @ exif_read_data($dir . $file);
            // get date
            $date = strptime($exif['DateTime'], "%Y:%m:%d %H:%M:%S");

            // propose folder based on exif data (date)
            $year = $date['tm_year'] + 1900;
            $month = $date['tm_mon'] + 1;
            $day = $date['tm_mday'];
            echo "<div class=\"cell-container\">";
            echo "<div class=\"cell-left\">";
            echo "<div class=\"cell\">SOURCE:</div>";
            echo "<div class=\"cell\">$dir$file</div>";
            echo "</div>";
            echo "<div class=\"cell-right\">";
            echo "<div class=\"cell\">TARGET:</div>";
            echo "<div class=\"cell\">" . $targetPath;
            printf("%04d/%04d-%02d-%02d/", $year,$year,$month,$day);
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

function getFolderOfMountedPartition($device) 
{
  $output = exec ("mount | grep $device");
  $substrings = explode(" ", $output);
  return $substrings[2];
}

function mount($device) 
{
  exec ("mount $device");
}

function umount($device) 
{
  exec ("umount $device");
}

function getSDcards() 
{
  echo "<form action=index.php?command=mount method=post>";
  echo "<select name=\"partitions\">";
  $partitions = listPartitions();
  foreach ($partitions as &$partition)
  {
    if (isSDcard($partition))
    {
      echo "<option value=$partition>$partition</option>";
    }
  }
  echo "</select>";
  echo "<input type=\"Submit\" value=\"Mount\"/>";
  echo "</form>";
}

function mountSD($device) 
{
  $mountoutput = exec("mount $device");
}

function listPartitions()
{
  exec ("ls /dev/sd??", $partitions);
  return $partitions;
}

function isSDcard($device)
{
  $output = exec ("udevadm info -a --name=$device | grep SD");
  if ($output != NULL && $output != "") 
  {
    return true;
  }
  else 
  {
    return false;
  }
}

?>
</body>
</html>
