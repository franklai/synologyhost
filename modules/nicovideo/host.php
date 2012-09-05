<?php

if (!class_exists('Common')) {
    require('common.php');
}
if (!class_exists('Curl')) {
    require('curl.php');
}

class FujirouHostNicoVideo {
    public function __construct($url, $username, $password, $hostInfo) {
        $this->url = $url;
        $this->username = $username;
        $this->password = $password;
        $this->hostInfo = $hostInfo;

        $this->cookiePath = '/tmp/nicovideo.cookie.txt';
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

        $userSession = $this->GetUserSession($this->username, $this->password);

        // failed to login, return error
        if (empty($userSession)) {
            return $ret;
        }

        $videoId = $this->GetVideoId($this->url);

        $title = $this->GetVideoTitle($this->url);

        $videoUrl = $this->GetVideoUrl($videoId, $userSession);
        Common::debug("url: $videoUrl, id: $videoId");

        $testResult = $this->DoLinkTest($videoUrl, $this->cookiePath);
        Common::debug("link test result is [$testResult]");

        $filename = Common::sanitizePath($title);
        if ($testResult === 'video/mp4') {
            $filename .= ".mp4";
        } else if ($testResult === 'video/flv') {
            $filename .= ".flv";
        }

        $ret = array(
            "downloadurl" => $videoUrl,
            "filename"    => $filename,
            "cookiepath"  => $this->cookiePath
        );

        return $ret;
    }

    // shall return true or false
    public function Verify() {
        $userSession = $this->GetUserSession($this->username, $this->password);

        if (!empty($userSession)) {
            return USER_IS_FREE;
        } else {
            return LOGIN_FAIL;
        }
    }

    private function DoLinkTest($url, $cookiePath) {
        $ret = FALSE;

        $ch = curl_init();
// curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
        curl_setopt($ch, CURLOPT_NOBODY, TRUE); // send HTTP HEAD
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Accept-Encoding. Empty sent all supported encoding types
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE); // Do not follow any "Location: " header
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // stop verifying peer's certificate
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookiePath); // set load cookie file
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookiePath); // set save cookie file
        curl_setopt($ch, CURLOPT_URL, $url); // set url

        $reply = curl_exec($ch);
        $replyInfo = curl_getinfo($ch);
        if ($replyInfo['http_code'] === 200) {
            $ret = $replyInfo['content_type'];
        }
        curl_close($ch);

        return $ret;
    }

    private function GetUserSession($username, $password) {
        // url:  https://secure.nicovideo.jp/secure/login
        // parameter in post: mail, password
        $userSession = '';
        $url = 'https://secure.nicovideo.jp/secure/login';

        $postData = http_build_query(array(
            "mail"     => $username,
            "password" => $password
        ));

        $response = new Curl($url, $postData, NULL, $this->cookiePath);
        $cookie = $response->get_header('Set-Cookie');

        if (Common::hasString($cookie, 'user_session')) {
            $pattern = '/(user_session=user_[^;]+);/';
            $userSession = Common::getFirstMatch($cookie, $pattern);
        }
        Common::debug("session is [$userSession]");
        
        return $userSession;
    }

    private function GetVideoId($url) {
        $pattern = '/watch\/([^\/]+)/';
        return Common::getFirstMatch($url, $pattern);
    }

    private function GetVideoTitle($url) {
        $response = new Curl($url, NULL, NULL, $this->cookiePath);
        $html = $response->get_content();

        $pattern = '/<h2>(.*)<\/h2>/';
        $title = Common::getFirstMatch($html, $pattern);

        if (empty($title)) {
            $pattern = '/<h1>([^<]+)<\/h1>/';
            $title = Common::getFirstMatch($html, $pattern);
        }
        if (empty($title)) {
            $pattern = '/<title>([^<]+)<\/title>/';
            $title = Common::getFirstMatch($html, $pattern);
        }
        $title = html_entity_decode($title, ENT_QUOTES, 'UTF-8');
        Common::debug("video title is [$title]");

        if (empty($title)) {
            $title = 'Unknown title';
        }

        return $title;
    }

    private function GetVideoUrl($videoId) {
        if (empty($videoId)) {
            return FALSE;
        }

        // api
        // http://flapi.nicovideo.jp/api/getflv/[video id]
        $url = sprintf(
            "http://flapi.nicovideo.jp/api/getflv/%s",
            $videoId
        );

        $response = new Curl($url, NULL, NULL, $this->cookiePath);
        $encodedVideoInfo = $response->get_content();
        $videoInfo = urldecode($encodedVideoInfo);

        $pattern = '/url=(http[^&]+)&/';
        $videoUrl = Common::getFirstMatch($videoInfo, $pattern);

        return $videoUrl;
    }
}

if (basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostNicoVideo';
    $url = 'http://www.nicovideo.jp/watch/sm5946332';

    $ini_file = 'account.conf';
    $setting = parse_ini_file($ini_file);
    $username = $setting['username'];
    $password = $setting['password'];

    $refClass = new ReflectionClass($module);
    $obj = $refClass->newInstance($url, $username, $password, array());

    $verifyRet = $obj->Verify();
    Common::debug("Verify return value: [$verifyRet]");

    $info = $obj->GetDownloadInfo();

    var_dump($info);
}

// vim: expandtab ts=4
?>
