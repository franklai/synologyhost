<?php
// declare (strict_types = 1);

require_once 'HostsTestCase.php';

final class FujirouVimeoTest extends HostsTestCase
{
    protected $module = 'FujirouHostVimeo';

    public function testGet()
    {
        $url = 'https://vimeo.com/43234495';
        $filename = 'Perfume Desktop Disco.mp4';

        $this->get($url, $filename);
    }

    public function testGetFromPlaylist()
    {
        $url = 'https://vimeo.com/109229310';
        $filename = 'Michael Jordan _23_.mp4';

        $this->get($url, $filename);
    }

    public function testGetJapaneseTitle()
    {
        $url = 'https://vimeo.com/647593213';
        $filename = 'コードギアス Genesic Re;CODE 反逆のルルーシュ篇.mp4';

        $this->get($url, $filename);
    }
}
