<?php

require_once '/usr/local/lib/Hoa/Core/Core.php';

use Hoa\Core;
use Hoa\Dispatcher;
use Hoa\Http;

Core::enableErrorHandler();
Core::enableExceptionHandler();

$router     = require_once 'hoa://Application/Router.php';
$dispatcher = new Dispatcher\Basic();

try {

    $dispatcher->dispatch($router);
}
catch ( Router\Exception\NotFound $e ) {

    echo 'Not found ', $e->getMessage(), "\n";
}
