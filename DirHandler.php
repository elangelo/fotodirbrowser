<html>
<head>
  <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
  <script type="text/javascript" src="js/slimbox2.js"></script>
  <link rel="stylesheet" href="style.css" type="text/css" media="screen"/>
  <link rel="stylesheet" href="css/slimbox2.css" type="text/css" media="screen" />
</head>

  <body>
  <div class="nav">
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

  <?php
    include("includes.inc");
    $saveDirName = htmlspecialchars($_GET['fileLocation']);

    $dir = str_replace("_*_", "&", $saveDirName);
    $dirNames = explode('/', $dir);
    $parentDir = $baseDir;
    for ($i = 1; $i < (sizeof($dirNames) - 1); $i++){
      $parentDir = $parentDir . "/" . $dirNames[$i];
    }

    $saveParentDir = "";
    for ($i = 1; $i < (sizeof($dirNames) - 1); $i++)
    {
      $saveParentDir = $saveParentDir . "/" . $dirNames[$i];
    }

    if ($handle = opendir($parentDir))
    {
      while (false !== ($file = readdir($handle)))
      {
        if ($file != "." && $file != ".." && is_dir($parentDir . "/" . $file))
        {
          $kdirs[] = str_replace("&", "_*_", $saveParentDir . "/" . $file);
        }
      }
      closedir($handle);
      if(!empty($kdirs))
      { 
        if (sizeof($kdirs) > 0)
        {
          sort($kdirs);
          $idx = array_search($dir, $kdirs);

          if ($idx == 0)
          {
            $previousDir = "";
            $nextDir = $kdirs[$idx + 1];
          }
          else if ($idx == sizeof($kdirs))
          {
            $previousDir = $kdirs[$idx - 1];
            $nextDir = "";
          }
          else
          {
            $previousDir = $kdirs[$idx - 1];
            $nextDir = $kdirs[$idx + 1];
          }

          echo "<div class=\"metanavprev\">";
          echo "<div class=\"navButton\">";
          echo "<a class=\"baseNavigation\" href=\"DirHandler.php?fileLocation=" . $previousDir . "\">previous</a>";
          echo "</div>";
          echo "</div>";
          echo "<div class=\"metanavnext\">";
          echo "<div class=\"navButton\">";
          echo "<a class=\"baseNavigation\" href=\"DirHandler.php?fileLocation=" . $nextDir . "\">next</a>";
          echo "</div>";
          echo "</div>";
        }
      }
    }
  ?>
  </div>
  
  <div class="center">
  <br/><br/>
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
          $realFileName = end(explode('/',$saveFileName));
          echo "<div class=\"thumb\">";
          echo "<a class=\"baseNavigation\" href=\"ImageHandler.php?fileLocation=" . $saveFileName . "&size=" . $slideSize . "\" rel=\"lightbox-pics\" title=\"$realFileName&nbsp;:&nbsp;&lt;a href=&quot;ImageHandler.php?fileLocation=" . $saveFileName . "&quot;&gt;original&lt;/a&gt;&nbsp;&nbsp;&lt;a href=&quot;ImageHandler.php?fileLocation=" . $saveFileName . "&size=" . $slideSize ."&quot;&gt;small&lt;/a&gt;\">";
          echo "<div class=\"thumbimg\">";
          echo "<img src=\"ThumbHandler.php?fileLocation=" . $saveFileName . "\" />";
          echo "</div>";
          /*echo "<div class=\"thumblabel\">";
          echo end(explode('/',$files[$i])) . "<br />";
          echo "</div>";*/
          echo "</a>";
          echo "</div>";
        }
      }
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

  </div>

  </body>
</html>
