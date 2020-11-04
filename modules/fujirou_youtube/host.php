<?php
if (!class_exists('Common')) {
    require 'common.php';
}

class FujirouHostYouTube
{
    public function __construct($url, $username, $password, $hostInfo, $verbose = false)
    {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;
        $this->verbose = $verbose;
        $this->decryptFuncArray = null;
        $this->actions = null;
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

        // 1. get html of YouTube url
        $html = Common::getContent($url);

        $videoMap = $this->getVideoMapGeneral($html);
        if (!$videoMap) {
            if (strpos($html, 'LOGIN_REQUIRED') !== false) {
                $this->printMsg("require login\n");
                $videoMap = $this->getVideoMapForAgeGate($html);
            }
        }

        $video = $this->chooseVideo($videoMap);
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
            DOWNLOAD_URL => $videoUrl,
            DOWNLOAD_FILENAME => $filename,
        );

        return $ret;
    }

    private function getVideoMapGeneral($html)
    {
        $this->playerUrl = $this->getPlayerUrl($html);
        $this->printMsg("\n == player url ==\n");
        $this->printMsg($this->playerUrl);
        $this->printMsg("\n\n");

        $playerConfig = $this->getPlayerConfig($html);
        if (!$playerConfig) {
            $this->printMsg("Failed to get player config\n");
            $this->printMsg("\n== html begin ==\n");
            $this->printMsg($html);
            $this->printMsg("\n== html end ==\n");
            return null;
        }
        $playerResponse = null;
        if (array_key_exists('args', $playerConfig)) {
            $playerResponse = json_decode($playerConfig['args']['player_response'], true);
            $this->printMsg("old style player response\n");
        } else if (array_key_exists('streamingData', $playerConfig)) {
            $playerResponse = $playerConfig;
            $this->printMsg("get from initial player response\n");
        }

        if (!$playerResponse) {
            $this->printMsg("Failed to find player response\n");
            return null;
        }

        $videoFormats = $playerResponse['streamingData']['formats'];
        $this->printMsg("\n == video formats ==\n");
        $this->printMsg($videoFormats);
        $this->printMsg("\n\n");

        $urlList = array();
        $cipherList = array();
        foreach ($videoFormats as $item) {
            if (array_key_exists('url', $item)) {
                array_push($urlList, $item['url']);
            } elseif (array_key_exists('cipher', $item)) {
                $url = $this->getUrlByCipher($item['cipher']);
                array_push($urlList, $url);
            } elseif (array_key_exists('signatureCipher', $item)) {
                $url = $this->getUrlByCipher($item['signatureCipher']);
                array_push($urlList, $url);
            } else {
                $this->printMsg("Faield to get url or cipher in item\n");
            }
        }

        return $this->getVideoMapByUrlList($urlList);
    }

    private function getVideoMapForAgeGate($html)
    {
        $playerResponse = $this->getPlayerResponseForAgeGate($html);
        if (!$playerResponse) {
            return null;
        }
        $videoFormats = $playerResponse['streamingData']['formats'];
        $videoMap = array();
        foreach ($videoFormats as $format) {
            $videoMap[$format['itag']] = $format['url'];
        }
        return $videoMap;
    }

    private function getPlayerResponseForAgeGate($html)
    {
        $url = $this->url;

        $videoId = $this->getVideoId($html);
        if (!$videoId) {
            return null;
        }

        $this->printMsg("video id: $videoId\n");
        return $this->getPlayerResponseFromEmbed($videoId);
    }

    private function getPlayerResponseFromEmbed($videoId)
    {
        $url = "https://www.youtube.com/get_video_info?video_id=$videoId";
        $html = Common::getContent($url);

        parse_str($html, $items);
        $playerResponse = $items['player_response'];
        return json_decode($playerResponse, true);
    }

    private function getVideoId($html)
    {
        $pattern = '/og:video:url" content="https:\/\/www.youtube.com\/embed\/([^"]+)">/';
        return Common::getFirstMatch($html, $pattern);
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

    private function getUrlByCipher($cipher)
    {
        parse_str($cipher, $items);
        $url = $items['url'];

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

        $url .= $paramSignature;
        return $url;
    }

    private function getVideoMapByCipherList($cipherList)
    {
        $urlList = array();
        foreach ($cipherList as $cipher) {
            if (empty($cipher)) {
                continue;
            }

            parse_str($cipher, $items);
            $this->printMsg($items);
            $url = $items['url'];

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

            $urlList[] = $url . $paramSignature;
        }
        return $this->getvideoMapByUrlList($urlList);
    }

    private function getVideoMapByUrlList($urlList)
    {
        foreach ($urlList as $url) {
            parse_str($url, $queries);
            $itag = $queries['itag'];

            $videoMap[$itag] = $url;
        }
        return $videoMap;
    }

    private function getPlayerConfig($html)
    {
        $patterns = [
            '/ytplayer\.config = ({.*?}});/',
            '/ytInitialPlayerResponse = ({.*?});/',
        ];
        foreach ($patterns as $pattern) {
            $configString = Common::getFirstMatch($html, $pattern);
            if ($configString) {
                return json_decode($configString, true);
            }
        }

        return null;
    }

    private function getPlayerUrl($html)
    {
        $patterns = [
            '/"(\/[^"]+?\/player_ias.+?\/base\.js)"/',
            '/"(\/yts\/jsbin\/player.+?base.js)"/',
            // '/"assets":.+?"js":\s*("[^"]+")/',
        ];

        foreach ($patterns as $pattern) {
            $url = Common::getFirstMatch($html, $pattern);
            if ($url) {
                return $url;
            }
        }

        return null;
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
        if (substr($url, 0, 1) === '/') {
            $url = "https://www.youtube.com$url";
        }

        $this->printMsg("\n player url: $url\n");

        $html = Common::getContent($url);

        $html = str_replace(";\n", ";", $html);

        $function_name_patterns = [
            '/([a-zA-Z$]{2})=function\(a\){a=a\.split\(""\);\w\w\./',
            '/c&&d\.set\([^,]+,encodeURIComponent\(([a-zA-Z0-9\$]+)\(/',
            '/\.sig\|\|([a-zA-Z0-9\$]+)\(/',
        ];

        $funcName = '';
        foreach ($function_name_patterns as $pattern) {
            $funcName = Common::getFirstMatch($html, $pattern);
            if ($funcName) {
                $this->printMsg("found decrypt function name [$funcName]\n");
                break;
            }
        }
        if (!$funcName) {
            return false;
        }

        $function_content_patterns = [
            "/$funcName=function\(a\){(.+?)}/",
            sprintf("/[,;]%s=function\(.*?\)\{(.+?)\}/", str_replace('$', '\\$', $funcName)),
            sprintf("/function %s\(.*?\)\{(.+?)\}/", str_replace('$', '\\$', $funcName)),
        ];

        $funcContent = '';
        foreach ($function_content_patterns as $pattern) {
            $this->printMsg("\n function content pattern: $pattern \n");
            $funcContent = Common::getFirstMatch($html, $pattern);
            if ($funcContent) {
                break;
            }
        }
        if (!$funcContent) {
            return false;
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

    private function reverse($array, $x = 0)
    {
        $array = array_reverse($array);

        return $array;
    }

    private function splice($array, $x)
    {
        $array = array_slice($array, $x);

        return $array;
    }

    private function swap($array, $x, $y = 0)
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

    private function chooseVideo($videoMap, $adaptiveMap = null)
    {
        // http://en.wikipedia.org/wiki/YouTube#Quality_and_codecs
        $itagMap = array(
            "37" => "mp4", // "1080p, MP4, H.264, AAC"
            "22" => "mp4", // "720p, MP4, H.264, AAC",
            "34" => "flv", // "360p, FLV, H.264, AAC",
            "18" => "mp4", // "360p, MP4, H.264, AAC",
            "35" => "flv", // "480p, FLV, H.264, AAC",
            "5" => "flv", // "240p, FLV, H.263, MP3",
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
            "171" => "webm", // DASH audio, Vorbis, 128
        );

        if (empty($videoMap)) {
            return false;
        }

        foreach ($itagMap as $itag => $ext) {
            if (array_key_exists($itag, $videoMap)) {
                return array(
                    "link" => $videoMap[$itag],
                    "ext" => $ext,
                );
            }
        }

        return array(
            "link" => reset($videoMap),
            "ext" => "flv",
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
    // $url = 'https://www.youtube.com/watch?v=m9tbPWjvGYM'; // Red Sparrow 2018 - Jennifer Lawrence School Scene - HD; age-gated
    // $url = 'https://www.youtube.com/watch?v=AQykKvUhTfo'; // B'z Live
    $url = 'https://www.youtube.com/watch?v=jNQXAC9IVRw'; // me at zoo

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
