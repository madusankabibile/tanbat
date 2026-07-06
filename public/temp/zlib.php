<?php
/**
 * zlib.php
 *
 * Web page that crawls Z-Library (z-library.sk) search results and renders
 * them natively as HTML — including cover images.
 *
 *   zlib.php                        -> search page (empty)
 *   zlib.php?req=sample book        -> results for "sample book"
 *   zlib.php?req=sample book&page=2 -> page 2
 *   zlib.php?req=sample book&format=json -> raw JSON
 *
 * Z-Library sits behind a JavaScript "Checking your browser…" proof-of-work
 * wall. The first response is a 503 challenge page that embeds a token `c`;
 * the browser must find an integer `i` such that the SHA-1 bytes of (c.i)
 * satisfy a fixed pattern, then resubmit with a `c_token` cookie. This script
 * replicates that proof-of-work in PHP so it can fetch results server-side.
 *
 * When this file is `require`d from another PHP script (e.g. Laravel's
 * AssistantController), define ZLIB_LIBRARY_ONLY beforehand. Only the function
 * definitions load — no input parsing, no output. The caller then invokes
 * crawl_zlib() directly, passing the configured base domain. This mirrors
 * annas.php's ANNAS_LIBRARY_ONLY contract.
 */

if (!defined('ZLIB_LIBRARY_ONLY')) {
    // ---- Input -----------------------------------------------------------------
    $req    = isset($_GET['req'])  ? trim($_GET['req'])  : '';
    $page   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'html';
    $base   = 'https://z-library.sk';
}

// ---- HTTP ------------------------------------------------------------------
/**
 * Fetch $url, returning [body, httpCode, error, setCookies[]].
 * $cookies is a "name=value; name2=value2" string sent with the request.
 */
function http_get($url, $cookies = '')
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_ENCODING       => '', // accept gzip/deflate
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                                . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                                . 'Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
    ]);
    if ($cookies !== '') {
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return [null, 0, $err, []];
    }
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $head  = substr($raw, 0, $hsize);
    $body  = substr($raw, $hsize);
    curl_close($ch);

    $set = [];
    if (preg_match_all('/^Set-Cookie:\s*([^=]+)=([^;\r\n]*)/im', $head, $m)) {
        foreach ($m[1] as $k => $name) {
            $set[trim($name)] = trim($m[2][$k]);
        }
    }
    return [$body, $code, null, $set];
}

/**
 * Detect the proof-of-work challenge and return its token `c`, or '' if the
 * page is already real content. The token lives in an obfuscated array, e.g.
 *   a0_0x2a54=['BDE4...BDB7','c_token=','array']
 */
function challenge_token($html)
{
    if (stripos($html, 'checking your browser') === false
        && stripos($html, 'c_token') === false) {
        return '';
    }
    if (preg_match("/=\\s*\\[\\s*'([0-9A-Fa-f]{32,})'/", $html, $m)) {
        return $m[1];
    }
    return '';
}

/**
 * Solve Z-Library's proof-of-work: find the smallest i >= 0 such that the raw
 * SHA-1 bytes of (c . i) have byte[n1] == 0xB0 and byte[n1+1] == 0x0B, where
 * n1 is the value of the first hex nibble of c. Returns the cookie value c.i
 * (or null if not solved within the cap).
 */
function solve_pow($c, $cap = 8000000)
{
    $n1 = hexdec($c[0]);
    for ($i = 0; $i < $cap; $i++) {
        $s = sha1($c . $i, true);
        if (ord($s[$n1]) === 0xB0 && ord($s[$n1 + 1]) === 0x0B) {
            return $c . $i;
        }
    }
    return null;
}

/**
 * Fetch $url, transparently clearing the proof-of-work wall if present.
 * Returns [body, httpCode, error].
 */
function fetch_zlib($url)
{
    list($body, $code, $err, $set) = http_get($url);
    if ($body === null) {
        return [null, $code, $err];
    }

    $c = challenge_token($body);
    if ($c === '') {
        return [$body, $code, null]; // already real content
    }

    $token = solve_pow($c);
    if ($token === null) {
        return [null, $code, 'Could not solve the proof-of-work challenge.'];
    }

    // Re-request with the session cookie plus the solved token.
    $cookies = '';
    foreach ($set as $name => $value) {
        $cookies .= $name . '=' . $value . '; ';
    }
    $cookies .= 'c_token=' . $token . '; c_time=0.3';

    list($body2, $code2, $err2) = http_get($url, $cookies);
    if ($body2 === null) {
        return [null, $code2, $err2];
    }
    if (challenge_token($body2) !== '') {
        return [null, $code2, 'Proof-of-work was rejected by Z-Library.'];
    }
    return [$body2, $code2, null];
}

// ---- Helpers ---------------------------------------------------------------
// Guarded with function_exists so this file can be loaded alongside annas.php
// (which defines identically-named helpers) without a fatal redeclaration.
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
        return $base . '/' . ltrim($href, '/');
    }
}

/** Crawl Z-Library for $req / $page. Returns [results[], status, error, url]. */
function crawl_zlib($req, $page, $base)
{
    $url = $base . '/s/' . rawurlencode($req);
    if ($page > 1) {
        $url .= '?page=' . $page;
    }

    list($html, $status, $error) = fetch_zlib($url);
    if ($html === null) {
        return [[], $status, $error, $url];
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xp = new DOMXPath($doc);

    // Each result is a <z-bookcard> custom element packed with attributes.
    $cards = $xp->query('//*[local-name()="z-bookcard"]');
    $results = [];

    foreach ($cards as $card) {
        $href = $card->getAttribute('href');

        // Title / author live in slotted <div>s; everything else is attributes.
        $titleNode  = $xp->query('.//*[@slot="title"]', $card)->item(0);
        $authorNode = $xp->query('.//*[@slot="author"]', $card)->item(0);
        $title  = clean_text($titleNode);
        $author = clean_text($authorNode);

        if ($title === '') {
            $title = clean_text($card->getAttribute('publisher'));
        }

        // Cover image (lazy-loaded into data-src).
        $cover = '';
        $img = $xp->query('.//img', $card)->item(0);
        if ($img) {
            $src = $img->getAttribute('data-src');
            if ($src === '') {
                $src = $img->getAttribute('src');
            }
            if ($src !== '' && strpos($src, 'cover-not-exists') === false) {
                $cover = abs_url($src, $base);
            }
        }

        if ($title === '' && $href === '') {
            continue;
        }

        $results[] = [
            'id'        => $card->getAttribute('id'),
            'title'     => $title,
            'url'       => abs_url($href, $base),
            'download'  => abs_url($card->getAttribute('download'), $base),
            'cover'     => $cover,
            'author'    => $author,
            'publisher' => clean_text($card->getAttribute('publisher')),
            'language'  => clean_text($card->getAttribute('language')),
            'extension' => strtolower(clean_text($card->getAttribute('extension'))),
            'size'      => clean_text($card->getAttribute('filesize')),
            'year'      => clean_text($card->getAttribute('year')),
            'rating'    => clean_text($card->getAttribute('rating')),
            'isbn'      => clean_text($card->getAttribute('isbn')),
        ];
    }

    return [$results, $status, null, $url];
}

if (!defined('ZLIB_LIBRARY_ONLY')) {
    // ---- Run --------------------------------------------------------------------
    $results = [];
    $status  = null;
    $error   = null;
    $source  = '';

    if ($req !== '') {
        list($results, $status, $error, $source) = crawl_zlib($req, $page, $base);
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

// Guarded so we don't collide with Laravel's e() helper (or annas.php's) when
// this file is loaded as a library inside the framework.
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

// In library mode the caller only wants the function definitions — stop before
// the HTML template below would otherwise be echoed.
if (defined('ZLIB_LIBRARY_ONLY')) return;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Z-Library Search<?php echo $req !== '' ? ' — ' . e($req) : ''; ?></title>
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
    .pager { display: flex; justify-content: center; gap: 12px; margin-top: 24px; }
    .pager a {
        text-decoration: none; padding: 8px 16px; border-radius: 8px; background: var(--panel);
        border: 1px solid var(--line); color: var(--text);
    }
    .pager a:hover { border-color: var(--accent); color: var(--accent); }
</style>
</head>
<body>
<header>
    <div class="wrap">
        <h1 class="brand">📚 Z-<span>Library</span> Search</h1>
        <form class="search" method="get" action="">
            <input type="text" name="req" value="<?php echo e($req); ?>"
                   placeholder="Search books, authors, titles…" autofocus>
            <button type="submit">Search</button>
        </form>
    </div>
</header>

<main>
<?php if ($req === ''): ?>
    <div class="empty">Type a query above to search Z-Library (with cover images).</div>

<?php elseif ($error !== null): ?>
    <div class="error">Failed to fetch Z-Library: <?php echo e($error); ?></div>

<?php elseif (count($results) === 0): ?>
    <div class="empty">No results found for “<?php echo e($req); ?>”.</div>

<?php else: ?>
    <div class="meta">
        <?php echo count($results); ?> result(s) for
        “<strong><?php echo e($req); ?></strong>” — page <?php echo (int) $page; ?>.
        <a href="?req=<?php echo urlencode($req); ?>&page=<?php echo (int)$page; ?>&format=json"
           style="color:var(--accent)">View JSON</a>
    </div>

    <?php foreach ($results as $r): ?>
        <article class="card">
            <div class="cover">
                <?php if ($r['cover'] !== ''): ?>
                    <img src="<?php echo e($r['cover']); ?>" alt="Cover of <?php echo e($r['title']); ?>"
                         referrerpolicy="no-referrer" loading="lazy"
                         onerror="this.style.display='none';this.insertAdjacentHTML('afterend','<span class=&quot;noimg&quot;>No cover</span>')">
                <?php else: ?>
                    <span class="noimg">No cover</span>
                <?php endif; ?>
            </div>
            <div class="details">
                <h3>
                    <a href="<?php echo e($r['url']); ?>" target="_blank" rel="noopener"><?php echo e($r['title']); ?></a>
                </h3>
                <div class="byline">
                    <?php if ($r['author'] !== ''): ?>by <?php echo e($r['author']); ?><?php endif; ?>
                    <?php if ($r['publisher'] !== '' && $r['publisher'] !== $r['author']): ?> · <?php echo e($r['publisher']); ?><?php endif; ?>
                </div>
                <div class="tags">
                    <?php if ($r['extension'] !== ''): ?><span class="tag"><?php echo e($r['extension']); ?></span><?php endif; ?>
                    <?php if ($r['size'] !== ''): ?><span class="tag"><?php echo e($r['size']); ?></span><?php endif; ?>
                    <?php if ($r['year'] !== ''): ?><span class="tag">📅 <?php echo e($r['year']); ?></span><?php endif; ?>
                    <?php if ($r['language'] !== ''): ?><span class="tag">🌐 <?php echo e($r['language']); ?></span><?php endif; ?>
                    <?php if ($r['rating'] !== '' && $r['rating'] !== '0.0'): ?><span class="tag">⭐ <?php echo e($r['rating']); ?></span><?php endif; ?>
                    <?php if ($r['download'] !== ''): ?><a class="dl" href="<?php echo e($r['download']); ?>" target="_blank" rel="noopener">⬇ Download</a><?php endif; ?>
                </div>
            </div>
        </article>
    <?php endforeach; ?>

    <div class="pager">
        <?php if ($page > 1): ?>
            <a href="?req=<?php echo urlencode($req); ?>&page=<?php echo $page - 1; ?>">← Prev</a>
        <?php endif; ?>
        <a href="?req=<?php echo urlencode($req); ?>&page=<?php echo $page + 1; ?>">Next →</a>
    </div>
<?php endif; ?>
</main>
</body>
</html>
