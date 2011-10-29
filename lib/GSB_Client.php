<?php

require_once 'GSB_URL.php';
require_once 'GSB_Exception.php';

/**
 * Provides functions to create hash keys for a given host and domain, and
 * lookup functions for the GSB blacklist.<p>
 *
 * Look at the GSB API specs for more details.
 */
class GSB_Client {

    var $store;
    var $gsb;

    /** Constructor
     *
     */
    function __construct($storage, $network, $logger) {
        $this->store = $storage;
        $this->gsb   = $network;
        $this->log   = $logger;
    }

    /**
     * Format a full-hash-lookup request
     * @param array $prefixes a list of prefixes
     * @return string  The formatted request body
     */
    function format_FullLookup($prefixes) {
        $num_p = count($prefixes);
        if ($num_p == 0) {
            throw new GSB_Exception("Got prefix list of 0");
        }
        $sz = -1;
        $data = '';
        foreach ($prefixes as $p) {
            if ($sz == -1) {
                $sz = strlen($p);
            } else {
                if ($sz != strlen($p)) {
                    throw new GSB_Exception("Prefixes have different sizes.  Not sure what to do");
                }
            }
            $data .= pack('H*', $p);
        }

        $body =  sprintf("%d:%d\n%s", $sz/2, strlen($data), $data);
        return $body;
    }

    function doFullLookup($candidates) {
        $matches = array();

        $prefixes = array();
        foreach ($candidates  as  $row) {

            // let's see if we have a existing match
            //print_r($row);
            if ($this->store->fullhash_exists($row)) {
                $this->log->debug(sprintf("Found %s in fullhash database",
                                          $row['match']));

                $row['listname'] = $this->store->id2list($row['list_id']);
                $matches[] = $row;
            } else {
                $prefixes[] = $row['prefix'];
            }
        }

        if (count($prefixes) == 0) {
            return $matches;
        }


        $body = $this->format_FullLookup($prefixes);

        $result = $this->gsb->getFullHash($body);

        // Extract hashes from response
        $extractedhashes = $this->parseFullhashResponse($result);

        foreach ($extractedhashes as $row) {

            // always insert
            $this->store->fullhash_insert($row);

            // now see if we have a match
            foreach ($candidates as $c) {
                $row['list_id'] = $this->store->list2id($row['listname']);

                if ($row['hash'] == $c['hash'] &&
                    $row['add_chunk_num'] == $c['add_chunk_num'] &&
                    $this->store->list2id($row['listname']) == $c['list_id']) {

                    $matches[] = $row;
                }
            }
        }

        return $matches;
    }

    /**
     * Process data provided from the response of a full-hash GSB request
     *
     * @param string $data
     * @return multitype:
     */
    static function parseFullhashResponse(&$raw) {
        $rows = array();
        $offset = 0;
        while (($pos = strpos($raw, "\n", $offset)) !== FALSE) {
            $header = substr($raw, $offset, $pos - $offset);
            list($listname, $addchunk, $hashlen) = explode(':', $header, 3);
            $hash = bin2hex(substr($raw, $pos + 1, $hashlen));
            $rows[] = array(
                'listname' => $listname,
                'add_chunk_num' => (int) $addchunk,
                'hash' => bin2hex(substr($raw, $pos + 1, $hashlen))
            );
            $offset = $pos + 1 + $hashlen;
        }
        return $rows;
    }

    /**
     * Does a full URL lookup on given lists, will check if its in
     * database, if slight match there then will do a full-hash lookup
     * on GSB, returns (bool) true on match and (bool) false on
     * negative.
     *
     * @param string $url
     * @return boolean
     */
    function doLookup($url) {

        // first canonicalize the URL
        $canurl = GSB_UrlUtil::canonicalize($url);

        $phashes = GSB_UrlUtil::makePrefixesHashes($canurl['host'],
                                                   $canurl['path'],
                                                   $canurl['query'],
                                                   $canurl['is_ip']);

        // make hostkeys
        $hostkeys = GSB_UrlUtil::makeHostKeyList($canurl['host'],
                                                 $canurl['is_ip']);
        $candidates = array();
        foreach ($hostkeys as $hostkey) {
            $rows = $this->store->hostkey_select_prefixes($hostkey['host_key']);

            $numrows = count($rows);
            if ($numrows == 0) {
                continue;
            } else if ($numrows == 1 &&
                       $rows[0]['prefix'] == $rows[0]['host_key']) {
                $row = $rows[0];
                $row['match'] = $hostkey['host'];
                $row['hash'] = $hostkey['hash'];
                // everything in domain might be blocked as for prefix
                // get full-length
                $candidates[] = $row;
            } else {
                // regular prefixes
                foreach ($rows as $row) {
                    // skip hostkey
                    if ($row['prefix'] == $row['host_key']) {
                        continue;
                    }
                    foreach ($phashes as $phash) {
                        if ($row['prefix'] == $phash['prefix']) {
                            $row['match'] = $phash['original'];
                            $row['hash'] = $phash['hash'];
                            $row['host'] = $hostkey['host'];
                            $candidates[] = $row;
                        }
                    }
                }
            }
        }

        return $this->doFullLookup($candidates);
    }
}
