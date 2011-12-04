<?php

require_once 'GSB_Client.php';

class GSB_ClientTests extends PHPUnit_Framework_TestCase {

    public function testParseFullHashResponse() {
        $raw = "foobar:20:10\n0123456789";
        $expected = array(
            array(
                'listname' => 'foobar',
                'add_chunk_num' => 20,
                'hash' => bin2hex('0123456789')
            )
        );
        $a = GSB_Client::parseFullhashResponse($raw);
        $this->assertEquals($expected, $a);

        $expected = array(
            array(
                'listname' => 'foobar',
                'add_chunk_num' => 20,
                'hash' => bin2hex('0123456789')
            ),
            array(
                'listname' => 'dingbat',
                'add_chunk_num' => 30,
                'hash' => bin2hex('123456789')
            )
        );

        $raw = "foobar:20:10\n0123456789dingbat:30:9\n123456789";
        $a = GSB_Client::parseFullhashResponse($raw);
        $this->assertEquals($expected, $a);
    }
}