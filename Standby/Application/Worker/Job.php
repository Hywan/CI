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

file_put_contents(__DIR__ . DS . 'Log', 'Here' . "\n", FILE_APPEND);

$response = new Http\Response(false);

if(Http\Request::METHOD_POST !== Http\Runtime::getMethod()) {

    $response->sendStatus($response::STATUS_METHOD_NOT_ALLOWED);
    $response->sendHeader('Allow', Http\Request::METHOD_POST);

    exit(1);
}

$data = Http\Runtime::getData();

if(!isset($data['websocketUri'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'WebSocket URI is missing.', "\n";

    exit(2);
}

$uri = $data['websocketUri'];

$response->sendStatus($response::STATUS_CREATED);

Zombie::fork();

file_put_contents(__DIR__ . DS . 'Log', 'There' . "\n", FILE_APPEND);
file_put_contents(__DIR__ . DS . 'Log', 'uri: ' . $uri . '' . "\n", FILE_APPEND);

$websocket = new Websocket\Client(new Socket\Client($uri));
$websocket->setHost('ci');
$websocket->connect();

file_put_contents(__DIR__ . DS . 'Log', 'Here we go…' . "\n", FILE_APPEND);

for($i = 15; $i > 0; --$i) {

    file_put_contents(__DIR__ . DS . 'Log', 'Send something', FILE_APPEND);
    sleep(mt_rand(1, 2));
    $websocket->send(sha1($i . time()));
}

$websocket->send('@ci:CLOSE');

}
