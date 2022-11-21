<html>

<head>
    <!-- <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script>
  <script type="text/javascript" src="js/slimbox2.js"></script>
  <link rel="stylesheet" href="style.css" type="text/css" media="screen"/>
  <link rel="stylesheet" href="css/slimbox2.css" type="text/css" media="screen" /> -->
    <!-- <link rel="stylesheet" href="grid.css" type="text/css" media="screen" /> -->
    <link rel="stylesheet" href="css/modal.css" type="text/css" media="screen" />
    <link rel="stylesheet" href="css/breadcrumb.css" type="text/css" media="screen" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.0/jquery.min.js"></script>
    <script type="text/javascript" src="js/jquery.mosaic.min.js"></script>
    <link rel="stylesheet" type="text/css" href="css/jquery.mosaic.min.css" />
</head>

<body>
    <!-- Breadcrumb navigation 2.0 -->
    <div class="navigation">
        <div>
            <ul class="breadcrumb">
                <li><a href="DirHandler.php?fileLocation=/">Home</a></li>
                <?php
                // file_put_contents('php://stdout', 'Hello world!!!');
                $dir = "/";
                if (array_key_exists('fileLocation', $_GET)) {
                    $dir = htmlspecialchars($_GET['fileLocation']);
                    if (empty($dir)) {
                        $dir = "/";
                    } else {
                        $dir = rtrim($dir, "/");
                    }
                }
                $dirNames = explode('/', $dir);
                $dirName = "";
                for ($i = 1; $i < sizeof($dirNames); $i++) {
                    $dirName = $dirName . "/" . $dirNames[$i];
                    echo "<li><a class=\"baseNavigation\" href=\"DirHandler.php?fileLocation=" . $dirName . "\">" . $dirNames[$i] . "</a></li>";
                }
                ?>
            </ul>
        </div>
        <div class="split">
            <!-- previous/next -->
            <?php
            include "includes.inc";
            include "src/Dal.php";
            $dal = new Dal();

            $prevAndNext = $dal->getPreviousAndNextDirectory($dir);
            // var_dump($prevAndNext);
            if (!empty($prevAndNext[0])) {
                echo "<a href=\"DirHandler.php?fileLocation=" . $prevAndNext[0] . "\">&lt;</a>";
            } else {
                echo "<span class=\"inactivenavigation\">&lt;</span>";
            }
            if (!empty($prevAndNext[1])) {
                echo "<a href=\"DirHandler.php?fileLocation=" . $prevAndNext[1] . "\">&gt;</a>";
            } else {
                echo "<span class=\"inactivenavigation\">&gt;</span>";
            }
            ?>
        </div>
    </div>
    <div style="clear:both"></div>
    <div id="gallery">
        <!--gallery-->
        <?php
        include "includes.inc";
        include "src/functions.php";
        include "src/Media.php";

        // $saveDirName = "/";
        // if (array_key_exists("fileLocation", $_GET)) {
        // $saveDirName = htmlspecialchars($_GET['fileLocation']);
        // }

        $dir = "/";
        if (array_key_exists("fileLocation", $_GET)) {
            $dir = htmlspecialchars($_GET['fileLocation']);
        }
        if ($dal->dirScanned($dir)) {
            $files = $dal->getMediaForDirectory($dir);
        }
        if (!empty($files)) {
            if (sizeof($files) > 0) {
                usort($files, [Media::class, "cmp_obj"]);
            }
            $counter = 0;
            foreach ($files as $file) {
                echo $file->getThumbUrl($counter++);
            }
        }
        ?>
    </div>

    <script>
        $('#gallery').Mosaic({
            maxRowHeight: 300,
            maxRowHeightPolicy: 'tail',
            innerGap: 1,
            showTailWhenNotEnoughItemsForEvenOneRow: true
        });
    </script>

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