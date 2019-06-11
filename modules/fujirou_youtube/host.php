<?php
if (!class_exists('Common')) {
    require('common.php');
}
if (!class_exists('Curl')) {
    require('curl.php');
}

class FujirouHostYouTube
{
    public function __construct($url, $username, $password, $hostInfo, $verbose=false) {
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

    public function GetDownloadInfo() {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST'
        );

        $url = $this->url;

        // 1. get html of YouTube url
        $response = new Curl($url);
        $html = $response->get_content();

        // parse player url
        $this->playerUrl = $this->getPlayerUrl($html);
        $this->printMsg("\n == player url ==\n");
        $this->printMsg($this->playerUrl);
        $this->printMsg("\n\n");

        // 2. find url_encoded_fmt_stream_map
        $encodedMapString = $this->getMapString($html);
        $mapString = $encodedMapString;

        $adaptiveFormats = $this->getAdaptiveFmts($html);
        $adaptiveMap = $this->parseMapString($adaptiveFormats, $html);

        // 3. parse map string
        $videoMap = $this->parseMapString($mapString, $html);

        $this->printMsg("\n == url map ==\n");
        $this->printMsg($videoMap);
        $this->printMsg("\n == adaptive ==\n");
        $this->printMsg($adaptiveMap);
        $this->printMsg("\n\n");

        $video = $this->chooseVideo($videoMap, $adaptiveMap);
        if (empty($video)) {
            return $ret;
        }
        $videoUrl = $video["link"];
        $videoExt = $video["ext"];

        // title
        $title = $this->parseTitle($html);

        if (empty($videoUrl)) {
            return $ret;
        }

        $filename = Common::sanitizePath($title) . "." . $videoExt;

        $ret = array(
            DOWNLOAD_URL      => $videoUrl,
            DOWNLOAD_FILENAME => $filename
        );

        // print decrypt function if exists
        $this->printDecryptFunction();

        return $ret;
    }

    private function hasItagMapping($itag)
    {
        return array_key_exists($itag, $this->itagMap);
    }

    private function getTitleByItag($title, $itag)
    {
        $itagString = "itag value $itag";

        if (array_key_exists($itag, $this->itagMap)) {
            $itagString = $this->itagMap[$itag];
        }

        return sprintf("%s (%s)", $title, $itagString);
    }

    private function parseMapString($mapString, $html)
    {
        $urlList = explode(',', $mapString);

        $videoMap = array();

        foreach ($urlList as $url) {
            if (empty($url)) {
                continue;
            }

            $queries = str_replace('\\u0026', '&', $url);

            parse_str($queries, $items);

            $signature = '';
            if (array_key_exists('sig', $items)) {
                $signature = $items['sig'];
                $this->printMsg("\tFound sig: $signature\n");
            } elseif (array_key_exists('s', $items)) {
                // encrypted signature
                $encrypted = $items['s'];
                $signature = $this->decryptSignature($encrypted);
                if (!$signature) {
                    $this->printMsg("\nFailed to decrypt signature, url: " . $this->url . "\n");
                    return false;
                }
                $this->printMsg("\tFound s: $signature, from encrypted: $encrypted\n");
            } else {
                $this->printMsg("no signature\n");
            }

            $paramSignature = '';
            if (!empty($signature)) {
                $paramSignature = '&sig=' . $signature;
            }

            $videoMap[$items['itag']] = $items['url'] . $paramSignature;
        }
        return $videoMap;
    }

    private function getPlayerUrl($html)
    {
        $pattern = '/"assets":.+?"js":\s*("[^"]+")/';
        $jsonUrl = Common::getFirstMatch($html, $pattern);
        $url = json_decode($jsonUrl, true);

        return $url;
    }

    private function parsePlayerUrlId($url)
    {
        $pattern = '/-([a-zA-Z0-9]+)\/html5player.js/';
        $id = Common::getFirstMatch($url, $pattern);

        if (!$id) {
            $pattern = '/player_([a-zA-Z0-9]+)/';
            $id = Common::getFirstMatch($url, $pattern);
        }

        return $id;
    }

    private function getDecryptFunctionArray()
    {
        if ($this->decryptFuncArray) {
            return $this->decryptFuncArray;
        }

        $url = $this->playerUrl;
        if (substr($url, 0, 2) === '//') {
            $url = 'https:' . $url;
        }
        if (substr($url, 0, 4) === '/yts') {
            $url = "https://www.youtube.com$url";
        }
        
        $this->playerId = $this->parsePlayerUrlId($url);
        $id = $this->playerId;
        $this->printMsg("\n player url id: $id\n");

        $response = new Curl($url);
        $html = $response->get_content();

        $html = str_replace(";\n", ";", $html);

        $pattern = '/\.sig\|\|([a-zA-Z0-9\$]+)\(/';
        $funcName = Common::getFirstMatch($html, $pattern);
        if (!$funcName) {
            $this->printMsg("no sig, try another pattern\n");
            $pattern = '/c&&d\.set\([^,]+,encodeURIComponent\(([a-zA-Z0-9\$]+)\(/';
            $funcName = Common::getFirstMatch($html, $pattern);
        }
        $this->printMsg("''' signature function [$funcName]'''\n");

        $pattern = sprintf("/function %s\(.*?\)\{(.+?)\}/", str_replace('$', '\\$', $funcName));
        $funcContent = Common::getFirstMatch($html, $pattern);
        if (!$funcContent) {
            $pattern = sprintf("/[,;]%s=function\(.*?\)\{(.+?)\}/", str_replace('$', '\\$', $funcName));
            $funcContent = Common::getFirstMatch($html, $pattern);
            if (!$funcContent) {
                return false;
            }
        }
        $this->printMsg("\n == decrypt function content begin == \n");
        $this->printMsg($funcContent);
        $this->printMsg("\n == decrypt function content end == \n");

        $pattern = sprintf("/\.([a-zA-Z0-9\$]{2})\(a,([0-9]+)\)/");
        $ret = preg_match_all($pattern, $funcContent, $matches, PREG_SET_ORDER);

        $subFuncDict = array();

        $actions = array();
        foreach ($matches as $match) {
            $name = $match[1];
            $parameter = $match[2];

            if (!array_key_exists($name, $subFuncDict)) {
                $subFuncDict[$name] = $this->parseSubFuncType($html, $name);
            }
            $type = $subFuncDict[$name];

            $actions[] = array('type' => $type, 'parameter' => $parameter);
        }

        // fixed decrypt function
//         $funcName = 'decryptBy_vflbxes4n';
        $funcName = 'decrypt_general';
        $this->actions = $actions;
        $this->decryptFuncArray = array($this, $funcName);

        return $this->decryptFuncArray;
    }

    private function parseSubFuncType($html, $funcName)
    {
        $pattern = sprintf("/%s:function\(.*?\)\{(.+?)\}/", $funcName);
        $content = Common::getFirstMatch($html, $pattern);

        if (false !== strpos($content, 'splice(0')) {
            return 'splice';
        } elseif (false !== strpos($content, 'reverse()')) {
            return 'reverse';
        } elseif (false !== strpos($content, 'c=a[0]')) {
            return 'swap';
        }

        return null;
    }

    private function decryptSignature($encrypted)
    {
        // get player content first
        $decryptFuncArray = $this->getDecryptFunctionArray();
        if (!$decryptFuncArray) {
            return false;
        }

        $decrypted = call_user_func_array($decryptFuncArray, array($encrypted));
        return $decrypted;
    }

    private function decrypt_general($encrypted)
    {
        $actions = $this->actions;

        $a = str_split($encrypted);

        foreach ($actions as $action) {
            $type = $action['type'];
            $parameter = intval($action['parameter']);

            $a = call_user_func_array(array($this, $type), array($a, $parameter));
            if (!$a) {
                $this->printMsg(" !! What Happened !!\n");
                break;
            }
        }

        $decrypted = implode('', $a);

        return $decrypted;
    }

    private function printDecryptFunction()
    {
        if (!$this->playerId || !$this->actions) {
            return;
        }

        $actions = $this->actions;

        $this->printMsg("\n@@@ Show Decrypt Function @@@\n");

        $this->printMsg(sprintf('private function decryptBy_%s($encrypted)'."\n", $this->playerId));
        $this->printMsg('{');
        $this->printMsg("\t".'$a = str_split($encrypted);'."\n");
        $this->printMsg("\n");

        foreach ($actions as $action) {
            $type = $action['type'];
            $parameter = $action['parameter'];

            $this->printMsg("\t".sprintf('$a = $this->%s($a, %s)', $type, $parameter)."\n");
        }

        $this->printMsg("\n");
        $this->printMsg("\t".'$decrypted = implode("", $a);'."\n");
        $this->printMsg("\t".'return $decrypted;'."\n");
        $this->printMsg("}\n");

        $this->printMsg("\n@@@ end decrypt function @@@\n");

    }

    private function decryptBy_vflbxes4n($encrypted) {
        $a = str_split($encrypted);

        // swap(0, 4)
        // slice(3)
        // swap(0, 53)
        // slice(2)
        $a = $this->swap($a, 0, 4);
        $a = array_slice($a, 3);
        $a = $this->swap($a, 0, 53);
        $a = array_slice($a, 2);

        $decrypted = implode('', $a);

        return $decrypted;
    }

    private function reverse($array, $x=0)
    {
        $array = array_reverse($array);

        return $array;
    }

    private function splice($array, $x)
    {
        $array = array_slice($array, $x);

        return $array;
    }

    private function swap($array, $x, $y=0)
    {
        $tmp = $array[$x];
        $array[$x] = $array[$y];
        $array[$y] = $tmp;

        return $array;
    }

    private function getMapString($html)
    {
        $pattern = '/"url_encoded_fmt_stream_map": ?"([^"]+)"/';
        return Common::getFirstMatch($html, $pattern);
    }

    private function getAdaptiveFmts($html)
    {
        $pattern = '/"adaptive_fmts": "([^"]+)"/';
        return Common::getFirstMatch($html, $pattern);
    }

    private function parseTitle($html)
    {
        // property="og:title" content="Don&#039;t Look Back in Anger"
        $pattern = '/property="og:title" content="([^"]+)"/';
        $encodedTitle = Common::getFirstMatch($html, $pattern);
        return htmlspecialchars_decode($encodedTitle, ENT_QUOTES);
    }

    private function chooseVideo($videoMap, $adaptiveMap) {
        // http://en.wikipedia.org/wiki/YouTube#Quality_and_codecs
        $itagMap = array(
            "37" => "mp4", // "1080p, MP4, H.264, AAC"
            "22" => "mp4", // "720p, MP4, H.264, AAC",
            "34" => "flv", // "360p, FLV, H.264, AAC",
            "18" => "mp4", // "360p, MP4, H.264, AAC",
            "35" => "flv", // "480p, FLV, H.264, AAC",
            "5"  => "flv" // "240p, FLV, H.263, MP3",
        );

        // http://en.wikipedia.org/wiki/YouTube#Quality_and_codecs
        $adaptiveItagMap = array(
            "137" => "mp4", // DASH video, "1080p", MP4, H.264, High
            "136" => "mp4", // DASH video, "720p", MP4, H.264, Main
            "135" => "mp4", // DASH video, "480p", MP4, H.264, Main
            "134" => "mp4", // DASH video, "360p", MP4, H.264, Main
            "133" => "mp4", // DASH video, "240p", MP4, H.264, Main

            "140" => "mp4", // DASH audio, AAC, 128
            "139" => "mp4", // DASH audio, AAC, 48
            "172" => "webm", // DASH audio, Vorbis, 192
            "171" => "webm" // DASH audio, Vorbis, 128
        );

        if (empty($videoMap)) {
            return FALSE;
        }

        foreach ($itagMap as $itag => $ext) {
            if (array_key_exists($itag, $videoMap)) {
                return array(
                    "link" => $videoMap[$itag],
                    "ext"  => $ext
                );
            }
        }

        return array(
            "link" => reset($videoMap),
            "ext"  => "flv"
        );
    }
}

// php -d open_basedir= host.php
if (!empty($argv) && basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostYouTube';
//    $url = 'http://www.youtube.com/watch?v=tNC9V2ewsb4';
//     $url = 'https://www.youtube.com/watch?v=-jej8YS4Slk';
//    $url = 'http://www.youtube.com/watch?v=RY35O02Fg8M';
    $url = 'http://www.youtube.com/watch?v=iul4SBlHIf8';
$url = 'http://www.youtube.com/watch?v=FXg4LXsg14s';
$url = 'http://www.youtube.com/watch?v=tNo3LuZXA1w';
$url = 'http://www.youtube.com/watch?v=Ci8REzfzMHY';
// $url = 'http://www.youtube.com/watch?v=UHFAjkD_LLg'; // Taylor Swift feat Paula Fernandes Long Live VEVO 1080p
// $url = 'https://www.youtube.com/watch?v=7QdCnvixNvM';
// $url = 'http://www.youtube.com/watch?v=w3KOowB4k_k'; // Mariah Carey - Honey (VEVO)
	$url = 'https://www.youtube.com/watch?v=2LbEN_Ph1-E'; // amuro namie - Sweet Kisses
    $url = 'https://www.youtube.com/watch?v=rfFEhd7mk7c'; // DJ Earworm Mashup - United State of Pop 2015
    $url = 'https://www.youtube.com/watch?v=RGRCx-g402I'; // Aimer Sun Dance Penny Rain

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
