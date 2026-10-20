<?php

namespace Src\Modules\Musician;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\Musician\Handlers\MusicianHandler;

final readonly class MusicianModule extends BaseModule
{
    public function setup(): void
    {
        $this->router->get('/', MusicianHandler::class, 'index');
        $this->router->post('/info', MusicianHandler::class, 'info');
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
