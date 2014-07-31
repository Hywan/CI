<?php

namespace Application\Worker {

require dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR .
        'Data' . DIRECTORY_SEPARATOR .
        'Core.link.php';

use Hoa\Core;
use Hoa\Http;
use Hoa\Zombie;
use Hoa\Socket;
use Hoa\Websocket;
use Hoa\File;
use Hoa\Console;
use Hoa\Fastcgi;

$response = new Http\Response(false);

if(Http\Request::METHOD_POST !== Http\Runtime::getMethod()) {

    $response->sendStatus($response::STATUS_METHOD_NOT_ALLOWED);
    $response->sendHeader('Allow', Http\Request::METHOD_POST);

    exit(1);
}

$data = Http\Runtime::getData();

file_put_contents(__DIR__ . DS . 'outputcode', var_export($data, true),
FILE_APPEND);

if(!isset($data['websocketUri'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'WebSocket URI is missing.', "\n";

    exit(2);
}

$uri = $data['websocketUri'];

$response->sendStatus($response::STATUS_CREATED);

Zombie::fork();

$websocket = new Websocket\Client(new Socket\Client($uri));
$websocket->setHost('php.ci');
$websocket->connect();

$websocket->send('Hello from . ' . phpversion());

}
