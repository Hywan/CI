<?php

return [
    // Address of the primary server.
    'primary.address'     => '192.168.0.101',

    // Address of the FPM server for the primary.
    'primary.fpm.address' => '127.0.0.1',

    // Port of the FPM server for the primary.
    'primary.fpm.port'    => '9001',


    // Address of the FPM server for the standby.
    'standby.fpm.address' => '192.168.0.108',

    // Port of the FPM server for the standby.
    'standby.fpm.port'    => '9001',

    // Root of the standby application.
    'standby.root'        => '/Ci/'
];
