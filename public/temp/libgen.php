<?php
/**
 * libgen.php
 *
 * Web page that crawls Library Genesis (libgen.li) search results and renders
 * them natively as HTML — including cover images.
 *
 *   libgen.php                        -> search page (empty)
 *   libgen.php?req=python            -> results for "python"
 *   libgen.php?req=python&page=2     -> page 2
 *   libgen.php?req=python&format=json-> raw JSON
 *
 * When this file is `require`d from another PHP script (e.g. Laravel's
 * AssistantController), define LIBGEN_LIBRARY_ONLY beforehand. Only the
 * function definitions will load — no input parsing, no output. The
 * caller then invokes crawl_libgen() directly.
 */

if (!defined('LIBGEN_LIBRARY_ONLY')) {
    // ---- Input -----------------------------------------------------------------
    $req    = isset($_GET['req'])  ? trim($_GET['req'])  : '';
    $page   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'html';
    $base   = 'https://libgen.li';
}

// ---- Helpers ---------------------------------------------------------------
if (!function_exists('clean_text')) {
    function clean_text($node)
    {
        if ($node === null) {
            return '';
        }
        $text = $node instanceof DOMNode ? $node->textContent : (string) $node;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }
}

if (!function_exists('abs_url')) {
    function abs_url($href, $base)
    {
        $href = trim($href);
        if ($href === '') {
            return '';
        }
        if (strpos($href, '//') === 0) {
            return 'https:' . $href;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        return rtrim($base, '/') . '/' . ltrim($href, '/');
    }
}

// ---- Fetch -----------------------------------------------------------------
if (!function_exists('fetch_libgen_url')) {
    function fetch_libgen_url($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_COOKIE         => 'covers=on',
                CURLOPT_COOKIEFILE     => '', // Enable in-memory cookie engine
                CURLOPT_ENCODING       => '', // Accept gzip/deflate
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                                        . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                                        . 'Chrome/126.0 Safari/537.36',
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                ],
            ]);
            $body = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($body === false || $code < 200 || $code >= 400) {
                return [null, $code, $err ?: "HTTP $code"];
            }
            return [$body, $code, null];
        }

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 30,
                'header'  => "User-Agent: Mozilla/5.0\r\nAccept-Language: en-US,en;q=0.9\r\nCookie: covers=on\r\n",
            ],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return [null, 0, 'file_get_contents failed'];
        }
        return [$body, 200, null];
    }
}

/** Crawl Library Genesis for $req / $page. Returns [results[], status, error, url]. */
function crawl_libgen($req, $page, $base)
{
    $base = rtrim($base, '/');
    $url  = $base . '/index.php?req=' . rawurlencode($req) . '&covers=on&res=25';
    if ($page > 1) {
        $url .= '&page=' . (int) $page;
    }

    list($html, $status, $error) = fetch_libgen_url($url);
    if ($html === null) {
        return [[], $status, $error, $url];
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xp = new DOMXPath($doc);
    $rows = $xp->query('//table[contains(@class,"table")]//tr');
    $results = [];

    // Row 0 is the table header
    for ($i = 1; $i < $rows->length; $i++) {
        $tr = $rows->item($i);
        $tds = $xp->query('./td', $tr);
        if ($tds->length < 9) {
            continue;
        }

        // Check if the first column is the cover image
        $hasCoverCol = false;
        $cover = '';
        $img = $xp->query('.//img', $tds->item(0))->item(0);
        if ($img) {
            $src = $img->getAttribute('src');
            if ($src !== '' && stripos($src, 'logo.png') === false) {
                $hasCoverCol = true;
                $cover = abs_url($src, $base);
            }
        }

        $offset = $hasCoverCol ? 1 : 0;
        $titleTd     = $tds->item($offset);
        $authorTd    = $tds->item($offset + 1);
        $publisherTd = $tds->item($offset + 2);
        $yearTd      = $tds->item($offset + 3);
        $languageTd  = $tds->item($offset + 4);
        $pagesTd     = $tds->item($offset + 5);
        $sizeTd      = $tds->item($offset + 6);
        $extTd       = $tds->item($offset + 7);
        $mirrorsTd   = $tds->item($offset + 8);

        // Title: extract from edition.php link, or the primary text block
        $titleAnchor = $xp->query('.//a[contains(@href,"edition.php") or contains(@href,"book/")]', $titleTd)->item(0);
        $title = $titleAnchor ? clean_text($titleAnchor) : '';
        if ($title === '') {
            $firstB = $xp->query('.//b', $titleTd)->item(0);
            $title = $firstB ? clean_text($firstB) : clean_text($titleTd);
        }

        // Metadata
        $author    = clean_text($authorTd);
        $publisher = clean_text($publisherTd);
        $year      = clean_text($yearTd);
        $language  = clean_text($languageTd);
        $size      = clean_text($sizeTd);
        $ext       = strtolower(clean_text($extTd));

        // MD5: extract from mirror links
        $md5 = '';
        $detailUrl = '';
        $downloadUrl = '';
        if ($mirrorsTd) {
            $mirrorAnchors = $xp->query('.//a', $mirrorsTd);
            foreach ($mirrorAnchors as $ma) {
                $href = $ma->getAttribute('href');
                if (preg_match('/[?&]md5=([a-f0-9]{32})/i', $href, $m)) {
                    $md5 = strtolower($m[1]);
                    $detailUrl = $base . '/ads.php?md5=' . $md5;
                    $downloadUrl = $base . '/get.php?md5=' . $md5;
                    break;
                } elseif (preg_match('#/([a-f0-9]{32})#i', $href, $m)) {
                    $md5 = strtolower($m[1]);
                    $detailUrl = $base . '/ads.php?md5=' . $md5;
                    $downloadUrl = $base . '/get.php?md5=' . $md5;
                    break;
                }
            }
        }

        if ($title === '' || $md5 === '') {
            continue;
        }

        $results[] = [
            'title'     => $title,
            'url'       => $detailUrl,
            'download'  => $downloadUrl,
            'cover'     => $cover,
            'author'    => $author,
            'publisher' => $publisher,
            'language'  => $language,
            'extension' => $ext,
            'size'      => $size,
            'year'      => $year,
            'type'      => 'Book',
            'md5'       => $md5,
        ];
    }

    return [$results, $status, null, $url];
}

if (!defined('LIBGEN_LIBRARY_ONLY')) {
    // ---- Run --------------------------------------------------------------------
    $results = [];
    $status  = null;
    $error   = null;
    $source  = '';

    if ($req !== '') {
        list($results, $status, $error, $source) = crawl_libgen($req, $page, $base);
    }

    // ---- JSON mode --------------------------------------------------------------
    if ($format === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode([
            'ok'      => $error === null,
            'query'   => $req,
            'page'    => $page,
            'source'  => $source,
            'error'   => $error,
            'count'   => count($results),
            'results' => $results,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Guarded so we don't collide with Laravel's e() helper
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

// In library mode stop before HTML output
if (defined('LIBGEN_LIBRARY_ONLY')) return;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Library Genesis Search<?php echo $req !== '' ? ' — ' . e($req) : ''; ?></title>
<style>
    :root {
        --bg: #0f1115; --panel: #181b22; --panel-2: #1f2530; --line: #2a313d;
        --text: #e6e9ef; --muted: #9aa4b2; --accent: #4f8cff; --accent-2: #2bd4a8;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        background: var(--bg); color: var(--text); line-height: 1.5;
    }
    header {
        position: sticky; top: 0; z-index: 10; background: rgba(15,17,21,.9);
        backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); padding: 18px 20px;
    }
    .wrap { max-width: 980px; margin: 0 auto; }
    .brand { font-weight: 700; font-size: 1.25rem; letter-spacing: .3px; margin: 0 0 12px; }
    .brand span { color: var(--accent); }
    form.search { display: flex; gap: 10px; }
    form.search input[type=text] {
        flex: 1; padding: 12px 16px; border: 1px solid var(--line); border-radius: 10px;
        background: var(--panel); color: var(--text); font-size: 1rem; outline: none;
    }
    form.search input[type=text]:focus { border-color: var(--accent); }
    form.search button {
        padding: 12px 22px; border: none; border-radius: 10px; background: var(--accent);
        color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer;
    }
    form.search button:hover { background: #3f7af0; }
    main { max-width: 980px; margin: 0 auto; padding: 24px 20px 60px; }
    .meta { color: var(--muted); font-size: .9rem; margin-bottom: 18px; }
    .card {
        display: flex; gap: 16px; background: var(--panel); border: 1px solid var(--line);
        border-radius: 12px; padding: 16px 18px; margin-bottom: 14px;
    }
    .cover {
        flex: 0 0 96px; width: 96px; height: 144px; border-radius: 8px; overflow: hidden;
        background: var(--panel-2); display: flex; align-items: center; justify-content: center;
        border: 1px solid var(--line);
    }
    .cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cover .noimg { color: var(--muted); font-size: .72rem; text-align: center; padding: 6px; }
    .details { flex: 1; min-width: 0; }
    .details h3 { margin: 0 0 6px; font-size: 1.05rem; }
    .details h3 a { color: var(--text); text-decoration: none; }
    .details h3 a:hover { color: var(--accent); }
    .byline { color: var(--muted); font-size: .9rem; margin-bottom: 10px; }
    .tags { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .tag {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; padding: 3px 8px;
        border-radius: 6px; background: var(--panel-2); color: var(--accent-2); border: 1px solid var(--line);
    }
    .dl {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; padding: 3px 10px;
        border-radius: 6px; background: var(--accent); color: #fff; text-decoration: none;
    }
    .dl:hover { background: #3f7af0; }
    .empty, .error { text-align: center; padding: 60px 20px; color: var(--muted); }
    .error { color: #ff7a7a; }
</style>
</head>
<body>
<header>
    <div class="wrap">
        <h1 class="brand">📚 Library <span>Genesis</span> Search</h1>
        <form class="search" method="get" action="">
            <input type="text" name="req" value="<?php echo e($req); ?>"
                   placeholder="Search books, authors, titles…" autofocus>
            <button type="submit">Search</button>
        </form>
    </div>
</header>

<main>
    <?php if ($error !== null): ?>
        <div class="error">An error occurred while searching: <?php echo e($error); ?></div>
    <?php elseif ($req === ''): ?>
        <div class="empty">Type a query above to search Library Genesis (with cover images).</div>
    <?php elseif (empty($results)): ?>
        <div class="empty">No results found for "<?php echo e($req); ?>".</div>
    <?php else: ?>
        <div class="meta">Found <?php echo count($results); ?> result<?php echo count($results) === 1 ? '' : 's'; ?> for "<?php echo e($req); ?>":</div>
        <?php foreach ($results as $r): ?>
            <div class="card">
                <div class="cover">
                    <?php if (!empty($r['cover'])): ?>
                        <img src="<?php echo e($r['cover']); ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <div class="noimg">No cover</div>
                    <?php endif; ?>
                </div>
                <div class="details">
                    <h3><a href="<?php echo e($r['url']); ?>" target="_blank" rel="noopener"><?php echo e($r['title']); ?></a></h3>
                    <div class="byline">
                        <?php if (!empty($r['author'])): ?>by <?php echo e($r['author']); ?><?php endif; ?>
                        <?php if (!empty($r['publisher'])): ?> &middot; <?php echo e($r['publisher']); ?><?php endif; ?>
                    </div>
                    <div class="tags">
                        <?php if (!empty($r['year'])): ?><span class="tag">📅 <?php echo e($r['year']); ?></span><?php endif; ?>
                        <?php if (!empty($r['language'])): ?><span class="tag">🌐 <?php echo e($r['language']); ?></span><?php endif; ?>
                        <?php if (!empty($r['extension'])): ?><span class="tag"><?php echo e($r['extension']); ?></span><?php endif; ?>
                        <?php if (!empty($r['size'])): ?><span class="tag"><?php echo e($r['size']); ?></span><?php endif; ?>
                        <?php if (!empty($r['download'])): ?>
                            <a class="dl" href="<?php echo e($r['download']); ?>" target="_blank" rel="noopener">Download</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</main>
</body>
</html>
