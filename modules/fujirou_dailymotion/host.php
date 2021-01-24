<?php

if (!class_exists('Common')) {
    require 'common.php';
}

class FujirouHostDailymotion
{
    public function __construct($url, $username, $password, $hostInfo, $verbose = false)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
        $this->verbose = $verbose;

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
        $this->printMsg("video id: $videoId");
        if (!$videoId) {
            $this->printMsg("Failed to get video id from url [$url].");
            return $ret;
        }

        // 2. get json info of video
        $jsonUrl = sprintf(
            "https://www.dailymotion.com/embed/video/%s",
            $videoId
        );
        $this->printMsg("JSON url is [$jsonUrl]");

        $html = Common::getContent($jsonUrl);

        $json = $this->getJsonFromHtml($html);
        if (!$json) {
            $this->printMsg('Failed to get json from html.');
            return $ret;
        }

        // 3. find url from json info
        $videoUrl = $this->getVideoUrl($json);
        if (empty($videoUrl)) {
            $this->printMsg('Failed to get video url from json.');
            return $ret;
        }

        // 4. find title from json
        $videoTitle = $this->getVideoTitle($json);
        $this->printMsg("video title: $videoTitle");

        // TODO: check it's flv or mp4 format
        $filename = Common::sanitizePath($videoTitle) . ".mp4";

        $ret = array(
            DOWNLOAD_URL => $videoUrl,
            DOWNLOAD_FILENAME => $filename,
        );

        return $ret;
    }

    private function getJsonFromHtml($html)
    {
        $prefix = '{"context":';
        $suffix = "};<";
        $js = Common::getSubString($html, $prefix, $suffix);
        if (empty($js)) {
            $this->printMsg('Failed to find info json');
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
        $m3u8 = Common::getContent($url);

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
            $this->printMsg("quality [" . $q . "]");

            foreach ($items as $item) {
                $type = $item['type'];
                $url = $item['url'];
                $this->printMsg("\ttype [$type], url: $url");
            }
        }

        if ($type === 'application/x-mpegURL') {
            $url_from_m3u8 = $this->findVideoFromM3u8($url);
            if ($url_from_m3u8) {
                $url = $url_from_m3u8;
                $this->printMsg("find video url: $url from m3u8");
                return $url;
            }
        }

        $this->printMsg("Choose last url: $url");

        return $url;
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

    $url = 'https://www.dailymotion.com/video/xt1mw1?playlist=x1hlho'; // Perfume 3rd Tour JPN - チョコレイト・ディスコ

    $url = 'https://www.dailymotion.com/video/k5fGL5hXWOukIqsUUjz';
    $url = 'https://www.dailymotion.com/video/k5i3FTWRM6JAbZt3Uq8';
    $url = 'https://www.dailymotion.com/video/x65eahd'; // 【妙WOW種子】Seed - 23 圍棋女神黑嘉嘉 生日快樂！

    if (count($argv) >= 2 && 0 === strncmp($argv[1], 'http', 4)) {
        $url = $argv[1];
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
