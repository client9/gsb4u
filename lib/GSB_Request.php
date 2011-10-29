<?php

require_once 'GSB_Exception.php';

/**
 * Provides functions to interact with the Google Safe Browsing API by sending
 * HTTP messages to download exists or perform lookups.
 *
 * It does NOT do any parsing or constructing of requests other than basic
 * URL stuff and processing of HTTP status codes.
 *
 */
class GSB_Request {
    const API_APPVER  = '1.5.2';
    const API_URL     = 'http://safebrowsing.clients.google.com/safebrowsing/';
    const API_CLIENT  = 'api';
    const API_PVER    = '2.2';

    function __construct($apikey, $mode=null, $path=null) {
        $this->apikey = $apikey;
        $this->counter = 0;

        $this->mode = $mode;
        $this->path = $path;
    }

    /**
     * Retrieves the types of the GSB lists.
     */
    function getListTypes() {
        $req = $this->makeRequest('list');
        $result = $this->request($req, null);
        return $result['response'];
    }

    /**
     * Downloads chunks of the GSB lists for the given list type.
     *
     * @param unknown_type $body
     * @param unknown_type $followBackoff
     */
    function download($body, $followBackoff = false) {
        $req = $this->makeRequest('downloads');
        $result = $this->post_request($req, $body . "\n", $followBackoff);
        return $result['response'];
    }

    /**
     * Retrieves the full hash from the GSB API.
     *
     * @param unknown_type $body
     */
    function getFullHash($body) {
        $req = $this->makeRequest('gethash');
        $result = $this->post_request($req, $body);
        $data = $result['response'];
        $httpcode = $result['info']['http_code'];
        if ($httpcode == 200 && !empty($data)) {
            return $data;
        } elseif ($httpcode == 204 && empty($data)) {
            // 204 Means no match
            return '';
        } else {
            throw new GSB_Exception("ERROR: Invalid response returned from GSB ($httpcode)");
        }
    }

    /**
     * Follows the redirect URL and downloads the real chunk data.
     *
     * @param unknown_type $redirectUrl
     * @param unknown_type $followBackoff
     * @return Ambigous <multitype:mixed, multitype:mixed >
     */
    function downloadChunks($redirectUrl) {
        $result = $this->request($redirectUrl);
        return $result['response'];
    }

    /**
     * Make a request to the GSB API from the given URL's, POST data can be
     * passed via $options. $followBackoff indicates whether to follow backoff
     * procedures or not
     *
     * @param unknown_type $url
     * @param unknown_type $options
     * @param unknown_type $followBackoff
     * @return multitype:mixed
     */
    function request($url, $followBackoff = false) {
        if ($this->mode == 'replay') {
            $fname = sprintf("%s-%03d.php-serialized", $this->path, $this->counter + 1, '.php-serialized');
            if (file_exists($fname)) {
                print "Replay with $fname\n";
                $result = unserialize(file_get_contents($fname));
                $this->counter++;

                return $result;
            }
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        if ($info['http_code'] == 400) {
            throw new GSB_Exception("Invalid request for $url");
        }

        if ($followBackoff && $info['http_code'] > 299) {
            //$this->backoff($info, $followBackoff);
        }

        $result = array('url'      => $url,
                        'postdata' => '',
                        'info'     => $info,
                        'response' => $data);

        if ($this->mode == 'replay') {
            $this->counter++;
            $fname = sprintf("%s-%03d.php-serialized", $this->path, $this->counter, '.php-serialized');
            printf("Writing $fname\n");
            file_put_contents($fname, serialize($result));
        }
        return $result;
    }

    /**
     * Make a request to the GSB API from the given URL's, POST data can be
     * passed via $options. $followBackoff indicates whether to follow backoff
     * procedures or not
     *
     * @param unknown_type $url
     * @param unknown_type $options
     * @param unknown_type $followBackoff
     * @return multitype:mixed
     */
    function post_request($url, $postdata, $followBackoff = false) {
        if ($this->mode == 'replay') {
            $fname = sprintf("%s-%03d.php-serialized", $this->path, $this->counter + 1, '.php-serialized');
            if (file_exists($fname)) {
                print "Replay with $fname\n";
                $result = unserialize(file_get_contents($fname));
                $this->counter++;
                return $result;
            }
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        $data = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        $httpcode = (int)$info['http_code'];
        switch ($httpcode) {
        case 204:
        case 200:
            // these are ok
            break;

        case 400:
            throw new GSB_Exception("400: Invalid request for $url");
        case 403:
            throw new GSB_Exception("403: Forbidden.  Client id is invalid");
        case 503:
            throw new GSB_Exception("503: Backoff son.");
        case 505:
            throw new GSB_Exception("505: Bad Protocol.");
        default:
            throw new GSB_Exception("Unknown http code " . $httpcode);
        }

        $result = array('url' => $url,
                        'postdata' => $postdata,
                        'info' => $info,
                        'response' => $data);

        if ($this->mode == 'replay') {
            $this->counter++;
            $fname = sprintf("%s-%03d.php-serialized", $this->path, $this->counter, '.php-serialized');
            printf("Writing $fname\n");
            file_put_contents($fname, serialize($result));
        }
        return $result;
    }

    /**
     * Constructs a URL to the GSB API
     */
    function makeRequest($cmd) {
        $str = sprintf("%s%s?client=%s&apikey=%s&appver=%s&pver=%s",
                       self::API_URL,
                       $cmd,
                       self::API_CLIENT,
                       $this->apikey,
                       self::API_APPVER,
                       self::API_PVER);
        return $str;
    }
};
