<?php

use Pano\Foundation\Boot;
use Pano\Foundation\Request;
use Pano\Foundation\Router;
use Pano\Kernel\BaseException;
use Pano\Kernel\HttpStatusEnum;
use Src\Foundation\DefaultFoundation;
use Src\Modules\Musician\Services\MusicLibrary;

require dirname(__DIR__) . '/vendor/autoload.php';

define('PANO_STARTED', microtime(true));
$basePath = dirname(__DIR__) . '/';
$foundation = new DefaultFoundation();
new Boot($basePath, $foundation);
// These checks use the supplied cache and never contact the music provider.
$_ENV['#_configs_#']['musician']['refresh'] = false;
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function dispatch(string $uri, string $method = 'GET'): array
{
    global $foundation;

    $_SERVER = ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri, 'HTTP_HOST' => 'tools.test'];
    $_POST = $_FILES = [];
    $request = new Request($_SERVER);
    $class = $foundation::module($request->getModule());
    check($class !== null, 'No module for ' . $uri);
    $module = new $class($request, $foundation);
    $module->setRouter(new Router($request, $module))->importPackages()->setup();

    ob_start();
    try {
        $status = $module->getRouter()->handle();
        return [$status, ob_get_contents(), $request->getModule()];
    } finally {
        ob_end_clean();
    }
}

$apps = [
    'calma' => 'Calma | Multi-instance Calculator',
    'colorfy' => 'Colorfy | Advanced Color Picker',
    'idate' => 'iDate | Universal Date Converter',
    'laralog' => 'Laralog | Laravel Log Viewer',
    'musician' => 'Musician | Music Player',
    'onregex' => 'OnRegex',
    'red-json' => 'Red JSON',
];
$assets = [];
$routes = 0;

foreach ($apps as $slug => $title) {
    foreach (['/' . $slug, '/' . $slug . '/', '/' . $slug . '?source=smoke'] as $uri) {
        [$status, $html, $moduleKey] = dispatch($uri);
        check($status === 200 && $moduleKey === $slug, 'Module dispatch failed for ' . $uri);
        check(str_contains($html, $title), 'Page title missing for ' . $uri);
        check(substr_count($html, '<!DOCTYPE html>') === 1, 'Duplicate document in ' . $uri);
        check(str_contains($html, '</html>') && !str_contains($html, '<?php'), 'Incomplete rendering for ' . $uri);
        check(!preg_match('~(?:href|src)=[\'"]assets/~', $html), 'Relative asset URL in ' . $uri);

        preg_match_all('~(?:href|src)=[\'"](/apps/[^\'"<>]+)~', $html, $matches);
        check($matches[1] !== [], 'Missing public assets for ' . $uri);
        foreach ($matches[1] as $asset) {
            $file = $basePath . 'public' . parse_url($asset, PHP_URL_PATH);
            check(is_file($file), 'Missing asset ' . $asset);
            $assets[$file] = true;
        }

        if ($slug === 'idate') {
            preg_match('~id="server-utc-time" value="([^"]+)"~', $html, $time);
            check(isset($time[1]) && abs(strtotime($time[1] . ' UTC') - time()) < 5, 'Invalid UTC server time');
        }
        if ($slug === 'musician') {
            $library = (new MusicLibrary($basePath . 'src/Modules/Musician/Files'))->read();
            foreach ($library as $variable => $expected) {
                preg_match('~var ' . $variable . ' = (.*);~', $html, $json);
                check(isset($json[1]), 'Missing music library ' . $variable);
                check(json_decode($json[1], true, 512, JSON_THROW_ON_ERROR) === $expected, 'Music cache was not passed to the view');
            }
        }
        $routes++;
    }

    foreach (['/' . $slug . '/missing', '/' . $slug . '/index.php'] as $uri) {
        try {
            dispatch($uri);
            throw new RuntimeException('Unexpected success for ' . $uri);
        } catch (BaseException $exception) {
            check($exception->status() === HttpStatusEnum::NOT_FOUND, 'Wrong missing-route status');
        }
    }
}

foreach (array_keys($assets) as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) !== 'css') {
        continue;
    }
    preg_match_all('~url\([\'"]?([^\s\)\'"]+)~', file_get_contents($file), $dependencies);
    foreach ($dependencies[1] as $dependency) {
        if (preg_match('~^(?:data:|https?:|//)~', $dependency)) {
            continue;
        }
        check(is_file(dirname($file) . '/' . parse_url($dependency, PHP_URL_PATH)), 'Missing CSS dependency ' . $dependency);
    }
}

[$status, $html] = dispatch('/');
check($status === 200 && str_contains($html, 'It works!'), 'Default module regressed');
[$status, $json] = dispatch('/musician/info', 'POST');
check($status === 400 && isset(json_decode($json, true)['error']), 'Metadata URL validation failed');
check((new MusicLibrary(sys_get_temp_dir() . '/tolio-missing-' . uniqid()))->read() === [
    'defaultSongs' => [], 'defaultCategories' => [], 'defaultPlaylists' => [],
], 'Missing-cache fallback failed');

restore_error_handler();
echo 'Passed: ' . $routes . ' app requests, ' . count($assets) . ' assets, CSS dependencies, UTC time, music data, missing routes, metadata validation, and Default module.' . PHP_EOL;
