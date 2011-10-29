<?php

require_once 'GSB_URL.php';

/**
 * Unit test cases for the utilities in GSB_UrlUtil
 */
class GSB_UrlUtilTests extends PHPUnit_Framework_TestCase {

    /**
     * 
     */
    public function testmakeHostList() {
        $ary = GSB_UrlUtil::makeHostList('foo', false);
        $expected = array('foo');
        $this->assertEquals($expected, $ary);

        $ary = GSB_UrlUtil::makeHostList('a.b', false);
        $this->assertEquals($ary, array('a.b'));
        $ary = GSB_UrlUtil::makeHostList('a.b.c', false);
        $this->assertEquals($ary, array('a.b.c', 'b.c'));
        $ary = GSB_UrlUtil::makeHostList('a.b.c.d.e.f.g', false);
        $this->assertEquals($ary, array('e.f.g', 'f.g'));
        $ary = GSB_UrlUtil::makeHostList('1.2.3.4', true);
        $this->assertEquals($ary, array('1.2.3.4'));

    }

    /**
     * Tests decomposition of a hostname in various subdomains
     *
     */
    public function testHosts() {
        $ary = GSB_UrlUtil::makeHosts('foo', false);
        $expected = array('foo');
        $this->assertEquals($expected, $ary);

        $ary = GSB_UrlUtil::makeHosts('a.b.c', false);
        $expected = array('a.b.c', 'b.c');
        $this->assertEquals($expected, $ary);

        $ary = GSB_UrlUtil::makeHosts('a.b.c.d.e.f.g', false);
        $expected =  array('a.b.c.d.e.f.g', 'c.d.e.f.g', 'd.e.f.g', 'e.f.g', 'f.g');
        $this->assertEquals($expected, $ary);

        $ary = GSB_UrlUtil::makeHosts('1.2.3.4', true);
        $expected =  array('1.2.3.4');
        $this->assertEquals($expected, $ary);
    }

    /**
     * Tests the function to explode a given URL to different forms
     */
    function testMakePaths() {
        $a = GSB_UrlUtil::makePaths('/', '');
        $this->assertEquals(array('/'), $a);

        $a = GSB_UrlUtil::makePaths('/1', '');
        $this->assertEquals(array('/1', '/'), $a);

        $a = GSB_UrlUtil::makePaths('/1/2.html', 'param=1');
        $this->assertEquals(array('/1/2.html?param=1', '/1/2.html', '/', '/1/'), $a);
    }

    /**
     * Tests the function to explode a given URL to different forms
     */
    function testPrefixes() {
        $a = GSB_UrlUtil::makePrefixes('www.google.com', '/foo/bar', 'a=b&c=d', false);
        $expected = array(
            'www.google.com/foo/bar?a=b&c=d',
            'www.google.com/foo/bar',
            'www.google.com/',
            'www.google.com/foo/',
            'google.com/foo/bar?a=b&c=d',
            'google.com/foo/bar',
            'google.com/',
            'google.com/foo/');

        $this->assertEquals($expected, $a);


        $expected = array(
            'a.b.c/1/2.html?param=1',
            'a.b.c/1/2.html',
            'a.b.c/',
            'a.b.c/1/',
            'b.c/1/2.html?param=1',
            'b.c/1/2.html',
            'b.c/',
            'b.c/1/'
        );
        $a = GSB_UrlUtil::makePrefixes('a.b.c', '/1/2.html', 'param=1', false);
        $this->assertEquals($expected, $a);

        $expected = array(
            'a.b.c.d.e.f.g/1.html',
            'a.b.c.d.e.f.g/',
            'c.d.e.f.g/1.html',
            'c.d.e.f.g/',
            'd.e.f.g/1.html',
            'd.e.f.g/',
            'e.f.g/1.html',
            'e.f.g/',
            'f.g/1.html',
            'f.g/'
        );
        $a = GSB_UrlUtil::makePrefixes('a.b.c.d.e.f.g', '/1.html', '', false);
        $this->assertEquals($expected, $a);

        $expected = array(
            '1.2.3.4/1/',
            '1.2.3.4/'
        );
        $a = GSB_UrlUtil::makePrefixes('1.2.3.4', '/1/', '', true);
        $this->assertEquals($expected, $a);

    }

    /**
     * Tests the canonicalizing URL's
     */
    public function testCanonicalize() {
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host/%25%32%35"),
                            "http://host/%25");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host/%25%32%35%25%32%35"),
                            "http://host/%25%25");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host/%2525252525252525"),
                            "http://host/%25");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host/asdf%25%32%35asd"),
                            "http://host/asdf%25asd");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host/%%%25%32%35asd%%"),
                            "http://host/%25%25%25asd%25%25");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.google.com/"),
                            "http://www.google.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://%31%36%38%2e%31%38%38%2e%39%39%2e%32%36/%2E%73%65%63%75%72%65/%77%77%77%2E%65%62%61%79%2E%63%6F%6D/"),
                            "http://168.188.99.26/.secure/www.ebay.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://195.127.0.11/uploads/%20%20%20%20/.verify/.eBaysecure=updateuserdataxplimnbqmn-xplmvalidateinfoswqpcmlx=hgplmcx/"),
                            "http://195.127.0.11/uploads/%20%20%20%20/.verify/.eBaysecure=updateuserdataxplimnbqmn-xplmvalidateinfoswqpcmlx=hgplmcx/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host%23.com/%257Ea%2521b%2540c%2523d%2524e%25f%255E00%252611%252A22%252833%252944_55%252B"),
                            'http://host%23.com/~a!b@c%23d$e%25f^00&11*22(33)44_55+');
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://3279880203/blah"),
                            "http://195.127.0.11/blah");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.google.com/blah/.."),
                            "http://www.google.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("www.google.com/"),
                            "http://www.google.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("www.google.com"),
                            "http://www.google.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.evil.com/blah#frag"),
                            "http://www.evil.com/blah");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.GOOgle.com/"),
                            "http://www.google.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.google.com.../"),
                            "http://www.google.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.google.com/foo\tbar\rbaz\n2"),
                            "http://www.google.com/foobarbaz2");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.google.com/q?"),
                            "http://www.google.com/q?");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.google.com/q?r?"),
                            "http://www.google.com/q?r?");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.google.com/q?r?s"),
                            "http://www.google.com/q?r?s");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://evil.com/foo#bar#baz"),
                            "http://evil.com/foo");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://evil.com/foo;"),
                            "http://evil.com/foo;");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://evil.com/foo?bar;"),
                            "http://evil.com/foo?bar;");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://\x01\x80.com/"),
                            "http://%01%80.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://notrailingslash.com"),
                            "http://notrailingslash.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://www.gotaport.com:1234/"),
                            "http://www.gotaport.com:1234/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("  http://www.google.com/  "),
                            "http://www.google.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http:// leadingspace.com/"),
                            "http://%20leadingspace.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://%20leadingspace.com/"),
                            "http://%20leadingspace.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("%20leadingspace.com/"),
                            "http://%20leadingspace.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("https://www.securesite.com/"),
                            "https://www.securesite.com/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host.com/ab%23cd"),
                            "http://host.com/ab%23cd");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host.com//twoslashes?more//slashes"),
                            "http://host.com/twoslashes?more//slashes");

        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host.com/foo/"),
                            "http://host.com/foo/");
        $this->assertEquals(GSB_UrlUtil::getCanonicalizedUrl("http://host.com/foo?"), "http://host.com/foo?");


        $this->assertEquals("http://host.com/",
                            GSB_UrlUtil::getCanonicalizedUrl("http://host.com:"));
        $this->assertEquals("http://host.com/",
                            GSB_UrlUtil::getCanonicalizedUrl("http://host.com:80"));
        $this->assertEquals("http://host.com/",
                            GSB_UrlUtil::getCanonicalizedUrl("http://host.com:80/"));
        $this->assertEquals("https://host.com/",
                            GSB_UrlUtil::getCanonicalizedUrl("https://host.com:443/"));

        /*
        $url = 'http://host.com/foo%3Fbar';
        $expected = array(
            'canonical' => $url,
            'original'=> $url,
            'host' => 'host.com',
            'path' => '/foo',
            'query' => 'bar',
            'is_ip' => FALSE
        );
        $this->assertEquals($expected, GSB_UrlUtil::canonicalize($url));
        */
    }
}
