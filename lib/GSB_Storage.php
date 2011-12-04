<?php

/**
 * Storage abstraction.  Should be easy to remap this to any
 *  sql-based database in any language.  This uses PHP's PDO, so with
 *  luck (and the right schema) you should be able to reuse in other
 *  databases under PHP.
 */
class GSB_Storage {

    /**
     * initialize the database connection
     */
    function __construct($pdoconnection) {
        $this->dbh = $pdoconnection;
        $this->timeout = 0;

        $driver = $this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver == 'mysql') {
            $this->ignore = 'IGNORE';
        } else {
            // Sqlite3 uses a slightly different syntax
            $this->ignore = 'OR IGNORE';
        }

        $this->statements = array();

        /* maps GSB name to Int */
        $this->LIST_ENUM = array (
            'goog-malware-shavar' => 1,
            'goog-regtest-shavar' => 2,
            'goog-whitedomain-shavar' => 3,
            'googpub-phish-shavar' => 4
        );
    }

    /**
     * Datalist mapping
     */
    function list2id($name) {
        return $this->LIST_ENUM[$name];
    }
    /**
     * Reverse mapping of list id to list name
     * I'm sure PHP has a snazzy way of doing this
     */
    function id2list($id) {
        foreach ($this->LIST_ENUM as $k => $v) {
            if ($id == $v) {
                return $k;
            }
        }
        return '???';
    }

    /**
     * Transaction Abstraction
     *
     */
    function transaction_begin() {
        // on a transactionless table such as MyISAM,
        $this->dbh->beginTransaction();
    }
    function transaction_commit() {
        $this->dbh->commit();
    }
    function transaction_rollback() {
        $this->dbh->rollback();
    }

    /**
     * private internal function
     */
    function prepare($sql) {
        if (isset($this->statements[$sql])) {
            $stmt = $this->statements[$sql];
        } else {
            $stmt = $this->dbh->prepare($sql);
            $this->statements[$sql] = $stmt;
        }
        return $stmt;
    }

    /**
     * Resets the tables in the GSB schema
     */
    function delete_all_data(&$data) {
        $driver = $this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver == 'mysql') {
            $this->dbh->query('TRUNCATE TABLE gsb_add');
            $this->dbh->query('TRUNCATE TABLE gsb_sub');
            $this->dbh->query('TRUNCATE TABLE gsb_fullhash');
        } else {
            // standard row-by-row deletion
            $this->dbh->query('DELETE FROM gsb_add');
            $this->dbh->query('DELETE FROM gsb_sub');
            $this->dbh->query('DELETE FROM gsb_fullhash');
        }

    }

    function rekey(&$data) {
        // TBD
    }

    /**
     * Fetch the chunk numbers from the database for the given list and mode.
     *
     * @param string $listname
     * @param string $mode
     */
    function add_chunk_get_nums($listname) {
        $listId = $this->LIST_ENUM[$listname];
        $stmt = $this->prepare('SELECT DISTINCT(add_chunk_num) FROM gsb_add WHERE list_id = ?');
        $stmt->execute(array($this->LIST_ENUM[$listname]));
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        $chunks = array();
        foreach ($rows as $row) {
            array_push($chunks, (int)$row[0]);
        }
        asort($chunks);
        return $chunks;
    }

    function sub_chunk_get_nums($listname) {
        $listId = $this->LIST_ENUM[$listname];
        $stmt = $this->prepare('SELECT DISTINCT(sub_chunk_num) FROM gsb_sub WHERE list_id = ?');
        $stmt->execute(array($this->LIST_ENUM[$listname]));
        $rows = $stmt->fetchAll(PDO::FETCH_NUM);
        $chunks = array();
        foreach ($rows as $row) {
            array_push($chunks, (int)$row[0]);
        }
        asort($chunks);
        return $chunks;
    }

    /**
     * Finds all prefixes the matches the host key.
     *
     * @param array[int]string|string $hostkeys
     * @return multitype:|array
     */
    function hostkey_select_prefixes($hostkeys) {
        // build the where clause
        if (empty($hostkeys)) {
            return array();
        } else if (is_array($hostkeys)) {
            $where = "WHERE a.host_key IN ('".implode("','", $hostkeys)."') ";
        } else {
            $where = "WHERE a.host_key = '$hostkeys' ";
        }

        // build the query, filter out lists that were "subtracted"
        $stmt = $this->prepare(
            'SELECT a.* FROM gsb_add a '.
            'LEFT OUTER JOIN gsb_sub s '.
            '    ON s.list_id        = a.list_id '.
            '    AND s.host_key      = a.host_key '.
            '    AND s.add_chunk_num = a.add_chunk_num '.
            '    AND s.prefix        = a.prefix '.
            $where.
            'AND s.sub_chunk_num IS NULL');

        $stmt->execute();

        return (array) $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    function add_insert(&$data) {
        $IGNORE = $this->ignore;
        $q = "INSERT $IGNORE INTO gsb_add (list_id, add_chunk_num, host_key, prefix) VALUES (?, ?, ?, ?)";
        $stmt = $this->prepare($q);
        $stmt->bindParam(1, $this->LIST_ENUM[$data['listname']]);
        $stmt->bindParam(2, $data['add_chunk_num']);
        $stmt->bindParam(3, $data['host_key']);
        $stmt->bindParam(4, $data['prefix']);
        $stmt->execute();
    }

    function add_delete(&$data) {
        $stmt = $this->prepare('DELETE FROM gsb_add where list_id=? AND add_chunk_num >= ? and add_chunk_num <= ?');
        $stmt->bindParam(1, $this->LIST_ENUM[$data['listname']]);
        $stmt->bindParam(2, $data['min']);
        $stmt->bindParam(3, $data['max']);
        $stmt->execute();
        $stmt = $this->prepare('DELETE FROM gsb_sub where list_id=? AND add_chunk_num >= ? and add_chunk_num <= ?');
        $stmt->bindParam(1, $this->LIST_ENUM[$data['listname']]);
        $stmt->bindParam(2, $data['min']);
        $stmt->bindParam(3, $data['max']);
        $stmt->execute();
        $stmt = $this->prepare('DELETE FROM gsb_fullhash where list_id=? AND add_chunk_num >= ? and add_chunk_num <= ?');
        $stmt->bindParam(1, $this->LIST_ENUM[$data['listname']]);
        $stmt->bindParam(2, $data['min']);
        $stmt->bindParam(3, $data['max']);
        $stmt->execute();
    }

    function sub_delete(&$data) {
        $stmt = $this->prepare('DELETE FROM gsb_sub where list_id=? AND sub_chunk_num >= ? and sub_chunk_num <= ?');
        $stmt->bindParam(1, $this->LIST_ENUM[$data['listname']]);
        $stmt->bindParam(2, $data['min']);
        $stmt->bindParam(3, $data['max']);
        $stmt->execute();
    }

    function add_empty(&$data) {
        $data['host_key'] = '';
        $data['prefix'] = '';
        $this->add_insert($data);
    }

    function sub_insert(&$data) {
        $IGNORE = $this->ignore;
        $q = "INSERT $IGNORE INTO gsb_sub (list_id, add_chunk_num, sub_chunk_num, host_key, prefix) VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->prepare($q);
        $stmt->bindParam(1, $this->LIST_ENUM[$data['listname']]);
        $stmt->bindParam(2, $data['add_chunk_num']);
        $stmt->bindParam(3, $data['sub_chunk_num']);
        $stmt->bindParam(4, $data['host_key']);
        $stmt->bindParam(5, $data['prefix']);
        $stmt->execute();
    }

    function sub_empty(&$data) {
        $data['host_key'] = '';
        $data['prefix'] = '';

        // ??
        $data['add_chunk_num']  = 0;
        $this->sub_insert($data);
    }

    /**
     * Delete all obsolete fullhashs
     */
    function fullhash_delete_old($now = null) {
        if (is_null($now)) {
            $now = time();
        }
        $stmt = $this->prepare('DELETE FROM gsb_fullhash WHERE create_ts < ?');
        $exp = $now - (60*45);
        $stmt->bindParam(1, $exp);
        $stmt->execute();
    }

    /** INSERT or Replace full fash
     *
     *
     */
    function fullhash_insert(&$data, $now = null) {
        if (is_null($now)) {
            $now = time();
        }
        $stmt = $this->prepare('REPLACE INTO gsb_fullhash VALUES(?,?,?,?)');
        $stmt->bindParam(1, $this->LIST_ENUM[$data['listname']]);
        $stmt->bindParam(2, $data['add_chunk_num']);
        $stmt->bindParam(3, $data['hash']);
        $stmt->bindParam(4, $now);
        $stmt->execute();
    }

    /**
     *
     *
     */
    function fullhash_exists(&$data, $now = null) {
        if (is_null($now)) {
            $now = time();
        }
        $stmt = $this->prepare('SELECT COUNT(*) FROM gsb_fullhash WHERE list_id = ? AND fullhash = ? AND create_ts > ?');

        // HACK -- need to pick
        if (isset($data['list_id'])) {
            $stmt->bindParam(1, $data['list_id']);
        } else {
            $stmt->bindParam(1, $this->LIST_ENUM[$data['listname']]);
        }

        $stmt->bindParam(2, $data['hash']);
        $exp = $now - 60*45;
        $stmt->bindParam(3, $exp);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();
        return ($count > 0);
    }

    function rfd_get() {
        $stmt = $this->prepare('SELECT * FROM gsb_rfd WHERE id = 1');
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    function rfd_set(&$data) {
        $stmt = $this->prepare('REPLACE INTO gsb_rfd VALUES(1,?,?,?,?)');
        $stmt->bindParam(1, $data['next_attempt']);
        $stmt->bindParam(2, $data['error_count']);
        $stmt->bindParam(3, $data['last_attempt']);
        $stmt->bindParam(4, $data['last_success']);
        $stmt->execute();

        // see below.
        $this->timeout = 0;
    }

    /**
     * This is a bit tricky.  Here instead of saving data, we just
     *  store it locally.  We'll update the gsb_rfd state table
     *  all at once later
     *
     */
    function set_timeout(&$data) {
        $this->timeout = $data['next'];
    }

    function get_timeout() {
        return $this->timeout;
    }


}
