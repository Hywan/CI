<?php

use Hoa\Router;

$router = new Router\Http();
$router
    ->post(
        'hook',
        '/api/hook',
        'Api',
        'Hook'
    )
    ->get(
        'home',
        '/',
        'Front',
        'Home'
    )
    ->get(
        'job',
        '/job/(?<id>[a-z0-9]{40})',
        'Front',
        'Job'
    );

return $router;
