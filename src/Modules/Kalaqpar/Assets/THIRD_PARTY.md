# Asset provenance

All essential resources are bundled and served through the module's allowlisted Pano asset handler. No runtime CDN or external image service is required.

- `bootstrap.rtl.min.css`: Bootstrap 5.3.3 RTL, Bootstrap Authors, MIT. Retrieved from `https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css`. The upstream copyright/license banner is preserved. Upstream full MIT terms are in `Bootstrap-LICENSE.txt`.
- `Vazirmatn-Regular.woff2`: Vazirmatn v33.003 by Saber Rastikerdar and contributors, SIL Open Font License 1.1. Source: `https://github.com/rastikerdar/vazirmatn/tree/v33.003`. Full license: `Vazirmatn-OFL.txt`. No font modifications or renaming.
- `hero.svg` and all 111 `Files/items/*.svg`: original vector artwork authored for this application, licensed under the repository's MIT license. The editable production source is `Database/Content.php`. No stock photos, unrelated emoji, generated bitmap dependencies or third-party species illustrations are used. Birds share an illustration vocabulary with species-specific plumage, crests, bills, necks and tails; insects, animals, fruit and objects have their own relevant shapes. These are stylized illustrations, not zoological identification plates.

Production catalogue: **111 unique Persian names, 39 flying and 72 non-flying**. Categories are private server data. Filenames are the first 24 hex characters of SHA-256 of image bytes; category and name are not embedded in SVG metadata. Public files contain drawing instructions only. The hero is the crow illustration from the catalogue. Never publish `Content.php`, database rows or a categorized manifest.

To intentionally edit production art, edit `Content.php` and regenerate the bundled SVG files using the PHP expression in OPERATIONS.md, review the drawings, then seed with the worker stopped. This is production content tooling, not a fixture or verification helper.

- `icon-192.png`, `icon-512.png`, `icon-maskable-512.png`, `apple-touch-icon.png`: local rasterizations of the existing original `hero.svg`, under the same repository MIT license. Opaque cream background; maskable artwork scaled to 72% and centered inside the 40%-radius safe circle. Generated once using the already available system librsvg/Cairo; no runtime or build dependency added.
