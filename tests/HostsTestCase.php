<?php
declare (strict_types = 1);

use PHPUnit\Framework\TestCase;

$defines = ['DOWNLOAD_ERROR', 'DOWNLOAD_FILENAME', 'DOWNLOAD_URL', 'DOWNLOAD_COOKIE'];
foreach ($defines as $key) {
    if (!defined($key)) {
        define($key, $key);
    }
}

class HostsTestCase extends TestCase
{
    protected $module;
    protected $ref_class;

    protected function setUp(): void
    {
        $this->ref_class = new ReflectionClass($this->module);
    }

    protected function get_obj($url)
    {
        $verbose = false;
        $obj = $this->ref_class->newInstance($url, '', '', array(), $verbose);
        return $obj;
    }

    protected function get_filename($obj)
    {
        $info = $obj->GetDownloadInfo();
        if (array_key_exists('DOWNLOAD_FILENAME', $info)) {
            return $info['DOWNLOAD_FILENAME'];
        } else {
            return '';
        }        
    }

    protected function get($url, $filename_answer, $cid_answer = null)
    {
        $obj = $this->get_obj($url);
        $filename = $this->get_filename($obj);
        $this->assertEquals($filename, $filename_answer);

        if ($cid_answer) {
            $cid = $obj->get_cid();
            $this->assertEquals($cid, $cid_answer);
        }
    }
}
