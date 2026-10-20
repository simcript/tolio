<?php

namespace Src\Modules\Calma\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;

final class CalmaHandler extends BaseHandler
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
