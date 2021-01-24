<?php

if (!class_exists('Common')) {
    require 'common.php';
}

class FujirouHostVimeo
{
    public function __construct($url, $username, $password, $hostInfo, $verbose = false)
    {
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

        $videoId = $this->get_video_id($url);
        $this->printMsg('video id:' . $videoId);

        // 1. get html of vimeo url
        $html = Common::getContent($url);

        // 2. find json with video info
        $json = $this->get_json($html);
        if (empty($json)) {
            return $ret;
        }

        // 3. get url and title
        $videoUrl = $this->get_video_url($json);
        $this->printMsg("final url: $videoUrl");

        $title = $this->get_title($json);
        $this->printMsg("title: $title");

        if (empty($videoUrl)) {
            return $ret;
        }

        $filename = Common::sanitizePath($title) . ".mp4";

        $ret = array(
            DOWNLOAD_URL => $videoUrl,
            DOWNLOAD_FILENAME => $filename,
        );

        return $ret;
    }

    private function get_json($html)
    {
        $pattern = '/window.vimeo.clip_page_config.player = (.*?);/';
        $json_str = Common::getFirstMatch($html, $pattern);
        if (empty($json_str)) {
            return false;
        }

        $player = json_decode($json_str, true);
        if (empty($player) || !array_key_exists('config_url', $player)) {
            $this->printMsg('Failed to get config_url');
            return false;
        }

        $config_url = $player['config_url'];
        $this->printMsg('config-url:' . $config_url);
        if (empty($config_url)) {
            return false;
        }

        $raw_json = Common::getContent($config_url);

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

        $item = &$json;
        foreach ($keys as $key) {
            if (array_key_exists($key, $item)) {
                $item = &$item[$key];
            } else {
                return false;
            }
        }

        return $item;
    }

    private function get_video_url($json)
    {
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

    private function get_title($json)
    {
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
    $url = 'https://vimeo.com/43234495'; // Perfume Desktop Disco

    if (count($argv) >= 2 && 1 === preg_match('/https?:\/\//', $argv[1])) {
        $url = $argv[1];
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
