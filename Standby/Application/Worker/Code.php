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

if(!isset($data['id'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'ID is missing.', "\n";

    exit(2);
}

if(!isset($data['token'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Token is missing.', "\n";

    exit(3);
}

if(!isset($data['websocketUri'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'WebSocket URI is missing.', "\n";

    exit(4);
}

if(!isset($data['workspace'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Workspace is missing.', "\n";

    exit(5);
}

if(!isset($data['environment'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Environment is missing.', "\n";

    exit(6);
}

$id           = $data['id'];
$token        = $data['token'];
$websocketUri = $data['websocketUri'];
$workspace    = $data['workspace'];
$environment  = $data['environment'];

$response->sendStatus($response::STATUS_CREATED);

//Zombie::fork();

$websocket = new Websocket\Client(new Socket\Client($websocketUri));
$websocket->setHost('standby.ci');
$websocket->connect();

$websocket->send(
    sprintf(
        '@token@%s',
        $token
    )
);

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
                                     use ( $websocket, $id ) {

            $websocket->send(
                sprintf(
                    '@%s@%d@%s',
                    $id,
                    0,
                    '$ ' . $bucket->getSource()->getCommandLine()
                )
            );

            return false;
        });
        $processus->on('input', function ( ) {

            return false;
        });
        $processus->on('output', function ( Core\Event\Bucket $bucket )
                                      use ( $websocket, $id ) {

            $websocket->send(
                sprintf(
                    '@%s@%d@%s',
                    $id,
                    0,
                    $bucket->getData()['line']
                )
            );

            return;
        });
        $processus->run();

        if(false === $processus->isSuccessful()) {

            $websocket->send(
                sprintf(
                    '@%s@%d@%s',
                    $id,
                    3,
                    'Command `' . $processus->getCommandLine() . '` has failed.'
                )
            );
            $websocket->send(
                sprintf(
                    '@%s@%d',
                    $id,
                    1
                )
            );
            exit(7);
        }

        $processus->close();
        unset($processus);
    }
}

$websocket->send(
    sprintf(
        '@%s@%d',
        $id,
        1
    )
);

}
