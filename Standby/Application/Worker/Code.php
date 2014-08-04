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
use Hoa\Console;

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

if(!isset($data['workspace'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Workspace is missing.', "\n";

    exit(3);
}

$workspace = $data['workspace'];

if(!isset($data['environment'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Environment is missing.', "\n";

    exit(4);
}

$environment = $data['environment'];

$response->sendStatus($response::STATUS_CREATED);

//Zombie::fork();

$websocket = new Websocket\Client(new Socket\Client($uri));
$websocket->setHost('php.ci');
$websocket->connect();

$websocket->send('Hello from ' . phpversion());

$commands = [
    ['atoum' => ['-d', 'tests']]
];

foreach($commands as $line) {

    foreach($line as $command => $options) {

        $processus = new Console\Processus(
            $command,
            $options,
            null,
            $workspace,
            $environment
        );
        $processus->on('start', function ( Core\Event\Bucket $bucket )
                                     use ( $websocket ) {

            $websocket->send('$ ' . $bucket->getSource()->getCommandLine());

            return false;
        });
        $processus->on('input', function ( ) {

            return false;
        });
        $processus->on('output', function ( Core\Event\Bucket $bucket )
                                      use ( $websocket ) {

            $websocket->send($bucket->getData()['line']);

            return;
        });
        $processus->run();

        if(false === $processus->isSuccessful()) {

            $websocket->send('///// :-(');
            $websocket->send('@ci@ stop');
            exit(4);
        }

        $processus->close();
        unset($processus);
    }
}

$websocket->send('@ci@ stop');

}
