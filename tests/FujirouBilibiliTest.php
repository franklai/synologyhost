<?php
// declare (strict_types = 1);

require_once 'HostsTestCase.php';

final class FujirouBilibiliTest extends HostsTestCase
{
    protected $module = 'FujirouHostBilibili';

    public function testGetAid()
    {
        $url = 'https://www.bilibili.com/video/av540146714';
        $filename = '【ARASHI】【字】周六的岚朋友SP 2020.04.04 龟梨和也 山下智久 浅田舞 浅田真央【Aloha字幕组】.flv';
        $cid = '174481071';

        $this->get($url, $filename, $cid);
    }

    public function testGetBvid()
    {
        $url = 'https://www.bilibili.com/video/BV1QE411P7E7/';
        $filename = '【岚】伏兵组的胡闹小剧场.flv';
        $cid = '166644611';

        $this->get($url, $filename, $cid);
    }

    public function testBangumiSs()
    {
        if (getenv('GITHUB_ACTIONS')) {
            $this->markTestSkipped('skip GitHub Actions due to ip/country restriction.');
        }

        $url = 'https://www.bilibili.com/bangumi/play/ss33092';
        $filename = '【4月】阿爾蒂（僅限台灣地區）01.flv';
        $cid = '173509498';

        $this->get($url, $filename, $cid);
    }

    public function testGetPages()
    {
        $url = 'https://www.bilibili.com/video/BV1vs411k7tn?p=11';
        $filename = '「Thunderbolt Fantasy 东离剑游纪」原声集专辑 - 11.Kguy&kill don_t 生kiLL.flv';
        $cid = '9726377';

        $this->get($url, $filename, $cid);
    }

    public function testList()
    {
        $url = 'https://www.bilibili.com/video/BV1qp4y1e7UA';
        $list_title = '【我想加个】【字】200910庚子年乙酉月丙辰日山上刮风下雨';
        $list_length = 4;

        $obj = $this->get_obj($url);
        $info = $obj->GetFileList();

        $this->assertEquals($info['list_name'], $list_title);
        $this->assertEquals(count($info['list_files']), $list_length);
    }
}
