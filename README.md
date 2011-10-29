
The lib directory contains the pieces you'll need to assemble to match
your enivonrment:

| lib/GSB_Client.php    | The client to test if a URL is blacklisted |
| lib/GSB_Exception.php | An empty class to namespace our exceptions |
| lib/GSB_Logger.php    | A really simple logger.  Ideally you'd subclass to modify for your environment |
| lib/GSB_Request.php   | All network (http) calls are here |
| lib/GSB_Storage.php   | Every database query is here, wrapper up in a function |
| lib/GSB_Updater.php   | Updates the local GSB database |
| lib/GSB_URL.php       | URL cannonicalization |

bin-sample shows how this might be done.  You'll want to rewrite these for match your environment:

| bin-sample/intro.php  | common code for both lookup and updater |
| bin-sample/lookup.php | CLI to test a URL |
| bin-sample/update.php | code to update the local GSB database |
| bin-sample/cron.sh    | shows how update might be run periodicially |

The short story is for a lookup, you'll want


    $api = 'YOUR-API-KEY-HERE';
    $gsblists = array('goog-malware-shavar', 'googpub-phish-shavar');

    // make a PDO object, 2nd line is just good practice
    $dbh = new PDO('mysql:host=127.0.0.1;dbname=gsb', 'root');
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // create the pieces.  Subclass, over-ride for your environment
    $storage = new GSB_StoreDB($dbh);
    $network = new GSB_Request($api);
    $logger  = new GSB_Logger(5);

    // make the client with the pieces, and test
    $client  = new GSB_Client($storage, $network, $logger);
    $client->lookup('A URL');

or for the database updater:

   $x = new GSB_Updater($storage, $network, $logger);
   $x->downloadData($gsblists, FALSE);
   $storage->fullhash_delete_old();
