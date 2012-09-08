<?php

if (!class_exists('Common')) {
    require('common.php');
}
if (!class_exists('Curl')) {
    require('curl.php');
}

class FujirouHostDailymotion
{
    public function __construct($url, $username, $password, $hostInfo) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
    }

    // shall return an array
    // {
    //     "downloadurl": ""
    // }
    //
    // other optional key is
    // "filename"
    // "cookiepath"
    public function GetDownloadInfo() {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST'
        );

        $url = $this->url;

        // 1. parse video id from url
        $videoId = $this->getVideoId($url);
        Common::debug('video id:' . $videoId);

        // 2. get json info of video
        $jsonUrl = sprintf(
            "http://www.dailymotion.com/sequence/full/%s",
            $videoId
        );
        $response = new Curl($jsonUrl, NULL, NULL, NULL);
        $json = $response->get_content();

        // 3. find url from json info
        $videoUrl = $this->getVideoUrl($json);
        Common::debug("final url: $videoUrl");

        // 4. find title from json
        $videoTitle = $this->getVideoTitle($json);
        Common::debug("video title: $videoTitle");

        if (empty($videoUrl)) {
            return $ret;
        }

        // TODO: check it's flv or mp4 format
        $filename = Common::sanitizePath($videoTitle) . ".mp4";

        $ret = array(
            DOWNLOAD_URL      => $videoUrl,
            DOWNLOAD_FILENAME => $filename
        );

        return $ret;
    }

    private function getVideoId($url)
    {
        $pattern = '/dailymotion.com\/video\/([^_]+)_/i';
        return Common::getFirstMatch($url, $pattern);
    }

    private function getVideoUrl($json) {
        $pattern = '/"hqURL":"("[^"]+")"/';
        $url = Common::getFirstMatch($json, $pattern);

        if (empty($url)) {
            // failed to find hqURL, try sd
            $pattern = '/"sdURL":"([^"]+)"/';
            $url = Common::getFirstMatch($json, $pattern);
        }

        if (empty($url)) {
            // hq and sd both failed, try ld
            $pattern = '/"ldURL":"([^"]+)"/';
            $url = Common::getFirstMatch($json, $pattern);
        }

        // manually json decode the string
        $url = str_replace('\\/', '/', $url);

        return $url;
    }

    private function getVideoTitle($json) {
        $pattern = '/"DMTITLE":"([^"]+)"/';
        return Common::getFirstMatch($json, $pattern);
    }
}

// how to test this script,
// just type the following in DS console
// php -d open_basedir= host.php
if (basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostDailymotion';
//     $url = 'http://www.dailymotion.com/video/xtdg4e_2012-mtv-vma-performance-recap-pink-taylor-swift-lil-wayne_music';
//     $url = 'http://www.dailymotion.com/video/xnqusv_the-next-list-jake-shimabukuro_lifestyle?search_algo=2';
//     $url = 'http://www.dailymotion.com/video/x25ef_jake-shimabukuro-virtuose-du-ukulel_music?search_algo=2';
    $url = 'http://www.dailymotion.com/video/xa02_while-my-ukulele-gently-weeps_music';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

    
// vim: expandtab ts=4
?>
