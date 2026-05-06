<?php
/**
 * deploy_dev_to_prod.php — v2026-05-06
 * Déploiement dev.kunming-afchine.org → production
 * Usage : https://kunming-afchine.org/falang-inject/deploy_dev_to_prod.php?token=FALANG_SECRET_TOKEN_AFK_2026
 */

define('DEPLOY_TOKEN', 'FALANG_SECRET_TOKEN_AFK_2026');
define('DB_HOST',   'localhost');
define('DB_NAME',   'Kunming.org');
define('DB_USER',   'root');
define('DB_PASS',   'Igouzing831917');
define('P',  'bwhwo_');   // préfixe prod
define('D',  'dev_');     // préfixe dev

$token = $_SERVER['HTTP_X_FALANG_TOKEN'] ?? $_GET['token'] ?? '';
if ($token !== DEPLOY_TOKEN) { http_response_code(403); die('Unauthorized'); }

header('Content-Type: text/plain; charset=utf-8');

$log    = [];
$errors = [];

// ─── DB ─────────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET NAMES utf8mb4");
} catch (Exception $e) { die("DB connection failed: ".$e->getMessage()); }

// ─── 1. SPPB page 173 ───────────────────────────────────────────────────────
foreach ([173] as $pid) {
    try {
        $row = $pdo->query("SELECT content,text FROM ".D."sppagebuilder WHERE id=$pid")->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $errors[] = "SPPB $pid: not found in dev"; continue; }
        $pdo->prepare("UPDATE ".P."sppagebuilder SET content=?,text=? WHERE id=?")->execute([$row['content'],$row['text'],$pid]);
        $lid = $pdo->query("SELECT id FROM ".P."sppagebuilder_versions WHERE page_id=$pid ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($lid) $pdo->prepare("UPDATE ".P."sppagebuilder_versions SET content=? WHERE id=?")->execute([$row['text'],$lid]);
        $log[] = "✓ SPPB page $pid";
    } catch (Exception $e) { $errors[] = "SPPB $pid: ".$e->getMessage(); }
}

// ─── 2. Formulaire examen (FormId=4) : champs critiques ─────────────────────
$form4_fields = ['JS','FormLayout','Thankyou'];
try {
    $row4 = $pdo->query("SELECT ".implode(',',$form4_fields)." FROM ".D."rsform_forms WHERE FormId=4")->fetch(PDO::FETCH_ASSOC);
    foreach ($form4_fields as $f) {
        $pdo->prepare("UPDATE ".P."rsform_forms SET `$f`=? WHERE FormId=4")->execute([$row4[$f]]);
        $log[] = "✓ Form4.$f";
    }
} catch (Exception $e) { $errors[] = "Form4 fields: ".$e->getMessage(); }

// Components form 4 : Passeport_fichier (85) et Photo_identite (86) masqués
try {
    $pdo->exec("UPDATE ".P."rsform_components SET Published=0 WHERE ComponentId IN (85,86)");
    $pdo->exec("UPDATE ".P."rsform_properties SET PropertyValue='NO' WHERE ComponentId IN (85,86) AND PropertyName='REQUIRED'");
    $log[] = "✓ Form4 components 85,86 masqués";
} catch (Exception $e) { $errors[] = "Form4 components: ".$e->getMessage(); }

// ─── 3. Formulaire cours (FormId=6) : tous les champs modifiés ──────────────
$form6_fields = [
    'JS','FormLayout','Thankyou',
    'UserEmailSubject','UserEmailText','UserEmailTo','UserEmailFrom','UserEmailFromName','UserEmailMode',
    'AdminEmailSubject','AdminEmailText','AdminEmailTo','AdminEmailFrom','AdminEmailFromName','AdminEmailMode',
];
try {
    $row6 = $pdo->query("SELECT ".implode(',',$form6_fields)." FROM ".D."rsform_forms WHERE FormId=6")->fetch(PDO::FETCH_ASSOC);
    foreach ($form6_fields as $f) {
        $pdo->prepare("UPDATE ".P."rsform_forms SET `$f`=? WHERE FormId=6")->execute([$row6[$f]]);
        $log[] = "✓ Form6.$f";
    }
} catch (Exception $e) { $errors[] = "Form6 fields: ".$e->getMessage(); }

// Components form 6 : champs masqués + non-obligatoires
// Récupérer les ComponentIds des champs masqués en dev
try {
    $dev_hidden = $pdo->query("SELECT ComponentId FROM ".D."rsform_components WHERE FormId=6 AND Published=0")->fetchAll(PDO::FETCH_COLUMN);
    if ($dev_hidden) {
        $ids = implode(',',$dev_hidden);
        $pdo->exec("UPDATE ".P."rsform_components SET Published=0 WHERE ComponentId IN ($ids) AND FormId=6");
        $pdo->exec("UPDATE ".P."rsform_properties SET PropertyValue='NO' WHERE ComponentId IN ($ids) AND PropertyName='REQUIRED'");
        $log[] = "✓ Form6 components masqués: ".count($dev_hidden);
    }
} catch (Exception $e) { $errors[] = "Form6 components: ".$e->getMessage(); }

// ─── 4. Nouvel article Blog TCF Canada (id=143) ──────────────────────────────
try {
    $alias = 'fidelia-loutil-daide-a-la-correction-automatisee-de-lepreuve-dexpression-ecrite-du-tcf-sur-ordinateur';
    $ex = $pdo->query("SELECT id FROM ".P."content WHERE alias='$alias'")->fetchColumn();
    if ($ex) {
        $log[] = "- Article déjà en prod (id=$ex)";
    } else {
        $a = $pdo->query("SELECT * FROM ".D."content WHERE alias='$alias' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($a) {
            $pdo->prepare("INSERT INTO ".P."content
              (`title`,`alias`,`introtext`,`fulltext`,`state`,`catid`,`created`,`created_by`,`created_by_alias`,
               `modified`,`modified_by`,`checked_out`,`publish_up`,`publish_down`,
               `images`,`urls`,`attribs`,`version`,`ordering`,`metakey`,`metadesc`,`access`,`hits`,
               `metadata`,`featured`,`language`,`note`)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([
                $a['title'],$a['alias'],$a['introtext'],$a['fulltext'],$a['state'],
                $a['catid'],$a['created'],$a['created_by'],$a['created_by_alias'],
                $a['modified'],$a['modified_by'],0,
                $a['publish_up'],$a['publish_down'],
                $a['images'],$a['urls'],$a['attribs'],1,0,
                $a['metakey'],$a['metadesc'],$a['access'],0,
                $a['metadata'],$a['featured'],$a['language'],$a['note']??''
            ]);
            $log[] = "✓ Article TCF Canada inséré (id=".$pdo->lastInsertId().")";
        } else {
            $errors[] = "Article non trouvé en dev";
        }
    }
} catch (Exception $e) { $errors[] = "Article: ".$e->getMessage(); }

// ─── 5. Fichiers template ────────────────────────────────────────────────────
$root     = '/srv/data/web/vhosts/kunming-afchine.org/htdocs';
$dev_root = '/srv/data/web/vhosts/dev.kunming-afchine.org/htdocs';

$template_files = [
    'templates/shaper_languageschool/css/afk-styles.css',
    'templates/shaper_languageschool/css/mobile_cards.css',
    'templates/shaper_languageschool/js/mobile-menu.js',
    'templates/shaper_languageschool/index.php',
    'templates/shaper_languageschool/html/com_rsform/rsform/default.php',
    'templates/shaper_languageschool/html/com_rseventspro/calendar/default.php',
    'templates/shaper_languageschool/html/com_rseventspro/calendar/module.php',
    'templates/shaper_languageschool/html/com_rseventspro/rseventspro/items_events.php',
    'templates/shaper_languageschool/html/com_rseventspro/rseventspro/show.php',
];

foreach ($template_files as $rel) {
    $src = "$dev_root/$rel";
    $dst = "$root/$rel";
    if (!file_exists($src)) { $errors[] = "Fichier absent en dev: $rel"; continue; }
    if (@copy($src, $dst)) { $log[] = "✓ $rel"; }
    else { $errors[] = "Échec copie: $rel"; }
}

// ─── 6. Vider les caches Joomla ─────────────────────────────────────────────
$cleared = 0;
foreach (["$root/cache", "$root/administrator/cache"] as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && !in_array($f->getFilename(),['.htaccess','index.html'])) {
            @unlink($f->getPathname()); $cleared++;
        }
    }
}
$log[] = "✓ Cache Joomla vidé ($cleared fichiers)";
if (function_exists('opcache_reset')) { opcache_reset(); $log[] = "✓ OPcache reset"; }
if (function_exists('apcu_clear_cache')) { apcu_clear_cache(); $log[] = "✓ APCu cleared"; }

// ─── 7. Purge Varnish ────────────────────────────────────────────────────────
$purge_urls = [
    'http://kunming-afchine.org/',
    'http://kunming-afchine.org/?lang=fr',
    'http://kunming-afchine.org/?lang=zh-CN',
    'http://kunming-afchine.org/?lang=en-GB',
];
$purged = 0;
foreach ($purge_urls as $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PURGE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5]);
    curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code>=200&&$code<400) $purged++;
}
// Purge globale
$ch = curl_init('http://kunming-afchine.org/');
curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PURGE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,
    CURLOPT_HTTPHEADER=>['X-Purge-Regex: .*']]);
curl_exec($ch); curl_close($ch);
$log[] = "✓ Varnish purge: $purged URLs";

// ─── Résultat ────────────────────────────────────────────────────────────────
echo "=== DÉPLOIEMENT ".date('Y-m-d H:i:s')." ===\n\n";
echo implode("\n",$log)."\n";
if ($errors) {
    echo "\n=== ERREURS ===\n".implode("\n",$errors)."\n";
    echo "\nSTATUS: PARTIEL\n";
} else {
    echo "\nSTATUS: OK\n";
}
