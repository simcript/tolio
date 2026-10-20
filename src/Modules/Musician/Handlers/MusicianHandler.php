<?php

namespace Src\Modules\Musician\Handlers;

use Pano\Foundation\Response;
use Pano\Kernel\BaseHandler;
use Pano\Kernel\HttpStatusEnum;
use Src\Modules\Musician\Services\AudioMetadata;
use Src\Modules\Musician\Services\MusicLibrary;

final class MusicianHandler extends BaseHandler
{
    public function index(): Response
    {
        $library = new MusicLibrary($this->module->path('Files'));
        $library->refresh();

        return Response::html(
            $this->module->view()
                ->with($library->read())
                ->layout('layout')
                ->render('home')
        );
    }

    public function info(): Response
    {
        $url = $this->request->getData()['url'] ?? null;

        if (!is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false
            || !in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return Response::json(['error' => 'A valid HTTP or HTTPS audio URL is required.'], HttpStatusEnum::BAD_REQUEST);
        }

        return Response::json((new AudioMetadata())->getAudioMetadata($url));
    }
}
