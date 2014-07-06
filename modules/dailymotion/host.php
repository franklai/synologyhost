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

        // type of video url please check the following url
        // http://www.dailymotion.com/doc/api/obj-video.html
        $this->video_url_list = array(
            'stream_h264_hd1080_url',
            'stream_h264_hd_url',
            'stream_h264_hq_url',
            'stream_h264_url',
            'stream_h264_ld_url'
        );
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
            "http://www.dailymotion.com/embed/video/%s",
            $videoId
        );
        $response = new Curl($jsonUrl, NULL, NULL, NULL);
        $html = $response->get_content();

        $json = $this->getJsonFromHtml($html);
        if (!$json) {
            return $ret;
        }

        // 3. find url from json info
        $videoUrl = $this->getVideoUrl($json);
        Common::debug("final url: $videoUrl");
        if (empty($videoUrl)) {
            return $ret;
        }

        // 4. find title from json
        $videoTitle = $this->getVideoTitle($json);
        Common::debug("video title: $videoTitle");

        // TODO: check it's flv or mp4 format
        $filename = Common::sanitizePath($videoTitle) . ".mp4";

        $ret = array(
            DOWNLOAD_URL      => $videoUrl,
            DOWNLOAD_FILENAME => $filename
        );

        return $ret;
    }

    private function getJsonFromHtml($html)
    {
        $prefix = 'var info = {';
        $suffix = '}},';
        $js = Common::getSubString($html, $prefix, $suffix);
        if (empty($js)) {
            return false;
        }

        $pattern = '/var info = (\{.*\}\}),/';
        $json_string = Common::getFirstMatch($js, $pattern);
        return json_decode($json_string, true);
    }

    private function getVideoId($url)
    {
        $pattern = '/dailymotion.com\/video\/([^_]+)_/i';
        return Common::getFirstMatch($url, $pattern);
    }

    private function getVideoUrl($json) {
        $url = null;

        foreach ($this->video_url_list as $key) {
            if (array_key_exists($key, $json) && isset($json[$key])) {
                $url = $json[$key];
                break;
            }
        }

        return $url;
    }

    private function getVideoTitle($json) {
        return $json['title'];
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
//     $url = 'http://www.dailymotion.com/video/xa02_while-my-ukulele-gently-weeps_music';
//    $url = 'http://www.dailymotion.com/video/x1u5p24_20080809-vs';
    $url = 'http://www.dailymotion.com/video/x1dt64a_%E5%AE%89%E5%AE%A4%E5%A5%88%E7%BE%8E%E6%81%B5-contrail-500th-live-%E3%82%A2%E3%83%B3%E3%82%B3%E3%83%BC%E3%83%AB_music';
    $url = 'http://www.dailymotion.com/video/x1xnacg_2014-05-30-%E5%B5%90-%E8%AA%B0%E3%82%82%E7%9F%A5%E3%82%89%E3%81%AA%E3%81%84-m%E3%82%B9%E3%83%86_music';

    if (count($argv) >= 2 && 0 === strncmp($argv[1], 'http://', 7)) {
        $url = $argv[1];
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

    
// vim: expandtab ts=4
?>
