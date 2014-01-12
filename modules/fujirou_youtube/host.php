<?php
if (!class_exists('Common')) {
    require('common.php');
}
if (!class_exists('Curl')) {
    require('curl.php');
}

class FujirouHostYouTube
{
    public function __construct($url, $username, $password, $hostInfo, $verbose=false) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
        $this->verbose = $verbose;
    }

    private function printMsg($msg)
    {
        if (!$this->verbose) {
            return;
        }

        if (is_array($msg)) {
            print_r($msg);
        } else {
            echo $msg;
        }
    }

    public function GetDownloadInfo() {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST'
        );

        $url = $this->url;

        // 1. get html of YouTube url
        $response = new Curl($url);
        $html = $response->get_content();

        // parse player url
        $this->playerUrl = $this->getPlayerUrl($html);

        // 2. find url_encoded_fmt_stream_map
        $encodedMapString = $this->getMapString($html);
        $mapString = $encodedMapString;

        $adaptiveFormats = $this->getAdaptiveFmts($html);
        $adaptiveMap = $this->parseMapString($adaptiveFormats, $html);

        // 3. parse map string
        $videoMap = $this->parseMapString($mapString, $html);

        $this->printMsg("\n == url map ==\n");
        $this->printMsg($videoMap);
        $this->printMsg("\n == adaptive ==\n");
        $this->printMsg($adaptiveMap);
        $this->printMsg("\n\n");

        $video = $this->chooseVideo($videoMap, $adaptiveMap);
        if (empty($video)) {
            return $ret;
        }
        $videoUrl = $video["link"];
        $videoExt = $video["ext"];

        // title
        $title = $this->parseTitle($html);

        if (empty($videoUrl)) {
            return $ret;
        }

        $filename = Common::sanitizePath($title) . "." . $videoExt;

        $ret = array(
            DOWNLOAD_URL      => $videoUrl,
            DOWNLOAD_FILENAME => $filename
        );

        return $ret;
    }

    private function hasItagMapping($itag)
    {
        return array_key_exists($itag, $this->itagMap);
    }

    private function getTitleByItag($title, $itag)
    {
        $itagString = "itag value $itag";

        if (array_key_exists($itag, $this->itagMap)) {
            $itagString = $this->itagMap[$itag];
        }

        return sprintf("%s (%s)", $title, $itagString);
    }

    private function parseMapString($mapString, $html)
    {
        $urlList = explode(',', $mapString);

        $videoMap = array();

        foreach ($urlList as $url) {
            if (empty($url)) {
                continue;
            }

            $queries = str_replace('\\u0026', '&', $url);

            parse_str($queries, $items);

            $signature = '';
            if (array_key_exists('sig', $items)) {
                $signature = $items['sig'];
                $this->printMsg("\tFound sig: $signature\n");
            } elseif (array_key_exists('s', $items)) {
                // encrypted signature
                $encrypted = $items['s'];
                $signature = $this->decryptSignature($encrypted);
                $this->printMsg("\tFound s: $signature\n");
            }

            $paramSignature = '';
            if (!empty($signature)) {
                $paramSignature = '&signature=' . $signature;
            }

            $videoMap[$items['itag']] = $items['url'] . $paramSignature;
        }

        return $videoMap;
    }

    private function getPlayerUrl($html)
    {
        $pattern = '/"assets":.+?"js":\s*("[^"]+")/';
        $jsonUrl = Common::getFirstMatch($html, $pattern);
        $url = json_decode($jsonUrl, true);

        return $url;
    }

    private function decryptSignature($encrypted)
    {
        // get player content first

        $url = $this->playerUrl;
        if (substr($url, 0, 2) === '//') {
            $url = 'https:' . $url;
        }

        $response = new Curl($url);
        $html = $response->get_content();

        $pattern = '/signature=([a-zA-Z]+)/';
        $funcName = Common::getFirstMatch($html, $pattern);

        $pattern = sprintf("/function %s\(.*?\)\{(.+?)\}/", $funcName);
        $funcContent = Common::getFirstMatch($html, $pattern);


        $pattern = sprintf("/function %s\(.*?\)\{(.+?)\}/", 'Sk');
        $Sk = Common::getFirstMatch($html, $pattern);

//         if ($playerId === 'vfln8xPyM')
        // mane shite

        $a = str_split($encrypted);

        // swap(0, 36)
        // swap(0, 14)
        // slice(1)
        // reverse()
        // slice(1)
        // swap(0, 54)
        $a = $this->swap($a, 0, 36);
        $a = $this->swap($a, 0, 14);
        $a = array_slice($a, 1);
        $a = array_reverse($a);
        $a = array_slice($a, 1);
        $a = $this->swap($a, 0, 54);

        $decrypted = implode('', $a);

//         echo "\n== == ==\n";
//         echo "player url: $url";
//         echo "\n== == ==\n";
//         echo "funcName: $funcName";
//         echo "\n== == ==\n";
//         echo "content: $funcContent";
//         echo "\n== == ==\n";
//         echo "Sk: $Sk";
//         echo "\n== == ==\n";
//         echo "encrypted: $encrypted";
//         echo "\n== == ==\n";
//         echo "decrypted: $decrypted";
//         echo "\n== == ==\n";
//         echo "\n== == ==\n";
        return $decrypted;
    }

    private function swap(&$array, $x, $y)
    {
        $tmp = $array[$x];
        $array[$x] = $array[$y];
        $array[$y] = $tmp;

        return $array;
    }

    private function getMapString($html)
    {
        $pattern = '/"url_encoded_fmt_stream_map": "([^"]+)"/';
        return Common::getFirstMatch($html, $pattern);
    }

    private function getAdaptiveFmts($html)
    {
        $pattern = '/"adaptive_fmts": "([^"]+)"/';
        return Common::getFirstMatch($html, $pattern);
    }

    private function parseTitle($html)
    {
        // property="og:title" content="Don&#039;t Look Back in Anger"
        $pattern = '/property="og:title" content="([^"]+)"/';
        $encodedTitle = Common::getFirstMatch($html, $pattern);
        return htmlspecialchars_decode($encodedTitle, ENT_QUOTES);
    }

    private function chooseVideo($videoMap, $adaptiveMap) {
        // http://en.wikipedia.org/wiki/YouTube#Quality_and_codecs
        $itagMap = array(
            "37" => "mp4", // "1080p, MP4, H.264, AAC"
            "22" => "mp4", // "720p, MP4, H.264, AAC",
            "34" => "flv", // "360p, FLV, H.264, AAC",
            "18" => "mp4", // "360p, MP4, H.264, AAC",
            "35" => "flv", // "480p, FLV, H.264, AAC",
            "5"  => "flv" // "240p, FLV, H.263, MP3",
        );

        // http://en.wikipedia.org/wiki/YouTube#Quality_and_codecs
        $adaptiveItagMap = array(
            "137" => "mp4", // DASH video, "1080p", MP4, H.264, High
            "136" => "mp4", // DASH video, "720p", MP4, H.264, Main
            "135" => "mp4", // DASH video, "480p", MP4, H.264, Main
            "134" => "mp4", // DASH video, "360p", MP4, H.264, Main
            "133" => "mp4", // DASH video, "240p", MP4, H.264, Main

            "140" => "mp4", // DASH audio, AAC, 128
            "139" => "mp4", // DASH audio, AAC, 48
            "172" => "webm", // DASH audio, Vorbis, 192
            "171" => "webm" // DASH audio, Vorbis, 128
        );

        if (empty($videoMap)) {
            return FALSE;
        }

        foreach ($itagMap as $itag => $ext) {
            if (array_key_exists($itag, $videoMap)) {
                return array(
                    "link" => $videoMap[$itag],
                    "ext"  => $ext
                );
            }
        }

        return array(
            "link" => reset($videoMap),
            "ext"  => "flv"
        );
    }
}

// php -d open_basedir= host.php
if (!empty($argv) && basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostYouTube';
//    $url = 'http://www.youtube.com/watch?v=tNC9V2ewsb4';
//     $url = 'https://www.youtube.com/watch?v=-jej8YS4Slk';
//    $url = 'http://www.youtube.com/watch?v=RY35O02Fg8M';
    $url = 'http://www.youtube.com/watch?v=iul4SBlHIf8';
$url = 'http://www.youtube.com/watch?v=FXg4LXsg14s';
$url = 'http://www.youtube.com/watch?v=tNo3LuZXA1w';
$url = 'http://www.youtube.com/watch?v=Ci8REzfzMHY';
$url = 'http://www.youtube.com/watch?v=UHFAjkD_LLg'; // Taylor Swift feat Paula Fernandes Long Live VEVO 1080p

    if ($argc >= 2) {
        $argument = $argv[1];
        if (substr(strtolower($argument), 0, 4) === 'http') {
            $url = $argument;
        }
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array(), true);

    echo "Get download info of '$url'\n\n";
    $info = $obj->GetDownloadInfo();

    print_r($info);
}

// vim: expandtab ts=4
?>
