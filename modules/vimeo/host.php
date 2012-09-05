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

        // 2. find signature and timestamp
        $signature = $this->get_signature($html);
        $timestamp = $this->get_timestamp($html);
        $isHD = $this->get_is_hd($html);
        Common::debug("signature: $signature, timestamp: $timestamp");

        // 3. send 2nd request, and get Location value
        $videoUrl = $this->get_video_url($videoId, $signature, $timestamp, $isHD);
        Common::debug("final url: $videoUrl");

        $title = $this->parse_title($html);

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

    private function get_video_id($url)
    {
        $pattern = '/vimeo.com\/([0-9]+)/i';
        return Common::getFirstMatch($url, $pattern);
    }

    private function get_signature($html)
    {
        $pattern = '/"signature":"([0-9a-z]+)"/i';
        return Common::getFirstMatch($html, $pattern);
    }

    private function get_timestamp($html)
    {
        $pattern = '/"timestamp":([0-9]+)/i';
        return Common::getFirstMatch($html, $pattern);
    }

    private function get_is_hd($html)
    {
        $pattern = '<meta itemprop="videoQuality" content="HD">';
        return Common::hasString($html, $pattern);
    }

    private function get_video_url($videoId, $signature, $timestamp, $isHD) {
        // http://player.vimeo.com/play_redirect?clip_id=12392080&sig=e62526d8ad02d4a36f8c820df6d60eee&time=1346743364&quality=sd&codecs=H264,VP8,VP6&type=moogaloop_local&embed_location=
        $requestUrl = sprintf(
            "http://player.vimeo.com/play_redirect?clip_id=%s&sig=%s&time=%s&quality=%s&codecs=H264,VP8,VP6",
            $videoId, $signature, $timestamp, $isHD ? 'hd' : 'sd'
        );

        $response = new Curl($requestUrl, NULL, NULL, NULL);
        $location = $response->get_header('Location');

        return $location;
    }

    private function parse_title($html) {
        // property="og:title" content="Don&#039;t Look Back in Anger"
        $pattern = '/property="og:title" content="([^"]+)"/';
        $encodedTitle = Common::getFirstMatch($html, $pattern);
        return html_entity_decode($encodedTitle, ENT_QUOTES, 'UTF-8');
    }
}

// how to test this script,
// just type the following in DS console
// php -d open_basedir= host.php
if (basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostVimeo';
    $url = 'http://vimeo.com/15076572';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
?>
