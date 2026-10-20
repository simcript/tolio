<?php

function getAudioMetadata($url)
{
    $result = [
        'title' => null,
        'artist' => null,
        'album' => null,
        'year' => null,
        'publisher' => null,
        'cover' => null,
        'error' => null,
    ];

    // فقط 128KB اول فایل رو دانلود میکنیم
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Range: bytes=0-131071\r\n",
            'timeout' => 10,
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
        ],
    ]);

    $data = @file_get_contents($url, false, $context, 0, 131072);

    if ($data === false) {
        $result['error'] = 'Cannot fetch URL';
        return $result;
    }

    // ── ID3v2 ──────────────────────────────────────────────
    if (substr($data, 0, 3) === 'ID3') {
        $result = array_merge($result, parseID3v2($data));
    }

    // ── ID3v1 (fallback — آخر فایل) ───────────────────────
    if (isAllNull($result)) {
        $tail = fetchTail($url, 128);
        if ($tail && substr($tail, 0, 3) === 'TAG') {
            $result = array_merge($result, parseID3v1($tail));
        }
    }

    return $result;
}


// ═══════════════════════════════════════════════
//  ID3v2 Parser
// ═══════════════════════════════════════════════
function parseID3v2($data)
{
    $out = [
        'title' => null,
        'artist' => null,
        'album' => null,
        'year' => null,
        'publisher' => null,
        'cover' => null,
    ];

    $version = ord($data[3]);  // 2, 3 یا 4
    $flags = ord($data[5]);
    $hasExtHeader = ($flags & 0x40) !== 0;

    // محاسبه طول header (syncsafe integer)
    $tagSize = syncsafeToInt(substr($data, 6, 4));

    $offset = 10;

    // skip extended header اگه وجود داشت
    if ($hasExtHeader) {
        $extSize = $version === 4
            ? syncsafeToInt(substr($data, 10, 4))
            : unpack('N', substr($data, 10, 4))[1];
        $offset += $extSize;
    }

    $end = min(10 + $tagSize, strlen($data));

    // Frame ID ها بر اساس ورژن
    $frameMap = $version === 2
        ? ['TT2' => 'title', 'TP1' => 'artist', 'TAL' => 'album',
            'TYE' => 'year', 'TPB' => 'publisher', 'PIC' => 'cover']
        : ['TIT2' => 'title', 'TPE1' => 'artist', 'TALB' => 'album',
            'TYER' => 'year', 'TDRC' => 'year', 'TPUB' => 'publisher',
            'APIC' => 'cover'];

    $idLen = $version === 2 ? 3 : 4;
    $sizeLen = $version === 2 ? 3 : 4;

    while ($offset + $idLen + $sizeLen < $end) {
        $frameId = substr($data, $offset, $idLen);

        // پایان frames
        if ($frameId === "\x00\x00\x00\x00" || $frameId === "\x00\x00\x00") {
            break;
        }

        // frame size
        if ($version === 2) {
            $frameSize = unpack('N', "\x00" . substr($data, $offset + 3, 3))[1];
        } elseif ($version === 4) {
            $frameSize = syncsafeToInt(substr($data, $offset + 4, 4));
        } else {
            $frameSize = unpack('N', substr($data, $offset + 4, 4))[1];
        }

        if ($frameSize <= 0 || $offset + $idLen + $sizeLen + $frameSize > strlen($data)) {
            break;
        }

        $frameFlags = $version === 2 ? '' : substr($data, $offset + 8, 2);
        $headerTotal = $version === 2 ? 6 : 10;
        $frameData = substr($data, $offset + $headerTotal, $frameSize);

        if (isset($frameMap[$frameId])) {
            $field = $frameMap[$frameId];

            if ($field === 'cover') {
                $out['cover'] = parseCoverFrame($frameData, $version);
            } else {
                $out[$field] = decodeTextFrame($frameData);
            }
        }

        $offset += $headerTotal + $frameSize;
    }

    return $out;
}


// ═══════════════════════════════════════════════
//  Cover frame (APIC / PIC)
// ═══════════════════════════════════════════════
function parseCoverFrame($data, $version)
{
    if (strlen($data) < 4) return null;

    $encoding = ord($data[0]);
    $pos = 1;

    if ($version === 2) {
        // PIC: 3 bytes image format
        $format = strtolower(substr($data, $pos, 3));
        $mime = $format === 'jpg' ? 'image/jpeg' : 'image/' . $format;
        $pos += 3;
    } else {
        // APIC: null-terminated MIME
        $nullPos = strpos($data, "\x00", $pos);
        if ($nullPos === false) return null;
        $mime = substr($data, $pos, $nullPos - $pos);
        $pos = $nullPos + 1;
    }

    // picture type (1 byte)
    $pos++;

    // description (null-terminated, encoding-aware)
    $nullSeq = ($encoding === 1 || $encoding === 2) ? "\x00\x00" : "\x00";
    $step = ($encoding === 1 || $encoding === 2) ? 2 : 1;

    while ($pos + $step <= strlen($data)) {
        if (substr($data, $pos, $step) === $nullSeq) {
            $pos += $step;
            break;
        }
        $pos += $step;
    }

    $imageData = substr($data, $pos);
    if (strlen($imageData) < 10) return null;

    return 'data:' . $mime . ';base64,' . base64_encode($imageData);
}


// ═══════════════════════════════════════════════
//  ID3v1 Parser (128 byte tail)
// ═══════════════════════════════════════════════
function parseID3v1($tag)
{
    return [
        'title' => trimNull(substr($tag, 3, 30)),
        'artist' => trimNull(substr($tag, 33, 30)),
        'album' => trimNull(substr($tag, 63, 30)),
        'year' => trimNull(substr($tag, 93, 4)),
        'publisher' => date('Y'),
        'cover' => null,
    ];
}


// ═══════════════════════════════════════════════
//  Helpers
// ═══════════════════════════════════════════════
function syncsafeToInt($bytes)
{
    $result = 0;
    foreach (str_split($bytes) as $byte) {
        $result = ($result << 7) | (ord($byte) & 0x7F);
    }
    return $result;
}

function decodeTextFrame($data)
{
    if ($data === '') return null;

    $encoding = ord($data[0]);
    $text = substr($data, 1);
    // null terminator temizle
    $text = rtrim($text, "\x00");

    switch ($encoding) {
        case 0: // ISO-8859-1
            return mb_convert_encoding($text, 'UTF-8', 'ISO-8859-1') ?: $text;
        case 1: // UTF-16 with BOM
        case 2: // UTF-16 BE
            return mb_convert_encoding($text, 'UTF-8', 'UTF-16') ?: $text;
        case 3: // UTF-8
        default:
            return $text;
    }
}

function trimNull($str)
{
    $str = trim(rtrim($str, "\x00"));
    return $str !== '' ? $str : null;
}

function isAllNull(array $result)
{
    foreach (['title', 'artist', 'album', 'year', 'publisher', 'cover'] as $k) {
        if (!empty($result[$k])) return false;
    }
    return true;
}

function fetchTail($url, $bytes)
{
    // HEAD برای گرفتن Content-Length
    $context = stream_context_create([
        'http' => ['method' => 'HEAD', 'timeout' => 5],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    $headers = @get_headers($url, 1, $context);
    if (!$headers) return null;

    $length = $headers['Content-Length'] ?? null;
    if (!$length) return null;

    $from = (int)$length - $bytes;
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Range: bytes={$from}-\r\n",
            'timeout' => 5,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);

    return @file_get_contents($url, false, $context) ?: null;
}


$meta = getAudioMetadata($_POST['url']);
echo json_encode($meta);