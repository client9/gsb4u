
This implements a toolkit for implementing the
[Google Safe Browsing API v2](http://code.google.com/apis/safebrowsing/)

The lib directory contains the pieces you'll need to assemble to match
your enivroment:

<table>
<tr><td> lib/GSB_Client.php </td><td> The client to test if a URL is blacklisted </td></tr>
<tr><td> lib/GSB_Exception.php </td><td> An empty class to namespace our exceptions </td></tr>
<tr><td> lib/GSB_Logger.php    </td><td> A really simple logger.  Ideally you'd subclass to modify for your environment  </td></tr>
<tr><td> lib/GSB_Request.php   </td><td> All network (http) calls are here  </td></tr>
<tr><td> lib/GSB_Storage.php   </td><td> Every database query is here, wrapper up in a function  </td></tr>
<tr><td> lib/GSB_Updater.php   </td><td> Updates the local GSB database  </td></tr>
<tr><td> lib/GSB_URL.php       </td><td> URL cannonicalization  </td></tr>
</table>

bin-sample shows how this might be done.  You'll want to rewrite these for match your environment:

<table>
<tr><td> bin-sample/intro.php  </td><td> common code for both lookup and updater </td></tr>
<tr><td> bin-sample/lookup.php </td><td> CLI to test a URL </td></tr>
<tr><td> bin-sample/update.php </td><td> code to update the local GSB database </td></tr>
<tr><td> bin-sample/cron.sh    </td><td> shows how update might be run periodicially </td></tr>
</table>

The URL lookup and the local database update shares common code:

    $api = 'YOUR-API-KEY-HERE';
    $gsblists = array('goog-malware-shavar', 'googpub-phish-shavar');

    // make a PDO object, 2nd line is just good practice
    $dbh = new PDO('mysql:host=127.0.0.1;dbname=gsb', 'root');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // create the pieces.  Subclass, over-ride for your environment
    $storage = new GSB_StoreDB($dbh);
    $network = new GSB_Request($api);
    $logger  = new GSB_Logger(5);


To make URL checker:

    // make the client with the pieces, and test
    $client  = new GSB_Client($storage, $network, $logger);
    $client->lookup('A URL');

To make a database updater:

    $x = new GSB_Updater($storage, $network, $logger);
    $x->downloadData($gsblists, FALSE);
    $storage->fullhash_delete_old();
