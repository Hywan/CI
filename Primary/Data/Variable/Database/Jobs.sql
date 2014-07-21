DROP TABLE IF EXISTS jobs;

CREATE TABLE jobs (
    id           VARCHAR(40),
    websocketUri VARCHAR(45),
    status       INTEGER,

    PRIMARY KEY(id)
);
