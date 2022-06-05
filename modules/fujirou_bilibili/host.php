<?php
if (!class_exists('Common')) {
    require 'common.php';
}

defined('DOWNLOAD_LIST_NAME') or define('DOWNLOAD_LIST_NAME', 'list_name');
defined('DOWNLOAD_LIST_FILES') or define('DOWNLOAD_LIST_FILES', 'list_files');
defined('DOWNLOAD_FILENAME') or define('DOWNLOAD_FILENAME', 'filename');
defined('DOWNLOAD_URL') or define('DOWNLOAD_URL', 'downloadurl');
defined('DOWNLOAD_LIST_SELECTED') or define('DOWNLOAD_LIST_SELECTED', 'list_selected');

class FujirouHostBilibili
{
    public function __construct($url, $username, $password, $hostInfo, $verbose = false)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
        $this->verbose = $verbose;
        $this->cid = null;
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
        try {
            return $this->get();
        } catch (Exception $e) {
            $this->printMsg("Exception, " . $e->getMessage());
            return array(
                DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST',
            );
        }
    }

    public function GetFileList()
    {
        try {
            return $this->get_list();
        } catch (Exception $e) {
            $this->printMsg("Exception, " . $e->getMessage());
            return false;
        }
    }

    // for test
    public function get_cid()
    {
        return $this->cid;
    }

    private function get()
    {
        $url = $this->url;

        // get bilibili json by url
        $json = $this->request_json_by_url($url);
        $page = $this->get_page_from_url($url);

        // get video url by json
        $video_url = $this->request_video_by_json($json, $page);
        $this->printMsg(" == video url: $video_url");

        // parse extension name from video url
        if (false !== strpos($video_url, '.mp4') || false !== strpos($video_url, '/mp4/')) {
            $video_ext = 'mp4';
        } elseif (false !== strpos($video_url, '.flv') || false !== strpos($video_url, '/flv/')) {
            $video_ext = 'flv';
        } else {
            $video_ext = 'unknown';
        }

        $data = $json['data'];
        $title = $this->get_title_by_json_and_page($json, $page);
        $filename = Common::sanitizePath($title) . "." . $video_ext;

        $ret = array(
            DOWNLOAD_URL => $video_url,
            DOWNLOAD_FILENAME => $filename,
            'referer' => $url,
        );

        if ($this->verbose) {
            echo "== curl command begin ==\n";
            echo "curl -v  --referer '$url' '$video_url'\n";
            echo "== curl command end ==\n";

            print_r($ret);
        }

        return $ret;
    }

    private function get_list()
    {
        $url = $this->url;

        // get bilibili json by url
        $json = $this->request_json_by_url($url);
        $page = $this->get_page_from_url($url);

        $list = $this->get_list_by_json($json);
        if (count($list) === 1) {
            $this->printMsg("only one item, return false as no list\n");
            return false;
        }

        $data = $json['data'];
        $list_name = $data['title'];
        $this->printMsg("list title: $list_name\n");

        $ret = array(
            DOWNLOAD_LIST_NAME => $list_name,
            DOWNLOAD_LIST_FILES => $list,
        );

        if (is_int($page)) {
            $ret[DOWNLOAD_LIST_SELECTED] = array($page);
            $this->printMsg("selected page: $page\n");
        }

        return $ret;
    }

    private function get_aid_from_url($url)
    {
        $pattern = '/video\/av(\d+)/';
        return Common::getFirstMatch($url, $pattern);
    }

    private function get_bvid_from_url($url)
    {
        $pattern = '/video\/(BV[0-9a-zA-Z]+)/';
        return Common::getFirstMatch($url, $pattern);
    }

    private function get_page_from_url($url)
    {
        $pattern = '/p=(\d+)/';
        $value = Common::getFirstMatch($url, $pattern);
        if ($value) {
            return intval($value, 10);
        }
        return null;
    }

    private function get_epid_from_url($url)
    {
        $pattern = '/bangumi\/play\/ep(\d+)/';
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

    private function is_aid_url($url)
    {
        if (strpos($url, '/video/av') > 0) {
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

    private function is_bangumi_url($url)
    {
        if (strpos($url, '/bangumi/play/') > 0) {
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

    private function request_json_by_url($original_url)
    {
        if ($this->is_bvid_url($original_url)) {
            $id = $this->get_bvid_from_url($original_url);
            $params = "bvid=$id";
            $this->printMsg("bvid is: $id\n");
        } elseif ($this->is_aid_url($original_url)) {
            $id = $this->get_aid_from_url($original_url);
            $params = "aid=$id";
            $this->printMsg("aid is: $id\n");
        } elseif ($this->is_bangumi_url($original_url)) {
            $id = $this->request_bvid_from_url($original_url);
            $params = "bvid=$id";
            $this->printMsg("bangumi, get bvid is: $id\n");
        } else {
            throw new Exception("Failed to find video id of url $original_url\n");
        }

        $url = "https://api.bilibili.com/x/web-interface/view?$params";

        $raw = Common::getContent($url);
        $this->printMsg("Get content of url: $url\n");

        if (!$raw) {
            throw new Exception("Failed to get content of url $url");
        }
        return json_decode($raw, true);
    }

    private function request_video_by_json($json, $page)
    {
        $data = $json['data'];
        $bvid = $data['bvid'];
        $cid = $data['cid'];

        $page_data = $this->get_page_data($json, $page);
        if ($page_data) {
            $cid = $page_data['cid'];
        }

        $this->cid = $cid;
        $params = "cid=$cid&bvid=$bvid";

        $url = "https://api.bilibili.com/x/player/playurl?$params";
        $this->printMsg("Get content of url: $url\n");

        $raw = Common::getContent($url);
        if (!$raw) {
            $this->printMsg("Failed to get content of url: $url");
            throw new Exception("Failed to get content of url $url");
        }

        $video_url = $this->find_url_in_json($raw);

        return $video_url;
    }

    private function request_bvid_from_url($url)
    {
        $raw = Common::getContent($url);
        if (!$raw) {
            throw new Exception("Failed to get content of url $url");
        }

        $epid = $this->get_epid_from_url($url);

        $bvid = $this->find_bvid_in_html($raw, $epid);
        return $bvid;
    }

    private function find_url_in_json($raw)
    {
        $json = json_decode($raw, true);
        if (!$json) {
            throw new Exception("Failed to parse [$raw] to JSON.");
        }

        $this->printMsg("===== JSON begin (find_url_in_json) =====");
        $this->printMsg($json);
        $this->printMsg("===== JSON end =====");

        if ($json['code'] !== 0) {
            $this->printMsg("Response code not 0");
            $this->printMsg("code: " . $json['code'] . ", message: " . $json['message']);
            return '';
        }

        $data = $json['data'];
        return $data['durl'][0]['url'];
    }

    private function find_bvid_in_html($raw, $epid)
    {
        if ($epid) {
            $pattern = "/\"$epid\":\{.*?\"bvid\":\"(BV[0-9a-zA-Z]+)\"/";
        } else {
            // get first bvid when no ep id
            $pattern = "/\"bvid\":\"(BV[0-9a-zA-Z]+)\"/";
        }

        return Common::getFirstMatch($raw, $pattern);
    }

    private function get_page_data($json, $page)
    {
        $data = $json['data'];
        $pages = $data['pages'];
        $title = $data['title'];

        if (is_numeric($page) && $page > 0) {
            foreach ($pages as $item) {
                if ($item['page'] === $page) {
                    return $item;
                }
            }
        }

        return null;
    }

    private function get_title_by_json_and_page($json, $page)
    {
        $data = $json['data'];
        $title = $data['title'];

        $page_data = $this->get_page_data($json, $page);
        if ($page_data) {
            $title .= " - " . html_entity_decode($page_data['part']);
        }

        return $title;
    }

    private function get_list_by_json($json)
    {
        $data = $json['data'];
        $bvid = $data['bvid'];
        $pages = $data['pages'];

        $list = array();
        foreach ($pages as $item) {
            $page = $item['page'];
            $part = $item['part'];
            $decoded_part = html_entity_decode($part);
            $url_with_page = "https://www.bilibili.com/video/$bvid?p=$page";
            array_push(
                $list,
                array(
                    DOWNLOAD_FILENAME => $decoded_part,
                    DOWNLOAD_URL => $url_with_page,
                )
            );

            $this->printMsg("\tpage $page, $decoded_part\n");
            $this->printMsg("\t\t$url_with_page\n");
        }

        return $list;
    }
}

// php -d open_basedir= host.php
if (!empty($argv) && basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostBilibili';
    $url = 'https://www.bilibili.com/bangumi/play/ep88573'; // mayoiga E09 bilibili official
    $url = 'https://www.bilibili.com/video/av4313184/';
    $url = 'https://www.bilibili.com/video/av5991225/'; // flv only

    $url = 'https://www.bilibili.com/video/av28641430'; // arashi ni shiyagare 180804
    $url = 'https://www.bilibili.com/video/BV12K4y1C7QU'; // arashi ni shiyagare 200328

    $getList = false;
    if ($argc >= 2) {
        $argument = $argv[1];
        if ($argument === '--list') {
            $argument = $argv[2];
            $getList = true;
        }
        if (substr(strtolower($argument), 0, 4) === 'http') {
            $url = $argument;
        }
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array(), true);

    if ($getList) {
        echo "Show list of $url\n\n";
        $info = $obj->GetFileList();
        // var_dump($info);
    } else {
        echo "Get download info of '$url'\n\n";
        $info = $obj->GetDownloadInfo();
    }
}

// vim: expandtab ts=4
