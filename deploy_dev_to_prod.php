<?php
/**
 * deploy_dev_to_prod.php — v2026-05-06b
 * Déploiement complet dev → prod, avec sync automatique des enregistrements nouveaux.
 * Usage : https://kunming-afchine.org/falang-inject/deploy_dev_to_prod.php?token=FALANG_SECRET_TOKEN_AFK_2026
 */

define('DEPLOY_TOKEN', 'FALANG_SECRET_TOKEN_AFK_2026');
define('DB_HOST', 'localhost');
define('DB_NAME', 'Kunming.org');
define('DB_USER', 'root');
define('DB_PASS', 'Igouzing831917');
define('P', 'bwhwo_');
define('D', 'dev_');

$token = $_SERVER['HTTP_X_FALANG_TOKEN'] ?? $_GET['token'] ?? '';
if ($token !== DEPLOY_TOKEN) { http_response_code(403); die('Unauthorized'); }

header('Content-Type: text/plain; charset=utf-8');
$log = []; $errors = [];

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET NAMES utf8mb4");
} catch (Exception $e) { die("DB: ".$e->getMessage()); }

// ─── 1. SPPB pages ───────────────────────────────────────────────────────────
foreach ([173] as $pid) {
    $row = $pdo->query("SELECT content,text FROM ".D."sppagebuilder WHERE id=$pid")->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $errors[] = "SPPB $pid absent en dev"; continue; }
    $pdo->prepare("UPDATE ".P."sppagebuilder SET content=?,text=? WHERE id=?")->execute([$row['content'],$row['text'],$pid]);
    $lid = $pdo->query("SELECT id FROM ".P."sppagebuilder_versions WHERE page_id=$pid ORDER BY id DESC LIMIT 1")->fetchColumn();
    if ($lid) $pdo->prepare("UPDATE ".P."sppagebuilder_versions SET content=? WHERE id=?")->execute([$row['text'],$lid]);
    $log[] = "✓ SPPB page $pid";
}

// ─── 2. RSForm — champs critiques des formulaires ────────────────────────────
$form_fields = ['JS','FormLayout','Thankyou',
    'UserEmailSubject','UserEmailText','UserEmailTo','UserEmailFrom','UserEmailFromName','UserEmailMode',
    'AdminEmailSubject','AdminEmailText','AdminEmailTo','AdminEmailFrom','AdminEmailFromName','AdminEmailMode'];

foreach ([4, 6] as $fid) {
    $row = $pdo->query("SELECT ".implode(',',$form_fields)." FROM ".D."rsform_forms WHERE FormId=$fid")->fetch(PDO::FETCH_ASSOC);
    if (!$row) { $errors[] = "Form $fid absent en dev"; continue; }
    foreach ($form_fields as $f) {
        $pdo->prepare("UPDATE ".P."rsform_forms SET `$f`=? WHERE FormId=$fid")->execute([$row[$f]]);
    }
    $log[] = "✓ Form $fid (".count($form_fields)." champs)";
}

// ─── 3. RSForm — nouveaux composants dev → prod ───────────────────────────────
// Trouve les ComponentIds qui existent en dev mais pas en prod, pour les FormIds concernés
$dev_ids = $pdo->query("SELECT ComponentId FROM ".D."rsform_components WHERE FormId IN (4,6) ORDER BY ComponentId")->fetchAll(PDO::FETCH_COLUMN);
$prod_ids = $pdo->query("SELECT ComponentId FROM ".P."rsform_components WHERE FormId IN (4,6)")->fetchAll(PDO::FETCH_COLUMN);
$missing = array_diff($dev_ids, $prod_ids);

if ($missing) {
    foreach ($missing as $cid) {
        // Lire le composant dev
        $c = $pdo->query("SELECT * FROM ".D."rsform_components WHERE ComponentId=$cid")->fetch(PDO::FETCH_ASSOC);
        // Mapper les colonnes dev vers prod (schémas différents entre versions RSForm)
        $prod_cols = $pdo->query("SHOW COLUMNS FROM ".P."rsform_components")->fetchAll(PDO::FETCH_COLUMN);
        $dev_cols  = $pdo->query("SHOW COLUMNS FROM ".D."rsform_components")->fetchAll(PDO::FETCH_COLUMN);
        $common = array_intersect($prod_cols, $dev_cols);
        // Colonnes présentes en prod mais pas en dev → valeur par défaut
        $insert_cols = $prod_cols;
        $vals = [];
        foreach ($insert_cols as $col) {
            if (in_array($col, $dev_cols)) $vals[] = $c[$col] ?? null;
            else $vals[] = null; // valeur par défaut DB
        }
        $ph = implode(',', array_fill(0, count($insert_cols), '?'));
        $colstr = '`'.implode('`,`', $insert_cols).'`';
        try {
            $pdo->prepare("INSERT IGNORE INTO ".P."rsform_components ($colstr) VALUES ($ph)")->execute($vals);
            $log[] = "✓ Composant $cid (Form$cid[FormId]) créé en prod";
        } catch(Exception $e) { $errors[] = "Composant $cid: ".$e->getMessage(); }

        // Copier les propriétés
        $props = $pdo->query("SELECT * FROM ".D."rsform_properties WHERE ComponentId=$cid")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($props as $p) {
            $pdo->prepare("INSERT IGNORE INTO ".P."rsform_properties (ComponentId,PropertyName,PropertyValue) VALUES (?,?,?)")
                ->execute([$p['ComponentId'], $p['PropertyName'], $p['PropertyValue']]);
        }
        if ($props) $log[] = "  + ".count($props)." propriétés pour C$cid";

        // Copier les traductions RSForm
        $trans = $pdo->query("SELECT * FROM ".D."rsform_translations WHERE ComponentId=$cid")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($trans as $t) {
            try {
                $pdo->prepare("INSERT IGNORE INTO ".P."rsform_translations (ComponentId,LanguageCode,PropertyName,PropertyValue) VALUES (?,?,?,?)")
                    ->execute([$t['ComponentId'], $t['LanguageCode'], $t['PropertyName'], $t['PropertyValue']]);
            } catch(Exception $e2) {} // ignore duplicates
        }
        if ($trans) $log[] = "  + ".count($trans)." traductions pour C$cid";
    }
} else {
    $log[] = "- Aucun nouveau composant RSForm à synchroniser";
}

// ─── 4. RSForm — Published/Required sync (composants masqués) ────────────────
foreach ([4, 6] as $fid) {
    $hidden = $pdo->query("SELECT ComponentId FROM ".D."rsform_components WHERE FormId=$fid AND Published=0")->fetchAll(PDO::FETCH_COLUMN);
    if ($hidden) {
        $ids = implode(',', $hidden);
        $pdo->exec("UPDATE ".P."rsform_components SET Published=0 WHERE ComponentId IN ($ids) AND FormId=$fid");
        $pdo->exec("UPDATE ".P."rsform_properties SET PropertyValue='NO' WHERE ComponentId IN ($ids) AND PropertyName='REQUIRED'");
        $log[] = "✓ Form $fid — ".count($hidden)." composant(s) masqués";
    }
}

// ─── 5. Menus — nouveaux items dev → prod ────────────────────────────────────
$dev_menu = $pdo->query("SELECT * FROM ".D."menu WHERE client_id=0 AND published=1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$prod_aliases = $pdo->query("SELECT alias FROM ".P."menu WHERE client_id=0")->fetchAll(PDO::FETCH_COLUMN);

foreach ($dev_menu as $item) {
    if (in_array($item['alias'], $prod_aliases)) continue;
    // Trouver parent prod via alias
    $parent_alias = $pdo->query("SELECT alias FROM ".D."menu WHERE id={$item['parent_id']}")->fetchColumn();
    $parent_prod = $parent_alias ? $pdo->query("SELECT id FROM ".P."menu WHERE alias='$parent_alias' LIMIT 1")->fetchColumn() : $item['parent_id'];
    $prod_cols = $pdo->query("SHOW COLUMNS FROM ".P."menu")->fetchAll(PDO::FETCH_COLUMN);
    $dev_cols  = $pdo->query("SHOW COLUMNS FROM ".D."menu")->fetchAll(PDO::FETCH_COLUMN);
    $vals = [];
    foreach ($prod_cols as $col) {
        if ($col === 'id') { $vals[] = null; continue; }
        if ($col === 'parent_id') { $vals[] = $parent_prod ?: $item['parent_id']; continue; }
        if ($col === 'checked_out_time') { $vals[] = null; continue; }
        $vals[] = in_array($col, $dev_cols) ? ($item[$col] ?? null) : null;
    }
    $colstr = '`'.implode('`,`', $prod_cols).'`';
    $ph = implode(',', array_fill(0, count($prod_cols), '?'));
    try {
        $pdo->prepare("INSERT INTO ".P."menu ($colstr) VALUES ($ph)")->execute($vals);
        $new_id = $pdo->lastInsertId();
        $log[] = "✓ Menu item '{$item['alias']}' créé en prod (id=$new_id)";
        $prod_aliases[] = $item['alias'];
    } catch(Exception $e) { $errors[] = "Menu '{$item['alias']}': ".$e->getMessage(); }
}

// ─── 6. Articles — nouveaux contenus dev → prod ──────────────────────────────
$dev_art = $pdo->query("SELECT alias FROM ".D."content WHERE state=1")->fetchAll(PDO::FETCH_COLUMN);
$prod_art = $pdo->query("SELECT alias FROM ".P."content")->fetchAll(PDO::FETCH_COLUMN);
$new_art = array_diff($dev_art, $prod_art);

foreach ($new_art as $alias) {
    $a = $pdo->query("SELECT * FROM ".D."content WHERE alias='".addslashes($alias)."' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $prod_cols = $pdo->query("SHOW COLUMNS FROM ".P."content")->fetchAll(PDO::FETCH_COLUMN);
    $dev_cols  = $pdo->query("SHOW COLUMNS FROM ".D."content")->fetchAll(PDO::FETCH_COLUMN);
    $vals = [];
    foreach ($prod_cols as $col) {
        if ($col === 'id') { $vals[] = null; continue; }
        if ($col === 'checked_out') { $vals[] = 0; continue; }
        $vals[] = in_array($col, $dev_cols) ? ($a[$col] ?? null) : null;
    }
    $colstr = '`'.implode('`,`', $prod_cols).'`';
    $ph = implode(',', array_fill(0, count($prod_cols), '?'));
    try {
        $pdo->prepare("INSERT INTO ".P."content ($colstr) VALUES ($ph)")->execute($vals);
        $log[] = "✓ Article '$alias' créé en prod (id=".$pdo->lastInsertId().")";
    } catch(Exception $e) { $errors[] = "Article '$alias': ".$e->getMessage(); }
}
if (!$new_art) $log[] = "- Aucun nouvel article à synchroniser";

// ─── 7. Fichiers template ────────────────────────────────────────────────────
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
    $src = "$dev_root/$rel"; $dst = "$root/$rel";
    if (!file_exists($src)) { $errors[] = "Absent en dev: $rel"; continue; }
    if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
    if (@copy($src, $dst)) $log[] = "✓ $rel";
    else $errors[] = "Échec: $rel";
}

// ─── 8. Cache ────────────────────────────────────────────────────────────────
$cleared = 0;
foreach (["$root/cache", "$root/administrator/cache"] as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile()) { @unlink($f->getPathname()); $cleared++; } }
}
if (function_exists('opcache_reset')) opcache_reset();
if (function_exists('apcu_clear_cache')) apcu_clear_cache();
$log[] = "✓ Cache Joomla vidé ($cleared fichiers)";

foreach (['http://kunming-afchine.org/', 'http://kunming-afchine.org/?lang=zh-CN', 'http://kunming-afchine.org/?lang=en-GB'] as $url) {
    $ch = curl_init($url); curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PURGE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5]); curl_exec($ch); curl_close($ch);
}
$ch = curl_init('http://kunming-afchine.org/'); curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PURGE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5,CURLOPT_HTTPHEADER=>['X-Purge-Regex: .*']]); curl_exec($ch); curl_close($ch);
$log[] = "✓ Varnish purgé";

// ─── Résultat ────────────────────────────────────────────────────────────────
echo "=== DÉPLOIEMENT ".date('Y-m-d H:i:s')." ===\n\n";
echo implode("\n", $log)."\n";
if ($errors) echo "\n=== ERREURS ===\n".implode("\n", $errors)."\n\nSTATUS: PARTIEL\n";
else echo "\nSTATUS: OK\n";
