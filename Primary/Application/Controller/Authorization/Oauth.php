<?php

namespace Application\Controller\Authorization;

use Hoa\Database;
use Hoa\Http;
use Hoa\Socket;
use Application\Controller\Blindgeneric;

class Oauth extends Blindgeneric {

    protected $_database = null;



    public function construct ( ) {

        parent::construct();

        require_once 'hoa://Application/Database.php';
        $this->_database = Database\Dal::getInstance('authorizations');

        return;
    }

    public function RequestAccessAction ( $resourceOwner ) {

        $database  = $this->getDatabase();
        $statement = $database->prepare(
            'SELECT s.login_uri, a.client_id, a.scope ' .
            'FROM oauth_services AS s INNER JOIN oauth_accesses AS a ' .
            'ON s.resource_owner = a.resource_owner ' .
            'WHERE s.resource_owner = :resource_owner'
        );
        $results   = $statement->execute(['resource_owner' => $resourceOwner])
                               ->fetchAll();
        $response  = new Http\Response(false);

        if(empty($results)) {

            $response->sendStatus($response::STATUS_NOT_FOUND);

            return;
        }

        $result = $results[0];
        $salt   = sha1(uniqid($result['client_id'], true));
        $url    = sprintf(
            '%s?client_id=%s&redirect_uri=%s&scope=%s&state=%s',
            $result['login_uri'],
            $result['client_id'],
            urlencode($this->router->unroute(
                'auth_oauth_callback',
                [
                    'resourceOwner' => $resourceOwner,
                    '_subdomain'    => '__self__'
                ]
            )),
            urlencode($result['scope']),
            $salt
        );

        $statement = $database->prepare(
            'UPDATE oauth_accesses ' .
            'SET salt = :salt ' .
            'WHERE resource_owner = :resource_owner'
        );
        $statement->execute([
            'resource_owner' => $resourceOwner,
            'salt'           => $salt
        ]);

        $response->sendStatus($response::STATUS_FOUND);
        $response->sendHeader('Location', $url);

        return;
    }

    public function CallbackAction ( $resourceOwner ) {

        $data     = Http\Runtime::getData();
        $response = new Http\Response(false);

        $database  = $this->getDatabase();
        $statement = $database->prepare(
            'SELECT salt FROM oauth_accesses ' .
            'WHERE resource_owner = :resource_owner'
        );
        $results   = $statement->execute(['resource_owner' => $resourceOwner])
                               ->fetchAll();

        if(empty($results)) {

            $response->sendStatus($response::STATUS_NOT_FOUND);

            return;
        }

        $result = $results[0];

        if($result['salt'] !== $data['state']) {

            $response->sendStatus($response::STATUS_BAD_REQUEST);

            return;
        }

        $statement = $database->prepare(
            'UPDATE oauth_accesses ' .
            'SET code = :code ' .
            'WHERE resource_owner = :resource_owner AND salt = :salt'
        );
        $statement->execute([
            'resource_owner' => $resourceOwner,
            'salt'           => $data['state'],
            'code'           => $data['code']
        ]);

        $statement = $database->prepare(
            'SELECT s.socket_uri, s.token_uri, a.client_id, a.client_secret, a.code ' .
            'FROM oauth_services AS s INNER JOIN oauth_accesses AS a ' .
            'ON s.resource_owner = a.resource_owner ' .
            'WHERE s.resource_owner = :resource_owner'
        );
        $result    = $statement->execute(['resource_owner' => $resourceOwner])
                               ->fetchAll()[0];

        $body = json_encode([
            'client_id'     => $result['client_id'],
            'client_secret' => $result['client_secret'],
            'code'          => $result['code'],
            'redirect_uri'  => $this->router->unroute(
                'auth_oauth_callback',
                [
                    'resourceOwner' => $resourceOwner,
                    '_subdomain'    => '__self__'
                ]
            )
        ]);

        $request = new Http\Request();
        $request->setMethod($request::METHOD_POST);
        $request->setUrl($result['token_uri']);
        $request['Host']           = 'github.com';
        $request['Accept']         = 'application/json';
        $request['Connection']     = 'close';
        $request['Content-Type']   = 'application/json';
        $request['Content-Length'] = strlen($body);
        $request->setBody($body);

        $client = new Socket\Client($result['socket_uri']);
        $client->connect();
        $client->setEncryption(true, $client::ENCRYPTION_TLS);
        $client->writeAll($request . $request->getBody() . CRLF);
        $clientResponse = new Http\Response(false);
        $clientResponse->parse($a = $client->readAll());

        $clientResult = json_decode($clientResponse->getBody(), true);

        if(!isset($clientResult['access_token'])) {

            echo '**ERROR**', "\n";
            echo $clientResponse->getBody();

            return;
        }

        $statement = $database->prepare(
            'INSERT OR REPLACE INTO oauth_tokens ' .
            'VALUES ( :resource_owner, :token, :created_at )'
        );
        $statement->execute([
            'resource_owner' => $resourceOwner,
            'token'          => $clientResult['access_token'],
            'created_at'     => time()
        ]);

        echo 'Connected!';

        return;
    }

    protected function getDatabase ( ) {

        return $this->_database;
    }
}
