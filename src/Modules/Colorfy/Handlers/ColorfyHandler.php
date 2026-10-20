<?php

namespace Src\Modules\Colorfy\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;

final class ColorfyHandler extends BaseHandler
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
