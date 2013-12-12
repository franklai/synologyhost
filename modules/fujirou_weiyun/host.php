<?php

if (!class_exists('FujirouCommon')) {
    if (file_exists(__DIR__.'/include/fujirou_common.php')) {
        include_once(__DIR__.'/include/fujirou_common.php');
    } else if (file_exists(__DIR__.'/../../include/fujirou_common.php')) {
        include_once(__DIR__.'/../../include/fujirou_common.php');
    }
}

if (!class_exists('Requests')) {
    if (file_exists(__DIR__.'/include/Requests.php')) {
        require_once __DIR__.'/include/Requests.php';
    } else if (file_exists(__DIR__.'/../../include/Requests.php')) {
        require_once __DIR__.'/../../include/Requests.php';
    }
}
Requests::register_autoloader();

class FujirouHostWeiyun
{

    public function __construct($url, $username, $password, $hostInfo) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
        $this->userAgent = 'Mozilla/5.0 (Android 4.4)';
    }

    public function GetDownloadInfo() {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST'
        );

        $url = $this->url;

        // weiyun,
        // url would be
        // http://share.weiyun.com/ or http://url.cn/
        //
        // 1. use Mobile user agent to get content
        //    http://share.weiyun.com/cf4014bb8ea286256aefc548b76d6292
        // 2. parse content to dl url
        // 3. use the same user agent and add referer to get redirect url
        //    http://sync.box.qq.com/share_dl.fcg?sharekey=cf4014bb8ea286256aefc548b76d6292
        // 4. report redirect url (ftn_handler)
        //    http://sz.yun.ftn.qq.com:80/ftn_handler/
        //
        $options = array(
            'useragent' => $this->userAgent
        );
        $response = Requests::get($url, array(), $options);
        if ($response->status_code !== 200) {
            return $ret;
        }

        $content = $response->body;
        $cookies = $response->cookies;

        $pattern = '/(http:\/\/web.cgi.weiyun.com[^"]+)/';
        $dlUrl = FujirouCommon::getFirstMatch($content, $pattern);
        if (!$dlUrl) {
            return $ret;
        }

        $options = array(
            'useragent' => $this->userAgent,
            'follow_redirects' => false,
            'cookies' => $cookies
        );
        $headers = array(
            'referer' => 'http://share.weiyun.com/'
        );
        $response = Requests::get($dlUrl, $headers, $options);
        if ($response->status_code !== 302) {
            return $ret;
        }
        $finalUrl = $response->headers['Location'];

        $pattern = '/fname=([^&]+)&/';
        $fname = FujirouCommon::getFirstMatch($finalUrl, $pattern);
        $fname = FujirouCommon::sanitizePath(urldecode($fname));

        $ret = array(
            DOWNLOAD_URL      => $finalUrl,
            DOWNLOAD_FILENAME => $fname
        );

        return $ret;
    }

}

// how to test this script,
// just type the following in DS console
// php -d open_basedir= host.php
if (basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostWeiyun';
    $url = 'http://url.cn/SUk0NR';
    $url = 'http://share.weiyun.com/8e921eae751070bbda9ccaf9a654c63c';
    $url = 'http://url.cn/MOR43j';
    $url = 'http://share.weiyun.com/4f7aad1ce0ebaeea694a2d899f3c7319';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
