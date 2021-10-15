<?php

class Common
{
    public static function getContent($url, $post_fields=null, $headers=null)
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        if (!empty($post_fields)) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_fields);
        }
        if (!empty($headers)) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        }

        // curl_setopt($curl, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Safari/537.36 Edg/92.0.902.67");
        // curl_setopt($curl, CURLOPT_VERBOSE, true);

        curl_setopt($curl, CURLOPT_URL, $url);

        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }

    // return substring that match prefix and suffix
    // returned string contains prefix and suffix
    public static function getSubString($string, $prefix, $suffix)
    {
        $start = strpos($string, $prefix);
        if ($start === false) {
            echo "cannot find prefix, string:[$string], prefix[$prefix]\n";
            return $string;
        }

        $end = strpos($string, $suffix, $start);
        if ($end === false) {
            echo "cannot find suffix [$suffix]\n";
            return $string;
        }

        if ($start >= $end) {
            return $string;
        }

        return substr($string, $start, $end - $start + strlen($suffix));
    }

    public static function getFirstMatch($string, $pattern)
    {
        if (1 === preg_match($pattern, $string, $matches)) {
            return $matches[1];
        }
        return false;
    }

    public static function getFirstMatchByPatterns($string, $patterns)
    {
        foreach ($patterns as $pattern) {
            $matched = self::getFirstMatch($string, $pattern);
            if ($matched) {
                return $matched;
            }
        }
        return false;
    }

    public static function getAllFirstMatch($string, $pattern)
    {
        $ret = preg_match_all($pattern, $string, $matches);
        if ($ret > 0) {
            return $matches[1];
        } else {
            return $ret;
        }
    }

    public static function hasString($string, $pattern)
    {
        return (false === strpos($string, $pattern)) ? false : true;
    }

    public static function decodeHtml($html)
    {
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        $html = str_replace('&apos;', "'", $html);
        return $html;
    }

    public static function sanitizePath($path)
    {
        $specialChars = array('\\', '/', ':', '*', '?', '"', '<', '>', '|');
        return str_replace($specialChars, '_', $path);
    }

    public static function debug($string)
    {
        if (!array_key_exists('HTTP_USER_AGENT', $_SERVER)
            && !defined('DOWNLOAD_STATION_USER_AGENT')) {
            echo $string . "\n";
        }
    }
}

// vim: expandtab ts=4
