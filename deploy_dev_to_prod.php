<?php
/**
 * deploy_dev_to_prod.php
 * Déploiement dev.kunming-afchine.org → production
 * Usage : accès direct via https://kunming-afchine.org/falang-inject/deploy_dev_to_prod.php
 * Sécurisé par token.
 */

define('DEPLOY_TOKEN', 'FALANG_SECRET_TOKEN_AFK_2026');
define('DB_HOST',   'localhost');
define('DB_NAME',   'Kunming.org');
define('DB_USER',   'root');
define('DB_PASS',   'Igouzing831917');
define('PROD_PREFIX', 'bwhwo_');
define('DEV_PREFIX',  'dev_');

// Pages SP Builder à synchroniser dev → prod
define('SPPB_PAGES', [173]);

// Fichiers template à synchroniser dev → prod (chemin relatif à la racine vhost)
define('TEMPLATE_FILES', [
    'templates/shaper_languageschool/css/mobile_cards.css',
    'templates/shaper_languageschool/js/mobile-menu.js',
    'templates/shaper_languageschool/index.php',
]);

// Auth
$token = $_SERVER['HTTP_X_FALANG_TOKEN'] ?? $_GET['token'] ?? '';
if ($token !== DEPLOY_TOKEN) {
    http_response_code(403);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json; charset=utf-8');

$log   = [];
$errors = [];

// ─── 1. Connexion DB ────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
}

// ─── 2. Synchroniser SP Builder pages dev → prod ────────────────────────────
$sppb_synced = [];
foreach (SPPB_PAGES as $pageId) {
    try {
        // Lire le contenu dev
        $stmt = $pdo->prepare("SELECT content, text FROM " . DEV_PREFIX . "sppagebuilder WHERE id = ?");
        $stmt->execute([$pageId]);
        $devRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$devRow) {
            $errors[] = "SPPB page $pageId: not found in dev DB";
            continue;
        }

        // Écrire en prod
        $upd = $pdo->prepare(
            "UPDATE " . PROD_PREFIX . "sppagebuilder SET content = ?, text = ? WHERE id = ?"
        );
        $upd->execute([$devRow['content'], $devRow['text'], $pageId]);

        // Mettre à jour la version la plus récente dans sppagebuilder_versions
        $latestVer = $pdo->prepare(
            "SELECT id FROM " . PROD_PREFIX . "sppagebuilder_versions WHERE page_id = ? ORDER BY id DESC LIMIT 1"
        );
        $latestVer->execute([$pageId]);
        $latestId = $latestVer->fetchColumn();
        if ($latestId) {
            $ver = $pdo->prepare("UPDATE " . PROD_PREFIX . "sppagebuilder_versions SET content = ? WHERE id = ?");
            $ver->execute([$devRow['text'], $latestId]);
        }

        $sppb_synced[] = "page $pageId (" . strlen($devRow['content']) . " bytes)";
        $log[] = "✓ SPPB page $pageId synced from dev";
    } catch (Exception $e) {
        $errors[] = "SPPB page $pageId: " . $e->getMessage();
    }
}

// ─── 2b. Synchroniser les fichiers template dev → prod ────────────────────────
$root        = '/srv/data/web/vhosts/kunming-afchine.org/htdocs';
$dev_root    = '/srv/data/web/vhosts/dev.kunming-afchine.org/htdocs';
$files_synced = [];
foreach (TEMPLATE_FILES as $relPath) {
    $src = $dev_root  . '/' . $relPath;
    $dst = $root . '/' . $relPath;
    if (!file_exists($src)) {
        $errors[] = "File not found in dev: $relPath";
        continue;
    }
    if (@copy($src, $dst)) {
        $files_synced[] = $relPath;
        $log[] = "✓ File synced: $relPath";
    } else {
        $errors[] = "Failed to copy: $relPath";
    }
}

// ─── 3. Vider les caches fichiers Joomla ────────────────────────────────────
$cacheDirs = [
    $root . '/cache',
    $root . '/administrator/cache',
];

$cache_cleared = 0;
foreach ($cacheDirs as $dir) {
    if (!is_dir($dir)) continue;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $f) {
        if ($f->isFile() && $f->getFilename() !== '.htaccess' && $f->getFilename() !== 'index.html') {
            @unlink($f->getPathname());
            $cache_cleared++;
        }
    }
}
$log[] = "✓ Joomla file cache cleared ($cache_cleared files)";

// ─── 4. Vider l'OPcache PHP ─────────────────────────────────────────────────
if (function_exists('opcache_reset')) {
    opcache_reset();
    $log[] = "✓ OPcache reset";
} else {
    $log[] = "- OPcache not available";
}

// ─── 5. Vider APCu ──────────────────────────────────────────────────────────
if (function_exists('apcu_clear_cache')) {
    apcu_clear_cache();
    $log[] = "✓ APCu cleared";
} else {
    $log[] = "- APCu not available";
}

// ─── 6. Purger Varnish (PURGE depuis le serveur lui-même) ───────────────────
$varnish_purged = 0;
$varnish_failed = 0;
$urlsToPurge = [
    'http://kunming-afchine.org/',
    'http://kunming-afchine.org/?lang=fr',
    'http://kunming-afchine.org/?lang=en',
    'http://kunming-afchine.org/?lang=zh',
    'http://kunming-afchine.org/index.php',
];
foreach ($urlsToPurge as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PURGE',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Host: kunming-afchine.org'],
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 400) $varnish_purged++;
    else $varnish_failed++;
}
// Purge globale via X-Purge-Regex si supporté
$ch = curl_init('http://kunming-afchine.org/');
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST  => 'PURGE',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
    CURLOPT_HTTPHEADER     => [
        'Host: kunming-afchine.org',
        'X-Purge-Regex: .*',
    ],
]);
curl_exec($ch);
curl_close($ch);

$log[] = "✓ Varnish purge: $varnish_purged OK, $varnish_failed failed";

// ─── 7. Vérification post-déploiement ───────────────────────────────────────
sleep(1); // Laisser le temps aux caches de se réinitialiser

$verify = [];
$checkUrls = [
    'homepage'   => 'https://kunming-afchine.org/',
    'direct_sppb' => 'https://kunming-afchine.org/?option=com_sppagebuilder&view=page&id=173',
];

foreach ($checkUrls as $label => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (deploy-verify)',
        CURLOPT_HTTPHEADER     => ['Cache-Control: no-cache'],
    ]);
    $html  = curl_exec($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$html) {
        $verify[$label] = ['status' => $code, 'error' => 'No response'];
        continue;
    }

    // Chercher des marqueurs clés dans la page
    $markers = [
        '191123f9'                   => substr_count($html, '191123f9'),  // Notre méthode
        'section-id-113b9392'        => substr_count($html, 'section-id-113b9392'),
        'section-id-681188c7'        => substr_count($html, 'section-id-681188c7'),
        'sppb-col-sm-4'              => substr_count($html, 'sppb-col-sm-4'),  // colonnes 33%
        'sppb-col-sm-6'              => substr_count($html, 'sppb-col-sm-6'),  // colonnes 50% (ancien)
    ];

    $verify[$label] = [
        'status'  => $code,
        'markers' => $markers,
        'ok'      => ($markers['191123f9'] > 0 && $markers['sppb-col-sm-4'] > 0),
    ];
}

// ─── Résultat ────────────────────────────────────────────────────────────────
$success = empty($errors) &&
    ($verify['homepage']['ok'] ?? false) &&
    ($verify['direct_sppb']['ok'] ?? false);

echo json_encode([
    'success'      => $success,
    'sppb_synced'  => $sppb_synced,
    'files_synced' => $files_synced,
    'cache_cleared'=> $cache_cleared,
    'log'          => $log,
    'verify'       => $verify,
    'errors'       => $errors,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
