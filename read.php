<?php
require_once __DIR__ . '/_shared.php';
require_once __DIR__ . '/vendor/autoload.php';

$page = intval($_GET['p'] ?? '0');

if (!is_file(DATA_QUERY_PATH)) {
    die('Not a file');
}

fileMTimeMod(DATA_QUERY_PATH, $_SERVER);

$small_query_path = strtolower(DATA_QUERY_PATH);

$comic_title = pathinfo(DATA_QUERY_PATH, PATHINFO_FILENAME);
$pageCnt = 0;
$transcriptionAvailable = false;
$transcript = '';

if (str_ends_with($small_query_path, '.cbz')) {
    $zipFile = new \PhpZip\ZipFile();
    $zipFile->openFile(DATA_QUERY_PATH);

    if ($zipFile->hasEntry('ComicInfo.xml')) {
        $comicInfoXmlRaw = $zipFile->getEntryContents('ComicInfo.xml');
        $parser = xml_parser_create();
        $comicInfoXml = null;
        $comicInfoXmlIndex = null;
        xml_parse_into_struct($parser, $comicInfoXmlRaw, $comicInfoXml, $comicInfoXmlIndex);

        foreach ($comicInfoXml as $comicInfoXmlEntry) {
            switch ($comicInfoXmlEntry['tag']) {
                case 'TITLE':
                    $comic_title = $comicInfoXmlEntry['value'];
                    break;

                default:
                    break;
            }
        }
    }

    $fileList = array_filter($zipFile->getListFiles(), 'filterImageFiles');
    $pageCnt = count($fileList);

    $transcriptionAvailable = $zipFile->hasEntry('transcript.txt');
    if ($transcriptionAvailable) {
        $transcript = $zipFile->getEntryContents('transcript.txt');
        $transcript = str_replace("\r\n", "\n", $transcript);
        $transcript = str_replace("\n---\n", "<hr />", $transcript);
        $transcript = str_replace("\n", "<br />", $transcript);
        $transcript = htmlentities($transcript);
    }

    $zipFile->close();
} else if (str_ends_with($small_query_path, '.pdf')) {
    if (filesize(DATA_QUERY_PATH) < 5_000_000) {
        function getPdfPages($path)
        {
            $pdf_str = file_get_contents($path);
            if (preg_match_all("/\/Count\s+(\d+)/", $pdf_str, $matches))
                return max($matches[1]);
            if (preg_match_all("/\/Page\W*(\d+)/", $pdf_str, $matches))
                return max($matches[1]);
            if (preg_match_all("/\/N\s+(\d+)/", $pdf_str, $matches))
                return max($matches[1]);

            return 0;
        }

        $pageCnt = getPdfPages(DATA_QUERY_PATH);
    }

    if ($pageCnt === 0) {
        $comic_pdf = new imagick();
        $comic_pdf->pingImage(DATA_QUERY_PATH);
        $pageCnt = $comic_pdf->getNumberImages();
    }
} else {
    http_response_code(400);
    die('Not a comic');
}



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlentities($comic_title) ?> - Reader</title>
    <style>
        html,
        body {
            height: 100%;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        img.page {
            max-height: 100%;
            max-width: 100%;
        }

        div.page-img-container {
            height: 100%;
            width: 100%;
        }

        div.page-img-list-container {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        div.page-container {
            height: calc(100% - 60px);
            width: 100%;
            text-align: center;
            position: relative;
        }

        div.page-container>a {
            display: block;
            position: absolute;
            top: 0;
            bottom: 0;
        }

        .prev-controller {
            left: 0;
            right: 50%;
        }

        .next-controller {
            right: 0;
            left: 50%;
        }

        header,
        footer {
            height: 30px;
            font-size: 18px;
            display: flex;
            margin: 0 15px;
            justify-content: space-between;
        }

        .flex-space {
            display: flex;
            margin: 0 15px;
            justify-content: space-between;
        }

        .modal-close {
            font-size: 32pt;
            color: black;
            text-decoration: none;
        }

        .modal {
            position: fixed;
            z-index: 150;
            left: 0;
            top: 0;
            right: 0;
            bottom: 0;
            background-color: white;
            overflow-y: scroll;
            padding: 30px;
        }

        .modal h2 {
            margin: 0;
        }

        .hide {
            display: none;
        }
    </style>
</head>

<body>
    <header>
        <div>
            <a href="list.php?path=<?= urlencode(dirname($path)) ?>">Back</a>
        </div>
        <div>
            <?= htmlentities($comic_title) ?>
        </div>
    </header>
    <div class="page-container">
        <div class="page-img-list-container">
            <?php for ($p = 1; $p <= $pageCnt; $p++) : ?>
                <div class="page-img-container" id="<?= $p ?>">
                    <img class="page" src="img.php?path=<?= urlencode($path) ?>&p=<?= $p ?>" alt="Page <?= $p ?>" loading="lazy" />
                </div>
            <?php endfor; ?>
        </div>
        <a class="prev-controller" href="javascript:void(0)" onclick="chPageDec(0)"></a>
        <a class="next-controller" href="javascript:void(0)" onclick="chPageInc(0)"></a>
    </div>
    <footer>
        <div>
            <a href="javascript:void(0)" onclick="chPageDec(0)">Prev</a>
        </div>
        <div>
            <?php if ($transcriptionAvailable) : ?>
                <a href="javascript:void(0)" onclick="transcriptsModal.classList.remove('hide')">Transcript</a>
            <?php else : ?>
                No transcript
            <?php endif; ?>
        </div>
        <div>
            <span id="pgNum">1</span> / <?= $pageCnt ?>
        </div>
        <div>
            <a href="javascript:void(0)" onclick="chPageInc(0)">Next</a>
        </div>
    </footer>
    <?php if ($transcriptionAvailable) : ?>
        <div class="modal hide" id="transcriptsModal">
            <div class="flex-space">
                <div>
                    <h2>Transcript</h2>
                </div>
                <div>
                    <a href="javascript:void(0)" onclick="transcriptsModal.classList.add('hide')" class="modal-close">&times;</a>
                </div>
            </div>
            <hr>
            <div>
                <?= $transcript /* HTML Entities Replaced String */ ?>
            </div>
        </div>
        <script>
            const transcriptsModal = document.getElementById('transcriptsModal');
        </script>
    <?php endif; ?>
    <script>
        const pgNum = document.getElementById('pgNum');

        function chPageDec() {
            const pageUrl = window.location.hash.substring(1);
            if (pageUrl === '') {
                return;
            }
            const page = parseInt(pageUrl);
            if (page <= 1)
                return;
            window.location.hash = '#' + (page - 1);
            pgNum.innerText = page - 1;
        }

        function chPageInc() {
            const pageUrl = window.location.hash.substring(1);
            if (pageUrl === '') {
                window.location.hash = '#2';
                pgNum.innerText = 2;
                return;
            }
            const page = parseInt(pageUrl);
            if (page >= <?= $pageCnt ?>)
                return;
            window.location.hash = '#' + (page + 1);
            pgNum.innerText = page + 1;
        }
    </script>
</body>

</html>