<?php
// declare (strict_types = 1);

require_once 'HostsTestCase.php';

final class FujirouYouTubeTest extends HostsTestCase
{
    protected $module = 'FujirouHostYouTube';

    public function testBlankSpace()
    {
        $url = 'https://www.youtube.com/watch?v=e-ORhEE9VVg';
        $filename = 'Taylor Swift - Blank Space.mp4';

        $this->get($url, $filename);
    }

    public function testTopGun2020()
    {
        $url = 'https://www.youtube.com/watch?v=g4U4BQW9OEk';
        $filename = 'Top Gun_ Maverick (2020) – New Trailer - Paramount Pictures.mp4';

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
        $url = 'https://www.youtube.com/watch?v=m9tbPWjvGYM';
        $filename = 'Red Sparrow 2018 - Jennifer Lawrence School Scene - HD.mp4';

        $this->get($url, $filename);
    }
}
