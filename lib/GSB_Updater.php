<?php

require_once 'GSB_Exception.php';

/**
 * Use the GSB API to pull down black and white listed URL's. Save the lists
 * to a local database. It follows the specification to pull data in trunks,
 * and throttle when server returns errors.
 */
class GSB_Updater {

    var $store;
    var $request;
    var $log;

    /** ctor
     *
     */
    function __construct($storage, $network, $logger) {
        $this->store = $storage;
        $this->request = $network;
        $this->log = $logger;
    }

    /** convert network order bytes to a php int
     *
     * @param $str string  binary string of exactly 4 bytes
     * @return int unsigned integer
     */
    static function network2int($str) {
        if (strlen($str) != 4) {
            throw new GSB_Exception("trying to convert to binary failed");
        }
        $hexparts = unpack("N", $str);
        return $hexparts[1];
    }

    /*
     * converts a GSB range to a list of intervals
     * 1-3,5-6,9,11 -> [[1,3], [5,6], [9,9], [11,11]]
     */
    static function range2list($str) {
        $r = array();
        $parts = explode(',', $str);
        foreach ($parts as $part) {
            $minmax = explode('-', $part, 2);
            if (count($minmax) == 1) {
                $val = (int) $part;
                $r[] = array($val, $val);
            } else {
                $r[] = array((int)$minmax[0], (int)$minmax[1]);
            }
        }
        return $r;
    }

    /**
     * List to range
     *  Takes a *sorted* list of integers and turns them into GSB style ranges
     *  e.g.  array(1,2,3,5,6,9,11) --> "1-3,5-6,9,11"
     */
    static function list2range($values) {
        $ranges = array();
        $i = 0;
        $start = 0;
        $previous = 0;

        foreach ($values as $chunk) {
            if ($i == 0) {
                $start = $chunk;
                $previous = $chunk;
            } else {
                $expected = $previous + 1;

                if ($chunk != $expected) {
                    if ($start == $previous) {
                        $ranges[] = $start;
                    } else {
                        $ranges[] = $start . '-' . $previous;
                    }

                    $start = $chunk;
                }

                $previous = $chunk;
            }

            $i++;
        }

        if ($start > 0 && $previous > 0) {
            if ($start == $previous) {
                $ranges[] = $start;
            } else {
                $ranges[] = $start . '-' . $previous;
            }
        }

        return implode(',', $ranges);
    }

    /**
     * Format a full request body for a desired list including name and full
     * ranges for add and sub
     */
    function formattedRequest($listname, $adds, $subs) {
        $buildpart = '';

        if (count($adds) > 0) {
            $buildpart .= 'a:' . $this->list2range($adds);
        }
        if (count($adds) > 0 && count($subs) > 0) {
            $buildpart .= ':';
        }
        if (count($subs) > 0) {
            $buildpart .= 's:' . $this->list2range($subs);
        }
        return $listname . ';' . $buildpart . "\n";
    }

    /**
     * Reads datastore for current add and sub chunks
     *  and composes the proper 'download' request
     *
     */
    function format_download_request($gsblist) {
        $require = array();
        foreach ($gsblist as $listname) {
            $adds = $this->store->add_chunk_get_nums($listname);
            $subs = $this->store->sub_chunk_get_nums($listname);
            $require[] = $this->formattedRequest($listname, $adds, $subs);
        }
        $request = implode('', $require);
        return $request;
    }

    /**
     * Parses the init download response, parses, convert, fetches
     * redirect data and returns a stateless "list of commands" (could
     * be saved/tested)
     *
     */
    function parseDownloadResponse($raw) {
        $result = array();

        $lines = explode("\n", trim($raw));
        $currentlist = null;

        foreach ($lines as $line) {
            $parts = explode(':', $line, 2);
            switch ($parts[0]) {
            case 'n':
                $result[] = array(
                    'action' => 'set_timeout',
                    'next' => (int)(trim($parts[1]))
                );
                break;
            case 'e':
                if ($parts[1] == 'pleaserekey') {
                    $result[] = array(
                        'action' => 'rekey'
                    );
                }
                break;
            case 'r':
                if ($parts[1] == 'pleasereset') {
                    $result[] = array(
                        'action' => 'delete_all_data'
                    );
                }
                break;
            case 'i':
                $currentlist = $parts[1];
                break;
            case 'u':
                if (is_null($currentlist)) {
                    throw new GSB_Exception("Got URL request before a list was set");
                }
                $rr = $this->request->downloadChunks('http://' . $parts[1]);
                $this->parseRedirectResponse($result, $currentlist, $rr);
                break;
            case 'ad':
                if (is_null($currentlist)) {
                    throw new GSB_Exception("Got URL request before a list was set");
                }
                $ranges = self::range2list($parts[1]);
                foreach ($ranges as $interval) {
                    $result[] = array(
                        'action' => 'add_delete',
                        'listname' => $currentlist,
                        'min' => $interval[0],
                        'max' => $interval[1]
                    );
                }
                break;
            case 'sd':
                if (is_null($currentlist)) {
                    throw new GSB_Exception("Got URL request before a list was set");
                }
                foreach ( $this->range2list($parts[1]) as $interval) {
                    $result[] = array(
                        'action' => 'sub_delete',
                        'listname' => $currentlist,
                        'min' => $interval[0],
                        'max' => $interval[1]
                    );
                }
                break;
            default:
                // "The client MUST ignore a line starting with a
                //  keyword that it doesn't understand."
                $this->log->warn("Unknown line in response: $line");
            }

        }
        return $result;
    }

    function parseAddShavarChunk(&$result, $listname, $add_chunk_num,
                                 $hashlen, $raw) {
        $sz = strlen($raw);
        $offset = 0;
        while ($offset < $sz) {
            $hostkey = bin2hex(substr($raw, $offset, 4)); $offset += 4;
            $count = ord(substr($raw, $offset, 1)); $offset += 1;
            $prefixes = array();
            if ($count == 0) {
                // special case, really 'hostkey, prefix'
                $result[] = array(
                    'action' => 'add_insert',
                    'listname' => $listname,
                    'add_chunk_num' => $add_chunk_num,
                    'host_key' => $hostkey,
                    'prefix' => $hostkey
                );
            } else {
                for ($i = 0; $i < $count; $i += 1) {
                    $result[] = array(
                        'action' => 'add_insert',
                        'listname' => $listname,
                        'add_chunk_num' => $add_chunk_num,
                        'host_key' => $hostkey,
                        'prefix' => bin2hex(substr($raw, $offset, $hashlen))
                    );
                    $offset += $hashlen;
                }
            }
        }

        if ($offset != $sz) {
            throw new GSB_Exception("Mismatch in AddShavar Chunk $offset $sz");
        }
    }

    /**
     *
     *
     */
    function parseSubShavarChunk(&$result, $listname, $sub_chunk_num,
                                 $hashlen, $raw) {
        $sz = strlen($raw);
        $offset = 0;
        while ($offset < $sz) {
            $hostkey = bin2hex(substr($raw, $offset, 4)); $offset += 4;
            $count = ord(substr($raw, $offset, 1)); $offset += 1;
            if ($count == 0) {
                // special case where hostkey is prefix
                $result[] = array(
                    'action' => 'sub_insert',
                    'listname' => $listname,
                    'sub_chunk_num' => $sub_chunk_num,
                    'add_chunk_num' => self::network2int(substr($raw,
                                                                $offset, 4)),
                    'host_key' => $hostkey,
                    'prefix' => $hostkey
                );
                $offset += 4;
            } else {
                for ($i = 0; $i < $count; $i++) {
                    $result[] = array(
                        'action' => 'sub_insert',
                        'listname' => $listname,
                        'sub_chunk_num' => $sub_chunk_num,
                        'add_chunk_num' => self::network2int(substr($raw,
                                                                    $offset,
                                                                    4)),
                        'host_key' => $hostkey,
                        'prefix' => bin2hex(substr($raw, $offset+4, $hashlen))
                    );
                    $offset += 4 + $hashlen;
                }
            }
        }
        if ($offset != $sz) {
            throw new GSB_Exception("Mismatch in SubShavar Chunk $offset != $sz");
        }
    }

    function parseRedirectResponse(&$result, $list_name, $raw) {
        $offset = 0;
        $sz = strlen($raw);
        while ($offset < $sz) {
            $newline = strpos($raw, "\n", $offset);
            if ($newline === FALSE) {
                throw new GSB_Exception("Counldn't find newline with $offset $sz");
            } else {
                $header = substr($raw, $offset, $newline - $offset);
                $parts = explode(':', $header, 4);
                $cmd = $parts[0];
                $chunk_num = (int) $parts[1];
                $hashlen = (int) $parts[2];
                $chunklen = (int) $parts[3];
                $msg = substr($raw, $newline+1, $chunklen);

                switch ($cmd) {
                case 'a':
                    if (empty($msg)) {
                        $result[] = array(
                            'action' => 'add_empty',
                            'listname' => $list_name,
                            'add_chunk_num' => $chunk_num
                        );
                    }  else {
                        $this->parseAddShavarChunk($result, $list_name,
                                                   $chunk_num, $hashlen, $msg);
                    }
                    break;
                case 's':
                    if (empty($msg)) {
                        $result[] = array(
                            'action' => 'sub_empty',
                            'listname' => $list_name,
                            'sub_chunk_num' => $chunk_num
                        );
                    } else {
                        $this->parseSubShavarChunk($result, $list_name,
                                                   $chunk_num, $hashlen, $msg);
                    }
                    break;
                default:
                    throw new GSB_Exception("Got bogus command in line $header");

                }
                $offset = $newline + 1 + $chunklen;
            }
        }
    }

    /**
     * Main part of updater function, will call all other functions, merely
     * requires the request body, it will then process and save all data as well
     * as checking for ADD-DEL and SUB-DEL, runs silently so won't return
     * anything on success.
     */
    function downloadData($gsblists, $force) {
        $start = time();
        $this->log->info("Updater woke up");

        $state = $this->store->rfd_get();
        $diff = (int)$state['next_attempt'] - $start;
        if ($diff > 0) {
            if ($force) {
                $this->log->info("Ignoring timeout guidance. (should wait for $diff seconds");
            } else {
                $this->log->info("Too soon.  Need to wait for $diff seconds");
                return;
            }
        }

        $now = time();
        $body = $this->format_download_request($gsblists);
        $this->log->debug(
            sprintf(
                "Computing existing chunks took %d seconds",
                time() - $now
            ));
        if (empty($body)) {
            throw new GSB_Exception("Missing a body for data request");
        }

        $this->log->debug("Request = $body");
        $now = time();
        $raw = $this->request->download($body);

        // processes and saves all data
        $commands = $this->parseDownloadResponse($raw);
        $this->log->info(sprintf("Got %d commands in %d seconds",
                                 count($commands), time() - $now));
        $now = time();
        $this->store->transaction_begin();
        try {
            foreach ($commands as $cmd) {
                $this->log->debug("Command " . http_build_query($cmd));
                $action = $cmd['action'];
                $this->store->$action($cmd);
            }

            // Ok we got this far, we need to update state.
            $state = $this->store->rfd_get();
            $state['error_count'] = 0;
            $state['last_attempt'] = $state['last_success'] = time();
            $timeout = $this->store->get_timeout();
            if ($timeout == 0) {
                $timeout = 60*15;
            }
            $state['next_attempt'] = time() + $timeout;
            $this->store->rfd_set($state);

            $this->store->transaction_commit();
            $this->log->info(
                sprintf("Processed %d entries in %d seconds.  Next update in %d seconds",
                        count($commands), time() - $now, $timeout)
            );

        } catch (Exception $e) {
            $this->log->error("Chunk update failed: $e");
            $this->store->transaction_rollback();

            $this->store->transaction_commit();
            $state = $this->store->get_rfd();
            $state['last_attempt'] = time();
            $state['error_count'] += 1;
            switch ($state['error_count']) {
            case 1:
                $next = 1;
                break;
            case 2:
                $next = 30;
                break;
            case 3:
                $next = 60;
                break;
            case 4:
                $next = 120;
                break;
            case 5:
                $next = 240;
                break;
            default:
                $next = 480;
            }

            $state['next_attempt'] = time() + $next*60;
            $this->store->rfd_set($state);
            $this->store->transaction_commit();
            $this->log->info(
                sprintf("Got %d errors, next Attempt in %d minutes",
                        $state['error_count'], $next
                )
            );


        }

        $this->log->info(
            sprintf(
                "Update complete in %d seconds",
                time() - $start
            )
        );
    }
}
