<?php

if (!class_exists('Common')) {
    require('common.php');
}
if (!class_exists('Curl')) {
    require('curl.php');
}

class FujirouHostVimeo
{
    public function __construct($url, $username, $password, $hostInfo) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
    }

    public function onDownloaded() {
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
    public function GetDownloadInfo() {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST'
        );

        $url = $this->url;

        $videoId = $this->get_video_id($url);
        Common::debug('video id:' . $videoId);

        // 1. get html of vimeo url
        $response = new Curl($url, NULL, NULL, NULL);
        $html = $response->get_content();

        // 2. find json with video info
        $json = $this->get_json($html);

        // 3. get url and title
        $videoUrl = $this->get_video_url($json);
        Common::debug("final url: $videoUrl");

        $title = $this->get_title($json);
        Common::debug("title: $title");

        if (empty($videoUrl)) {
            return $ret;
        }

        $filename = Common::sanitizePath($title) . ".mp4";

        $ret = array(
            DOWNLOAD_URL      => $videoUrl,
            DOWNLOAD_FILENAME => $filename
        );

        return $ret;
    }

    private function get_json($html)
    {
        $pattern = '/"GET","(https:\/\/player.vimeo.com\/video\/.*?)",/';
        $config_url = Common::getFirstMatch($html, $pattern);
        Common::debug('config-url:' . $config_url);
        if (empty($config_url)) {
            return false;
        }

        $response = new Curl($config_url, NULL, NULL, NULL);
        $raw_json = $response->get_content();

        $json = json_decode($raw_json, true);

        return $json;
    }

    private function get_video_id($url)
    {
        $pattern = '/vimeo.com\/([0-9]+)/i';
        return Common::getFirstMatch($url, $pattern);
    }

    private function get_json_by_keys(&$json, $key_string)
    {
        $keys = explode('.', $key_string);

        $item =& $json;
        foreach ($keys as $key) {
            if (array_key_exists($key, $item)) {
                $item =& $item[$key];
            } else {
                return false;
            }
        }

        return $item;
    }

    private function get_video_url($json) {
        $items = $this->get_json_by_keys($json, 'request.files.progressive');
        if (!$items) {
            return false;
        }

        $max_width = 0;
        $url = '';
        foreach ($items as $item) {
            $width = $item['width'];
            if ($width > $max_width) {
                $max_width = $width;
                $url = $item['url'];
            }
        }

        return $url;
    }

    private function get_title($json) {
        if (isset($json['video']) && isset($json['video']['title'])) {
            return $json['video']['title'];
        }

        return false;
    }
}

// how to test this script,
// just type the following in DS console
// php -d open_basedir= host.php
if (!empty($argv) && basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostVimeo';
    $url = 'https://vimeo.com/15076572';

    if (count($argv) >= 2 && 1 === preg_match('/https?:\/\//', $argv[1])) {
        $url = $argv[1];
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
?>
