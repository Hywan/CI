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

if(!isset($data['hook'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Hook is missing.', "\n";

    exit(3);
}

$uri  = $data['websocketUri'];
$hook = $data['hook'];

$response->sendStatus($response::STATUS_CREATED);

Zombie::fork();

// Wait the WebSocket server to be up.
sleep(3);

$websocket = new Websocket\Client(new Socket\Client($uri));
$websocket->setHost('ci');
$websocket->connect();

$workspace  = File\Temporary::getTemporaryDirectory() . DS . 'Ci' . DS;
while(is_dir($wId = sha1(uniqid('ci', true))));
$workspace .= $wId;

File\Directory::create($workspace);

$commands = [
    ['php'  => ['-r', 'echo getcwd();']],
    ['ls'   => []],
    ['who'  => []],
    ['date' => []],
    ['git'  => ['clone', '--no-checkout', $hook['repository_uri'], '.']],
    ['git'  => ['checkout', '--quiet', $hook['head_commit_id']]],
    ['ls'   => []]
];

$exitCode = 0;

foreach($commands as $line) {

    foreach($line as $command => $options) {

        $processus = new Console\Processus(
            $command,
            $options,
            null,
            $workspace
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

            $exitCode = 1;

            break 2;
        }

        $processus->close();
        unset($processus);

        sleep(1);
    }
}

$websocket->send('Exit code:' . $exitCode);
$websocket->send('@ci:CLOSE');

}
