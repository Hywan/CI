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

if(false === File\Directory::create($workspace)) {

    $websocket->send('Cannot create the workspace: ' . $workspace);
    $websocket->send('@ci:CLOSE');

    exit;
}

$commands = [
    ['php'   => ['-r', 'echo getcwd();']],
    ['git'   => ['clone', '--no-checkout', $hook['repository_uri'], '.']],
    ['git'   => ['checkout', '--quiet', $hook['head_commit_id']]],
    ['ls'    => []]
];

$exitCode = 0;

foreach($commands as $line) {

    foreach($line as $command => $options) {

        $processus = new Console\Processus(
            $command,
            $options,
            null,
            $workspace,
            [
                'PATH' => '/usr/local/bin:/usr/bin:/bin'
            ]
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
            $websocket->send('@ci:CLOSE');
            exit(4);
        }

        $processus->close();
        unset($processus);
    }
}

$websocket->send('////// Repository is ready!');
sleep(2);

$fpmPool = file('/Development/Php/Pool');

foreach($fpmPool as $entry) {

    list($version, $port) = explode(' ', $entry);

    $websocket->send('///// Start ' . $version);

    $content = json_encode([
        'websocketUri' => $data['websocketUri']
    ]);
    $websocket->send('[[[[[' . $content . ']]]]]');

    $fastcgi = new Fastcgi\Responder(
        new Socket\Client('tcp://127.0.0.1:' . $port)
    );
    $fastcgi->send(
        [
            'REQUEST_METHOD'  => 'POST',
            'REQUEST_URI'     => '/',
            'SCRIPT_FILENAME' => resolve('hoa://Application/Worker/Code.php'),
            'CONTENT_TYPE'    => 'application/json',
            'CONTENT_LENGTH'  => strlen($content)
        ],
        $content
    );

    $websocket->send(
        $version . ' => ' .
        var_export($fastcgi->getResponseHeaders(), true)
    );
    $websocket->send(
        $version . ' => ' .
        $fastcgi->getResponseContent()
    );
}

}
