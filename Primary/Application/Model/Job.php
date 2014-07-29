<?php

namespace Application\Model {

class Job {

    const STATUS_PENDING =  1;
    const STATUS_DONE    =  2;
    const STATUS_SUCCESS =  4;
    const STATUS_FAIL    =  8;
    const STATUS_ERROR   = 16;
}

}
