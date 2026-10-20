<?php

namespace Src\Modules\Calma;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\Calma\Handlers\CalmaHandler;

final readonly class CalmaModule extends BaseModule
{
    public function setup(): void
    {
        $this->router->get('/', CalmaHandler::class, 'index');
    }

    public function view(): BaseView
    {
        return new View($this->viewPath());
    }

    public function log(): BaseLogger
    {
        return new Logger($this->logPath());
    }
}
