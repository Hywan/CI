<?php

namespace Application\Controller;

use Hoa\Dispatcher;
use Hoa\File;
use Hoa\Http;
use Hoa\Xyl;

class Generic extends Blindgeneric {

    public function construct ( ) {

        if(false === $this->router->isAsynchronous())
            $main = 'hoa://Application/View/Shared/Main.xyl';
        else
            $main = 'hoa://Application/View/Shared/Main.fragment.xyl';

        $xyl = new Xyl(
            new File\Read($main),
            new Http\Response(),
            new Xyl\Interpreter\Html(),
            $this->router
        );
        $xyl->setTheme('');

        $this->view = $xyl;
        $this->data = $xyl->getData();

        return;
    }

    public function render ( ) {

        if(false === $this->router->isAsynchronous()) {

            $this->view->render();

            return;
        }

        $this->view->interprete();
        $this->view->render($this->view->getSnippet('async_content'));

        return;
    }
}
