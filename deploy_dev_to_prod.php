<?php
/**
 * deploy_dev_to_prod.php — v2026-05-06c
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

// ─── 1. SPPB pages — sync toutes les pages modifiées dev → prod ─────────────
// Nouvelles pages (existent en dev mais pas en prod)
try {
    $pdo->exec("INSERT IGNORE INTO ".P."sppagebuilder SELECT * FROM ".D."sppagebuilder");
    $log[] = "✓ SPPB: nouvelles pages copiées";
} catch (Exception $e) { $errors[] = "SPPB insert: ".$e->getMessage(); }

// Mettre à jour les pages existantes des deux côtés (MD5 différent = contenu modifié)
try {
    $dev_pages  = $pdo->query("SELECT id, MD5(content) as h FROM ".D."sppagebuilder")->fetchAll(PDO::FETCH_ASSOC);
    $prod_pages = $pdo->query("SELECT id, MD5(content) as h FROM ".P."sppagebuilder")->fetchAll(PDO::FETCH_KEY_PAIR);
    $updated = 0;
    foreach ($dev_pages as $dp) {
        if (!isset($prod_pages[$dp['id']])) continue; // nouveau, déjà copié
        if ($prod_pages[$dp['id']] === $dp['h']) continue; // identique
        $row = $pdo->query("SELECT content,text,title FROM ".D."sppagebuilder WHERE id={$dp['id']}")->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE ".P."sppagebuilder SET content=?,text=?,title=? WHERE id=".$dp['id'])
            ->execute([$row['content'],$row['text'],$row['title']]);
        $lid = $pdo->query("SELECT id FROM ".P."sppagebuilder_versions WHERE page_id={$dp['id']} ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($lid) $pdo->prepare("UPDATE ".P."sppagebuilder_versions SET content=? WHERE id=?")->execute([$row['text'],$lid]);
        $updated++;
        $log[] = "✓ SPPB page {$dp['id']} mis à jour ({$row['title']})";
    }
    if (!$updated) $log[] = "- SPPB: aucune page à mettre à jour";
} catch (Exception $e) { $errors[] = "SPPB update: ".$e->getMessage(); }

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

// ─── 6b. FaLang — nouvelles traductions dev → prod ──────────────────────────
try {
    // Mapping alias → ID pour menus et articles
    $menu_map = [];
    $dev_menu_rows  = $pdo->query("SELECT id,alias FROM ".D."menu WHERE client_id=0")->fetchAll(PDO::FETCH_ASSOC);
    $prod_menu_rows = $pdo->query("SELECT id,alias FROM ".P."menu WHERE client_id=0")->fetchAll(PDO::FETCH_ASSOC);
    $prod_menu_by_alias = array_column($prod_menu_rows,'id','alias');
    foreach ($dev_menu_rows as $dm) {
        if (isset($prod_menu_by_alias[$dm['alias']])) $menu_map[$dm['id']] = $prod_menu_by_alias[$dm['alias']];
    }
    $content_map = [];
    $dev_art_rows  = $pdo->query("SELECT id,alias FROM ".D."content WHERE state=1")->fetchAll(PDO::FETCH_ASSOC);
    $prod_art_rows = $pdo->query("SELECT id,alias FROM ".P."content")->fetchAll(PDO::FETCH_ASSOC);
    $prod_art_by_alias = array_column($prod_art_rows,'id','alias');
    foreach ($dev_art_rows as $da) {
        if (isset($prod_art_by_alias[$da['alias']])) $content_map[$da['id']] = $prod_art_by_alias[$da['alias']];
    }

    // Lire toutes les traductions dev
    $dev_fc = $pdo->query("SELECT language_id,reference_id,reference_table,reference_field,value,original_value,published FROM ".D."falang_content")->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("INSERT IGNORE INTO ".P."falang_content (language_id,reference_id,reference_table,reference_field,value,original_value,published) VALUES (?,?,?,?,?,?,?)");
    $fc_ins = 0; $fc_skip = 0;
    foreach ($dev_fc as $row) {
        $prod_ref = match($row['reference_table']) {
            'menu'    => $menu_map[$row['reference_id']] ?? null,
            'content' => $content_map[$row['reference_id']] ?? null,
            default   => $row['reference_id']
        };
        if (!$prod_ref) { $fc_skip++; continue; }
        $stmt->execute([$row['language_id'],$prod_ref,$row['reference_table'],$row['reference_field'],$row['value'],$row['original_value']??'',$row['published']]);
        $stmt->rowCount() > 0 ? $fc_ins++ : $fc_skip++;
    }
    $log[] = "✓ FaLang: $fc_ins traductions copiées, $fc_skip ignorées";
} catch (Exception $e) { $errors[] = "FaLang: ".$e->getMessage(); }

// ─── 6c. Rebuild menu tree (lft/rgt) ─────────────────────────────────────────
// Items avec lft=0 ne s'affichent pas dans la nav — les insérer après le dernier enfant de leur parent
try {
    $orphans = $pdo->query("SELECT id,parent_id FROM ".P."menu WHERE lft=0 AND rgt=0 AND client_id=0")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orphans as $item) {
        $parent = $pdo->query("SELECT rgt FROM ".P."menu WHERE id={$item['parent_id']}")->fetchColumn();
        if (!$parent) continue;
        $insert_pos = $parent; // insérer juste avant le rgt du parent
        $pdo->exec("UPDATE ".P."menu SET rgt=rgt+2 WHERE rgt>=$insert_pos");
        $pdo->exec("UPDATE ".P."menu SET lft=lft+2 WHERE lft>$insert_pos");
        $pdo->exec("UPDATE ".P."menu SET lft=$insert_pos, rgt=".($insert_pos+1)." WHERE id={$item['id']}");
        $log[] = "✓ Menu item {$item['id']} positionné dans l'arbre (lft=$insert_pos)";
    }
    if (!$orphans) $log[] = "- Aucun item de menu orphelin (lft=0)";
} catch (Exception $e) { $errors[] = "Rebuild menu: ".$e->getMessage(); }

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
