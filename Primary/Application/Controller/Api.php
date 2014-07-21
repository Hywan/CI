<?php

namespace Application\Controller {

use Hoa\Http;
use Hoa\Socket;
use Hoa\Fastcgi;

class Api extends Generic {

    public function HookAction ( ) {

        echo 'hook: ';

        $port    = $this->findFreeEphemeralPort();
        var_dump($port);
        $content = json_encode(['port' => $port]);

        $fastcgi = new Fastcgi\Responder(
            new Socket\Client('tcp://127.0.0.1:9001')
        );
        $fastcgi->send(
            [
                'REQUEST_METHOD'  => 'POST',
                'REQUEST_URI'     => '/',
                'SCRIPT_FILENAME' => resolve('hoa://Application/Worker/Job.php'),
                'CONTENT_TYPE'    => 'application/json',
                'CONTENT_LENGTH'  => strlen($content),

                'SCRIPT_NAME'     => $_SERVER['SCRIPT_NAME'],
                'SERVER_PORT'     => $_SERVER['SERVER_PORT']
            ],
            $content
        );

        var_dump($fastcgi->getResponseHeaders());
        var_dump($fastcgi->getResponseContent());


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
