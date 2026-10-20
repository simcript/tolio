<?php

namespace Src\Modules\Musician\Services;

use RuntimeException;
use Throwable;

final class MusicLibrary
{
    public function __construct(private readonly string $cachePath)
    {
    }

    public function read(): array
    {
        $library = [];

        foreach (['defaultSongs' => 'songs', 'defaultCategories' => 'categories', 'defaultPlaylists' => 'playlists'] as $key => $file) {
            $path = $this->cachePath . '/' . $file . '.log';
            $data = is_file($path) ? json_decode(file_get_contents($path), true) : null;
            $library[$key] = is_array($data) && array_is_list($data) ? $data : [];
        }

        return $library;
    }

    public function refresh(): void
    {
        if (!config('musician.refresh', true) || !function_exists('curl_init')) {
            return;
        }

        $timeFile = $this->cachePath . '/time.log';
        $lastRefresh = is_file($timeFile) ? (int) file_get_contents($timeFile) : 0;

        if (time() - $lastRefresh < 3600) {
            return;
        }

        try {
            $categories = [];
            $songs = [];
            $types = ['music', 'religious', 'ritual', 'declamation', 'poem', 'blues', 'eulogy', 'tawashih', 'lecture', 'book', 'podcast', 'other'];

            foreach ($types as $type) {
                $list = $this->getSongs($type);

                if ($list === []) {
                    continue;
                }

                $categories[] = ['name' => ucfirst($type), 'songs' => $list];
                $songs[] = $list[array_rand($list)];
            }

            if ($songs === []) {
                return;
            }

            shuffle($songs);
            $this->write('categories', $categories);
            $this->write('songs', $songs);
            // Playlists belong to users; refreshing provider songs must preserve them.
            if (!is_file($this->cachePath . '/playlists.log')) {
                $this->write('playlists', []);
            }
            if (file_put_contents($timeFile, (string) time(), LOCK_EX) === false) {
                throw new RuntimeException('Unable to update the music cache timestamp.');
            }
        } catch (Throwable $exception) {
            error_log('Musician refresh: ' . $exception->getMessage());
        }
    }

    private function getSongs(string $type): array
    {
        $url = config('musician.api_url') . '&type=' . rawurlencode($type);
        $request = curl_init($url);

        curl_setopt_array($request, [
            CURLOPT_ENCODING => 'gzip, deflate',
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . config('musician.api_token'),
                'User-Agent: tolio_musician',
                'X-Package-Name: ir.tolio.283',
            ],
        ]);

        $response = curl_exec($request);
        $status = curl_getinfo($request, CURLINFO_HTTP_CODE);
        $error = curl_error($request);
        curl_close($request);

        if ($response === false || $status !== 200) {
            throw new RuntimeException($error !== '' ? $error : 'Music provider returned HTTP ' . $status . '.');
        }

        $result = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($result['results']) || !is_array($result['results'])) {
            throw new RuntimeException('Music provider returned no song results.');
        }

        $songs = [];
        foreach ($result['results'] as $song) {
            $songs[] = [
                'title' => $song['title'],
                'artist' => $song['artists'],
                'album' => $song['album'],
                'year' => date('Y'),
                'publisher' => $song['source'],
                'cover' => $song['cover_url'],
                'link' => $song['high_quality_url'],
            ];
        }

        return $songs;
    }

    private function write(string $name, array $data): void
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR);
        if (file_put_contents($this->cachePath . '/' . $name . '.log', $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the music cache.');
        }
    }
}
