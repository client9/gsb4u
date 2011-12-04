<?php

require_once 'GSB_Updater.php';

class GSB_UpdaterTests extends PHPUnit_Framework_TestCase {

    function xtestDownloadParse() {
        $raw = <<<EOT
n:1858
i:goog-malware-shavar
u:safebrowsing-cache.google.com/safebrowsing/rd/1
u:safebrowsing-cache.google.com/safebrowsing/rd/2
EOT;

        $result = GSB_Updater::parseDownloadResponse($raw);
        print_r($result);
    }

    function testNetwork2Int() {
        $str = "\0\0\0\0";
        $val = GSB_Updater::network2int($str);
        $this->assertEquals(0, $val);

        $str = "\0\0\0\1";
        $val = GSB_Updater::network2int($str);
        $this->assertEquals(1, $val);

        $str = "\1\0\0\0";
        $val = GSB_Updater::network2int($str);
        $this->assertEquals(16777216, $val);

        // now test failure case
        $str = "\0\0\0";
        try {
            $val = GSB_Updater::network2int($str);
            $this->assertTrue(FALSE);
        } catch (GSB_Exception $e) {
            $this->assertTrue(TRUE);
        }
    }

    function testListToRange() {
        $vals = array(1,2,3,5,6,8,10,12);
        $str = GSB_Updater::list2range($vals);
        $this->assertEquals("1-3,5-6,8,10,12", $str);

        $vals = array(1,2,3,4);
        $str = GSB_Updater::list2range($vals);
        $this->assertEquals("1-4", $str);

        $vals = array(1,3,5,7);
        $str = GSB_Updater::list2range($vals);
        $this->assertEquals('1,3,5,7', $str);

        $vals = array(1);
        $str = GSB_Updater::list2range($vals);
        $this->assertEquals('1', $str);
    }

    function testRangeToList() {
        $s = '93865-93932';
        $expected = array(
            array(93865, 93932)
        );
        $a = GSB_Updater::range2list($s);
        $this->assertEquals($expected, $a);

        $s = '1-3,5-6,9,11';
        $expected = array(
            array(1,3),
            array(5,6),
            array(9,9),
            array(11,11)
        );
        $a = GSB_Updater::range2list($s);
        $this->assertEquals($expected, $a);


        $s = '1';
        $expected = array(
            array(1,1)
        );
        $a = GSB_Updater::range2list($s);
        $this->assertEquals($expected, $a);

    }
}
