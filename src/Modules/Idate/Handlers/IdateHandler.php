<?php

namespace Src\Modules\Idate\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;

final class IdateHandler extends BaseHandler
{
    public function index(): Response
    {
        return Response::html(
            $this->module->view()
                ->with(['serverUtcTime' => gmdate('Y-m-d H:i:s')])
                ->layout('layout')
                ->render('home')
        );
    }
}
