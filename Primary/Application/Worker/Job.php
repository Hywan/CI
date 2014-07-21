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

$port   = $data['port'];
$uri    = 'tcp://127.0.0.1:' . $port;
$id     = sha1(uniqid('ci', true));
$router = require_once 'hoa://Application/Router.php';

$response->sendStatus($response::STATUS_CREATED);
$response->sendHeader('Location', $router->unroute('job', ['id' => $id]));

require_once 'hoa://Application/Database.php';
$database  = Database\Dal::getInstance('jobs');
$statement = $database->prepare(
    'INSERT INTO jobs (id, websocketUri, status) ' .
    'VALUES (:id, :websocketUri, :status)'
);
$statement->execute([
    'id'           => $id,
    'websocketUri' => $uri,
    'status'       => Job::STATUS_PENDING
]);

Zombie::fork();

$content = json_encode(['websocketUri' => $uri]);
$fastcgi = new Fastcgi\Responder(
    new Socket\Client('tcp://127.0.0.1:9001')
);
$fastcgi->send(
    [
        'REQUEST_METHOD'  => 'POST',
        'REQUEST_URI'     => '/',
        'SCRIPT_FILENAME' => resolve('hoa://Application/External/Standby/Worker/Job.php'),
        'CONTENT_TYPE'    => 'application/json',
        'CONTENT_LENGTH'  => strlen($content)
    ],
    $content
);

$websocket = new Websocket\Server(new Socket\Server($uri));
$websocket->on('open', function ( Core\Event\Bucket $bucket ) {

    //$bucket->getSource()->getConnection()->quiet();

    return;
});
$websocket->on('message', function ( Core\Event\Bucket $bucket ) use ( $port,
                                                                       $id,
                                                                       $database ) {

    $message = $bucket->getData()['message'];

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

    $bucket->getSource()->broadcast($bucket->getData()['message']);

    return;
});
$websocket->run();

}
