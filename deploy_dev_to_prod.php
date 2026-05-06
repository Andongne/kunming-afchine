<?php
/**
 * deploy_dev_to_prod.php — v3 (2026-05-06)
 * Déploiement complet dev.kunming-afchine.org → production
 *
 * Modes :
 *   ?token=...            → déploiement complet
 *   ?token=...&dry=1      → pré-vol uniquement (diff sans modifier)
 *   ?token=...&check=1    → vérification post-déploiement uniquement
 *
 * Usage : https://kunming-afchine.org/falang-inject/deploy_dev_to_prod.php?token=FALANG_SECRET_TOKEN_AFK_2026
 */

define('DEPLOY_TOKEN', 'FALANG_SECRET_TOKEN_AFK_2026');
define('P',  'bwhwo_');
define('D',  'dev_');
define('LOG_FILE', __DIR__ . '/deploy_log.txt');

$token = $_SERVER['HTTP_X_FALANG_TOKEN'] ?? $_GET['token'] ?? '';
if ($token !== DEPLOY_TOKEN) { http_response_code(403); die('Unauthorized'); }

$dry_run   = !empty($_GET['dry']);
$check_only = !empty($_GET['check']);

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = new PDO("mysql:host=localhost;dbname=Kunming.org;charset=utf8mb4",
        'root', 'Igouzing831917', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("SET NAMES utf8mb4");
} catch (Exception $e) { die("DB: " . $e->getMessage()); }

$log = []; $errors = []; $diff = []; $start = microtime(true);
$ts = date('Y-m-d H:i:s');

// ════════════════════════════════════════════════════════════════════════════
// SECTION 1 — PRÉ-VOL : DIFF COMPLET dev vs prod
// ════════════════════════════════════════════════════════════════════════════
echo "╔══════════════════════════════════════════╗\n";
echo "║  DÉPLOIEMENT $ts  ║\n";
echo "╚══════════════════════════════════════════╝\n\n";

if ($check_only) goto post_check;

echo "── 1. PRÉ-VOL (diff complet) ───────────────\n\n";

// 1a. SP Builder — pages modifiées ou nouvelles
$dev_spb  = $pdo->query("SELECT id, MD5(content) h, title FROM ".D."sppagebuilder ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$prod_spb = $pdo->query("SELECT id, MD5(content) h FROM ".P."sppagebuilder")->fetchAll(PDO::FETCH_KEY_PAIR);
foreach ($dev_spb as $r) {
    if (!isset($prod_spb[$r['id']])) {
        $diff[] = "[SPB] Nouvelle page id={$r['id']} \"{$r['title']}\"";
    } elseif ($prod_spb[$r['id']] !== $r['h']) {
        $diff[] = "[SPB] Page modifiée id={$r['id']} \"{$r['title']}\"";
    }
}

// 1b. RSForm — champs critiques
foreach ([4, 6] as $fid) {
    foreach (['JS','FormLayout','Thankyou','UserEmailText','AdminEmailText'] as $f) {
        $dh = $pdo->query("SELECT MD5(`$f`) FROM ".D."rsform_forms WHERE FormId=$fid")->fetchColumn();
        $ph = $pdo->query("SELECT MD5(`$f`) FROM ".P."rsform_forms WHERE FormId=$fid")->fetchColumn();
        if ($dh !== $ph) $diff[] = "[Form$fid] Champ $f modifié";
    }
}

// 1c. Composants RSForm manquants
$dev_cids  = $pdo->query("SELECT ComponentId FROM ".D."rsform_components WHERE FormId IN (4,6)")->fetchAll(PDO::FETCH_COLUMN);
$prod_cids = $pdo->query("SELECT ComponentId FROM ".P."rsform_components WHERE FormId IN (4,6)")->fetchAll(PDO::FETCH_COLUMN);
$missing_comps = array_diff($dev_cids, $prod_cids);
foreach ($missing_comps as $cid) $diff[] = "[Composant] C$cid absent en prod";

// 1d. Menus manquants
$dev_aliases  = $pdo->query("SELECT alias FROM ".D."menu WHERE client_id=0 AND published=1")->fetchAll(PDO::FETCH_COLUMN);
$prod_aliases = $pdo->query("SELECT alias FROM ".P."menu WHERE client_id=0")->fetchAll(PDO::FETCH_COLUMN);
foreach (array_diff($dev_aliases, $prod_aliases) as $a) $diff[] = "[Menu] '$a' absent en prod";

// 1e. Menus orphelins (lft=0)
$orphans = $pdo->query("SELECT id,alias FROM ".P."menu WHERE lft=0 AND rgt=0 AND client_id=0")->fetchAll(PDO::FETCH_ASSOC);
foreach ($orphans as $o) $diff[] = "[Menu] Item '{$o['alias']}' orphelin (lft=0) en prod";

// 1f. Articles manquants
$dev_art_aliases  = $pdo->query("SELECT alias FROM ".D."content WHERE state=1")->fetchAll(PDO::FETCH_COLUMN);
$prod_art_aliases = $pdo->query("SELECT alias FROM ".P."content")->fetchAll(PDO::FETCH_COLUMN);
foreach (array_diff($dev_art_aliases, $prod_art_aliases) as $a) $diff[] = "[Article] '$a' absent en prod";

// 1g. Modules manquants (positions custom)
$dev_mods_custom  = $pdo->query("SELECT id,title,position FROM ".D."modules WHERE position IN ('rse-calendar-sidebar','rse-exams-sidebar')")->fetchAll(PDO::FETCH_ASSOC);
$prod_mod_ids     = $pdo->query("SELECT id FROM ".P."modules")->fetchAll(PDO::FETCH_COLUMN);
foreach ($dev_mods_custom as $m) {
    if (!in_array($m['id'], $prod_mod_ids)) $diff[] = "[Module] id={$m['id']} '{$m['title']}' ({$m['position']}) absent en prod";
}

// 1h. Fichiers template
$dev_root  = '/srv/data/web/vhosts/dev.kunming-afchine.org/htdocs';
$root      = '/srv/data/web/vhosts/kunming-afchine.org/htdocs';
$tpl_files = [
    'templates/shaper_languageschool/css/afk-styles.css',
    'templates/shaper_languageschool/css/mobile_cards.css',
    'templates/shaper_languageschool/js/mobile-menu.js',
    'templates/shaper_languageschool/index.php',
    'templates/shaper_languageschool/html/com_rsform/rsform/default.php',
    'templates/shaper_languageschool/html/com_rseventspro/rseventspro/default.php',
    'templates/shaper_languageschool/html/com_rseventspro/rseventspro/items_events.php',
    'templates/shaper_languageschool/html/com_rseventspro/rseventspro/show.php',
    'templates/shaper_languageschool/html/com_rseventspro/calendar/default.php',
    'templates/shaper_languageschool/html/com_rseventspro/calendar/module.php',
    'templates/shaper_languageschool/html/mod_rseventspro_upcoming/default.php',
];
foreach ($tpl_files as $rel) {
    $src = "$dev_root/$rel"; $dst = "$root/$rel";
    if (!file_exists($src)) { $diff[] = "[Fichier] $rel absent en dev (ignoré)"; continue; }
    if (!file_exists($dst)) { $diff[] = "[Fichier] $rel absent en prod → à copier"; continue; }
    if (md5_file($src) !== md5_file($dst)) $diff[] = "[Fichier] $rel modifié";
}

// Afficher le diff
if (empty($diff)) {
    echo "✓ Dev et prod sont identiques — rien à déployer.\n\n";
} else {
    echo count($diff) . " différence(s) détectée(s) :\n";
    foreach ($diff as $d) echo "  → $d\n";
    echo "\n";
}

if ($dry_run) {
    echo "Mode dry-run — aucune modification effectuée.\n";
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 2 — DÉPLOIEMENT
// ════════════════════════════════════════════════════════════════════════════
echo "── 2. DÉPLOIEMENT ──────────────────────────\n\n";

// 2a. SP Builder — nouvelles pages + pages modifiées
try {
    $pdo->exec("INSERT IGNORE INTO ".P."sppagebuilder SELECT * FROM ".D."sppagebuilder");
    $updated_spb = 0;
    foreach ($dev_spb as $r) {
        if (!isset($prod_spb[$r['id']])) continue; // nouveau, déjà inséré
        if ($prod_spb[$r['id']] === $r['h']) continue; // identique
        $row = $pdo->query("SELECT content,text,title FROM ".D."sppagebuilder WHERE id={$r['id']}")->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("UPDATE ".P."sppagebuilder SET content=?,text=?,title=? WHERE id={$r['id']}")->execute([$row['content'],$row['text'],$row['title']]);
        $lid = $pdo->query("SELECT id FROM ".P."sppagebuilder_versions WHERE page_id={$r['id']} ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($lid) $pdo->prepare("UPDATE ".P."sppagebuilder_versions SET content=? WHERE id=?")->execute([$row['text'],$lid]);
        $updated_spb++;
    }
    $new_spb = count(array_filter($diff, fn($d) => strpos($d,'[SPB] Nouvelle') !== false));
    $log[] = "✓ SPB: $new_spb nouvelle(s) + $updated_spb mise(s) à jour";
} catch (Exception $e) { $errors[] = "SPB: " . $e->getMessage(); }

// 2b. RSForm — champs critiques forms 4 et 6
$form_fields = ['JS','FormLayout','Thankyou',
    'UserEmailSubject','UserEmailText','UserEmailTo','UserEmailFrom','UserEmailFromName','UserEmailMode',
    'AdminEmailSubject','AdminEmailText','AdminEmailTo','AdminEmailFrom','AdminEmailFromName','AdminEmailMode'];
foreach ([4, 6] as $fid) {
    try {
        $row = $pdo->query("SELECT ".implode(',',$form_fields)." FROM ".D."rsform_forms WHERE FormId=$fid")->fetch(PDO::FETCH_ASSOC);
        if (!$row) { $errors[] = "Form $fid absent en dev"; continue; }
        foreach ($form_fields as $f) $pdo->prepare("UPDATE ".P."rsform_forms SET `$f`=? WHERE FormId=$fid")->execute([$row[$f]]);
        $log[] = "✓ Form $fid (". count($form_fields) ." champs)";
    } catch (Exception $e) { $errors[] = "Form $fid: " . $e->getMessage(); }
}

// 2c. Composants RSForm manquants
if (!empty($missing_comps)) {
    $type_map_xml = [
        'textbox' => 1,'text' => 1,'textarea' => 2,'selectlist' => 3,'select' => 3,
        'calendar' => 6,'checkboxgroup' => 4,'checkbox' => 4,'radiogroup' => 5,'radio' => 5,
        'button' => 7,'captcha' => 8,'fileupload' => 9,'file' => 9,
        'freetext' => 10,'hidden' => 11,'submitbutton' => 13,'submit' => 13,'password' => 14,
    ];
    $comp_ins = 0;
    foreach ($missing_comps as $cid) {
        try {
            $dev_c = $pdo->query("SELECT * FROM ".D."rsform_components WHERE ComponentId=$cid")->fetch(PDO::FETCH_ASSOC);
            if (!$dev_c) continue;
            $xml = strtolower($dev_c['XMLFile'] ?? '');
            $type_id = 1;
            foreach ($type_map_xml as $kw => $tid) { if (strpos($xml,$kw)!==false){$type_id=$tid;break;} }
            // Fallback via properties si XMLFile ne donne rien
            if ($type_id === 1 && empty($xml)) {
                $pnames = $pdo->query("SELECT PropertyName FROM ".D."rsform_properties WHERE ComponentId=$cid")->fetchAll(PDO::FETCH_COLUMN);
                $pset = array_flip($pnames);
                if (isset($pset['ITEMS'])&&isset($pset['FLOW'])) $type_id=4;
                elseif (isset($pset['ITEMS'])) $type_id=3;
                elseif (isset($pset['ROWS'])) $type_id=2;
                elseif (isset($pset['DATEORDERING'])) $type_id=6;
                elseif (isset($pset['BUTTONTYPE'])) $type_id=7;
                elseif (isset($pset['FILETYPE'])) $type_id=9;
                elseif (isset($pset['TEXT'])&&!isset($pset['MAXLENGTH'])) $type_id=10;
            }
            $ord = $dev_c['Ordering'] ?? 99; $pub = $dev_c['Published'] ?? 1; $fid = $dev_c['FormId'];
            $pdo->exec("INSERT IGNORE INTO ".P."rsform_components (ComponentId,ComponentTypeId,FormId,`Order`,Published) VALUES ($cid,$type_id,$fid,$ord,$pub)");
            $pdo->exec("INSERT IGNORE INTO ".P."rsform_properties SELECT * FROM ".D."rsform_properties WHERE ComponentId=$cid");
            $pdo->exec("INSERT IGNORE INTO ".P."rsform_translations SELECT * FROM ".D."rsform_translations WHERE ComponentId=$cid");
            $comp_ins++;
        } catch (Exception $e) { $errors[] = "Composant $cid: " . $e->getMessage(); }
    }
    $log[] = "✓ $comp_ins composants RSForm créés";
}

// 2d. RSForm Published/Required sync
foreach ([4, 6] as $fid) {
    $hidden = $pdo->query("SELECT ComponentId FROM ".D."rsform_components WHERE FormId=$fid AND Published=0")->fetchAll(PDO::FETCH_COLUMN);
    if ($hidden) {
        $ids = implode(',', $hidden);
        $pdo->exec("UPDATE ".P."rsform_components SET Published=0 WHERE ComponentId IN ($ids) AND FormId=$fid");
        $pdo->exec("UPDATE ".P."rsform_properties SET PropertyValue='NO' WHERE ComponentId IN ($ids) AND PropertyName='REQUIRED'");
        $log[] = "✓ Form $fid — " . count($hidden) . " composant(s) masqué(s)";
    }
}

// 2e. Menus manquants
$prod_aliases_now = $pdo->query("SELECT alias FROM ".P."menu WHERE client_id=0")->fetchAll(PDO::FETCH_COLUMN);
$dev_menu_rows    = $pdo->query("SELECT * FROM ".D."menu WHERE client_id=0 AND published=1")->fetchAll(PDO::FETCH_ASSOC);
$prod_menu_by_alias = array_column($pdo->query("SELECT id,alias FROM ".P."menu WHERE client_id=0")->fetchAll(PDO::FETCH_ASSOC),'id','alias');
$menus_added = 0;
foreach ($dev_menu_rows as $item) {
    if (in_array($item['alias'], $prod_aliases_now)) continue;
    $parent_alias = $pdo->query("SELECT alias FROM ".D."menu WHERE id={$item['parent_id']}")->fetchColumn();
    $parent_prod  = $parent_alias ? ($prod_menu_by_alias[$parent_alias] ?? $item['parent_id']) : $item['parent_id'];
    $prod_cols    = $pdo->query("SHOW COLUMNS FROM ".P."menu")->fetchAll(PDO::FETCH_COLUMN);
    $dev_cols     = $pdo->query("SHOW COLUMNS FROM ".D."menu")->fetchAll(PDO::FETCH_COLUMN);
    $vals = [];
    foreach ($prod_cols as $col) {
        if ($col==='id'){$vals[]=null;continue;}
        if ($col==='parent_id'){$vals[]=$parent_prod;continue;}
        if ($col==='checked_out_time'){$vals[]=null;continue;}
        $vals[] = in_array($col,$dev_cols) ? ($item[$col]??null) : null;
    }
    try {
        $pdo->prepare("INSERT INTO ".P."menu (`".implode('`,`',$prod_cols)."`) VALUES (".implode(',',array_fill(0,count($prod_cols),'?')).")")->execute($vals);
        $new_id = $pdo->lastInsertId();
        $prod_menu_by_alias[$item['alias']] = $new_id;
        $prod_aliases_now[] = $item['alias'];
        $menus_added++;
    } catch (Exception $e) { $errors[] = "Menu '{$item['alias']}': " . $e->getMessage(); }
}
if ($menus_added) $log[] = "✓ $menus_added item(s) de menu créé(s)";

// 2f. Rebuild menu tree (orphelins lft=0)
try {
    $orphans = $pdo->query("SELECT id,parent_id,alias FROM ".P."menu WHERE lft=0 AND rgt=0 AND client_id=0")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($orphans as $item) {
        $parent_rgt = $pdo->query("SELECT rgt FROM ".P."menu WHERE id={$item['parent_id']}")->fetchColumn();
        if (!$parent_rgt) continue;
        $pdo->exec("UPDATE ".P."menu SET rgt=rgt+2 WHERE rgt>=$parent_rgt");
        $pdo->exec("UPDATE ".P."menu SET lft=lft+2 WHERE lft>$parent_rgt");
        $pdo->exec("UPDATE ".P."menu SET lft=$parent_rgt, rgt=".($parent_rgt+1)." WHERE id={$item['id']}");
        $log[] = "✓ Menu '{$item['alias']}' positionné dans l'arbre (lft=$parent_rgt)";
    }
} catch (Exception $e) { $errors[] = "Rebuild menu: " . $e->getMessage(); }

// 2g. Articles manquants
$new_art = array_diff($dev_art_aliases, $prod_art_aliases);
foreach ($new_art as $alias) {
    $a = $pdo->query("SELECT * FROM ".D."content WHERE alias='".addslashes($alias)."' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $pc = $pdo->query("SHOW COLUMNS FROM ".P."content")->fetchAll(PDO::FETCH_COLUMN);
    $dc = $pdo->query("SHOW COLUMNS FROM ".D."content")->fetchAll(PDO::FETCH_COLUMN);
    $vals = array_map(fn($c) => $c==='id' ? null : ($c==='checked_out' ? 0 : (in_array($c,$dc) ? ($a[$c]??null) : null)), $pc);
    try {
        $pdo->prepare("INSERT INTO ".P."content (`".implode('`,`',$pc)."`) VALUES (".implode(',',array_fill(0,count($pc),'?')).")")->execute($vals);
        $log[] = "✓ Article '$alias' (id=" . $pdo->lastInsertId() . ")";
    } catch (Exception $e) { $errors[] = "Article '$alias': " . $e->getMessage(); }
}

// 2h. FaLang
try {
    $menu_map = []; $art_map = [];
    foreach ($pdo->query("SELECT dm.id dev_id, pm.id prod_id FROM ".D."menu dm JOIN ".P."menu pm ON pm.alias=dm.alias WHERE dm.client_id=0")->fetchAll(PDO::FETCH_ASSOC) as $r) $menu_map[$r['dev_id']]=$r['prod_id'];
    foreach ($pdo->query("SELECT dc.id dev_id, pc.id prod_id FROM ".D."content dc JOIN ".P."content pc ON pc.alias=dc.alias")->fetchAll(PDO::FETCH_ASSOC) as $r) $art_map[$r['dev_id']]=$r['prod_id'];
    $dev_fc = $pdo->query("SELECT * FROM ".D."falang_content")->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("INSERT IGNORE INTO ".P."falang_content (language_id,reference_id,reference_table,reference_field,value,original_value,published) VALUES (?,?,?,?,?,?,?)");
    $fc_ins = 0;
    foreach ($dev_fc as $row) {
        $prod_ref = match($row['reference_table']) { 'menu' => $menu_map[$row['reference_id']] ?? null, 'content' => $art_map[$row['reference_id']] ?? null, default => $row['reference_id'] };
        if (!$prod_ref) continue;
        $stmt->execute([$row['language_id'],$prod_ref,$row['reference_table'],$row['reference_field'],$row['value'],$row['original_value']??'',$row['published']]);
        if ($stmt->rowCount()>0) $fc_ins++;
    }
    $log[] = "✓ FaLang: $fc_ins traduction(s) copiée(s)";
} catch (Exception $e) { $errors[] = "FaLang: " . $e->getMessage(); }

// 2i. Modules sidebar
try {
    $pdo->exec("INSERT IGNORE INTO ".P."modules SELECT * FROM ".D."modules WHERE position IN ('rse-calendar-sidebar','rse-exams-sidebar')");
    // Sync position/published pour les modules qui existaient déjà avec mauvaises valeurs
    $pdo->exec("UPDATE ".P."modules pm JOIN ".D."modules dm ON dm.id=pm.id SET pm.position=dm.position, pm.published=dm.published WHERE dm.position IN ('rse-calendar-sidebar','rse-exams-sidebar')");
    // Assignations menu
    $map_src = $pdo->query("SELECT dm.id dev_id, pm.id prod_id FROM ".D."menu dm JOIN ".P."menu pm ON pm.alias=dm.alias")->fetchAll(PDO::FETCH_ASSOC);
    $menu_map_full = array_column($map_src,'prod_id','dev_id');
    $mm_rows = $pdo->query("SELECT moduleid,menuid FROM ".D."modules_menu WHERE moduleid IN (SELECT id FROM ".D."modules WHERE position IN ('rse-calendar-sidebar','rse-exams-sidebar'))")->fetchAll(PDO::FETCH_ASSOC);
    $mm_stmt = $pdo->prepare("INSERT IGNORE INTO ".P."modules_menu (moduleid,menuid) VALUES (?,?)");
    foreach ($mm_rows as $mm) {
        $prod_menuid = $menu_map_full[$mm['menuid']] ?? $mm['menuid'];
        $mm_stmt->execute([$mm['moduleid'],$prod_menuid]);
        // Aussi menuid=-1 (toutes pages) pour le fallback ModuleHelper
        $mm_stmt->execute([$mm['moduleid'], -1]);
    }
    $log[] = "✓ Modules sidebar synchronisés";
} catch (Exception $e) { $errors[] = "Modules: " . $e->getMessage(); }

// 2j. RSEvents cours (non TCF/TEF)
try {
    $pdo->exec("INSERT IGNORE INTO ".P."rseventspro_events SELECT * FROM ".D."rseventspro_events WHERE name NOT LIKE '%TCF%' AND name NOT LIKE '%TEF%'");
    $log[] = "✓ RSEvents cours synchronisés";
} catch (Exception $e) { $errors[] = "RSEvents cours: " . $e->getMessage(); }

// 2k. Fichiers template
$files_copied = 0;
foreach ($tpl_files as $rel) {
    $src = "$dev_root/$rel"; $dst = "$root/$rel";
    if (!file_exists($src)) continue;
    if (!is_dir(dirname($dst))) @mkdir(dirname($dst), 0755, true);
    if (@copy($src, $dst)) { $files_copied++; }
    else { $errors[] = "Fichier: $rel"; }
}
$log[] = "✓ $files_copied fichier(s) template copiés";

// ════════════════════════════════════════════════════════════════════════════
// SECTION 3 — CACHE & VARNISH
// ════════════════════════════════════════════════════════════════════════════
echo "── 3. CACHE & VARNISH ──────────────────────\n\n";

$cleared = 0;
foreach (["$root/cache", "$root/administrator/cache"] as $dir) {
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) { if ($f->isFile()) { @unlink($f->getPathname()); $cleared++; } }
}
if (function_exists('opcache_reset')) opcache_reset();
if (function_exists('apcu_clear_cache')) apcu_clear_cache();
$log[] = "✓ Cache Joomla: $cleared fichiers vidés";

// Varnish — purge ciblée sur les URLs affectées
$purge_urls = [
    'http://kunming-afchine.org/',
    'http://kunming-afchine.org/cours-de-francais/inscription-cours-de-francais/calendrier-des-cours',
    'http://kunming-afchine.org/cours-de-francais/inscription-cours-de-francais/reservation-cours-en-ligne',
    'http://kunming-afchine.org/certifications-et-diplomes/inscription-aux-tests-et-certifications/calendrier-sessions',
    'http://kunming-afchine.org/certifications-et-diplomes/inscription-aux-tests-et-certifications/formulaire-inscription-aux-examen',
];
$purged = 0;
foreach ($purge_urls as $url) {
    foreach (['', '?lang=zh-CN', '?lang=en-GB'] as $qs) {
        $ch = curl_init($url.$qs);
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PURGE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4]);
        curl_exec($ch); $code=curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
        if ($code>=200&&$code<400) $purged++;
    }
}
// Purge globale regex
$ch = curl_init('http://kunming-afchine.org/');
curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'PURGE',CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>4,CURLOPT_HTTPHEADER=>['X-Purge-Regex: .*']]);
curl_exec($ch); curl_close($ch);
$log[] = "✓ Varnish: $purged URLs purgées + regex globale";

// ════════════════════════════════════════════════════════════════════════════
// SECTION 4 — VÉRIFICATION POST-DÉPLOIEMENT
// ════════════════════════════════════════════════════════════════════════════
post_check:
sleep(1);
echo "── 4. VÉRIFICATION POST-DÉPLOIEMENT ────────\n\n";

$check_pages = [
    ['label' => 'Accueil FR',       'url' => 'https://kunming-afchine.org/',                                                                                                                               'markers' => ['sp-component','Alliance Fran']],
    ['label' => 'Accueil ZH',       'url' => 'https://kunming-afchine.org/?lang=zh-CN',                                                                                                                   'markers' => ['sp-component']],
    ['label' => 'Cal. sessions FR', 'url' => 'https://kunming-afchine.org/certifications-et-diplomes/inscription-aux-tests-et-certifications/calendrier-sessions',                                        'markers' => ['afk-tarif-badge','formulaire-inscription']],
    ['label' => 'Cal. sessions ZH', 'url' => 'https://kunming-afchine.org/certifications-et-diplomes/inscription-aux-tests-et-certifications/calendrier-sessions?lang=zh-CN',                             'markers' => ['afk-tarif-badge']],
    ['label' => 'Form. examen FR',  'url' => 'https://kunming-afchine.org/certifications-et-diplomes/inscription-aux-tests-et-certifications/formulaire-inscription-aux-examen',                         'markers' => ['Choix_exam','rsf_form4_data','R\u00e9gler votre inscription']],
    ['label' => 'Cal. cours FR',    'url' => 'https://kunming-afchine.org/cours-de-francais/inscription-cours-de-francais/calendrier-des-cours',                                                          'markers' => ['rs_event_detail']],
    ['label' => 'Form. cours FR',   'url' => 'https://kunming-afchine.org/cours-de-francais/inscription-cours-de-francais/reservation-cours-en-ligne',                                                   'markers' => ['afk_cours_data','WeChat_ID','Inscription aux Cours']],
    ['label' => 'Form. cours ZH',   'url' => 'https://kunming-afchine.org/cours-de-francais/inscription-cours-de-francais/reservation-cours-en-ligne?lang=zh-CN',                                        'markers' => ['afk_cours_data','WeChat_ID']],
    ['label' => 'Form. cours EN',   'url' => 'https://kunming-afchine.org/cours-de-francais/inscription-cours-de-francais/reservation-cours-en-ligne?lang=en-GB',                                        'markers' => ['afk_cours_data','WeChat_ID']],
];

$all_ok = true;
foreach ($check_pages as $page) {
    $ch = curl_init($page['url']);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>15,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache']]);
    $html = curl_exec($ch);
    $code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);

    $status = $code === 200 ? '✓' : '✗';
    $missing = [];
    if ($html) foreach ($page['markers'] as $m) { if (strpos($html,$m)===false) $missing[] = $m; }
    if (!empty($missing)) $status = '✗';

    $msg = "$status [{$page['label']}] HTTP $code";
    if (!empty($missing)) $msg .= " — marqueurs manquants: " . implode(', ',$missing);
    if (preg_match('/Fatal error:|Parse error:/i', $html??'')) $msg .= " — PHP_ERR";

    echo "  $msg\n";
    if ($status !== '✓') { $errors[] = "CHECK: " . $page['label']; $all_ok = false; }
}

// ════════════════════════════════════════════════════════════════════════════
// SECTION 5 — RAPPORT FINAL + LOG
// ════════════════════════════════════════════════════════════════════════════
$duration = round(microtime(true) - $start, 1);
$status_global = (empty($errors) && $all_ok) ? 'OK' : 'PARTIEL';

echo "\n── RÉSULTAT ────────────────────────────────\n";
foreach ($log as $l) echo "  $l\n";
if (!empty($errors)) { echo "\n  ERREURS :\n"; foreach ($errors as $e) echo "  ✗ $e\n"; }
echo "\n  Durée : {$duration}s | Statut : $status_global\n";

// Log persistant
$log_entry = "\n[{$ts}] Statut={$status_global} Durée={$duration}s\n";
$log_entry .= "DIFF (" . count($diff) . "): " . implode(" | ", array_slice($diff,0,5)) . (count($diff)>5 ? " ..." : "") . "\n";
$log_entry .= "ACTIONS: " . implode(" | ", $log) . "\n";
if (!empty($errors)) $log_entry .= "ERREURS: " . implode(" | ", $errors) . "\n";
$log_entry .= "CHECK: " . ($all_ok ? "PASS" : "FAIL") . "\n";
@file_put_contents(LOG_FILE, $log_entry, FILE_APPEND);
