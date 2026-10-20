<?php

namespace Src\Modules\Colorfy;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\Colorfy\Handlers\ColorfyHandler;

final readonly class ColorfyModule extends BaseModule
{
    public function setup(): void
    {
        $this->router->get('/', ColorfyHandler::class, 'index');
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
