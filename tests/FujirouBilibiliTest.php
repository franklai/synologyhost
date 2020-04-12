<?php
// declare (strict_types = 1);

require_once 'HostsTestCase.php';

final class FujirouBilibiliTest extends HostsTestCase
{
    protected $module = 'FujirouHostBilibili';

    public function testGetAid()
    {
        $url = 'https://www.bilibili.com/video/av540146714';
        $filename = '【ARASHI】【字】周六的岚朋友SP 2020.04.04 龟梨和也 山下智久 浅田舞 浅田真央【Aloha字幕组】 - [Aloharashi]20200404_arashi_ni_shiyagar_chinese_subbed.flv';

        $this->get($url, $filename);
    }

    public function testGetBvid()
    {
        $url = 'https://www.bilibili.com/video/BV1QE411P7E7/';
        $filename = '【岚】伏兵组的胡闹小剧场 - 【岚】关于伏兵组的那些小剧场.flv';

        $this->get($url, $filename);
    }
}
