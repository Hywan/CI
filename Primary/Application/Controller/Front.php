<?php

namespace Application\Controller;

use Hoa\Core;
use Hoa\Database;
use Hoa\Socket;
use Hoa\Http;
use Hoa\Websocket;
use Application\Model\Job;

class Front extends Generic {

    public function HomeAction ( ) {

        $this->view->addOverlay('hoa://Application/View/En/Home.xyl');
        $this->render();

        return;
    }

    public function JobAction ( $id ) {

        require_once 'hoa://Application/Database.php';
        $database  = Database\Dal::getInstance('jobs');
        $statement = $database->prepare(
            'SELECT * FROM jobs WHERE id = :id'
        );
        $job       = $statement->execute(['id' => $id])->fetchAll()[0];
        $status    = $job['status'];
        $_status   = null;
        $live      = false;

        if(0 !== ($status & Job::STATUS_PENDING)) {

            $_status .= 'pending';
            $live     = true;
        }
        else
            $_status .= 'done';

        $_status .= ' ';

        if(0 !== ($status & Job::STATUS_SUCCESS))
            $_status .= '(success)';
        elseif(0 !== ($status & Job::STATUS_FAIL))
            $_status .= '(fail)';
        else
            $_status .= '(inconclusive)';

        $data = [
            'live'   => (int) $live,
            'id'     => $id,
            'status' => $_status
        ];

        if(true === $live)
            $data['websocketUri'] = str_replace('tcp:', 'ws:', $job['websocketUri']);
        else
            $data['logs']         = $job['logs'];

        $this->data->job = $data;
        $this->view->addOverlay('hoa://Application/View/En/Job.xyl');
        $this->render();
    }

    public function TestAction ( ) {

        require_once 'hoa://Application/Database.php';
        $database  = Database\Dal::getInstance('authorizations');
        $statement = $database->prepare(
            'SELECT token FROM oauth_tokens ' .
            'WHERE resource_owner = :resource_owner'
        );
        $result    = $statement->execute(['resource_owner' => 'github'])
                               ->fetchAll()[0];

        print_r($result);

        $body = json_encode([
            'state'       => 'success',
            'target_url'  => 'http://127.0.0.1/job/123',
            'description' => 'Hoa\'s CI: bla bla',
            'context'     => 'That\'s a context.'
        ]);
        $request = new Http\Request();
        $request->setMethod($request::METHOD_POST);
        $request->setUrl('/repos/Hywan/Foobar/statuses/f9be0e0472e3df3997052c799f9e01cbf8a85f92');
        $request['Host']           = 'api.github.com';
        $request['User-Agent']     = 'hoa/http';
        $request['Connection']     = 'close';
        $request['Accept']         = '*/*';
        $request['Content-Type']   = 'application/json';
        $request['Content-Length'] = strlen($body);
        $request['Authorization']  = 'token ' . $result['token'];
        $request->setBody($body);

        echo $request . $request->getBody();

        $client = new Socket\Client('tcp://api.github.com:443');
        $client->connect();
        $client->setEncryption(true, $client::ENCRYPTION_TLS);
        $client->writeAll($request . $request->getBody() . CRLF);

        var_dump($client->readAll());
    }
}
