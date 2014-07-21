<?php

use Hoa\Database;

Database\Dal::initializeParameters([
    'connection.list.jobs.dal' => Database\Dal::PDO,
    'connection.list.jobs.dsn' => 'sqlite:hoa://Data/Variable/Database/Jobs.sqlite'
]);
