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
use Application\Model\Worker\Node;
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
$id     = sha1(uniqid('ci/primary/id', true));
$token  = sha1(uniqid('ci/primary/token', true));
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
    'token'        => $token,
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

$waiting = 0;
$status  = Job::STATUS_SUCCESS;
$buffer  = [];

$websocket = new Websocket\Server(new Socket\Server($uri));
$websocket->getConnection()->setNodeName('\Application\Model\Worker\Node');
$websocket->on('open', function ( Core\Event\Bucket $bucket ) use ( &$buffer ) {

    $bucket->getSource()->send(json_encode($buffer));

    return;
});
$websocket->on('message', function ( Core\Event\Bucket $bucket ) use ( $id,
                                                                       $token,
                                                                       $database,
                                                                       &$waiting,
                                                                       &$status,
                                                                       &$buffer ) {

    $source      = $bucket->getSource();
    $currentNode = $source->getConnection()->getCurrentNode();
    $_message    = $bucket->getData()['message'];

    if(null === $currentNode->getToken()) {

        if(0 === preg_match('#^@token@(?<token>.+)$#', $_message, $match)) {

            $source->close(
                $source::CLOSE_POLICY_ERROR,
                'You are not authorized to send messages to this server, ' .
                'just to read them.'
            );

            return;
        }

        if($match['token'] !== $token) {

            $source->close(
                $source::CLOSE_POLICY_ERROR,
                'Your token mismatch. You seem to not be authorized to send ' .
                'messages to this server, just to read them.'
            );

            return;
        }

        $currentNode->setToken($token);

        return;
    }

    preg_match(
        '#^@(?<id>[^@]+)@(?<code>[0-3])(@(?<message>.+))?$#',
        $_message,
        $message
    );

    preg_match(
        '#^(?<primary>[^/]+)/(?<version>[^/]+)/(?<standby>.+)$#',
        $message['id'],
        $ids
    );

    switch(intval($message['code'])) {

        case 3: // error
            if('*' === $version)
                $status = Job::STATUS_ERROR;
            else
                $status = Job::STATUS_FAIL;

            $output = sprintf(
                '!%s@%s@%s',
                $ids['version'],
                $ids['standby'],
                '❌  ' . $message['message']
            );
            $source->broadcastIf(
                function ( Node $node ) {

                    // client from the primary
                    return null === $node->getToken();
                },
                json_encode($output)
            );
            $buffer[] = $output;
          break;

        case 2: // wait
            $waiting += intval($message['message']);
          break;

        case 1: // stop
            --$waiting;

            if(0 >= $waiting) {

                $statement = $database->prepare(
                    'UPDATE jobs SET status = :status, logs = :logs ' .
                    'WHERE id = :id'
                );
                $statement->execute([
                    'id'     => $id,
                    'status' => Job::STATUS_DONE | $status,
                    'logs'   => json_encode($buffer)
                ]);

                exit;
            }
          break;

        case 0: // log
            $output = sprintf(
                '@%s@%s@%s',
                $ids['version'],
                $ids['standby'],
                $message['message']
            );
            $source->broadcastIf(
                function ( Node $node ) {

                    // client from the primary
                    return null === $node->getToken();
                },
                json_encode($output)
            );
            $buffer[] = $output;
          break;
    }

    return;
});

$websocket->run();

}
