<?php

namespace Application\Model {

class Hook implements \JsonSerializable {

    protected $_repositoryUri = null;
    protected $_headCommitId  = null;

    public function __construct ( $repositoryUri ) {

        $this->setRepositoryUri($repositoryUri);

        return;
    }

    public function setRepositoryUri ( $repositoryUri ) {

        $old                  = $this->_repositoryUri;
        $this->_repositoryUri = $repositoryUri;

        return $old;
    }

    public function getRepositoryUri ( ) {

        return $this->_repositoryUri;
    }

    public function setHeadCommitId ( $id ) {

        $old                 = $this->_headCommitId;
        $this->_headCommitId = $id;

        return $old;
    }

    public function getHeadCommitId ( ) {

        return $this->_headCommitId;
    }

    public function jsonSerialize ( ) {

        return [
            'repository_uri' => $this->getRepositoryUri(),
            'head_commit_id' => $this->getHeadCommitId()
        ];
    }
}

}
