<?php

namespace Application\Controller {

use Hoa\Http;
use Hoa\Socket;
use Hoa\Fastcgi;

use Application\Model;

class Api extends Generic {

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

            $repositoryUri = $payload['repository']['url'];
            $headCommitId  = $payload['head_commit']['id'];
            $hook          = new Model\Hook($repositoryUri);
            $hook->setHeadCommitId($headCommitId);
        }
        else {

            $response->sendStatus($response::STATUS_NOT_IMPLEMENTED);
            echo 'Hook from your platform is not supported.';

            return;
        }

        $port    = $this->findFreeEphemeralPort();
        echo 'ephemeral port: '; var_dump($port);
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
}

}
