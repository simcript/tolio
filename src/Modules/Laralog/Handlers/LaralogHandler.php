<?php

namespace Src\Modules\Laralog\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;

final class LaralogHandler extends BaseHandler
{
    public function index(): Response
    {
        return Response::html(
            $this->module->view()
                ->layout('layout')
                ->render('home')
        );
    }
}
