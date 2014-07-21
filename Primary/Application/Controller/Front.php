<?php

namespace Application\Controller {

use Hoa\Core;
use Hoa\Database;
use Hoa\Socket;
use Hoa\Websocket;
use Application\Model\Job;

class Front extends Generic {

    public function HomeAction ( ) {

        echo 'home', "\n";
    }

    public function JobAction ( $id ) {

        require_once 'hoa://Application/Database.php';
        $database  = Database\Dal::getInstance('jobs');
        $statement = $database->prepare(
            'SELECT * FROM jobs WHERE id = :id'
        );
        $job       = $statement->execute(['id' => $id])->fetchAll()[0];
        print_r($job);

        $status = $job['status'];

        echo 'Status: ';

        if(0 !== ($status & Job::STATUS_PENDING))
            echo 'pending';
        else
            echo 'done';

        echo ' ';

        if(0 !== ($status & Job::STATUS_SUCCESS))
            echo '(success)';
        elseif(0 !== ($status & Job::STATUS_FAIL))
            echo '(fail)';
        else
            echo '(inconclusive)';

        $host = str_replace('tcp:', 'ws:', $job['websocketUri']);

        echo <<<OUTPUT
<pre id="output"></pre>
<script>
    var host   = '$host';
    var socket = null;
    var output = document.getElementById('output');
    var print  = function ( message ) {

        var samp       = document.createElement('samp');
        samp.innerHTML = message + '\\n';
        output.appendChild(samp);

        return;
    };

    try {

        socket = new WebSocket(host);
        socket.onopen = function ( ) {

            print('connection is opened');
            input.focus();

            return;
        };
        socket.onmessage = function ( msg ) {

            print(msg.data);

            return;
        };
        socket.onclose = function ( e ) {

            print(
                'connection is closed (' + e.code + ' ' +
                (e.reason || '—no reason—') + ')'
            );

            return;
        };
    }
    catch ( e ) {

        console.log(e);
    }
</script>
OUTPUT;
    }
}

}
