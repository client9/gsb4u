
* Need a lot more in-code comments
* How to create the database (see the schema dir)
* Notes on MySQL vs. Sqlite3, and other database assumptions
  * This implementation does not use autoincrementing IDs for maximum poratability
* Notes running unit tests
* test code-coverage
* Notes / commentary on the reference client
  * This version does use the hostkey
  * This version checks the 'add' list, and then 'sub' list.
    Ref client, when it gets a sub entry, it delete the add entry.
* Other architecture notes
