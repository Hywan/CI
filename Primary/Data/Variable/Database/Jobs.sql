DROP TABLE IF EXISTS jobs;

CREATE TABLE jobs (
    id           VARCHAR(40),
    datetime     INTEGER,
    websocketUri VARCHAR(45),
    status       INTEGER,
    logs         TEXT,

    PRIMARY KEY(id)
);
