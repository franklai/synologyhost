<?php
require_once 'host.php';

if (!empty($argv) && basename($argv[0]) === basename(__FILE__)) {
    $module = 'FujirouHostYouTube';

    $urls = [
        [
            'http://www.youtube.com/watch?v=iul4SBlHIf8',
            'Oasis - Don\'t look back in anger.mp4',
        ],
        [
          'https://www.youtube.com/watch?v=rfFEhd7mk7c',
          'DJ Earworm Mashup - United State of Pop 2015 (50 Shades of Pop).mp4',
        ],
        [
          'https://www.youtube.com/watch?v=tNo3LuZXA1w',
          '黃妃 Huang Fei【追追追】Official Music Video.mp4',
        ],
        [
          'http://www.youtube.com/watch?v=UHFAjkD_LLg',
          'Taylor Swift feat Paula Fernandes Long Live VEVO 1080p.mp4',
        ],
        [
            'https://www.youtube.com/watch?v=RGRCx-g402I',
            'Aimer 『3min』MUSIC VIDEO (5th album『Sun Dance』『Penny Rain』4_10同時発売).mp4',
        ],
        [
            'https://www.youtube.com/watch?v=m9tbPWjvGYM',
            'Red Sparrow 2018 - Jennifer Lawrence School Scene - HD.mp4',
        ],
    ];

    $refClass = new ReflectionClass($module);

    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_WARNING);

    foreach ($urls as $item) {
      list($url, $filename) = $item;
      $obj = $refClass->newInstance($url, '', '', array(), false);
      $info = $obj->GetDownloadInfo();

      $response = new Curl($url);
      $good = ($response->get_header('Status-Code') === '200');

      if ($info['DOWNLOAD_FILENAME'] === $filename && $good) {
        echo "o $filename\n";
      } else {
        echo "x $filename, $url\n";
      }
    }
}
