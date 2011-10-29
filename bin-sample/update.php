#!/usr/bin/env php
<?php

require_once 'intro.php';

$x = new GSB_Updater($storage, $network, $logger);

// get new chunks
$x->downloadData($gsblists, FALSE);

// zap outdated fullhash definitions (they are only good for 45m)
$storage->fullhash_delete_old();


