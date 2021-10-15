<?php
if (!class_exists('Common')) {
    require 'common.php';
}

defined('DOWNLOAD_LIST_NAME') or define('DOWNLOAD_LIST_NAME', 'list_name');
defined('DOWNLOAD_LIST_FILES') or define('DOWNLOAD_LIST_FILES', 'list_files');
defined('DOWNLOAD_FILENAME') or define('DOWNLOAD_FILENAME', 'filename');
defined('DOWNLOAD_URL') or define('DOWNLOAD_URL', 'downloadurl');
defined('DOWNLOAD_LIST_SELECTED') or define('DOWNLOAD_LIST_SELECTED', 'list_selected');

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

    private function getByAndroidAPI($html)
    {
        $apiKey = 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';

        $videoId = $this->getVideoId($html);
        if (!$videoId) {
            $this->printMsg("failed to get video id\n");
            return null;
        }
        $this->printMsg("video id: $videoId\n");

        $apiUrl = "https://www.youtube.com/youtubei/v1/player?key=$apiKey";
        $headers = array(
            'X-YouTube-Client-Name: 3',
            'X-YouTube-Client-Version: 16.20',
            'content-type: application/json',
        );
        $post_data = array(
            'videoId' => $videoId,
            'context' => array(
                'client' => array(
                    'clientName' => 'ANDROID',
                    'clientVersion' => '16.20',
                    'hl' => 'en',
                )
            ),
            'playbackContext' => array(
                'contentPlaybackContext' => array(
                    'html5Preference' => 'HTML5_PREF_WANTS',
                )
            ),
            'contentCheckOk' => true,
            'racyCheckOk' => true,
        );
        $post_fields = json_encode($post_data);
        $resp = Common::getContent($apiUrl, $post_fields, $headers);
        if (empty($resp)) {
            $this->printMsg("Failed to get resp from Android API\n");
            return null;
        }

        $obj = json_decode($resp, true);
        if (empty($obj)) {
            $this->printMsg("Failed to json decode of resp from Android API\n");
            return null;
        }
        if (!array_key_exists('streamingData', $obj) || !array_key_exists('formats', $obj['streamingData'])) {
            $this->printMsg("Failed to get streamingData.formats from Android API\n");
            return null;
        }
        $formats = $obj['streamingData']['formats'];
        $map = array();
        foreach ($formats as $item) {
            $map[$item['itag']] = $item['url'];
        }

        return $map;
    }

    public function GetDownloadInfo()
    {
        $ret = array(
            DOWNLOAD_ERROR => 'ERR_FILE_NO_EXIST',
        );

        $url = $this->url;

        // 1. get html of YouTube url
        $html = Common::getContent($url);

        $videoMap = $this->getByAndroidAPI($html);

        if (empty($videoMap)) {
            $this->printMsg("Failed to get video map from Android API; try general get\n");
            $videoMap = $this->getVideoMapGeneral($html);
        }
        if (empty($videoMap)) {
            if (strpos($html, 'LOGIN_REQUIRED') !== false) {
                $this->printMsg("require login\n");
                $videoMap = $this->getVideoMapForAgeGate($html);
            }
        }
        $this->printMsg("\n=== gonna be video map ===\n");
        $this->printMsg($videoMap);
        $this->printMsg("\n=== video map end ===\n");

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

    public function GetFileList()
    {
        try {
            return $this->get_list();
        } catch (Exception $e) {
            $this->printMsg("Exception, " . $e->getMessage());
            return false;
        }
    }

    // https://www.youtube.com/playlist?list=OLAK5uy_lhO8Ij0yqMycUpr7Jnk4R0c1OfH4f84kk
    private function get_list()
    {
        $url = $this->url;

        $list_id = $this->getListIdFromUrl($url);
        if (!$list_id) {
            return false;
        }
        $list_url = "https://www.youtube.com/playlist?list=$list_id";

        $html = Common::getContent($list_url);
        $init_data = $this->get_initial_data($html);
        if (!$init_data) {
            $this->printMsg("Failed to get init data\n");
            return null;
        }
        $video_list = [];
        $tabs = $this->extract_array_by_key($init_data, "tabs");
        // $tabs = @$init_data["contents"]["twoColumnBrowseResultsRenderer"]["tabs"];
        if ($tabs) {
            $items = $this->extract_from_tabs($tabs);
            if (!$items) {
                $this->printMsg("Failed to extract data from tabs\n");
                return null;
            }
            $item_count = count($items);
            $video_list = $this->get_video_list($items);
        } else {
            $this->printMsg("Failed to extract from tabs \n");
            var_dump($html);
        }

        $playlist_title = $this->get_playlist_title($html);

        $ret = array(
            DOWNLOAD_LIST_NAME => $playlist_title,
            DOWNLOAD_LIST_FILES => $video_list,
        );

        return $ret;
    }

    private function get_playlist_title($html)
    {
        $pattern = '/meta property="og:title" content="(.+?)"/';
        return Common::getFirstMatch($html, $pattern);
    }

    private function get_video_list($items)
    {
        $video_list = [];
        foreach ($items as $key => $video) {
            $id = $video['videoId'];
            $text = @$video['title']['runs'][0]['text'];
            $no = $key + 1;
            array_push($video_list, [
                DOWNLOAD_FILENAME => sprintf("%02d. %s", $no, $text),
                DOWNLOAD_URL => "https://www.youtube.com/watch?v=$id",
            ]);
        }
        return $video_list;
    }

    private function extract_from_tabs($tabs)
    {
        return $this->find_all_values_by_key($tabs, "playlistVideoRenderer");
    }

    private function extract_array_by_key($value, $target_key)
    {
        if (!is_array($value)) {
            return null;
        }
        foreach ($value as $key => $value) {
            if ($key === $target_key) {
                return $value;
            }
            $found = $this->extract_array_by_key($value, $target_key);
            if ($found) {
                return $found;
            }
        }
        return null;
    }

    private function find_all_values_by_key($value, $target_key)
    {
        if (!is_array($value)) {
            return [];
        }
        $all_items = [];
        foreach ($value as $key => $value) {
            if ($key === $target_key) {
                return [$value];
            }
            $found = $this->find_all_values_by_key($value, $target_key);
            $all_items = array_merge($all_items, $found);

            $count = count($all_items);
        }
        return $all_items;
    }

    private function get_initial_data($html)
    {
        $pattern = '/var ytInitialData = ({.+?});<\/script>/';
        return json_decode(Common::getFirstMatch($html, $pattern), true);
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
        $url = "https://www.youtube.com/get_video_info?video_id=$videoId&html5=1&c=TVHTML5&cver=6.20180913";
        $html = Common::getContent($url);

        parse_str($html, $items);
        $playerResponse = $items['player_response'];
        return json_decode($playerResponse, true);
    }

    private function getListIdFromUrl($url)
    {
        $query = parse_url($url, PHP_URL_QUERY);
        parse_str($query, $items);
        return $items['list'];
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
        $configString = Common::getFirstMatchByPatterns($html, $patterns);
        if ($configString) {
            return json_decode($configString, true);
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
        $url = Common::getFirstMatchByPatterns($html, $patterns);
        if ($url) {
            return $url;
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

        // Nx=function(a){a=a.split("");Mx["do"](a,17);Mx.FH(a,61);Mx.xK(a,3);Mx["do"](a,12);Mx.xK(a,1);Mx.FH(a,37);Mx["do"](a,47);Mx.FH(a,6);return a.join("")};
        $function_name_patterns = [
            '/([a-zA-Z$]{2})=function\(a\){a=a\.split\(""\);\w\w[\.\[]/',
            '/c&&d\.set\([^,]+,encodeURIComponent\(([a-zA-Z0-9\$]+)\(/',
            '/\.sig\|\|([a-zA-Z0-9\$]+)\(/',
        ];

        $funcName = Common::getFirstMatchByPatterns($html, $function_name_patterns);
        if (!$funcName) {
            $this->printMsg("Failed to find function name\n");
            return false;
        }

        $this->printMsg("found decrypt function name [$funcName]\n");

        $function_content_patterns = [
            "/$funcName=function\(a\){(.+?)}/",
            sprintf("/[,;]%s=function\(.*?\)\{(.+?)\}/", str_replace('$', '\\$', $funcName)),
            sprintf("/function %s\(.*?\)\{(.+?)\}/", str_replace('$', '\\$', $funcName)),
        ];

        $funcContent = Common::getFirstMatchByPatterns($html, $function_content_patterns);
        if (!$funcContent) {
            return false;
        }
        $this->printMsg("\n == decrypt function content begin == \n");
        $this->printMsg($funcContent);
        $this->printMsg("\n == decrypt function content end == \n");

        $pattern = sprintf("/\.?\[?\"?([a-zA-Z0-9\$]{2})\"?\]?\(a,([0-9]+)\)/");
        $ret = preg_match_all($pattern, $funcContent, $matches, PREG_SET_ORDER);

        $subFuncDict = array();

        $actions = array();
        foreach ($matches as $match) {
            $name = $match[1];
            $parameter = $match[2];
            $type = null;

            if (array_key_exists($name, $subFuncDict)) {
                $type = $subFuncDict[$name];
            } else {
                $type = $this->parseSubFuncType($html, $name);
                if (!$type) {
                    continue;
                }
                $subFuncDict[$name] = $type;
            }

            $actions[] = array('type' => $type, 'parameter' => $parameter);

            $this->printMsg("name: $name, type: $type, para: $parameter\n");
        }

        $funcName = 'decrypt_general';
        $this->actions = $actions;
        $this->decryptFuncArray = array($this, $funcName);

        return $this->decryptFuncArray;
    }

    private function parseSubFuncType($html, $funcName)
    {
        // FH:function(a){a.reverse()},
        // "do":function(a,b){var c=a[0];a[0]=a[b%a.length];a[b%a.length]=c},
        // xK:function(a,b){a.splice(0,b)}
        $patterns = [
            sprintf("/\"?%s\"?:function\(a,b\)\{(a\..+?)\}/", $funcName),
            sprintf("/\"?%s\"?:function\(a,b\)\{(var c=a.+?)\}/", $funcName),
        ];
        $content = Common::getFirstMatchByPatterns($html, $patterns);
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
            $this->printMsg("Failed to get decrypt function array\n");
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
    $url = 'https://www.youtube.com/watch?v=iul4SBlHIf8'; // Oasis - Don't look back in anger
    // $url = 'http://www.youtube.com/watch?v=UHFAjkD_LLg'; // Taylor Swift feat Paula Fernandes Long Live VEVO 1080p
    // $url = 'https://www.youtube.com/watch?v=7QdCnvixNvM';
    // $url = 'http://www.youtube.com/watch?v=w3KOowB4k_k'; // Mariah Carey - Honey (VEVO)
    $url = 'https://www.youtube.com/watch?v=2LbEN_Ph1-E'; // amuro namie - Sweet Kisses
    $url = 'https://www.youtube.com/watch?v=rfFEhd7mk7c'; // DJ Earworm Mashup - United State of Pop 2015
    $url = 'https://www.youtube.com/watch?v=RGRCx-g402I'; // Aimer Sun Dance Penny Rain
    // $url = 'https://www.youtube.com/watch?v=m9tbPWjvGYM'; // Red Sparrow 2018 - Jennifer Lawrence School Scene - HD; age-gated
    // $url = 'https://www.youtube.com/watch?v=AQykKvUhTfo'; // B'z Live
    $url = 'https://www.youtube.com/watch?v=jNQXAC9IVRw'; // me at zoo
    // $url = 'https://www.youtube.com/watch?v=_tRZ5EQHMqI'; // 韋禮安 WeiBird - 一口一口
    // $url = 'https://www.youtube.com/playlist?list=OLAK5uy_kDvtd-ErInjfwkppvJ9DGp1-CgJBccpHc'; // Chata - SUMMER FOCUS

    $get_list = false;
    if ($argc >= 2) {
        $argument = $argv[1];
        if ($argument === '--list') {
            $argument = $argv[2];
            $get_list = true;
        }
        if (substr(strtolower($argument), 0, 4) === 'http') {
            $url = $argument;
        }
    }

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, '', '', array(), true);

    if ($get_list) {
        // php host.php --list [youtube_playlist_Url]
        echo "Show list of $url\n\n";
        $info = $obj->GetFileList();
    } else {
        // php host.php [youtube_url]
        echo "Get download info of '$url'\n\n";
        $info = $obj->GetDownloadInfo();
    }

    print_r($info);
}

// vim: expandtab ts=4
