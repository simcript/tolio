<?php

namespace Src\Modules\Onregex\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;

final class OnregexHandler extends BaseHandler
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
