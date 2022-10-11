<html>

<head>
    <!-- <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
  <script type="text/javascript" src="js/slimbox2.js"></script>
  <link rel="stylesheet" href="style.css" type="text/css" media="screen"/>
  <link rel="stylesheet" href="css/slimbox2.css" type="text/css" media="screen" /> -->
    <link rel="stylesheet" href="grid.css" type="text/css" media="screen" />
    <link rel="stylesheet" href="modal.css" type="text/css" media="screen" />
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
    <!-- previous/next -->
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

    <ul>
        <!--gallery-->
        <?php
        include "includes.inc";
        include "src/Media.php";

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
                        $files[] = new Media($dir, $file);
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
                    echo "<li>";
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
                    echo "</li>";
                }
            }
            if (!empty($files)) {
                if (sizeof($files) > 0) {
                    usort($files, [Media::class, "cmp_obj"]);
                }

                $counter = 0;
                foreach ($files as $file) {
                    echo "<li>";
                    echo $file->getThumbUrl($counter++);
                    echo "</li>";
                }
            }
        }
        ?>
        <li></li>
    </ul>

    <!-- The Modal/Lightbox -->
    <div id="myModal" class="modal">
        <span class="close cursor" onclick="closeModal()">&times;</span>
        <div class="modal-content">
            <?php
            foreach ($files as $file) {
                echo $file->getPreviewUrl();
            }
            ?>
        </div>

        <!-- Next/previous controls -->
        <a class="prev" onclick="plusSlides(-1)">&#10094;</a>
        <a class="next" onclick="plusSlides(1)">&#10095;</a>

    </div>
    <script>
        // Open the Modal
        function openModal() {
            document.body.style.overflow = "hidden";
            document.getElementById("myModal").style.display = "block";
        }

        // Close the Modal
        function closeModal() {
            document.body.style.overflow = "auto";
            document.getElementById("myModal").style.display = "none";
        }

        var slideIndex = 1;
        showSlides(slideIndex);

        // Next/previous controls
        function plusSlides(n) {
            showSlides(slideIndex += n);
        }

        // Thumbnail image controls
        function currentSlide(n) {
            showSlides(slideIndex = n);
        }

        function showSlides(n) {
            var i;
            var slides = document.getElementsByClassName("mySlides");
            var dots = document.getElementsByClassName("demo");
            var captionText = document.getElementById("caption");
            if (n > slides.length) {
                slideIndex = 1
            }
            if (n < 1) {
                slideIndex = slides.length
            }
            for (i = 0; i < slides.length; i++) {
                slides[i].style.display = "none";
            }
            for (i = 0; i < dots.length; i++) {
                dots[i].className = dots[i].className.replace(" active", "");
            }
            slides[slideIndex - 1].style.display = "block";
            dots[slideIndex - 1].className += " active";
            captionText.innerHTML = dots[slideIndex - 1].alt;
        }
    </script>

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
                                    echo $_SERVER['PHP_SELF'] . "?fileLocation=" . $dir; ?>">
            <input name="tag" type="text"></input><input type="submit" value="OK" />
        </form>

        <?php
        include "includes.inc";
        if (isset($POST) && in_array("tag", $POST)) {
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
<script type="text/javascript">
    var availableHeight = window.innerHeight - 20;
    document.getElementById("center").style.height = availableHeight;

    window.onresize = resize;

    function resize() {
        var availableHeight = window.innerHeight - 20;
        document.getElementById("center").style.height = availableHeight;
    }
</script>

</html>