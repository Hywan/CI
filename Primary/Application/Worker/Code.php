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
use Hoa\Database;
use Application\Model\Job;
use Hoa\Fastcgi;

$response = new Http\Response(false);

if(Http\Request::METHOD_POST !== Http\Runtime::getMethod()) {

    $response->sendStatus($response::STATUS_METHOD_NOT_ALLOWED);
    $response->sendHeader('Allow', Http\Request::METHOD_POST);

    exit(1);
}

$data = Http\Runtime::getData();

if(!isset($data['port'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Port is missing.', "\n";

    exit(2);
}

if(!isset($data['hook'])) {

    $response->sendStatus($response::STATUS_BAD_REQUEST);
    echo 'Hook is missing.', "\n";

    exit(3);
}

$configurations = require_once 'hoa://Data/Etc/Configuration/Ci.php';

$port   = $data['port'];
$uri    = sprintf(
    'tcp://%s:%s',
    $configurations['primary.address'],
    $port
);
$id     = sha1(uniqid('ci/primary', true));
$router = require_once 'hoa://Application/Router.php';

$response->sendStatus($response::STATUS_CREATED);
$response->sendHeader(
    'Location',
    $router->unroute(
        'job',
        [
            'id'         => $id,
            '_subdomain' => '__self__'
        ]
    )
);

require_once 'hoa://Application/Database.php';
$database  = Database\Dal::getInstance('jobs');
$statement = $database->prepare(
    'INSERT INTO jobs (id, datetime, websocketUri, status) ' .
    'VALUES (:id, :datetime, :websocketUri, :status)'
);
$statement->execute([
    'id'           => $id,
    'datetime'     => time(),
    'websocketUri' => $uri,
    'status'       => Job::STATUS_PENDING
]);

Zombie::fork();

$content = json_encode([
    'primaryId'    => $id,
    'websocketUri' => $uri,
    'hook'         => $data['hook']
]);
$fastcgi = new Fastcgi\Responder(
    new Socket\Client(
        sprintf(
            'tcp://%s:%s',
            $configurations['standby.fpm.address'],
            $configurations['standby.fpm.port']
        )
    )
);
$fastcgi->send(
    [
        'REQUEST_METHOD'  => 'POST',
        'REQUEST_URI'     => '/',
        'SCRIPT_FILENAME' => $configurations['standby.root'] . 'Worker/Job.php',
        'CONTENT_TYPE'    => 'application/json',
        'CONTENT_LENGTH'  => strlen($content)
    ],
    $content
);

$superI = 0;

$websocket = new Websocket\Server(new Socket\Server($uri));
$websocket->on('open', function ( Core\Event\Bucket $bucket ) {

    //$bucket->getSource()->getConnection()->quiet();

    return;
});
$websocket->on('message', function ( Core\Event\Bucket $bucket ) use ( $port,
                                                                       $id,
                                                                       $database,
                                                                       &$superI ) {

    preg_match(
        '#^@(?<id>[^@]+)@(?<code>[0-2])(@(?<message>.+))?$#',
        $bucket->getData()['message'],
        $message
    );

    preg_match(
        '#^(?<primary>[^/]+)/(?<version>[^/]+)/(?<standby>.+)$#',
        $message['id'],
        $ids
    );

    switch(intval($message['code'])) {

        case 2: // wait
            $superI += intval($message['message']);
          break;

        case 1: // stop
            --$superI;
            $bucket->getSource()->close();

            if(0 >= $superI)
                exit;
          break;

        case 0: // log
            $bucket->getSource()->broadcast(
                sprintf(
                    '@%s@%s@%s',
                    $ids['version'],
                    $ids['standby'],
                    $message['message']
                )
            );
          break;
    }

    /*
    if('@ci:CLOSE' === $message) {

        $statement = $database->prepare(
            'UPDATE jobs SET status = :status WHERE id = :id'
        );
        $statement->execute([
            'id'     => $id,
            'status' => Job::STATUS_DONE
        ]);

        $bucket->getSouce()->close();

        exit;
    }
    */

    return;
});

$websocket->run();

}
