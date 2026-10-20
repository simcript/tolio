<?php

namespace Src\Modules\RedJson\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;

final class RedJsonHandler extends BaseHandler
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
