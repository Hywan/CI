<?php

use Hoa\Router;

$router = new Router\Http();
$router
    // API.
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

    // Front.
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

    // Authorization.
    ->get(
        'auth_oauth_request',
        '/authorization/oauth/request-access/(?<resourceOwner>\w+)',
        'Authorization\Oauth',
        'RequestAccess'
    )
    ->get(
        'auth_oauth_callback',
        '/authorization/oauth/callback/(?<resourceOwner>\w+)',
        'Authorization\Oauth',
        'Callback'
    )

    // Private.
    ->_get(
        '_resource',
        '/(?<resource>)'
    );

return $router;
