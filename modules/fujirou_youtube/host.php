<?php
if (!class_exists('Common')) {
    require('common.php');
}
if (!class_exists('Curl')) {
    require('curl.php');
}

class FujirouHostYouTube
{
    public function __construct($url, $username, $password, $hostInfo) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
    }

    public function GetDownloadInfo() {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST'
        );

        $url = $this->url;

        // 1. get html of YouTube url
        $response = new Curl($url);
        $html = $response->get_content();

        // 2. find url_encoded_fmt_stream_map
        $encodedMapString = $this->getMapString($html);
        $mapString = $encodedMapString;

        // 3. parse map string
        $videoMap = $this->parseMapString($mapString);
        $video = $this->chooseVideo($videoMap);
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

    private function parseMapString($mapString)
    {
        $urlList = explode(',', $mapString);

        $videoMap = array();

        foreach ($urlList as $url) {
            if (empty($url)) {
                continue;
            }

            $queries = str_replace('\\u0026', '&', $url);

            parse_str($queries, $items);

            $videoMap[$items['itag']] = $items['url'] . "&signature=" . $items['sig'];
        }

        return $videoMap;
    }

    private function getMapString($html)
    {
        $pattern = '/"url_encoded_fmt_stream_map": "([^"]+)"/';
        return Common::getFirstMatch($html, $pattern);
    }

    private function parseTitle($html)
    {
        // property="og:title" content="Don&#039;t Look Back in Anger"
        $pattern = '/property="og:title" content="([^"]+)"/';
        $encodedTitle = Common::getFirstMatch($html, $pattern);
        return htmlspecialchars_decode($encodedTitle, ENT_QUOTES);
    }

    private function chooseVideo($videoMap) {
        // http://en.wikipedia.org/wiki/YouTube#Quality_and_codecs
        $itagMap = array(
            "37" => "mp4", // "1080p, MP4, H.264, AAC"
            "22" => "mp4", // "720p, MP4, H.264, AAC",
            "18" => "mp4", // "360p, MP4, H.264, AAC",
            "35" => "flv", // "480p, FLV, H.264, AAC",
            "34" => "flv", // "360p, FLV, H.264, AAC",
            "5"  => "flv" // "240p, FLV, H.263, MP3",
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
    $url = 'http://www.youtube.com/watch?v=DzOQ-shPKV4';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
?>
