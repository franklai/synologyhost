<?php
// declare (strict_types = 1);

require_once 'HostsTestCase.php';

final class FujirouDailymotionTest extends HostsTestCase
{
    protected $module = 'FujirouHostDailymotion';

    public function testGet()
    {
        $url = 'https://www.dailymotion.com/video/x65eahd';
        $filename = '【妙WOW種子】Seed - 23 圍棋女神黑嘉嘉 生日快樂！.mp4';

        $this->get($url, $filename);
    }

    public function testGetFromPlaylist()
    {
        $url = 'https://www.dailymotion.com/video/xt1mw1?playlist=x1hlho';
        $filename = 'Perfume 3rd Tour JPN - チョコレイト・ディスコ.mp4';

        $this->get($url, $filename);
    }
}
