<?php

namespace Application\Controller;

use Hoa\Http;
use Hoa\Socket;
use Hoa\Fastcgi;
use Hoa\Eventsource;
use Hoa\Database;

use Application\Model;

class Api extends Blindgeneric {

    public function HookAction ( ) {

        $response = new Http\Response(false);
        $hook     = null;

        if(null !== $event = Http\Runtime::getHeader('x-github-event')) {

            $payload = Http\Runtime::getData();

            if(!is_array($payload)) {

                $response->sendStatus($response::STATUS_BAD_REQUEST);
                echo 'Payload seems to be corrupted.';

                return;
            }

            if('push' === $event) {

                $action = $payload['action'];

                if(   'opened'      !== $action
                   && 'reopened'    !== $action
                   && 'synchronize' !== $action)
                    return;

                $repositoryUri = $payload['repository']['git_url'];
                $headCommitId  = $payload['head_commit']['id'];
                $hook          = new Model\Hook($repositoryUri);
                $hook->setHeadCommitId($headCommitId);
            }
            elseif('pull_request' === $event) {

                $repositoryUri = $payload['pull_request']['head']['repo']['git_url'];
                $headCommitId  = $payload['pull_request']['head']['sha'];
                $hook          = new Model\Hook($repositoryUri);
                $hook->setHeadCommitId($headCommitId);
            }
            else {

                $response->sendStatus($response::STATUS_NOT_IMPLEMENTED);
                echo 'The event “' . $event . '” is not supported.';

                return;
            }
        }
        else {

            $response->sendStatus($response::STATUS_NOT_IMPLEMENTED);
            echo 'Hook from your platform is not supported.';

            return;
        }

        $port    = $this->findFreeEphemeralPort();
        echo 'ephemeral port: '; var_dump($port);
        echo json_encode($hook), "\n";
        $content = json_encode([
            'port' => $port,
            'hook' => $hook
        ]);

        $configurations = require_once 'hoa://Data/Etc/Configuration/Ci.php';

        $fastcgi = new Fastcgi\Responder(
            new Socket\Client(
                sprintf(
                    'tcp://%s:%s',
                    $configurations['primary.fpm.address'],
                    $configurations['primary.fpm.port']
                )
            )
        );
        $fastcgi->send(
            [
                'REQUEST_METHOD'  => 'POST',
                'REQUEST_URI'     => '/',
                'SCRIPT_FILENAME' => resolve('hoa://Application/Worker/Code.php'),
                'CONTENT_TYPE'    => 'application/json',
                'CONTENT_LENGTH'  => strlen($content),

                'SCRIPT_NAME'     => $this->router->getBootstrap(),
                'SERVER_NAME'     => $this->router->getDomain(),
                'SERVER_PORT'     => $this->router->getPort()
            ],
            $content
        );
        $headers = $fastcgi->getResponseHeaders();

        $response->sendStatus($headers['status']);
        $response->sendHeader('Location', $headers['location']);

        return;
    }

    protected function findFreeEphemeralPort ( ) {

        $max = 20;
        $i   = $max;

        do {

            $port   = mt_rand(49152, 65535);
            $server = stream_socket_server('tcp://127.0.0.1:' . $port);

            if(--$i < 0) {

                sleep(2);
                $i = $max;

                continue;
            }

        } while(false === $server);

        fclose($server);

        return $port;
    }

    public function LastJobsAction ( ) {

        try {

            $server = new Eventsource\Server();
        }
        catch ( Eventsource\Exception $e ) {

            echo 'You must send a request with ',
                 '“Accept: ', Eventsource\Server::MIME_TYPE, '”.', "\n";

            return;
        }

        $id = $server->getLastId();

        require_once 'hoa://Application/Database.php';
        $database  = Database\Dal::getInstance('jobs');

        if(empty($id)) {

            $statement = $database->prepare(
                'SELECT id, datetime FROM jobs ' .
                'ORDER BY datetime DESC LIMIT :limit'
            );
            $jobs      = array_reverse($statement->execute(['limit' => 5])->fetchAll());
        }
        else {

            $statement = $database->prepare(
                'SELECT id, datetime FROM jobs ' .
                'WHERE datetime > :datetime'
            );
            $jobs      = $statement->execute(['datetime' => $id])->fetchAll();
        }

        if(empty($jobs))
            $server->send(json_encode(null), $id);
        else
            foreach($jobs as $job) {

                $job['uri'] = $this->router->unroute('job', ['id' => $job['id']]);
                $server->send(json_encode($job), $job['datetime']);
            }

        $server->setReconnectionTime(5000);

        return;
    }
}
