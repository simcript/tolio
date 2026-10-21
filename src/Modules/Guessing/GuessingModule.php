<?php

namespace Src\Modules\Guessing;

use Pano\Foundation\Logger;
use Pano\Foundation\View;
use Pano\Kernel\BaseLogger;
use Pano\Kernel\BaseModule;
use Pano\Kernel\BaseView;
use Src\Modules\Guessing\Commands\MigrateCommand;
use Src\Modules\Guessing\Commands\PruneCommand;
use Src\Modules\Guessing\Commands\SeedCommand;
use Src\Modules\Guessing\Commands\ServeCommand;
use Src\Modules\Guessing\Handlers\ApiHandler;
use Src\Modules\Guessing\Handlers\LobbyHandler;

/**
 * Guessing — real-time multiplayer Persian word racing, integrated as a
 * standard Pano module: pages + JSON API under the `guessing` module key
 * (`/guessing/...` URLs), and module CLI commands (`php pano guessing
 * guessing:*`). All game logic lives in Services/Repositories; handlers
 * only translate requests/responses.
 */
final readonly class GuessingModule extends BaseModule
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
        $this->router->command('guessing:migrate', MigrateCommand::class);
        $this->router->command('guessing:seed', SeedCommand::class);
        $this->router->command('guessing:serve/[opts?]', ServeCommand::class);
        $this->router->command('guessing:prune', PruneCommand::class);
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
