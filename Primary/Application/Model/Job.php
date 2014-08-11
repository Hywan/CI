<?php

namespace Application\Model;

use Hoa\Database;
use Hoa\Http;
use Hoa\Socket;

class Job {

    const STATUS_PENDING =  1;
    const STATUS_DONE    =  2;
    const STATUS_SUCCESS =  4;
    const STATUS_FAIL    =  8;
    const STATUS_ERROR   = 16;

    public static function notifyStatus ( $status, $to, $commitId, $jobUri ) {

        require_once 'hoa://Application/Database.php';
        $database  = Database\Dal::getInstance('authorizations');
        $statement = $database->prepare(
            'SELECT s.socket_api_uri, t.token ' .
            'FROM oauth_services AS s INNER JOIN oauth_tokens AS t ' .
            'ON s.resource_owner = t.resource_owner ' .
            'WHERE s.resource_owner = :resource_owner'
        );
        $result    = $statement->execute(['resource_owner' => $to])
                               ->fetchAll()[0];
        $state       = 'success';
        $description = 'Le Comte Intatto: ';

        if(0 !== ($status & self::STATUS_PENDING)) {

            $state        = 'pending';
            $description .= 'tests are pending…';
        }
        elseif(0 !== ($status & self::STATUS_SUCCESS)) {

            $state        = 'success';
            $description .= 'all tests passed!';
        }
        elseif(0 !== ($status & self::STATUS_ERROR)) {

            $state        = 'error';
            $description .= 'an error occured while running tests.';
        }
        elseif(0 !== ($status & self::STATUS_FAIL)) {

            $state        = 'failure';
            $description .= 'a failure has been detected!';
        }

        $body = json_encode([
            'state'       => $state,
            'target_url'  => $jobUri,
            'description' => $description,
            'context'     => 'comte-intatto'
        ]);
        $request = new Http\Request();
        $request->setMethod($request::METHOD_POST);
        $request->setUrl('/repos/Hywan/Foobar/statuses/' . $commitId);
        $request['Host']           = 'api.github.com';
        $request['User-Agent']     = 'hoa/http';
        $request['Connection']     = 'close';
        $request['Accept']         = '*/*';
        $request['Content-Type']   = 'application/json';
        $request['Content-Length'] = strlen($body);
        $request['Authorization']  = 'token ' . $result['token'];
        $request->setBody($body);

        $client = new Socket\Client($result['socket_api_uri']);
        $client->connect();
        $client->setEncryption(true, $client::ENCRYPTION_TLS);
        $client->writeAll($request . $request->getBody() . CRLF);

        return;
    }
}
