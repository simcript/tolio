<?php

namespace Src\Modules\Laralog;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\Laralog\Handlers\LaralogHandler;

final readonly class LaralogModule extends BaseModule
{
    public function setup(): void
    {
        $this->router->get('/', LaralogHandler::class, 'index');
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
