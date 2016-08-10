<?php
if (!class_exists('Common')) {
    require('common.php');
}
if (!class_exists('Curl')) {
    require('curl.php');
}

class FujirouHostBilibili
{
    public function __construct($url, $username, $password, $hostInfo, $verbose=false) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
        $this->verbose = $verbose;
        $this->api_appkey = '8e9fc618fbd41e28';
        $this->interface_appkey = '86385cdc024c0f6c';

//         $this->proxy = '124.88.67.54:843';
        $this->proxy = null;
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

    public function GetDownloadInfo() {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST'
        );

        $url = $this->url;

        // 1. get bilibili json by url
        $json = $this->request_json_by_url($url);
        if (!$json) {
            return $ret;
        }

        // 2. get video url by json
        $video_url = $this->request_video_by_json($json);
        if (!$video_url) {
            return $ret;
        }

        // parse extension name from video url
        if (false !== strpos($video_url, '.mp4?')) {
            $video_ext = 'mp4';
        } elseif (false !== strpos($video_url, '.flv?')) {
            $video_ext = 'flv';
        } else{
            $video_ext = 'unknown';
        }

        $title = $json['title'];
        if ($json['partname']) {
            $title .= " - " . $json['partname'];
        }

        $filename = Common::sanitizePath($title) . "." . $video_ext;

        $ret = array(
            DOWNLOAD_URL      => $video_url,
            DOWNLOAD_FILENAME => $filename
        );

        return $ret;
    }

    private function parse_video_id($url)
    {
        $pattern = '/video\/av(\d+)/';
        return Common::getFirstMatch($url, $pattern);
    }

    private function parse_page($url)
    {
        $pattern = '/index_(\d+)\.html/';
        return Common::getFirstMatch($url, $pattern);
    }

    private function request_json_by_url($original_url)
    {
        $id = $this->parse_video_id($original_url);
        if (!$id) {
            return false;
        }
        $page = $this->parse_page($original_url);
        if (!$page) {
            $page = 1;
        }

        $appkey = $this->api_appkey;
        $url = "http://api.bilibili.com/view?type=json&appkey=$appkey&id=$id&page=$page";

        $response = new Curl($url, null, null, $this->proxy);
        $raw = $response->get_content();
        if (!$raw) {
            return false;
        }

        return json_decode($raw, true);
    }

    private function request_video_by_json($json)
    {
        $appkey = $this->interface_appkey;
        $url = "http://interface.bilibili.com/v_cdn_play?appkey=$appkey&cid=" . $json['cid'];

//        $sign = '3bb70b3bc5bed057be6c11cf319d17fa';
//        $ts = '1465392650';
//        $url = "http://interface.bilibili.com/playurl?sign=$sign&from=miniplay&player=1&quality=1&ts=$ts&cid=" . $json['cid'];

        $response = new Curl($url, null, null, $this->proxy);
        $raw = $response->get_content();
        if (!$raw) {
            return false;
        }

        if ($this->verbose) {
            echo "\n===== JSON begin =====\n";
            echo json_encode($json, JSON_PRETTY_PRINT);
            echo "\n===== JSON end =====\n";

            echo "\n=== url: $url\n";
            echo "\n===== XML begin =====\n";
            echo $raw;
            echo "\n===== XML end =====\n";
        }

        $video_url = $this->find_url_in_xml($raw);

        return $video_url;
    }

    private function find_url_in_xml($raw)
    {
        $prefix = '<url><![CDATA[';
        $suffix = ']]></url>';

        if (false === strpos($raw, '<durl>')) {
            return false;
        }

        $url = Common::getSubString($raw, $prefix, $suffix, false);
        
        return $url;
    }

    private function find_url_in_xml_using_dom($raw)
    {
        $doc = new DOMDocument();
        $doc->loadXML($raw);

        $xpath = new DOMXpath($doc);

        $elements = $xpath->query('durl/backup_url/url');
        if (!$elements) {
            $elements = $xpath->query('durl/url');
        }

        var_dump($elements);
        if (!is_null($elements)) {
            foreach ($elements as $elem) {
                return $elem->nodeValue;
            }
        }

        return '';
    }

    private function find_url_in_xml_using_simplexml($raw)
    {
        $elem = simplexml_load_string($raw);
        if (!$elem) {
            return '';
        }

        $result = $elem->xpath('durl/backup_url/url');
        if (!$result) {
            $result = $elem->xpath('durl/url');
        }
        $video_url = $result[0];
        return '' . $video_url;
    }
}

// php -d open_basedir= host.php
if (!empty($argv) && basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostBilibili';
    $url = 'http://www.bilibili.com/video/av710996/'; // Trick S1E01
    $url = 'http://www.bilibili.com/video/av710996/index_2.html'; // Trick S1E02
    $url = 'http://www.bilibili.com/video/av710996/index_46.html'; // Trick 2010 Movie
//     $url = 'http://www.bilibili.com/video/av4775518/';
    $url = 'http://www.bilibili.com/video/av4782176/'; // mayoiga E09 bilibili official
    $url = 'http://www.bilibili.com/video/av4313184/';
    $url = 'http://www.bilibili.com/video/av4209456/'; // VS Arashi
    $url = 'http://www.bilibili.com/video/av5751080/'; // The Yakai 20160804
    $url = 'http://www.bilibili.com/video/av2937029/index_16.html'; //
    $url = 'http://www.bilibili.com/mobile/video/av3397162.html'; // mobile page

    if ($argc >= 2) {

        $argument = $argv[1];
        if (substr(strtolower($argument), 0, 4) === 'http') {
            $url = $argument;
        }
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array(), true);

    echo "Get download info of '$url'\n\n";
    $info = $obj->GetDownloadInfo();

    print_r($info);
}

// vim: expandtab ts=4
?>
