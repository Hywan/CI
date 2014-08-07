<?php

namespace Application\Controller {

use Hoa\Core;
use Hoa\Database;
use Hoa\Socket;
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
}

}
