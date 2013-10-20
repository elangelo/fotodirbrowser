<html>
  <script type="text/javascript" src="js/prototype.js"></script>
  <script type="text/javascript" src="js/scriptaculous.js?load=effects,builder"></script>
  <script type="text/javascript" src="js/lightbox.js"></script>
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="stylesheet" href="css/lightbox.css" type="text/css" media="screen" />

  <body>
  <div class="center">
  <?php
    $saveDirName = htmlspecialchars($_GET['fileLocation']);
    $dir = str_replace("_*_", "&", $saveDirName);
    $dirNames = explode('/', $dir);
    
    echo "<div class=\"navButton\">";
    echo "<a class=\"baseNavigation\" href=\"DirHandler.php?fileLocation=\">root</a>";
    echo "</div>";
    
    $dirName = "";
    for ($i = 1; $i < sizeof($dirNames); $i++) {
      $dirName = $dirName . "/" . $dirNames[$i];

      echo "<div class=\"navSplitter\">/</div>";
      echo "<div class=\"navButton\">";
      echo "<a class=\"baseNavigation\" href=\"DirHandler.php?fileLocation=" . $dirName . "\">" . $dirNames[$i] . "</a>";
      echo "</div>";
    }
  ?>
  </div>
  
  <div class="tags">
  <?php
    include("includes.inc");
    $dir = htmlspecialchars($_GET['fileLocation']);
    $dir = str_replace("_*_", "&", $dir);
    $tagFileName = $baseDir . $dir . '/tags';
    if (is_file($tagFileName)) {
      $tags = file_get_contents($tagFileName);
      $tagarray = explode(';', $tags);
      foreach ($tagarray as $tag) {
        echo '<p>' . $tag . '</p>';
      }
    }
  ?>

  <form method="post" action="<?php  
    include("includes.inc");
    $dir = htmlspecialchars($_GET['fileLocation']);
    $dir = str_replace("_*_", "&", $dir);
    echo $_SERVER['PHP_SELF'] . "?fileLocation=" . $dir; ?>">
    <input name="tag" type="text"></input><input type="submit" value="OK"</input>
  </form>

  </div>
  <?php  
    include("includes.inc");
    $newTag = $_POST["tag"];
    if (!empty ($newTag)) 
    {
      $dir = htmlspecialchars($_GET['fileLocation']);
      $dir = str_replace("_*_", "&", $dir);
      $tagFileName = $baseDir . $dir . '/tags';
      file_put_contents($tagFileName, ';'.$newTag, FILE_APPEND);
    }
  ?>


  <div class="center">
  <?php
    include("includes.inc");
    $dir = htmlspecialchars($_GET['fileLocation']);
    $dir = str_replace("_*_", "&", $dir);
    $saveDirName = str_replace("&", "_*_", $dir);
    if ($handle = opendir($baseDir . $dir)) 
    {
      while (false !== ($file = readdir($handle))) 
      {
        $saveFileName = str_replace("&", "_*_", $file);
        if ($file != "." && $file != "..") 
        {
          if (is_dir($baseDir . $dir . '/' . $file))
          {
            $dirs[] = $saveDirName . '/' . $file;
            $tagFilename = $baseDir . $dir . '/' . $file . '/tags';
            if (is_file($tagFilename)) 
            {
              $tags = file_get_contents($tagFilename);
            }
            else
            { 
              $tags = "";
            }
            $dirtags[$saveDirName . '/' . $file] = $tags;
          }
          else 
          {
            $extension = end(explode('.',$file));
            if ($extension == "JPG" || $extension == "jpg")
            {
              $files[] = $dir . '/' . $file;
            }
          }
        }
      }
      closedir($handle);
      
      if(!empty($dirs))
      { 
        if (sizeof($dirs) > 0)
        {
          sort($dirs);
        }
        for ($i = 0; $i < sizeof($dirs); $i++)
        {
          $saveDirName = str_replace("&", "_*_", $dirs[$i]);
          echo "<div class=\"thumb\">";
          echo "<a class=\"baseNavigation\" href=\"DirHandler.php?fileLocation=$saveDirName\">";
          echo "<div class=\"thumbimg\">";
          echo "<div class=\"tagcloud\">";
          foreach (explode(';',$dirtags[$dirs[$i]]) as $dirTag)
          {
            if (!empty($dirTag))
            {
              echo "<div class=\"thumbtag\">" . $dirTag . '</div>';
            }
          }
          echo "</div>";
          echo "<img height=" . $thumbSize . " src=\"folder_200.png\">";
          echo "</div>";
          echo "<div class=\"thumblabel\">";
          echo end(explode('/',$dirs[$i])) . "<br />";
          echo "</div>";
          echo "</a>";
          echo "</div>";
        }
      }
      if (!empty($files))
      {
        if (sizeof($files) > 0)
        {
          sort($files);
        }
        
        for ($i = 0; $i < sizeof($files); $i++)
        {
          $saveFileName = str_replace("&", "_*_", $files[$i]);
          echo "<div class=\"thumb\">";
          echo "<a class=\"baseNavigation\" href=\"ImageHandler.php?fileLocation=" . $saveFileName . "&size=" . $slideSize ."\" rel=\"lightbox[group]\" title=\"&lt;a href=&quot;ImageHandler.php?fileLocation=" . $saveFileName . "&size=" . $slideSize ."&quot;&gt;download&lt;/a&gt;\">";
          echo "<div class=\"thumbimg\">";
          echo "<img src=\"ThumbHandler.php?fileLocation=" . $saveFileName . "\" />";
          echo "</div>";
          echo "<div class=\"thumblabel\">";
          echo end(explode('/',$files[$i])) . "<br />";
          echo "</div>";
          echo "</a>";
          echo "</div>";
        }
      }
    }
  ?>
  </div>
  </body>
</html>
