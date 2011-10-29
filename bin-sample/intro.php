<?php

error_reporting(-1);
ini_set('error_log', '/dev/stderr');
ini_set('max_execution_time', -1);
ini_set('memory_limit', -1);

date_default_timezone_set('UTC');


set_include_path('.' . PATH_SEPARATOR . '../lib');

// turn off output buffering
//ob_end_flush();

require_once 'GSB_Updater.php';
require_once 'GSB_Request.php';
require_once 'GSB_Storage.php';
require_once 'GSB_Exception.php';
require_once 'GSB_Logger.php';

$api = 'YOUR-API-KEY-HERE';
$gsblists = array('goog-malware-shavar', 'googpub-phish-shavar');

$dbh = new PDO('mysql:host=127.0.0.1;dbname=gsb', 'root');
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$storage = new GSB_StoreDB($dbh);
$network = new GSB_Request($api);
$logger = new GSB_Logger(5);
