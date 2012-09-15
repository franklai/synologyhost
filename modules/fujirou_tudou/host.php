<?php

if (!class_exists('Common')) {
    require('common.php');
}
if (!class_exists('Curl')) {
    require('curl.php');
}

class FujirouHostTudou
{
    private $brtList = array(
        "2" => "256P",
        "3" => "360P",
        "4" => "480P",
        "5" => "720P",
        "99"=> "Original"
    );

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

        $html = $this->getHtml($url);

        $iid = $this->getIid($html, $url);
        if (FALSE === $iid) {
            Common::debug("Failed to get iid from url: $url");
            return $ret;
        }

        $useProxy = TRUE;

        $xml = $this->getXml($iid, $useProxy);

        $videoList = $this->parseXml($xml, $html, $url);

        $title = array_key_exists('title', $videoList) ? $videoList['title'] : '';
        $videoUrl = array_key_exists('link', $videoList) ? $videoList['link'] : '';

        if (empty($title) || empty($videoUrl)) {
            return $ret;
        }

        $filename = Common::sanitizePath($title) . ".flv";

        $ret = array(
            DOWNLOAD_URL      => $videoUrl,
            DOWNLOAD_FILENAME => $filename
        );

        return $ret;
    }

    private function getHtml($url) {
        $response = new Curl($url);

        $html = $response->get_content();

        if (empty($html)) {
            // check if Location in header
            $headers = $response->get_info();
            if (is_array($headers) && array_key_exists('Location', $headers)) {
                $url = $headers['Location'];
                $html = $this->getHtml($url);
            }
        }
        $html = mb_convert_encoding($html, "UTF-8", "GBK");

        return $html;
    }

    private function getXml($iid, $useProxy) {
        $proxy = NULL;

        $videoListUrl = sprintf("http://v2.tudou.com/v.action?st=2,3,4,5,99&it=%s", $iid);
        Common::debug("url is $videoListUrl");

        $headers = array();
        if (array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
            $headers['User-Agent'] = $_SERVER['HTTP_USER_AGENT'];
        }
        if (defined('DOWNLOAD_STATION_USER_AGENT')) {
            $headers['User-Agent'] = DOWNLOAD_STATION_USER_AGENT;
        }

        // curl -v --cookie-jar cookie.txt --proxy  h3.dxt.bj.ie.sogou.com:80  --header "X-Sogou-Timestamp: 5051b765" --header "X-Sogou-Tag: 39eb3b68"   "http://v2.tudou.com/v.action?st=2,3,4,5,99&it=148748772"
        if ($useProxy) {
            $proxy = $this->getProxy();
            Common::debug("sogou proxy host is [$proxy]");

            $timestamp = $this->getTimestamp();
// $timestamp = '5051b765';
            $hostname = 'v2.tudou.com';
            $tag = $this->calculateTag($timestamp, $hostname);
            Common::debug("sogou tag is [$tag], timestamp [$timestamp]");
// $tag = '39eb3b68';

            $headers['X-Sogou-Timestamp'] = $timestamp;
            $headers['X-Sogou-Tag'] = $tag;
        }

        $response = new Curl(
            $videoListUrl,
            NULL,
            $headers,
            $proxy
        );
        $xml = $response->get_content();

        return $xml;
    }

    private function getIid($html, $url) {
        // /programs/view/[icode]
        $pattern = '/iid = ([0-9]+)/';
        $iid = Common::getFirstMatch($html, $pattern);

        if (empty($iid)) {
            $icode = $this->getIcode($html, $url);
            if (empty($icode)) {
                Common::debug("Failed to get icode from url: $url");
                return FALSE;
            }

            $iid = $this->getIidByIcode($html, $icode);
            Common::debug("iid from icode is $iid");
        }

        return $iid;
    }

    private function getIcode($html, $url) {
        // /albumplay/[acode]/[icode]
        // /listplay/[lcode]/[icode]
        $pattern = '/play\/[^\/]+\/([^\/]+).html/';
        $icode = Common::getFirstMatch($url, $pattern);

        if (empty($icode)) {
            $pattern = '/location.href\) \|\| \'([^\']+)\'/';
            $icode = Common::getFirstMatch($html, $pattern);
            Common::debug("icode from location.href is $icode");
        }

        return $icode;
    }

    private function getIidByIcode($html, $icode) {
        $iidPattern = '/\niid:([0-9]+)/';
        $icodePattern = '/\n,icode:"([^"]+)"/';

        $iidAll = Common::getAllFirstMatch($html, $iidPattern);
        $icodeAll = Common::getAllFirstMatch($html, $icodePattern);

        if (empty($iidAll) || empty($icodeAll)) {
            return FALSE;
        }

        if (count($iidAll) === 0 || count($icodeAll) === 0) {
            return FALSE;
        }
        $key = array_search($icode, $icodeAll);
        if ($key === FALSE) {
            return FALSE;
        }

        return $iidAll[$key];
    }

    private function parseXml($xml, $html, $url) {
        $list = array();

        if (strpos($xml, "error='ip is forbidden'") !== FALSE) {
            $list = array(
                "title" => "IP is forbidden",
                "link" => $url
            );
            return $list;
        }

        $decodedXml = Common::decodeHtml($xml);

        $title = $this->parseTitle($decodedXml, $html, $url);

// echo $decodedXml;
//         echo str_replace(">", ">\n", $decodedXml);

        $pattern = '/brt="([0-9]+)">(http:[^<]+)</';
        $ret = preg_match_all($pattern, $decodedXml, $matches, PREG_SET_ORDER);

        if (FALSE === $ret) {
            return $list;
        }

        if ($ret > 0) {
            // take last one (implement other later)
            $videoUrl = $matches[count($matches) - 1][2];
            $list = array(
                "title" => $title,
                "link" => $videoUrl
            );
        }

        return $list;
    }

    private function parseTitle($xml, $html, $url) {
        $defaultTitle = 'Unknown title';

        $title = $this->parseTitleFromXml($xml);
        if (empty($title)) {
            $title = $this->parseTitleFromHtml($html);
        }

        if (empty($this)) {
            $title = $defaultTitle;
        }

        return $title;
    }

    private function parseTitleFromXml($xml) {
        $pattern = '/title="([^"]+)"/';
        $ret = Common::getFirstMatch($xml, $pattern);
        if (FALSE === $ret) {
            return FALSE;
        }

        $title = $ret;

        return $title;
    }

    private function parseTitleFromHtml($html) {
        $pattern = '/<h1>([^<]+)<\/h1>/';
        return Common::getFirstMatch($html, $pattern);
    }

    private function getConvertedTitle($title, $brt) {
        $ret = $title . $this->getFormatString($brt);

        return $ret;
    }

    private function getFormatString($brt) {
        if (array_key_exists($brt, $this->brtList)) {
            return sprintf(" (%s)", $this->brtList[$brt]);
        } else {
            return sprintf(" (Unknown brt %s)", $brt);
        }
    }

    private function getProxy() {
        $dxtSuffix = '.dxt.bj.ie.sogou.com:80';
        $eduSuffix = '.edu.bj.ie.sogou.com:80';
        $num = rand(0, 15);

        $proxy = sprintf("h%d%s", $num, (rand(0, 1)) ? $dxtSuffix : $eduSuffix);
        return $proxy;
    }

    private function getTimestamp() {
        return sprintf("%x", time());
    }

    private function calculateTag($timestamp, $hostname) {
        $src = sprintf("%s%s%s", $timestamp, $hostname, 'SogouExplorerProxy');
        $totalLen = strlen($src);

        $hash = $totalLen;

        function urshift($n, $s) {
            if (PHP_INT_MAX > 2147483647) {
                return $n >> $s;
            } else {
                return ($n >= 0) ? ($n >> $s) :
                    (($n & 0x7fffffff) >> $s) | 
                        (0x40000000 >> ($s - 1));
            }
        } 

        function to32bitInteger($value) {
            if (PHP_INT_MAX > 2147483647 && $value > 2147483647) {
                $tmp = $value % 0x100000000;
                return $tmp;
            }
            return $value;
        }

        // skip last block in iteration
        for ($i = 0; $i < ($totalLen - 4); $i += 4) {
            $low  = ord($src[$i + 1]) * 256 + ord($src[$i]);
            $high = ord($src[$i + 3]) * 256 + ord($src[$i + 2]);

            $hash += $low;
            $hash = to32bitInteger($hash);
            $hash ^= $hash << 16;
            $hash = to32bitInteger($hash);

            $hash ^= $high << 11;
//             $hash += $hash >> 11;
            $hash += urshift($hash, 11);
            $hash = to32bitInteger($hash);
        }

        switch (($totalLen) % 4) {
            case 3:
                $hash += (ord($src[$totalLen - 2]) << 8) + ord($src[$totalLen - 3]);
                $hash = to32bitInteger($hash);
                $hash ^= $hash << 16;
                $hash = to32bitInteger($hash);

                $hash ^= (ord($src[$totalLen - 1])) << 18;
//                 $hash += $hash >> 11;
                $hash += urshift($hash, 11);
                $hash = to32bitInteger($hash);
                break;
            case 2:
                $hash += (ord($src[$totalLen - 1]) << 8) + ord($src[$totalLen - 2]);
                $hash = to32bitInteger($hash);
                $hash ^= $hash << 11;
                $hash = to32bitInteger($hash);

//                 $hash += $hash >> 17;
                $hash += urshift($hash, 17);
                $hash = to32bitInteger($hash);
                break;
            case 1:
                $hash += ord($src[$totalLen - 1]);
                $hash = to32bitInteger($hash);
                $hash ^= $hash << 10;
                $hash = to32bitInteger($hash);

//                 $hash += $hash >> 1;
                $hash += urshift($hash , 1);
                $hash = to32bitInteger($hash);
                break;
            default:
                break;
        }

        $hash ^= $hash << 3;
        $hash = to32bitInteger($hash);
//         $hash += $hash >> 5;
        $hash += urshift($hash, 5);
        $hash = to32bitInteger($hash);

        $hash ^= $hash << 4;
        $hash = to32bitInteger($hash);
//         $hash += $hash >> 17;
        $hash += urshift($hash, 17);
        $hash = to32bitInteger($hash);

        $hash ^= $hash << 25;
        $hash = to32bitInteger($hash);
//         $hash += $hash >> 6;
        $hash += urshift($hash, 6);
        $hash = to32bitInteger($hash);

        $tag = sprintf("%08x", $hash);
        return $tag;
    }
}

// how to test this script,
// just type the following in DS console
// php -d open_basedir= host.php
if (basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostTudou';
//    $url = 'http://www.tudou.com/albumplay/n9e8zZsySQc/bmT51zM7_3o.html';
    $url = 'http://www.tudou.com/albumplay/tZ-uP7dTBwA/3m5a_mgEnkc.html';

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array());

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
?>
