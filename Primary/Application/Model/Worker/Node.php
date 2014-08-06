<?php

namespace Application\Model\Worker {

use Hoa\Websocket;

class Node extends Websocket\Node {

    /**
     * To determine if the client is from the standby or from the primary.
     */
    protected $_token = null;



    public function setToken ( $token ) {

        $old          = $this->_token;
        $this->_token = $token;

        return $old;
    }

    public function getToken ( ) {

        return $this->_token;
    }
}

}
