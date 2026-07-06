<?php
/**
 * annas.php
 *
 * Web page that crawls Anna's Archive (annas-archive.gl) search results
 * and renders them natively as HTML — including cover images.
 *
 *   annas.php                       -> search page (empty)
 *   annas.php?req=python            -> results for "python"
 *   annas.php?req=python&page=2     -> page 2
 *   annas.php?req=python&format=json-> raw JSON
 *
 * When this file is `require`d from another PHP script (e.g. Laravel's
 * AssistantController), define ANNAS_LIBRARY_ONLY beforehand. Only the
 * function definitions will load — no input parsing, no output. The
 * caller then invokes crawl_annas() / fetch_url() / parse_meta_line() etc.
 * directly, which avoids a self-HTTP roundtrip (blocked on many shared
 * hosts) and any duplicate output.
 */

if (!defined('ANNAS_LIBRARY_ONLY')) {
    // ---- Input -----------------------------------------------------------------
    $req    = isset($_GET['req'])  ? trim($_GET['req'])  : '';
    $page   = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $format = isset($_GET['format']) ? strtolower($_GET['format']) : 'html';
    $base   = 'https://annas-archive.gl';
}

// ---- Fetch -----------------------------------------------------------------
function fetch_url($url)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 40,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_ENCODING       => '', // accept gzip/deflate
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                                    . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                                    . 'Chrome/124.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept-Language: en-US,en;q=0.9'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        if ($body === false) {
            return [null, 0, $err];
        }
        return [$body, $code, null];
    }

    $ctx = stream_context_create([
        'http' => [
            'timeout' => 40,
            'header'  => "User-Agent: Mozilla/5.0\r\nAccept-Language: en-US,en;q=0.9\r\n",
        ],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return [null, 0, 'file_get_contents failed'];
    }
    return [$body, 200, null];
}

// ---- Helpers ---------------------------------------------------------------
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

/**
 * Anna's Archive packs a result's facts into one "·"-separated line, e.g.
 *   "✅ English [en] · PDF · 6.4MB · 2016 · 📘 Book (non-fiction) · 🚀/lgli/zlib"
 * Pull language / extension / size / year / type out of it.
 */
function parse_meta_line($line)
{
    $out = ['language' => '', 'extension' => '', 'size' => '', 'year' => '', 'type' => ''];
    $parts = array_map('trim', explode('·', $line));
    foreach ($parts as $i => $p) {
        $p = trim(preg_replace('/^[✅🚀📘📗📕📙📓📔🟢🔵⭐]+/u', '', $p));
        if ($p === '') {
            continue;
        }
        if ($out['size'] === '' && preg_match('/^[\d.]+\s*[KMGT]?B$/i', $p)) {
            $out['size'] = $p;
        } elseif ($out['year'] === '' && preg_match('/^(1[5-9]\d{2}|20\d{2})$/', $p)) {
            $out['year'] = $p;
        } elseif ($out['extension'] === '' && preg_match('/^[a-z0-9]{2,5}$/i', $p) && !ctype_digit($p)) {
            $out['extension'] = strtolower($p);
        } elseif ($i === 0 && $out['language'] === '') {
            $out['language'] = $p;
        } elseif (stripos($p, 'book') !== false || stripos($p, 'comic') !== false
               || stripos($p, 'magazine') !== false || stripos($p, 'article') !== false) {
            $out['type'] = $p;
        }
    }
    return $out;
}

/** Crawl Anna's Archive for $req / $page. Returns [results[], status, error, url]. */
function crawl_annas($req, $page, $base)
{
    $url = $base . '/search?q=' . rawurlencode($req) . '&page=' . $page;
    list($html, $status, $error) = fetch_url($url);

    if ($html === null) {
        return [[], $status, $error, $url];
    }

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
    libxml_clear_errors();

    $xp = new DOMXPath($doc);

    // Title links carry the class "js-vim-focus"; each anchors one result row.
    $titleLinks = $xp->query('//a[contains(@class,"js-vim-focus") and starts-with(@href,"/md5/")]');
    $results = [];

    foreach ($titleLinks as $titleA) {
        $title = clean_text($titleA);
        $href  = $titleA->getAttribute('href');
        $md5   = '';
        if (preg_match('#/md5/([a-f0-9]{32})#i', $href, $m)) {
            $md5 = strtolower($m[1]);
        }

        // The enclosing result row (nearest ancestor div with border-b).
        $row = $xp->query('ancestor::div[contains(@class,"border-b")][1]', $titleA)->item(0);
        if (!$row) {
            $row = $titleA->parentNode;
        }

        // Cover image.
        $cover = '';
        $img = $xp->query('.//img', $row)->item(0);
        if ($img) {
            $cover = abs_url($img->getAttribute('src'), $base);
        }

        // Author / publisher: anchors that link back into search.
        $author = '';
        $publisher = '';
        foreach ($xp->query('.//a[contains(@href,"/search?q=")]', $row) as $a) {
            $txt = clean_text($a);
            if ($txt === '') {
                continue;
            }
            $icon = $xp->query('.//span[contains(@class,"icon-")]', $a)->item(0);
            $iconClass = $icon ? $icon->getAttribute('class') : '';
            if (strpos($iconClass, 'user-edit') !== false && $author === '') {
                $author = $txt;
            } elseif (strpos($iconClass, 'company') !== false && $publisher === '') {
                $publisher = $txt;
            } elseif ($author === '') {
                $author = $txt;
            }
        }

        // Metadata line (leading text node of the gray-800 facts div).
        $metaLine = '';
        $metaDiv = $xp->query('.//div[contains(@class,"text-gray-800")]', $row)->item(0);
        if ($metaDiv) {
            foreach ($metaDiv->childNodes as $cn) {
                if ($cn->nodeType === XML_TEXT_NODE) {
                    $metaLine .= $cn->nodeValue;
                }
            }
            $metaLine = clean_text($metaLine);
        }
        $meta = parse_meta_line($metaLine);

        if ($title === '') {
            continue;
        }

        $results[] = [
            'title'     => $title,
            'url'       => abs_url($href, $base),
            'cover'     => $cover,
            'author'    => $author,
            'publisher' => $publisher,
            'language'  => $meta['language'],
            'extension' => $meta['extension'],
            'size'      => $meta['size'],
            'year'      => $meta['year'],
            'type'      => $meta['type'],
            'md5'       => $md5,
        ];
    }

    return [$results, $status, null, $url];
}

if (!defined('ANNAS_LIBRARY_ONLY')) {
    // ---- Run --------------------------------------------------------------------
    $results = [];
    $status  = null;
    $error   = null;
    $source  = '';

    if ($req !== '') {
        list($results, $status, $error, $source) = crawl_annas($req, $page, $base);
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

// Guarded so we don't collide with Laravel's e() helper when this file is
// loaded as a library inside the framework.
if (!function_exists('e')) {
    function e($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
    }
}

// In library mode the caller only wants the function definitions — stop
// before the HTML template below would otherwise be echoed.
if (defined('ANNAS_LIBRARY_ONLY')) return;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Anna's Archive Search<?php echo $req !== '' ? ' — ' . e($req) : ''; ?></title>
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
    .tags { display: flex; gap: 8px; flex-wrap: wrap; }
    .tag {
        font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; padding: 3px 8px;
        border-radius: 6px; background: var(--panel-2); color: var(--accent-2); border: 1px solid var(--line);
    }
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
        <h1 class="brand">🗃️ Anna's <span>Archive</span> Search</h1>
        <form class="search" method="get" action="">
            <input type="text" name="req" value="<?php echo e($req); ?>"
                   placeholder="Search books, authors, titles…" autofocus>
            <button type="submit">Search</button>
        </form>
    </div>
</header>

<main>
<?php if ($req === ''): ?>
    <div class="empty">Type a query above to search Anna's Archive (with cover images).</div>

<?php elseif ($error !== null): ?>
    <div class="error">Failed to fetch Anna's Archive: <?php echo e($error); ?></div>

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
                    <?php if ($r['publisher'] !== ''): ?> · <?php echo e($r['publisher']); ?><?php endif; ?>
                </div>
                <div class="tags">
                    <?php if ($r['extension'] !== ''): ?><span class="tag"><?php echo e($r['extension']); ?></span><?php endif; ?>
                    <?php if ($r['size'] !== ''): ?><span class="tag"><?php echo e($r['size']); ?></span><?php endif; ?>
                    <?php if ($r['year'] !== ''): ?><span class="tag">📅 <?php echo e($r['year']); ?></span><?php endif; ?>
                    <?php if ($r['language'] !== ''): ?><span class="tag">🌐 <?php echo e($r['language']); ?></span><?php endif; ?>
                    <?php if ($r['type'] !== ''): ?><span class="tag"><?php echo e($r['type']); ?></span><?php endif; ?>
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
