<?php

namespace Application\Model {

class Oauth {

    /**
     * Authorization code.
     */
    const TYPE_CODE     = 0;

    /**
     * Implicit.
     */
    const TYPE_IMPLICIT = 1;

    /**
     * Password.
     */
    const TYPE_PASSWORD = 2;

    /**
     * Client credentials.
     */
    const TYPE_CLIENT   = 3;
}

}
