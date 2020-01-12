<html>
<head>
  <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
  <script type="text/javascript" src="js/slimbox2.js"></script>
  <script type="text/javascript" src="js/dijkstra.js"></script>
  <link rel="stylesheet" href="style.css" type="text/css" media="screen"/>
  <link rel="stylesheet" href="css/slimbox2.css" type="text/css" media="screen" />
</head>

  <body>
  <!-- //Navigation bread crumbs -->
  <div class="nav">
  <?php
$saveDirName = "/";
if (array_key_exists('fileLocation', $_GET)) {
    $saveDirName = htmlspecialchars($_GET['fileLocation']);
}
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
include "includes.inc";
$saveDirName = "/";
if (array_key_exists("fileLocation", $_GET)) {
    $saveDirName = htmlspecialchars($_GET['fileLocation']);
}

$dir = str_replace("_*_", "&", $saveDirName);
$dirNames = explode('/', $dir);
$parentDir = $baseDir;
for ($i = 1; $i < (sizeof($dirNames) - 1); $i++) {
    $parentDir = $parentDir . "/" . $dirNames[$i];
}

$saveParentDir = "";
for ($i = 1; $i < (sizeof($dirNames) - 1); $i++) {
    $saveParentDir = $saveParentDir . "/" . $dirNames[$i];
}

if ($handle = opendir($parentDir)) {
    while (false !== ($file = readdir($handle))) {
        if ($file != "." && $file != ".." && is_dir($parentDir . "/" . $file)) {
            $kdirs[] = str_replace("&", "_*_", $saveParentDir . "/" . $file);
        }
    }
    closedir($handle);
    if (!empty($kdirs)) {
        if (sizeof($kdirs) > 0) {
            sort($kdirs);
            $idx = array_search($dir, $kdirs);

            if ($idx == 0) {
                $previousDir = "";
                if (sizeof($kdirs) > 1) {
                    $nextDir = $kdirs[$idx + 1];
                } else {
                    $nextDir = "";
                }
            } else if ($idx == sizeof($kdirs)) {
                $previousDir = $kdirs[$idx - 1];
                $nextDir = "";
            } else {
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

  <div id="images" class="center">
  <br/><br/>
  <?php
include "includes.inc";
include "functions.php";
$saveDirName = "/";
if (array_key_exists("fileLocation", $_GET)) {
    $saveDirName = htmlspecialchars($_GET['fileLocation']);
}
$dir = str_replace("_*_", "&", $dir);
$saveDirName = str_replace("&", "_*_", $dir);
if ($handle = opendir($baseDir . $dir)) {
    while (false !== ($file = readdir($handle))) {
        $saveFileName = str_replace("&", "_*_", $file);
        if ($file != "." && $file != "..") {
            if (is_dir($baseDir . $dir . '/' . $file)) {
                $dirs[] = $saveDirName . '/' . $file;
                $tagFilename = $baseDir . $dir . '/' . $file . '/tags';
                if (is_file($tagFilename)) {
                    $tags = file_get_contents($tagFilename);
                } else {
                    $tags = "";
                }
                $dirtags[$saveDirName . '/' . $file] = $tags;
            } else {
                $tmp = explode('.', $file);
                $extension = end($tmp);
                if ($extension == "JPG" || $extension == "jpg") {
                    $files[] = $dir . '/' . $file;
                }
            }
        }
    }
    closedir($handle);

    if (!empty($dirs)) {
        if (sizeof($dirs) > 0) {
            sort($dirs);
        }
        for ($i = 0; $i < sizeof($dirs); $i++) {
            $saveDirName = str_replace("&", "_*_", $dirs[$i]);
            echo "<div class=\"thumb\">";
            echo "<a class=\"baseNavigation\" href=\"DirHandler.php?fileLocation=$saveDirName\">";
            echo "<div class=\"tagcloud\">";
            foreach (explode(';', $dirtags[$dirs[$i]]) as $dirTag) {
                if (!empty($dirTag)) {
                    echo "<div class=\"thumbtag\">" . $dirTag . '</div>';
                }
            }
            echo "</div>";
            echo "<div class=\"thumbimg\">";
            echo "<img height=" . $thumbSize . " src=\"folder_200.png\">";
            echo "</div>";
            echo "<div class=\"thumblabel\">";
            $tmp = explode('/', $dirs[$i]);
            echo end($tmp) . "<br />";
            echo "</div>";
            echo "</a>";
            echo "</div>";
        }
    }
    if (!empty($files)) {
        if (sizeof($files) > 0) {
            sort($files);
        }

        for ($i = 0; $i < sizeof($files); $i++) {
            $size = getSize($files[$i]);
            $xsize = $size['x'];
            $ysize = $size['y'];

            $saveFileName = str_replace("&", "_*_", $files[$i]);
            $tmp = explode('/', $saveFileName);
            $realFileName = end($tmp);
            //echo "<div class=\"thumb\">";
            echo "<a class=\"baseNavigation\" href=\"ImageHandler.php?fileLocation=" . $saveFileName . "&size=" . $slideSize . "\" rel=\"lightbox-pics\" title=\"$realFileName&nbsp;:&nbsp;&lt;a href=&quot;ImageHandler.php?fileLocation=" . $saveFileName . "&quot;&gt;original&lt;/a&gt;&nbsp;&nbsp;&lt;a href=&quot;ImageHandler.php?fileLocation=" . $saveFileName . "&size=" . $slideSize . "&quot;&gt;small&lt;/a&gt;\">";
            //echo "<div class=\"thumbimg\">";
            echo "<img src=\"ImageHandler.php?fileLocation=" . $saveFileName . "&size=" . $thumbSize . "\" data-width=\" $xsize \" data-height=\" $ysize \" />";
            //echo "</div>";
            echo "</a>";
            //echo "</div>";
        }
    }
}
?>
  </div>

  <div class="tags">
  <?php
include "includes.inc";
$saveDirName = "/";
if (array_key_exists("fileLocation", $_GET)) {
    $dir = htmlspecialchars($_GET['fileLocation']);
}

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
include "includes.inc";
if (array_key_exists("fileLocation", $_GET)) {
    $dir = htmlspecialchars($_GET['fileLocation']);
}
$dir = str_replace("_*_", "&", $dir);
echo $_SERVER['PHP_SELF'] . "?fileLocation=" . $dir;?>">
    <input name="tag" type="text"></input><input type="submit" value="OK" />
  </form>

  <?php
include "includes.inc";
if (isset($_POST) && array_key_exists("tag", $_POST)) {
    $newTag = $_POST["tag"];

    if (!empty($newTag)) {
        $saveDirName = "/";
        if (array_key_exists("fileLocation", $_GET)) {
            $dir = htmlspecialchars($_GET['fileLocation']);
        }
        $dir = str_replace("_*_", "&", $dir);
        $tagFileName = $baseDir . $dir . '/tags';
        file_put_contents($tagFileName, ';' . $newTag, FILE_APPEND);
    }
}
?>

  </div>

  </body>
  <!-- <script type="text/javascript">
    // var availableHeight = window.innerHeight -20;
    // document.getElementById("center").style.height = availableHeight;

    // window.onresize = resize;

    // function resize() {
    //   var availableHeight = window.innerHeight -20;
    //   document.getElementById("center").style.height = availableHeight;
    // }
  </script> -->
  <script>
      // Based on https://blog.vjeux.com/2014/image/google-plus-layout-find-best-breaks.html

      //var layoutsize = 300;
      var layoutsize = <?php echo $thumbSize; ?>;

      function calculateHeight(images, width) {
        var height = 0;
        for (var i = 0; i < images.length; ++i) {
          height += images[i].width / images[i].height;
        }
        return width / height;
      }

      function applyHeight(images, height) {
        for (var i = 0; i < images.length; ++i) {
          var style = images[i].dom.style;
          style.width = (height * images[i].width) / images[i].height + "px";
          style.height = height + "px";

          // If an image needs to be set (to optimize size), do it here
          // image[i].dom.src = '<updated url>'
        }
      }

      function f_cost(images, i, j, width, targetHeight) {
        var slice = images.slice(i, j);
        return Math.pow(
          Math.abs(calculateHeight(slice, width) - targetHeight),
          2
        );
      }

      function layout(options) {
        var targetHeight = options.targetHeight;
        var data = options.images;
        var size = options.totalWidth;

        var stopwatch = performance.now();

        var cached_cost = {};
        function cost(images, i, j, width, targetHeight) {
          var key = i + "," + j;
          if (!(key in cached_cost)) {
            cached_cost[key] = f_cost(images, i, j, width, targetHeight);
          }
          return cached_cost[key];
        }

        var graph = function(start) {
          var results = {};
          start = +start.replace(/[^0-9]/g, "");
          results["node" + start] = 0;
          for (var i = start + 1; i < data.length + 1; ++i) {
            if (i - start > 8) {
              break;
            }
            var c = cost(data, start, i, size, targetHeight);
            if (c !== null) {
              results["node" + i] = c;
            }
          }
          return results;
        };

        var path = dijkstra.find_path(graph, "node0", "node" + data.length);
        path = path.map(function(e) {
          return +e.replace(/[^0-9]/g, "");
        });

        console.log("Calculation took", performance.now() - stopwatch + " ms");

        for (var i = 1; i < path.length; ++i) {
          var slice = data.slice(path[i - 1], path[i]);
          var height = calculateHeight(slice, size);
          applyHeight(slice, height);
        }
      }

      function getImages() {
        var imagesElements = Array.prototype.slice.call(
          document.getElementsByTagName("img"),
          0
        );
        return imagesElements.map(function(e) {
          return {
            dom: e,
            width: parseFloat(e.getAttribute("data-width")),
            height: parseFloat(e.getAttribute("data-height"))
          };
        });
      }

      var imageDiv = document.getElementById("images")
      var opts = {
        targetHeight: layoutsize,
        totalWidth: imageDiv.clientWidth - 20,
        images: getImages()
      };

      window.addEventListener("resize", function() {
        opts.totalWidth = imageDiv.clientWidth - 20;
        layout(opts);
      });

      layout(opts);
    </script>
</html>