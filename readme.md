# Tolio Tools
## Developer Utility Suite
### Free & Open-Source Tools for Developers

The apps run as native Pano modules, registered in
`src/Foundation/DefaultFoundation.php`. Point the web server's document root
at `public/` and install dependencies with `composer install`.

| App | URL | Module |
| --- | --- | --- |
| Calma | `/calma` | `src/Modules/Calma` |
| Colorfy | `/colorfy` | `src/Modules/Colorfy` |
| iDate | `/idate` | `src/Modules/Idate` |
| LaraLog | `/laralog` | `src/Modules/Laralog` |
| Musician | `/musician` | `src/Modules/Musician` |
| OnRegex | `/onregex` | `src/Modules/Onregex` |
| Red JSON | `/red-json` | `src/Modules/RedJson` |

Each module registers its routes in `setup()`, renders `Views/home.php` through
`Views/layout.php`, and returns a Pano response from its handler. The original
styles, scripts, icons, and fonts remain in `public/apps/<app>/assets/`.
The Default module still serves `/`. Apache redirects the old `/apps/<app>/`
and `/apps/<app>/index.html` or `index.php` bookmarks to the module URL.

Idate's handler supplies the current UTC server time. Musician loads its existing
JSON caches from the private `src/Modules/Musician/Files/` directory and refreshes
them through `Services/MusicLibrary.php`. PHP cURL and write access to that cache
directory are needed for provider refreshes; cached playback remains available
without cURL. Provider settings and their existing defaults are in
`config/musician.php`; `MUSICIAN_REFRESH`, `MUSICIAN_API_URL`, and
`MUSICIAN_API_TOKEN` can override them in `.env`. The existing audio metadata
helper is available through `POST /musician/info` with a `url` form field.

Run the app integration checks without contacting the music provider:

```bash
php tests/apps-smoke.php
```

These checks exercise Pano module resolution and routing, rendered pages, public
assets and font references, server time, cached music data, and the Default page.
