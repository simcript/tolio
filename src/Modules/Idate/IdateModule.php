<?php

namespace Src\Modules\Idate;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\Idate\Handlers\IdateHandler;

final readonly class IdateModule extends BaseModule
{
    public function setup(): void
    {
        $this->router->get('/', IdateHandler::class, 'index');
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
