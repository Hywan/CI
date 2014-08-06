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
        'event_last_jobs',
        '/api/event/last_jobs',
        'Api',
        'LastJobs'
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
    )

    ->_get(
        '_resource',
        '/(?<resource>)'
    );

return $router;
