<?php

namespace Src\Modules\RedJson;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\RedJson\Handlers\RedJsonHandler;

final readonly class RedJsonModule extends BaseModule
{
    public function setup(): void
    {
        $this->router->get('/', RedJsonHandler::class, 'index');
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
