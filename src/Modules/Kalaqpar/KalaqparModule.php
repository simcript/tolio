<?php

namespace Src\Modules\Kalaqpar;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\Kalaqpar\Commands\MigrateCommand;
use Src\Modules\Kalaqpar\Commands\PruneCommand;
use Src\Modules\Kalaqpar\Commands\SeedCommand;
use Src\Modules\Kalaqpar\Commands\ServeCommand;
use Src\Modules\Kalaqpar\Handlers\ApiHandler;
use Src\Modules\Kalaqpar\Handlers\LobbyHandler;

/**
 * Kalaqpar — real-time multiplayer Persian word racing, integrated as a
 * standard Pano module: pages + JSON API under the `kalaqpar` module key
 * (`/kalaqpar/...` URLs), and module CLI commands (`php pano kalaqpar
 * kalaqpar:*`). All game logic lives in Services/Repositories; handlers
 * only translate requests/responses.
 */
final readonly class KalaqparModule extends BaseModule
{
    public function setup(): void
    {
        // Pages (module-scoped RTL layout, Persian UI)
        $this->router->get('/', LobbyHandler::class, 'home');
        $this->router->get('/room/[code]', LobbyHandler::class, 'room');

        // JSON API
        $this->router->get('/rooms', ApiHandler::class, 'rooms');
        $this->router->get('/state', ApiHandler::class, 'state');
        $this->router->get('/leaderboard', ApiHandler::class, 'leaderboard');
        $this->router->post('/rooms', ApiHandler::class, 'createRoom');
        $this->router->post('/rooms/join', ApiHandler::class, 'joinRoom');
        $this->router->post('/rooms/leave', ApiHandler::class, 'leaveRoom');
        $this->router->post('/rooms/start', ApiHandler::class, 'startGame');
        $this->router->post('/guess', ApiHandler::class, 'guess');
        $this->router->post('/profile', ApiHandler::class, 'profile');

        // Module CLI: schema, seed, persistent worker, retention. `serve`
        // accepts --interval/--max-ticks; the framework appends CLI
        // options to the command URL, so its pattern keeps an optional
        // trailing segment to match them.
        $this->router->command('kalaqpar:migrate', MigrateCommand::class);
        $this->router->command('kalaqpar:seed', SeedCommand::class);
        $this->router->command('kalaqpar:serve/[opts?]', ServeCommand::class);
        $this->router->command('kalaqpar:prune', PruneCommand::class);
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
