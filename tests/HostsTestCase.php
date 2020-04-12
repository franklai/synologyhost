<?php
declare (strict_types = 1);

use PHPUnit\Framework\TestCase;

define('DOWNLOAD_ERROR', 'DOWNLOAD_ERROR');
define('DOWNLOAD_URL', 'DOWNLOAD_URL');
define('DOWNLOAD_FILENAME', 'DOWNLOAD_FILENAME');

class HostsTestCase extends TestCase
{
    protected $module;
    protected $ref_class;

    protected function setUp(): void
    {
        $this->ref_class = new ReflectionClass($this->module);
    }

    protected function get_filename($url)
    {
        $verbose = false;
        $obj = $this->ref_class->newInstance($url, '', '', array(), $verbose);
        $info = $obj->GetDownloadInfo();
        return $info['DOWNLOAD_FILENAME'];
    }

    protected function get($url, $filename_answer)
    {
        $filename = $this->get_filename($url);
        $this->assertEquals($filename, $filename_answer);
    }
}
