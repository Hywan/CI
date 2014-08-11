PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS oauth_services;
DROP TABLE IF EXISTS oauth_accesses;
DROP TABLE IF EXISTS oauth_tokens;

CREATE TABLE oauth_services (
    resource_owner VARCHAR(127),
    socket_uri     VARCHAR(255),
    socket_api_uri VARCHAR(255),
    login_uri      VARCHAR(255),
    token_uri      VARCHAR(255),

    PRIMARY KEY(resource_owner)
);

CREATE TABLE oauth_accesses (
    resource_owner VARCHAR(127),
    response_type  INTEGER DEFAULT 0,
    client_id      VARCHAR(255),
    client_secret  VARCHAR(255),
    scope          VARCHAR(1023),
    salt           VARCHAR(40),
    code           VARCHAR(255),

    FOREIGN KEY(resource_owner) REFERENCES oauth_services(resource_owner)
);

CREATE TABLE oauth_tokens (
    resource_owner  VARCHAR(127),
    token           VARCHAR(1023),
    created_at      INTEGER,

    FOREIGN KEY(resource_owner) REFERENCES oauth_services(resource_owner)
);

INSERT INTO oauth_services VALUES (
    'github',
    'tcp://github.com:443',
    'tcp://api.github.com:443',
    'https://github.com/login/oauth/authorize',
    '/login/oauth/access_token'
);
