<?php
// declare (strict_types = 1);

require_once 'HostsTestCase.php';

final class FujirouYouTubeTest extends HostsTestCase
{
    protected $module = 'FujirouHostYouTube';

    public function testBlankSpace()
    {
        $url = 'https://www.youtube.com/watch?v=YHxSSEYA7_E';
        $filename = '水樹奈々『禁断のレジスタンス』MUSIC CLIP（Full Ver.）.mp4';

        $this->get($url, $filename);
    }

    public function testTopGun2020()
    {
        $url = 'https://www.youtube.com/watch?v=g4U4BQW9OEk';
        $filename = 'Top Gun_ Maverick (2021) – New Trailer - Paramount Pictures.mp4';

        $this->get($url, $filename);
    }

    public function testMeAtZoo()
    {
        $url = 'https://www.youtube.com/watch?v=jNQXAC9IVRw';
        $filename = 'Me at the zoo.mp4';

        $this->get($url, $filename);
    }

    public function testAgeGate()
    {
        $url = 'https://www.youtube.com/watch?v=YKf6BJi-9tU';
        $filename = 'LIFE - Restricted Trailer (In Theaters March 24).mp4';

        $this->get($url, $filename);
    }

    public function testMusicTrack()
    {
        $url = 'https://www.youtube.com/watch?v=_tRZ5EQHMqI';
        $filename = '一口一口.mp4';

        $this->get($url, $filename);
    }
}
