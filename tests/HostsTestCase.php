<?php
declare (strict_types = 1);

use PHPUnit\Framework\TestCase;


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
        return $info['DOWNLOAD_FILENAME'];
    }

    protected function get($url, $filename_answer, $cid_answer)
    {
        $obj = $this->get_obj($url);
        $filename = $this->get_filename($obj);
        $this->assertEquals($filename, $filename_answer);

        $cid = $obj->get_cid();
        $this->assertEquals($cid, $cid_answer);
    }
}
