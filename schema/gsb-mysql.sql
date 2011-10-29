
DROP TABLE IF EXISTS gsb_add;
CREATE TABLE IF NOT EXISTS gsb_add (
    `list_id`       INT             NOT NULL,
    `add_chunk_num` INT             NOT NULL,
    `host_key`      CHAR(8)         NOT NULL,
    `prefix`        VARCHAR(64)     NOT NULL,
    PRIMARY KEY (list_id, add_chunk_num, host_key, prefix)
)
ENGINE=InnoDB;
CREATE INDEX add_host_key on gsb_add (host_key);


DROP TABLE IF EXISTS gsb_sub;
CREATE TABLE IF NOT EXISTS gsb_sub (
    `list_id`       INT             NOT NULL,
    `add_chunk_num` INT             NOT NULL,
    `sub_chunk_num` INT             NOT NULL,
    `host_key`      CHAR(8)         NOT NULL,
    `prefix`        VARCHAR(64)     NOT NULL,
    PRIMARY KEY (list_id, add_chunk_num, host_key, prefix)
)
ENGINE=InnoDB;
CREATE INDEX sub_chunk_idx ON gsb_sub (list_id, sub_chunk_num);

DROP TABLE IF EXISTS gsb_fullhash;
CREATE TABLE IF NOT EXISTS gsb_fullhash (
    `list_id`       INT             NOT NULL,
    `add_chunk_num` INT             NOT NULL,
    `fullhash`      CHAR(64)        NOT NULL,
    `create_ts`     INT UNSIGNED    NOT NULL,
    PRIMARY KEY (list_id, add_chunk_num, fullhash)
)
ENGINE=InnoDB;

-- request for data state
DROP TABLE IF EXISTS gsb_rfd;
CREATE TABLE IF NOT EXISTS gsb_rfd (
   `id`             INT NOT NULL,
   `next_attempt`   INT UNSIGNED NOT NULL,
   `error_count`    INT UNSIGNED NOT NULL,
   `last_attempt`   INT UNSIGNED NOT NULL,
   `last_success`   INT UNSIGNED NOT NULL,
   PRIMARY KEY(id)
)
ENGINE=InnoDB;
INSERT INTO gsb_rfd VALUES(1,0,0,0,0);
