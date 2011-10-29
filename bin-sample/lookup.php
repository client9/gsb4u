#!/usr/bin/env php
<?php

require_once 'intro.php';
require_once 'GSB_Client.php';

$client  = new GSB_Client($storage, $network, $logger);

$len = count($argv);
for ($i = 1; $i < $len; $i++) {
    $url = $argv[$i];
    $matches = $client->doLookup($url);
    print "$url: " . count($matches) . "\n";
}


//$client->doLookup('http://malware.testing.google.test/testing/malware/');
//$client->doLookup('http://www.iospace.com/');
//$client->doLookup('http://the-zen.co.kr/');
//$client->doLookup('http://adingurj.com/');
//$client->doLookup('http://matzines.com/');
// http://online-xp-antivir.com 