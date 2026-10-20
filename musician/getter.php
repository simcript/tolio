<?php

function getSongs($type)
{

    $url = "https://wavechannel.ir/api/songs/list?from=2025-01-01&to=2026-01-01&type=$type";

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_ENCODING => "gzip, deflate",
        CURLOPT_TIMEOUT => 0,
        CURLOPT_VERBOSE => 0,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer 427bc38136e6900c20ee9891e7fc7b0e",
            "User-Agent: tolio_musician",
            "X-Package-Name: ir.tolio.283",
        ],
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        file_put_contents(__DIR__ . '/assets/error.log', "cURL Error: " . curl_error($ch) . PHP_EOL, FILE_APPEND);
        return;
    }

    curl_close($ch);

    $result = json_decode($response, true);
    $result = $result['results'];
    $data = [];
    foreach ($result as $song) {
        $data[] = [
            "title" => $song['title'],
            "artist" => $song['artists'],
            "album" => $song['album'],
            "year" => date('Y'),
            "publisher" => $song['source'],
            "cover" => $song['cover_url'],
            "link" => $song['high_quality_url'],
        ];
    }
    return $data;

}

function main () {
    try {
        $timeFile = __DIR__ . '/assets/time.log';
        $time = file_get_contents($timeFile);
        if ((time() - $time) < 3600) {
            return;
        }
        file_put_contents($timeFile, time());
        $types = ['music','religious','ritual','declamation','poem','blues','eulogy','tawashih','lecture','book','podcast','other'];
        $playlists = [];
        $songs = [];
        foreach ($types as $type) {
            $list = getSongs($type);
            if (empty($list)) {
                continue;
            }
            $playlists[] = [
                'name' => ucfirst($type),
                'songs' => $list
            ];
            $songs[] = $list[rand(0, count($list) - 1)];
        }
        shuffle($songs);
        file_put_contents(__DIR__ . '/assets/categories.log', json_encode($playlists));
        file_put_contents(__DIR__ . '/assets/playlists.log', json_encode([]));
        file_put_contents(__DIR__ . '/assets/songs.log', json_encode($songs));
    } catch (\Exception $e) {
        file_put_contents(__DIR__ . '/assets/error.log', $e->getMessage() . PHP_EOL, FILE_APPEND);
    }
}

main();