<?php
namespace Src\Modules\Kalaqpar;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\Kalaqpar\Commands\ManageCommand;
use Src\Modules\Kalaqpar\Handlers\ApiHandler;
use Src\Modules\Kalaqpar\Handlers\AccountHandler;
use Src\Modules\Kalaqpar\Handlers\DefaultHandler;
use Src\Modules\Kalaqpar\Handlers\PwaHandler;

final readonly class KalaqparModule extends BaseModule
{
    public function setup(): void
    {
        $this->router->get('/', DefaultHandler::class, 'home');
        foreach (['register' => 'register', 'login' => 'login', 'forgot-password' => 'forgot', 'reset-password' => 'reset'] as $path => $action) {
            $this->router->get('/' . $path, AccountHandler::class, $action);
            $this->router->post('/' . $path, AccountHandler::class, $action . 'Post');
        }
        $this->router->get('/account', AccountHandler::class, 'account');
        $this->router->post('/account/name', AccountHandler::class, 'rename');
        $this->router->post('/logout', AccountHandler::class, 'logout');
        $this->router->get('/manifest.webmanifest', PwaHandler::class, 'manifest');
        $this->router->get('/sw.js', PwaHandler::class, 'worker');
        $this->router->get('/offline', PwaHandler::class, 'offline');
        $this->router->get('/join/[token]', DefaultHandler::class, 'invitation');
        $this->router->get('/r/[token]', DefaultHandler::class, 'invitation');
        $this->router->get('/assets/[file*]', DefaultHandler::class, 'asset');
        foreach (['state','profile','leaderboard','preview','health'] as $action) {
            $this->router->get('/api/' . $action, ApiHandler::class, $action);
        }
        foreach (['bootstrap','heartbeat','queue','create','join','leave','start','answer','rematch','spectate','resume','rename'] as $action) {
            $this->router->post('/api/' . $action, ApiHandler::class, $action);
        }
        foreach (['migrate','seed','worker','stop','rebuild','backup','mail'] as $command) {
            $this->router->command('kalaqpar:' . $command, ManageCommand::class);
        }
    }
    public function view(): BaseView { return new View($this->viewPath()); }
    public function log(): BaseLogger { return new Logger($this->logPath()); }
}
