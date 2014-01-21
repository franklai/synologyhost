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

class FujirouHostBaidu
{

    public function __construct($url, $username, $password, $hostInfo, $verbose=false) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
//         $this->userAgent = 'Mozilla/5.0 (Windows NT 6.3; rv:26.0) Gecko/20100101 Firefox/26.0';
        $this->userAgent = '';
        $this->verbose = $verbose;
        $this->downloadPrefix = 'http://pan.baidu.com/share/download';

        $this->parameterPatterns = array(
            'uk' => '/FileUtils.share_uk="([0-9]+)"/',
            'shareid' => '/FileUtils.share_id="([0-9]+)"/',
            'timestamp' => '/FileUtils.share_timestamp="([0-9]+)"/',
            'sign' => '/FileUtils.share_sign="([a-z0-9]+)"/'
        );

        $this->defaultParameters = array(
            'channel' => 'chunlei',
            'clienttype' => '0',
            'web' => '1',
            'bdstoken' => 'null'
        );
    }

    private function printMsg($msg) {
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

        $options = array(
            'useragent' => $this->userAgent
        );
        $response = Requests::get($url, array(), $options);
        if ($response->status_code !== 200) {
            return $ret;
        }

        $content = $response->body;
        $cookies = $response->cookies;

        $dlUrl = $this->getDownloadUrl($content);
        if (!$dlUrl) {
            $this->printMsg("Failed to get dl url.");
            return $ret;
        }
        $this->printMsg("dl link url: $dlUrl\n");

        $fidList = $this->getDownloadFidList($content);
        if (!$fidList) {
            $this->printMsg("Failed to get fid_list.");
            return $ret;
        }

        $filename = $this->getFilename($content);

        $options = array(
            'useragent' => $this->userAgent,
            'cookies' => $cookies
        );
        $headers = array(
            'referer' => $url
        );
        $data = array(
            'fid_list' => "[\"$fidList\"]"
        );
        $response = Requests::post($dlUrl, $headers, $data, $options);
        $content = $response->body;

        $this->printMsg("$content\n");

        $json = json_decode($content, true);
        if (!$json) {
            $this->printMsg("Failed to decode content to json: $content\n");
            return $ret;
        }

        if ($json['errno'] === -19) {
            $this->printMsg("Captcha showed, img url: " . $json['img'] . "\n");
            return $ret;
        }

        if ($json['errno'] !== 0) {
            $this->printMsg("Failed to get dlink\n");
            return $ret;
        }

        $finalUrl = $json['dllink'];

        $ret = array(
            DOWNLOAD_URL      => $finalUrl,
            DOWNLOAD_FILENAME => $filename
        );

        return $ret;
    }

    function getDownloadUrl($content) {
        $parameters = $this->getDownloadParameters($content);

        if (!$parameters) {
            return false;
        }

        $url = "http://pan.baidu.com/share/download?";

        $query = http_build_query(array_merge($parameters, $this->defaultParameters));
        $this->printMsg("query of download url is $query\n");

        return $this->downloadPrefix . "?" . $query;
    }

    function getDownloadParameters($content) {
        $parameters = array();

        foreach ($this->parameterPatterns as $key => $pattern) {
            $value = FujirouCommon::getFirstMatch($content, $pattern);
            if (!$value) {
                $this->printMsg("key $key not found.\n");
                $this->printMsg("\n\n$content\n");
                return false;
            }

            $parameters[$key] = $value;
        }

        return $parameters;
    }

    function getDownloadFidList($content) {
        $pattern = '/disk.util.ViewShareUtils.fsId="([0-9]+)"/';
        $value = FujirouCommon::getFirstMatch($content, $pattern);

        return $value;
    }

    function getFilename($content) {
        $pattern = '/server_filename="([^"]+)";/';
        $value = FujirouCommon::getFirstMatch($content, $pattern);

        return $value;
    }
}

// how to test this script,
// just type the following in DS console
// php -d open_basedir= host.php
if (basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostBaidu';
    $verbose = true;
    $url = 'http://pan.baidu.com/s/1dDtarNr';

    if ($argc >= 2) {
        $argument = $argv[1];
        if (substr(strtolower($argument), 0, 4) === 'http') {
            $url = $argument;
        }
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array(), $verbose);

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
