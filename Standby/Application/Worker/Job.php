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

if(!isset($data['primaryId'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Primary ID is missing.', "\n";

    exit(2);
}

if(!isset($data['websocketUri'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'WebSocket URI is missing.', "\n";

    exit(3);
}

if(!isset($data['hook'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Hook is missing.', "\n";

    exit(4);
}

$primaryId    = $data['primaryId'];
$standbyId    = sha1(uniqid('ci/standby', true));
$id           = $primaryId . '/*/' . $standbyId;
$websocketUri = $data['websocketUri'];
$hook         = $data['hook'];

$response->sendStatus($response::STATUS_CREATED);

Zombie::fork();

// Wait the WebSocket server to be up.
sleep(3);

$websocket = new Websocket\Client(new Socket\Client($websocketUri));
$websocket->setHost('standby.ci');
$websocket->connect();

$workspace  = File\Temporary::getTemporaryDirectory() . DS . 'Ci' . DS;
while(is_dir($wId = sha1(uniqid('ci/standby', true))));
$workspace .= $wId;

if(false === File\Directory::create($workspace)) {

    $websocket->send(
        sprintf(
            '@%s@%d@%s',
            $id,
            3,
            'Cannot create the workspace: ' . $workspace
        )
    );
    $websocket->send(
        sprintf(
            '@%s@%d',
            $id,
            1
        )
    );

    exit;
}

$commands = [
    ['php'   => ['-r', 'echo getcwd();']],
    ['git'   => ['clone', '--no-checkout', $hook['repository_uri'], '.']],
    ['git'   => ['checkout', '--quiet', $hook['head_commit_id']]],
    ['ls'    => []]
];

foreach($commands as $line) {

    foreach($line as $command => $options) {

        $processus = new Console\Processus(
            $command,
            $options,
            null,
            $workspace,
            [
                'PATH' => '/usr/local/bin' .
                          ':/usr/bin' .
                          ':/bin',
                'PWD'  => $workspace
            ]
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
            exit(5);
        }

        $processus->close();
        unset($processus);
    }
}

$fpmPool = file('/Development/Php/Pool');
$websocket->send(
    sprintf(
        '@%s@%d@%d',
        $id,
        2,
        count($fpmPool)
    )
);

$finalIdFormat = sprintf('%s/%%s/%s', $primaryId, $standbyId);

foreach($fpmPool as $entry) {

    list($version, $port) = explode(' ', $entry);

    $websocket->send(
        sprintf(
            '@%s@%d@%s',
            0,
            '# start ' . $version
        )
    );

    $content = json_encode([
        'id'           => sprintf($finalIdFormat, $version),
        'websocketUri' => $websocketUri,
        'workspace'    => $workspace,
        'environment'  => [
            'PATH' => '/Development/Php/' . $version . '/bin' .
                      ':/usr/local/bin' .
                      ':/usr/bin' .
                      ':/bin',
            'PWD'  => $workspace
        ]
    ]);
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
}

}
