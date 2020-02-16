<?php

if (!class_exists('Common')) {
    require 'common.php';
}
if (!class_exists('Curl')) {
    require 'curl.php';
}

class FujirouHostDailymotion
{
    public function __construct($url, $username, $password, $hostInfo)
    {
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
            'stream_h264_ld_url',
        );
    }

    public function onDownloaded()
    {
        // dummy function to avoid PHP error log
        return true;
    }

    // shall return an array
    // {
    //     "downloadurl": ""
    // }
    //
    // other optional key is
    // "filename"
    // "cookiepath"
    public function GetDownloadInfo()
    {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST',
        );

        $url = $this->url;

        // 1. parse video id from url
        $videoId = $this->getVideoId($url);
        Common::debug('video id:' . $videoId);
        if (!$videoId) {
            Common::debug("Failed to get video id from url [$url].");
            return $ret;
        }

        // 2. get json info of video
        $jsonUrl = sprintf(
            "https://www.dailymotion.com/embed/video/%s",
            $videoId
        );
        Common::debug("JSON url is [$jsonUrl]");

        $response = new Curl($jsonUrl, null, null, null);
        $html = $response->get_content();

        $json = $this->getJsonFromHtml($html);
        if (!$json) {
            Common::debug('Failed to get json from html.');
            return $ret;
        }

        // 3. find url from json info
        $videoUrl = $this->getVideoUrl($json);
        if (empty($videoUrl)) {
            return $ret;
        }

        // 4. find title from json
        $videoTitle = $this->getVideoTitle($json);
        Common::debug("video title: $videoTitle");

        // TODO: check it's flv or mp4 format
        $filename = Common::sanitizePath($videoTitle) . ".mp4";

        $ret = array(
            DOWNLOAD_URL => $videoUrl,
            DOWNLOAD_FILENAME => $filename,
            DOWNLOAD_COOKIE => $this->tmpCookiePath,
        );

        return $ret;
    }

    private function getJsonFromHtml($html)
    {
        $prefix = '{"context":';
        $suffix = "};\n";
        $js = Common::getSubString($html, $prefix, $suffix);
        if (empty($js)) {
            Common::debug('Failed to find info json');
            return false;
        }

        $pattern = '/(\{"context":.*\});/';
        $json_string = Common::getFirstMatch($js, $pattern);
        return json_decode($json_string, true);
    }

    private function getVideoId($url)
    {
        $pattern = '/dailymotion.com\/video\/([a-zA-Z0-9]+)/i';
        $id = Common::getFirstMatch($url, $pattern);
        if ($id) {
            return $id;
        }

        $pattern = '/dailymotion.com\/embed\/video\/([a-zA-Z0-9]+)/i';
        $id = Common::getFirstMatch($url, $pattern);
        if ($id) {
            return $id;
        }

        // try playlist type
        $pattern = '/dailymotion.com\/playlist\/.*#video=(.*)/i';
        $id = Common::getFirstMatch($url, $pattern);

        return $id;
    }

    private function findVideoFromM3u8($url)
    {
        $response = new Curl($url);
        $m3u8 = $response->get_content();

        $pattern = '/PROGRESSIVE-URI="(http.*?\.mp4).*?"/';
        $matches = common::getAllFirstMatch($m3u8, $pattern);

        $length = count($matches);
        if ($length > 0) {
            // choose last video url, usually best video quality
            return $matches[$length - 1];
        }
        return null;
    }

    private function getVideoUrl($json)
    {
        $url = null;
        $type = null;

        if (!array_key_exists('metadata', $json)) {
            return $url;
        }
        $metadata = $json['metadata'];

        if (!array_key_exists('qualities', $metadata)) {
            return $url;
        }

        $qualities = $metadata['qualities'];

        foreach ($qualities as $q => $items) {
            Common::debug("quality [" . $q . "]");

            foreach ($items as $item) {
                $type = $item['type'];
                $url = $item['url'];
                Common::debug("\ttype [$type], url: $url");
            }
        }

        if ($type === 'application/x-mpegURL') {
            $url_from_m3u8 = $this->findVideoFromM3u8($url);
            if ($url_from_m3u8) {
                $url = $url_from_m3u8;
                common::debug("find video url: $url from m3u8");
                return $url;
            }
        }

        Common::debug("Choose last url: $url");

        $hash = md5($url);
        $this->tmpCookiePath = "/tmp/dailymotion.cookie.$hash.txt";
        $response = new Curl($url, null, null, $this->tmpCookiePath);
        $location = $response->get_header('Location');
        if (empty($location)) {
            return $url;
        }

        Common::debug("location: $location");
        $pos = strpos($location, "#");
        if ($pos === false) {
            return $location;
        }

        return substr($location, 0, $pos);
    }

    private function getVideoTitle($json)
    {
        if (!array_key_exists('metadata', $json)) {
            return null;
        }
        if (!array_key_exists('title', $json['metadata'])) {
            return null;
        }
        return $json['metadata']['title'];
    }
}

// how to test this script,
// just type the following in DS console
// php -d open_basedir= host.php
if (!empty($argv) && basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostDailymotion';
//     $url = 'http://www.dailymotion.com/video/xtdg4e_2012-mtv-vma-performance-recap-pink-taylor-swift-lil-wayne_music';
    //     $url = 'http://www.dailymotion.com/video/xnqusv_the-next-list-jake-shimabukuro_lifestyle?search_algo=2';
    //     $url = 'http://www.dailymotion.com/video/x25ef_jake-shimabukuro-virtuose-du-ukulel_music?search_algo=2';
    //     $url = 'http://www.dailymotion.com/video/xa02_while-my-ukulele-gently-weeps_music';
    //    $url = 'http://www.dailymotion.com/video/x1u5p24_20080809-vs';
    $url = 'http://www.dailymotion.com/video/x2kmre5_56kast-52-on-a-tous-des-choses-a-cacher-et-des-points-a-relier_tech';
    $url = 'http://www.dailymotion.com/video/x2bzn2n_taylor-swift-blank-space-live-at-kiis-fm-jingle-ball-2014_music';
    $url = 'http://www.dailymotion.com/playlist/x1hlho_ginji030_perfume-2/1#video=xt1mw1';

    $url = 'http://www.dailymotion.com/video/xn30yp_fairy-tail-bande-annonce-preview-film-2012_shortfilms'; // short, 00:31, 1.72MB
    $url = 'http://www.dailymotion.com/video/x4xeb5l_the-amazing-world-of-gumball-the-choices-s5e6_tv';
    $url = 'https://www.dailymotion.com/video/k5fGL5hXWOukIqsUUjz';
    $url = 'https://www.dailymotion.com/video/k5i3FTWRM6JAbZt3Uq8';

    if (count($argv) >= 2 && 0 === strncmp($argv[1], 'http', 4)) {
        $url = $argv[1];
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
