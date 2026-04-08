<?php
// ═══════════════════════════════════════════════════════════════
//  Koleksiyoncum — SEO Blog & Affiliate Yönetici
//  Google İndeksleme Uyumlu · YunoHost PHP · Veritabansız
// ═══════════════════════════════════════════════════════════════

// ── Yapılandırma ────────────────────────────────────────────────
// YunoHost ortamında config.php otomatik oluşturulur ve
// ADMIN_PASS_HASH ile DATA_DIR sabitlerini tanımlar.
// Geliştirme / manuel kurulum için config.php yoksa varsayılanlar kullanılır.
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

// ADMIN_PASS_HASH: bcrypt hash (password_verify ile kontrol edilir)
// Tanımlı değilse varsayılan düz metin şifresiyle uyumlu sahte hash kullan
if (!defined('ADMIN_PASS_HASH')) {
    // Varsayılan şifre: 'koleksiyoncum2026' — Değiştirin!
    define('ADMIN_PASS_HASH', '$2y$10$defaultHashPlaceholderXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');
    define('ADMIN_PASS_LEGACY', 'koleksiyoncum2026');
}

// DATA_DIR: posts.json'ın tutulacağı dizin
// YunoHost'ta /home/yunohost.app/<app>/, manuel kurulumda uygulama dizini
if (!defined('DATA_DIR')) {
    define('DATA_DIR', __DIR__);
}

define('SITE_NAME',   'Koleksiyoncum');
define('SITE_DESC',   'Özenle seçilmiş en iyi ürün incelemeleri ve fırsatlar');
define('SITE_URL',    '');                    // Boş = otomatik algıla
define('DATA_FILE',   rtrim(DATA_DIR, '/') . '/posts.json');
define('PER_PAGE',    12);

// ── Site URL ────────────────────────────────────────────────────
function siteUrl(string $path = ''): string {
    if (SITE_URL) return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/');
    return $proto . '://' . $host . $base . '/' . ltrim($path, '/');
}

// ── Veri Yönetimi ───────────────────────────────────────────────
function loadPosts(): array {
    if (!file_exists(DATA_FILE)) return [];
    $d = json_decode(file_get_contents(DATA_FILE), true);
    return is_array($d) ? $d : [];
}
function savePosts(array $posts): void {
    file_put_contents(DATA_FILE, json_encode(array_values($posts), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function genId(): string { return uniqid('p_', true); }

// ── Slug Üretici ────────────────────────────────────────────────
function slugify(string $text): string {
    $tr = ['ş'=>'s','ı'=>'i','ğ'=>'g','ü'=>'u','ö'=>'o','ç'=>'c',
           'Ş'=>'s','İ'=>'i','Ğ'=>'g','Ü'=>'u','Ö'=>'o','Ç'=>'c'];
    $text = strtr($text, $tr);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}
function uniqueSlug(string $slug, array $posts, string $excludeId = ''): string {
    $existing = array_column(array_filter($posts, fn($p) => $p['id'] !== $excludeId), 'slug');
    $base = $slug; $i = 2;
    while (in_array($slug, $existing)) { $slug = $base . '-' . $i++; }
    return $slug;
}

// ── Mini Markdown Parser ─────────────────────────────────────────
function markdownToHtml(string $text): string {
    $text = htmlspecialchars_decode($text); // admin'den gelen encode'u çöz
    // Başlıklar
    $text = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.+)$/m',  '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.+)$/m',   '<h1>$1</h1>', $text);
    // Kalın & İtalik
    $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.+?)\*/',     '<em>$1</em>', $text);
    // Linkler
    $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank" rel="noopener noreferrer nofollow sponsored">$1</a>', $text);
    // Listeler
    $text = preg_replace('/^\- (.+)$/m', '<li>$1</li>', $text);
    $text = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $text);
    // Paragraflar
    $blocks = preg_split('/\n{2,}/', trim($text));
    $out = '';
    foreach ($blocks as $b) {
        $b = trim($b);
        if (!$b) continue;
        if (preg_match('/^<(h[123]|ul|ol|blockquote)/', $b)) { $out .= $b; }
        else { $out .= '<p>' . nl2br($b) . '</p>'; }
    }
    return $out;
}

function excerpt(string $content, int $len = 160): string {
    $text = strip_tags(markdownToHtml($content));
    $text = preg_replace('/\s+/', ' ', trim($text));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
}

// ── Session & Auth ───────────────────────────────────────────────
session_start();
$isAdmin = !empty($_SESSION['admin']);

// ── Kategoriler & Platformlar ────────────────────────────────────
$CATS = ['Ev & Mutfak','Moda','Elektronik','Sağlık & Güzellik','Anne & Bebek','Spor','Kitap','Diğer'];
$PLTS = ['Amazon','Trendyol','Hepsiburada','N11','Çiçeksepeti','Diğer'];
$PLT_COLORS = ['Amazon'=>'#FF9900','Trendyol'=>'#F27A1A','Hepsiburada'=>'#FF6000',
               'N11'=>'#6D3FD1','Çiçeksepeti'=>'#E91E8C','Diğer'=>'#6B7280'];

// ── Router ───────────────────────────────────────────────────────
$action  = $_POST['action'] ?? $_GET['action'] ?? '';
$section = $_GET['section'] ?? '';
$slug    = $_GET['p']       ?? '';
$catSlug = $_GET['cat']     ?? '';
$page    = max(1, intval($_GET['pg'] ?? 1));

// Sitemap
if (isset($_GET['sitemap'])) { outputSitemap(); exit; }
// Robots
if (isset($_GET['robots'])) { outputRobots(); exit; }

// ── Action Handler ───────────────────────────────────────────────
$flashMsg = '';

if ($action === 'login') {
    $pw = $_POST['pw'] ?? '';
    // YunoHost kurulumunda bcrypt hash kullanılır; eski/manuel kurulum için düz metin fallback
    $valid = password_verify($pw, ADMIN_PASS_HASH)
          || (defined('ADMIN_PASS_LEGACY') && $pw === ADMIN_PASS_LEGACY);
    if ($valid) { $_SESSION['admin'] = true; header('Location: ?section=admin'); exit; }
    $flashMsg = 'error:Hatalı şifre!';
}
if ($action === 'logout') { session_destroy(); header('Location: ?'); exit; }

if ($action === 'save' && $isAdmin) {
    $posts   = loadPosts();
    $id      = trim($_POST['id'] ?? '');
    $rawSlug = trim($_POST['slug'] ?? '') ?: slugify(trim($_POST['title'] ?? ''));
    $rawSlug = slugify($rawSlug);
    $rawSlug = $rawSlug ?: 'post';
    $rawSlug = uniqueSlug($rawSlug, $posts, $id);
    $post = [
        'id'          => $id ?: genId(),
        'slug'        => $rawSlug,
        'title'       => trim($_POST['title'] ?? ''),
        'meta_title'  => trim($_POST['meta_title'] ?? ''),
        'meta_desc'   => trim($_POST['meta_desc'] ?? ''),
        'content'     => trim($_POST['content'] ?? ''),
        'link'        => trim($_POST['link'] ?? ''),
        'image'       => trim($_POST['image'] ?? ''),
        'category'    => trim($_POST['category'] ?? 'Diğer'),
        'platform'    => trim($_POST['platform'] ?? 'Amazon'),
        'price'       => trim($_POST['price'] ?? ''),
        'badge'       => trim($_POST['badge'] ?? ''),
        'active'      => isset($_POST['active']),
        'created_at'  => '',
        'updated_at'  => date('c'),
    ];
    if ($id) {
        $idx = array_search($id, array_column($posts, 'id'));
        if ($idx !== false) { $post['created_at'] = $posts[$idx]['created_at']; $posts[$idx] = $post; }
        else { $post['created_at'] = date('c'); array_unshift($posts, $post); }
    } else {
        $post['created_at'] = date('c');
        array_unshift($posts, $post);
    }
    savePosts($posts);
    header('Location: ?section=admin&saved=1'); exit;
}

if ($action === 'delete' && $isAdmin) {
    $id = $_POST['id'] ?? '';
    $posts = loadPosts();
    savePosts(array_filter($posts, fn($p) => $p['id'] !== $id));
    header('Location: ?section=admin&deleted=1'); exit;
}

if ($action === 'toggle' && $isAdmin) {
    $id = $_POST['id'] ?? '';
    $posts = loadPosts();
    foreach ($posts as &$p) if ($p['id'] === $id) { $p['active'] = !($p['active'] ?? true); }
    savePosts($posts);
    header('Location: ?section=admin'); exit;
}

// ── Veri ─────────────────────────────────────────────────────────
$allPosts    = loadPosts();
$activePosts = array_values(array_filter($allPosts, fn($p) => $p['active'] ?? true));

// Tekil post
$currentPost = null;
if ($slug) {
    foreach ($activePosts as $p) if ($p['slug'] === $slug) { $currentPost = $p; break; }
}

// Kategori
$catLabel    = '';
$filteredCat = [];
if ($catSlug) {
    foreach ($CATS as $c) if (slugify($c) === $catSlug) { $catLabel = $c; break; }
    $filteredCat = array_values(array_filter($activePosts, fn($p) => slugify($p['category'] ?? '') === $catSlug));
}

// Sayfalama (ana sayfa)
$totalPosts  = count($activePosts);
$totalPages  = max(1, ceil($totalPosts / PER_PAGE));
$pagedPosts  = array_slice($activePosts, ($page - 1) * PER_PAGE, PER_PAGE);

// ── Yardımcı Fonksiyonlar ────────────────────────────────────────
function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function postUrl(array $p): string { return siteUrl('?p=' . $p['slug']); }
function catUrl(string $cat): string { return siteUrl('?cat=' . slugify($cat)); }
function dateFormat(string $d): string { return $d ? date('d.m.Y', strtotime($d)) : ''; }
function readTime(string $c): int { return max(1, (int)ceil(str_word_count(strip_tags($c)) / 200)); }

// ── SEO Head ─────────────────────────────────────────────────────
function seoHead(string $title, string $desc, string $url, string $image = '', string $type = 'website', ?array $post = null): void {
    global $allPosts;
    $siteN = SITE_NAME;
    $fullTitle = $title ? h($title) . ' | ' . h($siteN) : h($siteN);
    $desc = h($desc ?: SITE_DESC);
    $imgTag = $image ? "<meta property=\"og:image\" content=\"".h($image)."\">\n    <meta name=\"twitter:image\" content=\"".h($image)."\">" : '';
    echo <<<HTML
    <title>{$fullTitle}</title>
    <meta name="description" content="{$desc}">
    <link rel="canonical" href="{$url}">
    <meta property="og:title" content="{$fullTitle}">
    <meta property="og:description" content="{$desc}">
    <meta property="og:url" content="{$url}">
    <meta property="og:type" content="{$type}">
    <meta property="og:site_name" content="".h($siteN)."">
    {$imgTag}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{$fullTitle}">
    <meta name="twitter:description" content="{$desc}">
    <link rel="alternate" type="application/rss+xml" title="{$siteN}" href="".siteUrl('?feed')."">
HTML;
    // JSON-LD
    if ($post) {
        $ld = [
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => $post['title'],
            'description'   => strip_tags(excerpt($post['content'] ?? '')),
            'url'           => postUrl($post),
            'datePublished' => $post['created_at'] ?? '',
            'dateModified'  => $post['updated_at']  ?? $post['created_at'] ?? '',
            'image'         => $post['image'] ? [$post['image']] : [],
            'author'        => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => siteUrl()],
            'publisher'     => ['@type' => 'Organization', 'name' => SITE_NAME,
                                'url' => siteUrl(),
                                'logo' => ['@type' => 'ImageObject', 'url' => siteUrl('?logo')]],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => postUrl($post)],
        ];
        if (!empty($post['price'])) {
            $ld['@type'] = ['Article','Product'];
            $ld['offers'] = ['@type'=>'Offer','priceCurrency'=>'TRY',
                             'price'=>preg_replace('/[^0-9.,]/','',$post['price']),'availability'=>'https://schema.org/InStock',
                             'url'=>$post['link']];
        }
        echo "\n    <script type=\"application/ld+json\">" . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "</script>\n";
    } else {
        // WebSite JSON-LD
        $ld = ['@context'=>'https://schema.org','@type'=>'WebSite','name'=>SITE_NAME,'url'=>siteUrl(),
               'potentialAction'=>['@type'=>'SearchAction','target'=>['@type'=>'EntryPoint','urlTemplate'=>siteUrl('?q={search_term_string}')],'query-input'=>'required name=search_term_string']];
        echo "\n    <script type=\"application/ld+json\">" . json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</script>\n";
    }
}

// ── Sitemap ───────────────────────────────────────────────────────
function outputSitemap(): void {
    global $activePosts, $CATS;
    header('Content-Type: application/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    // Ana sayfa
    echo '<url><loc>' . siteUrl() . '</loc><changefreq>daily</changefreq><priority>1.0</priority></url>' . "\n";
    // Kategoriler
    foreach ($CATS as $c) {
        echo '<url><loc>' . h(catUrl($c)) . '</loc><changefreq>weekly</changefreq><priority>0.7</priority></url>' . "\n";
    }
    // Postlar
    foreach ($activePosts as $p) {
        $mod = $p['updated_at'] ?? $p['created_at'] ?? date('c');
        echo '<url><loc>' . h(postUrl($p)) . '</loc>'
           . '<lastmod>' . date('Y-m-d', strtotime($mod)) . '</lastmod>'
           . '<changefreq>monthly</changefreq><priority>0.8</priority></url>' . "\n";
    }
    echo '</urlset>';
}

// ── Robots ────────────────────────────────────────────────────────
function outputRobots(): void {
    header('Content-Type: text/plain');
    echo "User-agent: *\nAllow: /\nDisallow: /?section=admin\nDisallow: /?section=login\n\nSitemap: " . siteUrl('?sitemap') . "\n";
}

// ── RSS Feed ─────────────────────────────────────────────────────
if (isset($_GET['feed'])) {
    header('Content-Type: application/rss+xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel>';
    echo '<title>' . h(SITE_NAME) . '</title><link>' . siteUrl() . '</link><description>' . h(SITE_DESC) . '</description>';
    foreach (array_slice($activePosts, 0, 20) as $p) {
        echo '<item><title>' . h($p['title']) . '</title><link>' . h(postUrl($p)) . '</link>'
           . '<description>' . h(excerpt($p['content'] ?? '')) . '</description>'
           . '<pubDate>' . date('r', strtotime($p['created_at'] ?? 'now')) . '</pubDate>'
           . '<guid>' . h(postUrl($p)) . '</guid></item>';
    }
    echo '</channel></rss>'; exit;
}

// Redirect admin if not logged in
if ($section === 'admin' && !$isAdmin) { header('Location: ?section=login'); exit; }

?>
<!DOCTYPE html>
<html lang="tr" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
if ($currentPost) {
    seoHead($currentPost['meta_title'] ?: $currentPost['title'],
            $currentPost['meta_desc']  ?: excerpt($currentPost['content'] ?? ''),
            postUrl($currentPost), $currentPost['image'] ?? '', 'article', $currentPost);
} elseif ($catLabel) {
    seoHead($catLabel . ' Ürün İncelemeleri', $catLabel . ' kategorisinde en iyi ürün incelemeleri ve fırsatlar', catUrl($catLabel));
} elseif ($section === 'admin' || $section === 'login') {
    echo '<title>Yönetici | ' . SITE_NAME . '</title><meta name="robots" content="noindex,nofollow">';
} else {
    seoHead('', SITE_DESC, siteUrl());
}
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=DM+Sans:wght@300..700&display=swap" rel="stylesheet">
<?php if ($section === 'admin' && $isAdmin): ?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
<?php endif; ?>
<style>
:root,[data-theme="light"]{
  --bg:#f7f6f2;--surface:#ffffff;--surface2:#f3f0eb;--border:rgba(40,37,29,.1);--divider:rgba(40,37,29,.07);
  --text:#28251d;--muted:#7a7974;--faint:#bab9b4;
  --accent:#b5510f;--accent-h:#8c3e0c;--accent-bg:#fef3ec;
  --success:#437a22;--error:#a12c2c;
  --r-sm:.375rem;--r-md:.5rem;--r-lg:.75rem;--r-xl:1rem;--r-full:9999px;
  --sh-sm:0 1px 3px rgba(0,0,0,.06);--sh-md:0 4px 16px rgba(0,0,0,.09);--sh-lg:0 16px 40px rgba(0,0,0,.13);
  --fd:'Instrument Serif',Georgia,serif;--fb:'DM Sans','Helvetica Neue',sans-serif;
  --tx:clamp(1rem,.95rem + .25vw,1.125rem);--tx-sm:clamp(.875rem,.8rem + .35vw,1rem);
  --tx-xs:clamp(.75rem,.7rem + .25vw,.875rem);--tx-lg:clamp(1.125rem,1rem + .75vw,1.5rem);
  --tx-xl:clamp(1.5rem,1.2rem + 1.25vw,2.25rem);--tx-2xl:clamp(2rem,1.2rem + 2.5vw,3.25rem);
}
[data-theme="dark"]{
  --bg:#171614;--surface:#1e1d1b;--surface2:#242320;--border:rgba(255,255,255,.08);--divider:rgba(255,255,255,.05);
  --text:#cdccca;--muted:#797876;--faint:#5a5957;
  --accent:#e8844a;--accent-h:#ffa06a;--accent-bg:#2a1a0e;
  --sh-sm:0 1px 3px rgba(0,0,0,.3);--sh-md:0 4px 16px rgba(0,0,0,.4);--sh-lg:0 16px 40px rgba(0,0,0,.5);
}
@media(prefers-color-scheme:dark){:root:not([data-theme]){
  --bg:#171614;--surface:#1e1d1b;--surface2:#242320;--border:rgba(255,255,255,.08);--divider:rgba(255,255,255,.05);
  --text:#cdccca;--muted:#797876;--faint:#5a5957;--accent:#e8844a;--accent-h:#ffa06a;--accent-bg:#2a1a0e;
}}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html{-webkit-font-smoothing:antialiased;scroll-behavior:smooth;scroll-padding-top:5rem}
body{min-height:100dvh;font-family:var(--fb);font-size:var(--tx);color:var(--text);background:var(--bg);line-height:1.7}
img{display:block;max-width:100%;height:auto}
a{color:var(--accent);text-decoration:none;transition:color .18s}
a:hover{color:var(--accent-h)}
button,input,textarea,select{font:inherit;color:inherit}
button{cursor:pointer;background:none;border:none}
:focus-visible{outline:2px solid var(--accent);outline-offset:3px;border-radius:var(--r-sm)}
h1,h2,h3,h4{text-wrap:balance;line-height:1.2}
p,li{text-wrap:pretty;max-width:72ch}

/* ── Layout ── */
.wrap{max-width:1140px;margin-inline:auto;padding-inline:1.25rem}
.wrap-narrow{max-width:760px;margin-inline:auto;padding-inline:1.25rem}

/* ── Navbar ── */
.nav{position:sticky;top:0;z-index:100;background:color-mix(in oklch,var(--bg) 88%,transparent);
     backdrop-filter:blur(14px);border-bottom:1px solid var(--border);padding-block:.75rem}
.nav-inner{display:flex;align-items:center;justify-content:space-between;gap:1rem}
.logo{display:flex;align-items:center;gap:.5rem;text-decoration:none;color:var(--text)}
.logo-name{font-family:var(--fd);font-size:var(--tx-lg);line-height:1;color:var(--text)}
.logo-tag{font-size:var(--tx-xs);color:var(--muted);font-style:italic}
.nav-links{display:flex;align-items:center;gap:.25rem}
.nav-link{padding:.375rem .75rem;border-radius:var(--r-md);font-size:var(--tx-sm);color:var(--muted);
          font-weight:500;text-decoration:none;transition:all .18s}
.nav-link:hover{background:var(--surface2);color:var(--text)}
.btn-icon{display:grid;place-items:center;width:36px;height:36px;border-radius:var(--r-md);
          color:var(--muted);transition:all .18s}
.btn-icon:hover{background:var(--surface2);color:var(--text)}

/* ── Hero ── */
.hero{padding:4rem 0 3rem;text-align:center}
.hero-tag{display:inline-flex;align-items:center;gap:.3rem;background:var(--accent-bg);color:var(--accent);
          padding:.2rem .75rem;border-radius:var(--r-full);font-size:var(--tx-xs);font-weight:700;
          letter-spacing:.04em;margin-bottom:1rem;text-transform:uppercase}
.hero h1{font-family:var(--fd);font-size:var(--tx-2xl);color:var(--text);margin-bottom:.75rem}
.hero h1 em{color:var(--accent);font-style:italic}
.hero p{font-size:var(--tx);color:var(--muted);max-width:54ch;margin-inline:auto}

/* ── Cat Filter ── */
.cat-bar{padding-bottom:2rem}
.cat-scroll{display:flex;gap:.5rem;overflow-x:auto;padding-bottom:2px;scrollbar-width:none}
.cat-scroll::-webkit-scrollbar{display:none}
.cat-chip{flex-shrink:0;padding:.35rem 1rem;border-radius:var(--r-full);font-size:var(--tx-sm);
          font-weight:500;text-decoration:none;color:var(--muted);background:var(--surface);
          border:1px solid var(--border);white-space:nowrap;transition:all .18s}
.cat-chip:hover{background:var(--surface2);color:var(--text)}
.cat-chip.on{background:var(--accent);color:#fff;border-color:var(--accent);box-shadow:0 2px 8px rgba(181,81,15,.3)}

/* ── Post Grid ── */
.post-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(320px,100%),1fr));gap:1.5rem;padding-bottom:3rem}
.post-card{background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border);
           box-shadow:var(--sh-sm);overflow:hidden;display:flex;flex-direction:column;
           transition:transform .2s,box-shadow .2s;text-decoration:none;color:var(--text)}
.post-card:hover{transform:translateY(-3px);box-shadow:var(--sh-md)}
.card-img{aspect-ratio:16/9;overflow:hidden;background:var(--surface2)}
.card-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.post-card:hover .card-img img{transform:scale(1.04)}
.card-img-ph{width:100%;height:100%;display:grid;place-items:center;color:var(--faint)}
.card-body{padding:1.25rem;flex:1;display:flex;flex-direction:column;gap:.5rem}
.card-meta{display:flex;align-items:center;justify-content:space-between;gap:.5rem}
.card-cat{font-size:var(--tx-xs);color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.06em}
.plt-badge{font-size:var(--tx-xs);font-weight:700;padding:.15rem .5rem;border-radius:var(--r-full);color:#fff}
.card-title{font-family:var(--fd);font-size:var(--tx-lg);line-height:1.25;color:var(--text)}
.card-excerpt{font-size:var(--tx-sm);color:var(--muted);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;flex:1}
.card-foot{display:flex;align-items:center;justify-content:space-between;padding-top:.75rem;border-top:1px solid var(--divider)}
.card-price{font-size:var(--tx-sm);font-weight:700;color:var(--accent)}
.card-rdm{font-size:var(--tx-xs);color:var(--muted)}
.btn-read{display:inline-flex;align-items:center;gap:.25rem;padding:.4rem 1rem;background:var(--accent);
          color:#fff;border-radius:var(--r-full);font-size:var(--tx-sm);font-weight:600;transition:all .18s}
.btn-read:hover{background:var(--accent-h)}

/* ── Article ── */
.article-header{padding:3rem 0 2rem}
.article-header .breadcrumb{font-size:var(--tx-xs);color:var(--muted);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.article-header .breadcrumb a{color:var(--muted);text-decoration:none}
.article-header .breadcrumb a:hover{color:var(--accent)}
.article-meta{display:flex;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:1rem}
.article-meta .dot{color:var(--faint)}
.article-meta span,.article-meta a{font-size:var(--tx-sm);color:var(--muted)}
.article-img{border-radius:var(--r-xl);overflow:hidden;margin:2rem 0;box-shadow:var(--sh-md);aspect-ratio:16/9;background:var(--surface2)}
.article-img img{width:100%;height:100%;object-fit:cover}
.article-cta{margin:2.5rem 0;padding:2rem;background:var(--accent-bg);border-radius:var(--r-xl);
             border:1px solid rgba(181,81,15,.15);display:flex;align-items:center;justify-content:space-between;
             gap:1rem;flex-wrap:wrap}
.article-cta .cta-text h3{font-family:var(--fd);font-size:var(--tx-lg);margin-bottom:.25rem}
.article-cta .cta-text p{font-size:var(--tx-sm);color:var(--muted)}
.btn-cta{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.75rem;background:var(--accent);
         color:#fff;border-radius:var(--r-full);font-size:var(--tx);font-weight:700;white-space:nowrap;
         text-decoration:none;transition:all .18s;box-shadow:0 4px 12px rgba(181,81,15,.3)}
.btn-cta:hover{background:var(--accent-h);transform:translateY(-1px)}
.article-body{font-size:var(--tx);line-height:1.85;color:var(--text)}
.article-body h2{font-family:var(--fd);font-size:var(--tx-xl);margin:2.5rem 0 1rem;color:var(--text)}
.article-body h3{font-family:var(--fd);font-size:var(--tx-lg);margin:2rem 0 .75rem;color:var(--text)}
.article-body p{margin-bottom:1.25rem;max-width:none}
.article-body ul{padding-left:1.5rem;margin-bottom:1.25rem}
.article-body li{margin-bottom:.5rem}
.article-body strong{font-weight:700;color:var(--text)}
.article-body em{font-style:italic}
.affiliate-disc{background:var(--surface2);border-radius:var(--r-md);padding:1rem 1.25rem;
                font-size:var(--tx-xs);color:var(--muted);margin-top:3rem;border:1px solid var(--border)}

/* ── Pagination ── */
.pagination{display:flex;justify-content:center;align-items:center;gap:.5rem;padding:1rem 0 3rem}
.pg-btn{padding:.5rem .875rem;border-radius:var(--r-md);font-size:var(--tx-sm);font-weight:500;
        text-decoration:none;color:var(--muted);border:1px solid var(--border);background:var(--surface);
        transition:all .18s}
.pg-btn:hover{background:var(--surface2);color:var(--text)}
.pg-btn.on{background:var(--accent);color:#fff;border-color:var(--accent)}

/* ── Empty ── */
.empty{text-align:center;padding:5rem 2rem;color:var(--muted)}
.empty svg{margin:0 auto 1rem;opacity:.4}
.empty h3{font-family:var(--fd);font-size:var(--tx-lg);color:var(--text);margin-bottom:.5rem}

/* ── Admin ── */
.admin-wrap{display:grid;grid-template-columns:220px 1fr;min-height:calc(100dvh - 60px)}
@media(max-width:768px){.admin-wrap{grid-template-columns:1fr}}
.admin-side{background:var(--surface);border-right:1px solid var(--border);padding:1.5rem 1rem}
.side-label{font-size:var(--tx-xs);font-weight:700;text-transform:uppercase;letter-spacing:.08em;
            color:var(--faint);margin-bottom:.75rem}
.side-nav{display:flex;flex-direction:column;gap:.25rem}
.side-lnk{display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;border-radius:var(--r-md);
          font-size:var(--tx-sm);font-weight:500;text-decoration:none;color:var(--muted);transition:all .18s}
.side-lnk:hover,.side-lnk.on{background:var(--surface2);color:var(--text)}
.admin-main{padding:2rem 1.75rem;overflow-x:hidden}
.pg-ttl{font-family:var(--fd);font-size:var(--tx-xl);color:var(--text);margin-bottom:1.5rem}

/* Table */
.tbl-wrap{overflow-x:auto;border-radius:var(--r-xl);border:1px solid var(--border);background:var(--surface)}
table{width:100%;border-collapse:collapse;font-size:var(--tx-sm)}
thead th{padding:.75rem 1rem;text-align:left;font-size:var(--tx-xs);font-weight:700;text-transform:uppercase;
         letter-spacing:.06em;color:var(--muted);border-bottom:1px solid var(--border);background:var(--surface2)}
tbody td{padding:.75rem 1rem;border-bottom:1px solid var(--divider);vertical-align:middle}
tbody tr:last-child td{border-bottom:none}
tbody tr:hover td{background:var(--surface2)}
.dot-on{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--success)}
.dot-off{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--faint)}
.act-btns{display:flex;gap:.5rem;align-items:center}
.btn-sm{padding:.275rem .75rem;border-radius:var(--r-md);font-size:var(--tx-xs);font-weight:600;
        text-decoration:none;display:inline-flex;align-items:center;gap:3px;border:1px solid transparent;transition:all .18s}
.btn-p{background:var(--accent);color:#fff;border-color:var(--accent)}
.btn-p:hover{background:var(--accent-h)}
.btn-g{background:transparent;color:var(--muted);border-color:var(--border)}
.btn-g:hover{background:var(--surface2);color:var(--text)}
.btn-d{background:transparent;color:var(--error);border-color:rgba(161,44,44,.3)}
.btn-d:hover{background:rgba(161,44,44,.06)}

/* Form */
.form-card{background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border);padding:2rem}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem}
@media(max-width:640px){.form-grid{grid-template-columns:1fr}}
.fg{display:flex;flex-direction:column;gap:.4rem}
.fg.full{grid-column:1/-1}
.flbl{font-size:var(--tx-xs);font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.flbl .req{color:var(--accent)}
.fin,.fsel,.ftxt{padding:.6rem .75rem;border:1px solid var(--border);border-radius:var(--r-md);
                 background:var(--surface2);font-size:var(--tx-sm);color:var(--text);
                 transition:border-color .18s,box-shadow .18s;width:100%}
.fin:focus,.fsel:focus,.ftxt:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(181,81,15,.12)}
.ftxt{resize:vertical;min-height:80px}
.fhint{font-size:var(--tx-xs);color:var(--muted)}
.fcheck{display:flex;align-items:center;gap:.75rem;padding:.75rem;background:var(--surface2);
        border-radius:var(--r-md);border:1px solid var(--border);cursor:pointer}
.fcheck input[type=checkbox]{width:18px;height:18px;accent-color:var(--accent);cursor:pointer}
.form-acts{display:flex;gap:.75rem;align-items:center;padding-top:1rem;border-top:1px solid var(--border);margin-top:1rem}
.btn-lg{padding:.7rem 2rem;border-radius:var(--r-full);font-size:var(--tx-sm);font-weight:700}
.alert{padding:.75rem 1rem;border-radius:var(--r-md);font-size:var(--tx-sm);font-weight:500;margin-bottom:1rem}
.alert-ok{background:rgba(67,122,34,.1);color:var(--success);border:1px solid rgba(67,122,34,.2)}
.alert-err{background:rgba(161,44,44,.1);color:var(--error);border:1px solid rgba(161,44,44,.2)}

/* Login */
.login-wrap{min-height:100dvh;display:grid;place-items:center;padding:1rem}
.login-card{background:var(--surface);border-radius:var(--r-xl);border:1px solid var(--border);
            box-shadow:var(--sh-lg);padding:3rem 2rem;width:100%;max-width:380px;text-align:center}
.login-logo{font-family:var(--fd);font-size:var(--tx-xl);margin-bottom:.5rem}
.login-sub{color:var(--muted);font-size:var(--tx-sm);margin-bottom:2rem}

/* Footer */
footer{padding:2rem 0;border-top:1px solid var(--border);text-align:center;font-size:var(--tx-xs);color:var(--faint)}
footer a{color:var(--muted);text-decoration:none}
footer a:hover{color:var(--accent)}

/* Editor overrides */
.CodeMirror,.EasyMDEContainer{border-radius:var(--r-md) !important;font-family:var(--fb) !important;font-size:var(--tx-sm) !important}
.EasyMDEContainer .CodeMirror{background:var(--surface2) !important;color:var(--text) !important}
</style>
</head>
<body>

<?php /* ══════ LOGIN ══════ */ if ($section === 'login'): ?>
<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo"><?= SITE_NAME ?></div>
    <p class="login-sub">Yönetici Paneline Giriş</p>
    <?php if ($flashMsg): ?>
      <div class="alert alert-err"><?= h(substr($flashMsg, 6)) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="login">
      <div class="fg" style="text-align:left;margin-bottom:1rem">
        <label class="flbl" for="pw">Şifre</label>
        <input class="fin" type="password" id="pw" name="pw" autofocus required>
      </div>
      <button type="submit" class="btn-cta btn-lg" style="width:100%">Giriş Yap</button>
    </form>
    <a href="?" style="display:block;margin-top:1rem;font-size:var(--tx-xs);color:var(--muted)">← Siteye Dön</a>
  </div>
</div>

<?php /* ══════ ADMIN ══════ */ elseif ($section === 'admin' && $isAdmin):
  $editPost = null;
  if (isset($_GET['edit']) && $_GET['edit'] !== 'new') {
      foreach ($allPosts as $p) if ($p['id'] === $_GET['edit']) { $editPost = $p; break; }
  }
?>
<nav class="nav">
  <div class="wrap nav-inner">
    <a href="?" class="logo">
      <?php echo svgLogo(); ?>
      <div><div class="logo-name"><?= SITE_NAME ?></div><div class="logo-tag">Yönetici</div></div>
    </a>
    <div class="nav-links">
      <a href="?" target="_blank" class="btn-icon" title="Siteyi Gör">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15,3 21,3 21,9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
      </a>
      <button onclick="toggleTheme()" class="btn-icon" aria-label="Tema">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
      </button>
      <form method="POST" style="margin:0"><input type="hidden" name="action" value="logout">
        <button type="submit" class="btn-sm btn-g">Çıkış</button>
      </form>
    </div>
  </div>
</nav>

<div class="admin-wrap">
  <aside class="admin-side">
    <div class="side-label">Menü</div>
    <nav class="side-nav">
      <a href="?section=admin" class="side-lnk <?= (!isset($_GET['edit'])) ? 'on':'' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        Tüm Yazılar
      </a>
      <a href="?section=admin&edit=new" class="side-lnk <?= (isset($_GET['edit'])) ? 'on':'' ?>">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Yeni Yaz
      </a>
      <a href="?sitemap" target="_blank" class="side-lnk">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        Sitemap.xml
      </a>
      <a href="?robots" target="_blank" class="side-lnk">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7H3a7 7 0 0 1 7-7h1V5.73c-.6-.34-1-.99-1-1.73a2 2 0 0 1 2-2z"/><path d="M5 14H3a9 9 0 0 0 18 0h-2"/><circle cx="9" cy="14" r="1" fill="currentColor"/><circle cx="15" cy="14" r="1" fill="currentColor"/></svg>
        Robots.txt
      </a>
    </nav>
    <div style="margin-top:2rem;padding:.75rem;background:var(--surface2);border-radius:var(--r-md);font-size:var(--tx-xs);color:var(--muted)">
      <strong style="color:var(--text)"><?= count($allPosts) ?></strong> toplam yazı<br>
      <strong style="color:var(--success)"><?= count(array_filter($allPosts,fn($p)=>$p['active']??true)) ?></strong> yayında
    </div>
  </aside>

  <main class="admin-main">
    <?php if (isset($_GET['saved'])): ?><div class="alert alert-ok">✓ Yazı kaydedildi ve yayınlandı!</div><?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?><div class="alert alert-ok">Yazı silindi.</div><?php endif; ?>

    <?php if (isset($_GET['edit'])): ?>
    <h1 class="pg-ttl"><?= $editPost ? 'Yazı Düzenle' : 'Yeni Yazı Ekle' ?></h1>
    <form method="POST" class="form-card">
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= h($editPost['id'] ?? '') ?>">
      <div class="form-grid">
        <div class="fg full">
          <label class="flbl" for="ttl">Başlık <span class="req">*</span></label>
          <input class="fin" type="text" id="ttl" name="title" required placeholder="Ör: 2026'nın En İyi Jean Modelleri" value="<?= h($editPost['title'] ?? '') ?>">
          <span class="fhint">Google'da arama başlığı olarak görünür</span>
        </div>
        <div class="fg full">
          <label class="flbl" for="slug">URL Slug</label>
          <input class="fin" type="text" id="slug" name="slug" placeholder="otomatik-uretilir" value="<?= h($editPost['slug'] ?? '') ?>">
          <span class="fhint">Boş bırakırsanız başlıktan otomatik üretilir · <?= h(siteUrl('?p=')) ?><strong>slug</strong></span>
        </div>
        <div class="fg full">
          <label class="flbl" for="mttl">SEO Başlığı (Meta Title)</label>
          <input class="fin" type="text" id="mttl" name="meta_title" placeholder="Arama motorları için özel başlık (maks 60 karakter)" value="<?= h($editPost['meta_title'] ?? '') ?>">
        </div>
        <div class="fg full">
          <label class="flbl" for="mdsc">SEO Açıklaması (Meta Description)</label>
          <textarea class="ftxt" id="mdsc" name="meta_desc" rows="2" style="min-height:60px" placeholder="Arama sonuçlarında görünen açıklama (maks 160 karakter)"><?= h($editPost['meta_desc'] ?? '') ?></textarea>
        </div>
        <div class="fg full">
          <label class="flbl" for="cnt">Makale İçeriği <span class="req">*</span> <span style="font-weight:400;text-transform:none;letter-spacing:0;font-size:var(--tx-xs)">(Markdown desteklenir)</span></label>
          <textarea class="ftxt" id="cnt" name="content" style="min-height:280px"><?= h($editPost['content'] ?? '') ?></textarea>
          <span class="fhint">**kalın** · *italik* · ## Başlık · - liste · [metin](url)</span>
        </div>
        <div class="fg full">
          <label class="flbl" for="lnk">Affiliate Link <span class="req">*</span></label>
          <input class="fin" type="url" id="lnk" name="link" required placeholder="https://amzn.to/xxx" value="<?= h($editPost['link'] ?? '') ?>">
        </div>
        <div class="fg full">
          <label class="flbl" for="img">Öne Çıkan Görsel URL</label>
          <input class="fin" type="url" id="img" name="image" placeholder="https://..." value="<?= h($editPost['image'] ?? '') ?>">
        </div>
        <div class="fg">
          <label class="flbl" for="cat">Kategori</label>
          <select class="fsel" id="cat" name="category">
            <?php foreach ($CATS as $c): ?>
              <option value="<?= h($c) ?>" <?= ($editPost['category'] ?? '') === $c ? 'selected':'' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl" for="plt">Platform</label>
          <select class="fsel" id="plt" name="platform">
            <?php foreach ($PLTS as $p): ?>
              <option value="<?= h($p) ?>" <?= ($editPost['platform'] ?? '') === $p ? 'selected':'' ?>><?= h($p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl" for="prc">Fiyat</label>
          <input class="fin" type="text" id="prc" name="price" placeholder="Ör: 349 TL" value="<?= h($editPost['price'] ?? '') ?>">
        </div>
        <div class="fg">
          <label class="flbl" for="bdg">Rozet</label>
          <input class="fin" type="text" id="bdg" name="badge" placeholder="Ör: %30 İNDİRİM" value="<?= h($editPost['badge'] ?? '') ?>">
        </div>
        <div class="fg full">
          <label class="fcheck">
            <input type="checkbox" name="active" <?= ($editPost['active'] ?? true) ? 'checked':'' ?>>
            <div><strong>Yayınla</strong> <span style="font-size:var(--tx-xs);color:var(--muted)">— işaretliyse Google indeksleyebilir</span></div>
          </label>
        </div>
      </div>
      <div class="form-acts">
        <button type="submit" class="btn-cta btn-lg">Kaydet & Yayınla</button>
        <a href="?section=admin" class="btn-sm btn-g" style="padding:.7rem 1.5rem">İptal</a>
        <?php if ($editPost): ?><a href="<?= h(postUrl($editPost)) ?>" target="_blank" class="btn-sm btn-g" style="padding:.7rem 1.5rem">Yazıyı Gör ↗</a><?php endif; ?>
      </div>
    </form>

    <?php else: /* TABLE */ ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
      <h1 class="pg-ttl" style="margin:0">Yazılar</h1>
      <a href="?section=admin&edit=new" class="btn-cta" style="padding:.55rem 1.25rem;font-size:var(--tx-sm)">+ Yeni Yazı</a>
    </div>
    <?php if (empty($allPosts)): ?>
      <div class="empty"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10,9 9,9 8,9"/></svg>
        <h3>Henüz yazı yok</h3><p>İlk blog yazını ekle, Google indekslemeye başlar!</p>
        <a href="?section=admin&edit=new" class="btn-cta" style="margin-top:1rem;display:inline-flex">+ İlk Yazıyı Ekle</a>
      </div>
    <?php else: ?>
    <div class="tbl-wrap">
      <table>
        <thead><tr><th>Durum</th><th>Başlık</th><th>Kategori</th><th>Platform</th><th>Tarih</th><th>İşlem</th></tr></thead>
        <tbody>
          <?php foreach ($allPosts as $p): ?>
          <tr>
            <td><form method="POST" style="margin:0"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
              <button type="submit" title="Durumu değiştir" style="padding:2px"><span class="<?= ($p['active']??true)?'dot-on':'dot-off' ?>"></span></button>
            </form></td>
            <td><strong style="font-size:var(--tx-sm)"><?= h(mb_strimwidth($p['title'],0,50,'…')) ?></strong>
                <br><span style="font-size:var(--tx-xs);color:var(--muted)"><?= h($p['slug']) ?></span></td>
            <td><span style="font-size:var(--tx-xs);color:var(--muted)"><?= h($p['category']??'') ?></span></td>
            <td><span class="plt-badge" style="background:<?= $PLT_COLORS[$p['platform']??'']??'#6B7280' ?>"><?= h($p['platform']??'') ?></span></td>
            <td style="font-size:var(--tx-xs);color:var(--muted)"><?= dateFormat($p['created_at']??'') ?></td>
            <td><div class="act-btns">
              <a href="?section=admin&edit=<?= h($p['id']) ?>" class="btn-sm btn-g">Düzenle</a>
              <a href="<?= h(postUrl($p)) ?>" target="_blank" class="btn-sm btn-g">Gör</a>
              <form method="POST" style="margin:0" onsubmit="return confirm('Bu yazı silinsin mi?')">
                <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= h($p['id']) ?>">
                <button type="submit" class="btn-sm btn-d">Sil</button>
              </form>
            </div></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; endif; ?>
  </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
<script>
<?php if (isset($_GET['edit'])): ?>
const cnt = document.getElementById('cnt');
if (cnt) {
  const easyMDE = new EasyMDE({
    element: cnt, spellChecker: false, autosave: {enabled:true, uniqueId:'koleksiyoncum-editor'},
    toolbar: ['bold','italic','heading','|','quote','unordered-list','ordered-list','|','link','image','|','preview','side-by-side','fullscreen'],
    placeholder: '## Ürün Hakkında\n\nBuraya ürün incelemenizi yazın...\n\n## Özellikler\n- Özellik 1\n- Özellik 2\n\n## Kimler İçin Uygun?\n\nAçıklama...',
    minHeight: '280px',
  });
}
<?php endif; ?>
</script>

<?php /* ══════ PUBLIC SINGLE POST ══════ */ elseif ($currentPost): ?>
<nav class="nav">
  <div class="wrap nav-inner">
    <a href="?" class="logo"><?php echo svgLogo(); ?><div><div class="logo-name"><?= SITE_NAME ?></div><div class="logo-tag">İnceleme</div></div></a>
    <div class="nav-links">
      <?php foreach (array_slice($CATS, 0, 4) as $c): ?><a href="<?= h(catUrl($c)) ?>" class="nav-link" style="display:none"><?= h($c) ?></a><?php endforeach; ?>
      <button onclick="toggleTheme()" class="btn-icon" aria-label="Tema"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></button>
    </div>
  </div>
</nav>
<div class="wrap-narrow">
  <header class="article-header">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="?">Anasayfa</a> <span>›</span>
      <a href="<?= h(catUrl($currentPost['category'])) ?>"><?= h($currentPost['category']) ?></a>
      <span>›</span> <span><?= h(mb_strimwidth($currentPost['title'],0,40,'…')) ?></span>
    </nav>
    <div class="card-meta" style="margin-bottom:.75rem">
      <span class="plt-badge" style="background:<?= $PLT_COLORS[$currentPost['platform']??'']??'#6B7280' ?>"><?= h($currentPost['platform']??'') ?></span>
      <?php if (!empty($currentPost['badge'])): ?><span style="background:var(--accent-bg);color:var(--accent);font-size:var(--tx-xs);font-weight:700;padding:.2rem .6rem;border-radius:var(--r-full)"><?= h($currentPost['badge']) ?></span><?php endif; ?>
    </div>
    <h1 style="font-family:var(--fd);font-size:var(--tx-2xl);line-height:1.15;color:var(--text)"><?= h($currentPost['title']) ?></h1>
    <div class="article-meta">
      <span><?= dateFormat($currentPost['created_at'] ?? '') ?></span>
      <span class="dot">·</span>
      <span><?= readTime($currentPost['content'] ?? '') ?> dk okuma</span>
      <span class="dot">·</span>
      <a href="<?= h(catUrl($currentPost['category'])) ?>"><?= h($currentPost['category']) ?></a>
    </div>
  </header>

  <?php if (!empty($currentPost['image'])): ?>
  <div class="article-img"><img src="<?= h($currentPost['image']) ?>" alt="<?= h($currentPost['title']) ?>" loading="eager" width="760" height="428"></div>
  <?php endif; ?>

  <?php if (!empty($currentPost['link'])): ?>
  <div class="article-cta">
    <div class="cta-text">
      <h3><?= h($currentPost['title']) ?></h3>
      <p><?= !empty($currentPost['price']) ? h($currentPost['price']) . ' · ' : '' ?><?= h($currentPost['platform'] ?? '') ?> üzerinden incele</p>
    </div>
    <a href="<?= h($currentPost['link']) ?>" class="btn-cta" target="_blank" rel="noopener noreferrer nofollow sponsored">
      Ürünü İncele →
    </a>
  </div>
  <?php endif; ?>

  <article class="article-body">
    <?= markdownToHtml($currentPost['content'] ?? '') ?>
  </article>

  <?php if (!empty($currentPost['link'])): ?>
  <div class="article-cta" style="margin-top:2.5rem">
    <div class="cta-text">
      <h3>Bu Ürünü Almak İster misiniz?</h3>
      <p>En güncel fiyat ve detaylar için tıklayın</p>
    </div>
    <a href="<?= h($currentPost['link']) ?>" class="btn-cta" target="_blank" rel="noopener noreferrer nofollow sponsored">
      <?= h($currentPost['platform'] ?? 'İncele') ?>'da Gör →
    </a>
  </div>
  <?php endif; ?>
  <div class="affiliate-disc">⚠️ <strong>Affiliate Bildirimi:</strong> Bu yazıdaki bağlantılar satış ortaklığı (affiliate) linkleri içerebilir. Bu linklerden alışveriş yaparsanız, sizin için ek bir maliyet olmaksızın küçük bir komisyon kazanabiliriz.</div>
</div>

<?php /* ══════ PUBLIC HOME / CATEGORY ══════ */ else: ?>
<nav class="nav">
  <div class="wrap nav-inner">
    <a href="?" class="logo"><?php echo svgLogo(); ?><div><div class="logo-name"><?= SITE_NAME ?></div><div class="logo-tag">Özenle Seçildi</div></div></a>
    <div class="nav-links">
      <button onclick="toggleTheme()" class="btn-icon" aria-label="Tema"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg></button>
      <a href="?section=admin" class="btn-icon" title="Yönetici" aria-label="Yönetici paneli"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.58-7 8-7s8 3 8 7"/></svg></a>
    </div>
  </div>
</nav>
<div class="wrap">
  <?php if (!$catLabel): ?>
  <section class="hero">
    <span class="hero-tag">★ Haftalık Seçimler</span>
    <h1><?= SITE_NAME ?> — <em>En İyi</em> Ürünler</h1>
    <p><?= SITE_DESC ?></p>
  </section>
  <?php else: ?>
  <div style="padding:2.5rem 0 1.5rem">
    <a href="?" style="font-size:var(--tx-sm);color:var(--muted)">← Anasayfa</a>
    <h1 style="font-family:var(--fd);font-size:var(--tx-2xl);margin-top:.75rem"><?= h($catLabel) ?> İncelemeleri</h1>
    <p style="color:var(--muted);margin-top:.5rem"><?= count($filteredCat) ?> yazı</p>
  </div>
  <?php endif; ?>

  <div class="cat-bar">
    <div class="cat-scroll">
      <a href="?" class="cat-chip <?= !$catSlug ? 'on' : '' ?>">Tümü</a>
      <?php foreach ($CATS as $c): ?>
        <a href="<?= h(catUrl($c)) ?>" class="cat-chip <?= slugify($c) === $catSlug ? 'on' : '' ?>"><?= h($c) ?></a>
      <?php endforeach; ?>
    </div>
  </div>

  <?php $displayPosts = $catLabel ? $filteredCat : $pagedPosts; ?>
  <?php if (empty($displayPosts)): ?>
    <div class="empty"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
      <h3>Henüz yazı yok</h3><p>Yakında yeni içerikler eklenecek.</p></div>
  <?php else: ?>
  <div class="post-grid">
    <?php foreach ($displayPosts as $p): ?>
    <a href="<?= h(postUrl($p)) ?>" class="post-card">
      <div class="card-img">
        <?php if (!empty($p['image'])): ?>
          <img src="<?= h($p['image']) ?>" alt="<?= h($p['title']) ?>" loading="lazy" width="320" height="180">
        <?php else: ?>
          <div class="card-img-ph"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg></div>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <div class="card-meta">
          <span class="card-cat"><?= h($p['category'] ?? '') ?></span>
          <span class="plt-badge" style="background:<?= $PLT_COLORS[$p['platform']??'']??'#6B7280' ?>"><?= h($p['platform']??'') ?></span>
        </div>
        <h2 class="card-title"><?= h($p['title']) ?></h2>
        <p class="card-excerpt"><?= h(excerpt($p['content'] ?? '', 120)) ?></p>
        <div class="card-foot">
          <div style="display:flex;align-items:center;gap:.5rem">
            <?php if (!empty($p['price'])): ?><span class="card-price"><?= h($p['price']) ?></span><?php endif; ?>
            <?php if (!empty($p['badge'])): ?><span style="background:var(--accent-bg);color:var(--accent);font-size:var(--tx-xs);font-weight:700;padding:.15rem .5rem;border-radius:var(--r-full)"><?= h($p['badge']) ?></span><?php endif; ?>
          </div>
          <span class="card-rdm"><?= readTime($p['content'] ?? '') ?> dk</span>
        </div>
      </div>
    </a>
    <?php endforeach; ?>
  </div>

  <?php if (!$catLabel && $totalPages > 1): ?>
  <div class="pagination">
    <?php if ($page > 1): ?><a href="?pg=<?= $page-1 ?>" class="pg-btn">← Önceki</a><?php endif; ?>
    <?php for ($i = max(1,$page-2); $i <= min($totalPages,$page+2); $i++): ?>
      <a href="?pg=<?= $i ?>" class="pg-btn <?= $i==$page?'on':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $totalPages): ?><a href="?pg=<?= $page+1 ?>" class="pg-btn">Sonraki →</a><?php endif; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<footer>
  <div class="wrap">
    <p><?= SITE_NAME ?> &copy; <?= date('Y') ?> &nbsp;·&nbsp;
    <a href="?sitemap" target="_blank">Sitemap</a> &nbsp;·&nbsp;
    <a href="?feed" target="_blank">RSS</a> &nbsp;·&nbsp;
    <a href="?section=admin">Yönetici</a></p>
  </div>
</footer>

<script>
(function(){
  const t=document.documentElement;
  const p=localStorage.getItem('theme')||(matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light');
  t.setAttribute('data-theme',p);
})();
function toggleTheme(){
  const t=document.documentElement;
  const c=t.getAttribute('data-theme')==='dark'?'light':'dark';
  t.setAttribute('data-theme',c);
  try{localStorage.setItem('theme',c);}catch(e){}
}
</script>
</body>
</html>
<?php

// ── SVG Logo ─────────────────────────────────────────────────────
function svgLogo(): string {
    return '<svg width="28" height="28" viewBox="0 0 28 28" fill="none" aria-hidden="true">
      <rect x="4" y="4" width="9" height="9" rx="2" fill="var(--accent)"/>
      <rect x="15" y="4" width="9" height="9" rx="2" fill="var(--accent)" opacity=".5"/>
      <rect x="4" y="15" width="9" height="9" rx="2" fill="var(--accent)" opacity=".5"/>
      <rect x="15" y="15" width="9" height="9" rx="2" fill="var(--accent)" opacity=".3"/>
    </svg>';
}
