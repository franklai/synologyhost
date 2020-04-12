<?php
if (!class_exists('Common')) {
    require 'common.php';
}
if (!class_exists('Curl')) {
    require 'curl.php';
}

class FujirouHostBilibili
{
    public function __construct($url, $username, $password, $hostInfo, $verbose = false)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
        $this->verbose = $verbose;
        $this->api_appkey = '8e9fc618fbd41e28';
        $this->interface_appkey = 'iVGUTjsxvpLeuDCf';
        $this->bili_key = 'aHRmhWMLkdeMuILqORnYZocwMBpMEOdt';

//         $this->proxy = '124.88.67.54:843';
        $this->proxy = null;

        $this->use_referer_proxy = false;
        $this->proxy_ip = '192.168.1.1';
        $this->proxy_path = '/referer_proxy/proxy.php';
        $this->referer_user_agent = 'Synology Download Station';

        $this->choose_flv_format = true;
    }

    private function get_url_by_referer_proxy($video_url, $referer)
    {
        $url = sprintf(
            "http://%s%s?url=%s&referer=%s&user_agent=%s",
            $this->proxy_ip, $this->proxy_path,
            urlencode($video_url), urlencode($referer),
            urlencode($this->referer_user_agent)
        );
        return $url;
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

    public function GetDownloadInfo()
    {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST',
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
        if (false !== strpos($video_url, '.mp4') || false !== strpos($video_url, '/mp4/')) {
            $video_ext = 'mp4';
        } elseif (false !== strpos($video_url, '.flv') || false !== strpos($video_url, '/flv/')) {
            $video_ext = 'flv';
        } else {
            $video_ext = 'unknown';
        }

        $title = $json['title'];
        if ($json['partname']) {
            $title .= " - " . $json['partname'];
        }

        $filename = Common::sanitizePath($title) . "." . $video_ext;

        if ($this->use_referer_proxy) {
            $video_url = $this->get_url_by_referer_proxy($video_url, $url);
        }

        $ret = array(
            DOWNLOAD_URL => $video_url,
            DOWNLOAD_FILENAME => $filename,
            "referer" => $url,
        );

        if ($this->verbose) {
            echo "== curl command begin ==\n";
            echo "curl -v  --referer '$url' '$video_url'\n";
            echo "== curl command end ==\n";
        }

        return $ret;
    }

    private function get_aid_from_url($url)
    {
        $pattern = '/video\/av(\d+)/';
        return Common::getFirstMatch($url, $pattern);
    }

    private function parse_page($url)
    {
        $pattern = '/index_(\d+)\.html/';
        return Common::getFirstMatch($url, $pattern);
    }

    private function is_anime_url($url)
    {
        if (strpos($url, '/anime/v/') > 0) {
            return true;
        }
        return false;
    }

    private function is_bvid_url($url)
    {
        if (strpos($url, '/video/BV') > 0) {
            return true;
        }
        return false;
    }

    private function get_anime_episode_id($url)
    {
        $pattern = '/anime\/v\/(\d+)/';
        $episode_id = Common::getFirstMatch($url, $pattern);
        return $episode_id;
    }

    private function get_anime_aid($original_url)
    {
        $episode_id = $this->get_anime_episode_id($original_url);
        if (!$episode_id) {
            return false;
        }

        $url = 'http://bangumi.bilibili.com/web_api/get_source';
        $data = "episode_id=$episode_id";

        $response = new Curl($url, $data, null, $this->proxy);
        $raw = $response->get_content();

        $json = json_decode($raw, true);
        if (!$json || !isset($json['result'])) {
            return false;
        }

        return $json['result']['aid'];
    }

    private function get_aid($original_url)
    {
        if ($this->is_bvid_url($original_url)) {
            $aid = $this->get_aid_from_bvid_url($original_url);
        } elseif ($this->is_anime_url($original_url)) {
            $aid = $this->get_anime_aid($original_url);
        } else {
            $aid = $this->get_aid_from_url($original_url);
        }

        return $aid;
    }

    private function get_aid_from_bvid_url($original_url)
    {
        $response = new Curl($original_url, null, null, $this->proxy);
        $html = $response->get_content();
        if (!$html) {
            return null;
        }

        $pattern = '/"aid":(\d+),/';
        return Common::getFirstMatch($html, $pattern);
    }

    private function request_json_by_url($original_url)
    {
        $aid = $this->get_aid($original_url);
        if (!$aid) {
            $this->printMsg("Failed to get aid\n");
            return false;
        }
        $this->printMsg("aid is: $aid\n");

        $page = $this->parse_page($original_url);
        if (!$page) {
            $page = 1;
        }

        $appkey = $this->api_appkey;

        $url = "https://api.bilibili.com/view?type=json&appkey=$appkey&id=$aid&page=$page";

        $response = new Curl($url, null, null, $this->proxy);
        $raw = $response->get_content();
        $this->printMsg("Get content of url: $url\n");

        if (!$raw) {
            return false;
        }

        return json_decode($raw, true);
    }

    private function get_sign($params_prefix)
    {
        $bili_key = $this->bili_key;
        return md5("$params_prefix$bili_key");
    }

    private function request_video_by_json($json)
    {
        $appkey = $this->interface_appkey;
        $cid = $json['cid'];

        $items = [
            "appkey=$appkey",
            "cid=$cid",
            "otype=json",
        ];
        if ($this->choose_flv_format) {
            $items = array_merge($items, [
                "qn=80",
                "quality=80",
                "type=",
            ]);
        } else {
            $items = array_merge($items, [
                "quality=2",
                "type=mp4",
            ]);
        }

        $params_prefix = implode('&', $items);

        $sign = $this->get_sign($params_prefix);

        $url = "https://interface.bilibili.com/playurl?$params_prefix&sign=$sign";
        $this->printMsg("Get content of url: $url\n");
        $response = new Curl($url, null, null, $this->proxy);
        $raw = $response->get_content();
        if (!$raw) {
            return false;
        }

        if ($this->verbose) {
            echo "\n===== JSON begin (request_video_by_json) =====\n";
            echo json_encode($json, JSON_PRETTY_PRINT);
            echo "\n===== JSON end =====\n";
        }

        $video_url = $this->find_url_in_json($raw);

        return $video_url;
    }

    private function find_url_in_json($raw)
    {

        $json = json_decode($raw, true);
        if (!$json) {
            $this->printMsg("Failed to parse [$raw] to JSON.");
            return false;
        }

        if ($this->verbose) {
            echo "\n===== JSON begin (find_url_in_json) =====\n";
            echo json_encode($json, JSON_PRETTY_PRINT);
            echo "\n===== JSON end =====\n";
        }

        return $json['durl'][0]['url'];
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
    //     $url = 'http://www.bilibili.com/mobile/video/av3397162.html'; // mobile page
    $url = 'http://www.bilibili.com/video/av5991225/'; // flv only

    $url = 'http://bangumi.bilibili.com/anime/v/29004'; // Ghost in the Shell: Innocence
    $url = 'https://www.bilibili.com/video/av28641430'; // arashi ni shiyagare 180804
    $url = 'https://www.bilibili.com/video/BV12K4y1C7QU'; // arashi ni shiyagare 200328

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
