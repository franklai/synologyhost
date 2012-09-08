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
            echo "cannot find suffix\n";
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

    static function hasString($string, $pattern)
    {
        return (FALSE === strpos($string, $pattern))? FALSE : TRUE;
    }

    static function sanitizePath($path) {
        $specialChars = array('\\', '/', ':', '*', '?', '"', '<', '>', '|');
        return str_replace($specialChars, '_', $path);
    }

    static function debug($string)
    {
        if (!array_key_exists('HTTP_USER_AGENT', $_SERVER)) {
//             echo $string ."\n";
        }
    }
}

if (basename($argv[0]) === basename(__FILE__)) {
    $testPath1     = 'ac/dc & your life? pipe |, back\slash no way <b style="color: black;">i ain\'t </b>';
    $expectedPath1 = 'ac_dc & your life_ pipe _, back_slash no way _b style=_color_ black;__i ain\'t __b_';

    $resultPath1 = Common::sanitizePath($testPath1);

    if ($expectedPath1 === $resultPath1) {
        echo "two string matched\n";
    } else {
        echo "not the same, \n  $expectedPath1\n  $resultPath1\n";
    }
}

// vim: expandtab ts=4
?>
