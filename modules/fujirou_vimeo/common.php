<?php

class Common {
    // return substring that match prefix and suffix
    // returned string contains prefix and suffix
    static function getSubString($string, $prefix, $suffix) {
        $start = strpos($string, $prefix);
        if ($start === FALSE) {
            echo "cannot find prefix, string:[$string], prefix[$prefix]\n";
            return $string;
        }

        $end = strpos($string, $suffix, $start);
        if ($end === FALSE) {
            echo "cannot find suffix [$suffix]\n";
            return $string;
        }

        if ($start >= $end) {
            return $string;
        }

        return substr($string, $start, $end - $start + strlen($suffix));
    }

    static function getFirstMatch($string, $pattern) {
        if (1 === preg_match($pattern, $string, $matches)) {
            return $matches[1];
        }
        return FALSE;
    }

    static function getAllFirstMatch($string, $pattern) {
        $ret = preg_match_all($pattern, $string, $matches);
        if ($ret > 0) {
            return $matches[1];
        } else {
            return $ret;
        }
    }

    static function hasString($string, $pattern) {
        return (FALSE === strpos($string, $pattern))? FALSE : TRUE;
    }

    static function decodeHtml($html) {
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        $html = str_replace('&apos;', "'", $html);
        return $html;
    }

    static function sanitizePath($path) {
        $specialChars = array('\\', '/', ':', '*', '?', '"', '<', '>', '|');
        return str_replace($specialChars, '_', $path);
    }

    static function debug($string)
    {
        if (!array_key_exists('HTTP_USER_AGENT', $_SERVER)
            && !defined('DOWNLOAD_STATION_USER_AGENT')) {
            echo $string ."\n";
        }
    }
}

// vim: expandtab ts=4
?>
