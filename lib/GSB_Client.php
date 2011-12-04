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
                $matches[$row['url']] = $row;
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

                    $matches[$c['url']] = $row;
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
     * on GSB.<p>
     *
     * The function returns detailed info if the input is identified as
     * blacklisted.  Here's a sample result:<pre>
     * 	input:
     * 		http://malware.testing.google.test/testing/malware/
     *
     *  output:
     * 		Array
     * 		(
     * 		    [0] => Array
     * 		        (
     * 		            [list_id] => 1
     * 		            [add_chunk_num] => 40130
     * 		            [host_key] => b2ae8c6f
     * 		            [prefix] => 51864045
     * 		            [match] => malware.testing.google.test/testing/malware/
     * 		            [hash] => 518640453f8b2a5f0d43bc225152f49530be2a40bfe2bab60aaaee7a67b10890
     * 		            [host] => testing.google.test/
     * 		            [url] => http://malware.testing.google.test/testing/malware/ <-- this is the original input
     * 		            [listname] => goog-malware-shavar
     * 		        )
     * 		)
     * </pre>
     *
     * @param string|array[int]string $urls - a single URL, or an array of URLs
     * @return array[string]mixed
     */
    function doLookup($urls) {
        // index the host keys by the key value
        $hostkeyMap = $this->hostkeysToHostKeyMap($urls);

        // lookup the host keys from local cache
        $rows = $this->store->hostkey_select_prefixes(array_keys($hostkeyMap));

        // if no matching host keys, skip the lookup
        if (0 == count($rows)) {
            return array();
        }

        // identify the candidates for hash lookup
        $candidates = array();
        foreach ($rows as $row) {
            $hostkey = $hostkeyMap[$row['host_key']];

            if ($row['prefix'] == $row['host_key']) {
                // everything in domain may be blocked as for prefix full-length
                $row['match'] = $hostkey['host'];
                $row['hash']  = $hostkey['hash'];
                $row['url']   = $hostkey['url'];
                $candidates[] = $row;
            } else {
                // regular prefixes
                $phashes = $hostkey['phashes'];

                foreach ($phashes as $phash) {
                    if ($row['prefix'] == $phash['prefix']) {
                        $row['match'] = $phash['original'];
                        $row['hash']  = $phash['hash'];
                        $row['host']  = $hostkey['host'];
                        $row['url']   = $hostkey['url'];
                        $candidates[] = $row;
                    }
                }
            }
        }

        return $this->doFullLookup($candidates);
    }

    //==========================================================================
    // private helpers
    //==========================================================================
    /**
     * Organizes an array of host keys to a map which is indexed by the host_key
     * value.
     *
     * NOTE: this function is made non-private so we can unit test it.
     *
     * @param array[int]array $urls
     * @return array[string]array
     */
    function hostkeysToHostKeyMap($urls) {
        $map = array();

        foreach ((array) $urls as $url) {
            // first canonicalize the URLs
            $canurl = GSB_UrlUtil::canonicalize($url);

            $phashes = GSB_UrlUtil::makePrefixesHashes(
                $canurl['host'], $canurl['path'], $canurl['query'], $canurl['is_ip']);

            // make hostkeys
            $hostkeys = GSB_UrlUtil::makeHostKeyList($canurl['host'], $canurl['is_ip']);

            foreach ((array) $hostkeys as $hostkey) {
                $hostkey['url'] = $url;
                $hostkey['phashes'] = $phashes;
                $map[$hostkey['host_key']] = $hostkey;
            }
        }

        return $map;
    }
}
