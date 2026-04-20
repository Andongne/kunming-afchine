<?php
ini_set('memory_limit','128M');
ini_set('max_execution_time','30');
header('Cache-Control: no-store, no-cache');
$token = $_SERVER['HTTP_X_FALANG_TOKEN'] ?? '';
if ($token !== 'FALANG_SECRET_TOKEN_AFK_2026') { die('Forbidden'); }
header('Content-Type: application/json; charset=utf-8');
$c = file_get_contents(dirname(__DIR__).'/configuration.php');
preg_match('/public \$host\s*=\s*\'([^\']+)\'/', $c, $mh);
preg_match('/public \$db\s*=\s*\'([^\']+)\'/', $c, $md);
preg_match('/public \$user\s*=\s*\'([^\']+)\'/', $c, $mu);
preg_match('/public \$password\s*=\s*\'([^\']+)\'/', $c, $mp);
preg_match('/public \$dbprefix\s*=\s*\'([^\']+)\'/', $c, $mx);
$pdo = new PDO("mysql:host={$mh[1]};dbname={$md[1]};charset=utf8mb4",$mu[1],$mp[1],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pfx = $mx[1];
$action = $_GET['action'] ?? '';

if ($action === 'slice') {
    $id = (int)($_GET['id'] ?? 173);
    $from = (int)($_GET['from'] ?? 0);
    $len = (int)($_GET['len'] ?? 3000);
    $stmt = $pdo->prepare("SELECT SUBSTRING(content, ?, ?) as slice FROM {$pfx}sppagebuilder WHERE id=?");
    $stmt->execute([$from+1, $len, $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['slice'=>$row['slice']], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'find_all') {
    $id = (int)($_GET['id'] ?? 173);
    $q = $_GET['q'] ?? 'Prochaines';
    $stmt = $pdo->prepare("SELECT content FROM {$pfx}sppagebuilder WHERE id=?");
    $stmt->execute([$id]);
    $content = $stmt->fetchColumn();
    $positions = [];
    $offset = 0;
    while (($pos = strpos($content, $q, $offset)) !== false) {
        $positions[] = $pos;
        $offset = $pos + 1;
    }
    echo json_encode(['count'=>count($positions),'positions'=>$positions]);
    exit;
}

if ($action === 'replace_text_field') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!$body) { echo json_encode(['error'=>'invalid json','raw_len'=>strlen($raw),'raw_start'=>substr($raw,0,100)]); exit; }

    $id = (int)($body['id'] ?? 173);
    $uid = $body['uid'] ?? '';
    $new_text = $body['new_text'] ?? '';
    $search_from = (int)($body['search_from'] ?? 0);

    $stmt = $pdo->prepare("SELECT content FROM {$pfx}sppagebuilder WHERE id=?");
    $stmt->execute([$id]);
    $content = $stmt->fetchColumn();
    if (!$content) { echo json_encode(['error'=>'record not found']); exit; }

    $uid_pos = strpos($content, $uid, $search_from);
    if ($uid_pos === false) { echo json_encode(['error'=>"uid not found after pos $search_from",'uid'=>$uid]); exit; }

    $text_key = '"text":"';
    $text_key_pos = strpos($content, $text_key, $uid_pos);
    if ($text_key_pos === false) { echo json_encode(['error'=>'text field not found after uid']); exit; }

    $val_start = $text_key_pos + strlen($text_key);
    $pos_end = $val_start;
    $max = strlen($content);
    while ($pos_end < $max) {
        if ($content[$pos_end] === '\\') { $pos_end += 2; continue; }
        if ($content[$pos_end] === '"') break;
        $pos_end++;
    }

    $old_val = substr($content, $val_start, $pos_end - $val_start);
    $new_val = str_replace(['\\','/','"',"\n","\r","\t"], ['\\\\','\\/','\"','\\n','\\r','\\t'], $new_text);
    $new_content = substr($content, 0, $val_start) . $new_val . substr($content, $pos_end);

    $stmt2 = $pdo->prepare("UPDATE {$pfx}sppagebuilder SET content=?, modified=NOW(), modified_by=898 WHERE id=?");
    $stmt2->execute([$new_content, $id]);

    echo json_encode([
        'ok'=>true,
        'uid_pos'=>$uid_pos,
        'old_preview'=>substr(strip_tags(stripcslashes($old_val)),0,150),
        'new_preview'=>substr(strip_tags($new_text),0,150),
        'rows'=>$stmt2->rowCount()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'purge_cache') {
    $root = dirname(__DIR__);
    $cleared = [];
    $errors  = [];

    // Dossiers de cache Joomla à purger
    $cache_dirs = [
        $root . '/cache',
        $root . '/administrator/cache',
    ];

    foreach ($cache_dirs as $dir) {
        if (!is_dir($dir)) continue;
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            if ($file->isFile() && $file->getFilename() !== 'index.html') {
                if (@unlink($file->getPathname())) {
                    $cleared[]= $file->getPathname();
                } else {
                    $errors[] = $file->getPathname();
                }
            }
        }
    }

    echo json_encode([
        'ok'      => true,
        'cleared' => count($cleared),
        'errors'  => count($errors),
    ]);
    exit;
}


if ($action === 'ext_params') {
    $ext_id = (int)($_GET['ext_id'] ?? 0);
    if (!$ext_id) { echo json_encode(['error'=>'ext_id required']); exit; }
    $stmt = $pdo->prepare("SELECT params FROM {$pfx}extensions WHERE extension_id=?");
    $stmt->execute([$ext_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['error'=>'not found']); exit; }
    echo json_encode(['ok'=>true,'params'=>$row['params']], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'set_ext_params') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!$body) { echo json_encode(['error'=>'invalid json']); exit; }
    $ext_id = (int)($body['ext_id'] ?? 0);
    $params = $body['params'] ?? null;
    if (!$ext_id || $params === null) { echo json_encode(['error'=>'ext_id and params required']); exit; }
    $stmt = $pdo->prepare("UPDATE {$pfx}extensions SET params=? WHERE extension_id=?");
    $stmt->execute([$params, $ext_id]);
    echo json_encode(['ok'=>true,'rows'=>$stmt->rowCount()]);
    exit;
}


if ($action === 'select_query') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $sql = $body['sql'] ?? '';
    if (!$sql || !preg_match('/^\s*SELECT/i', $sql)) { echo json_encode(['error'=>'SELECT only']); exit; }
    $sql = str_replace('##', $pfx, $sql);
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok'=>true,'rows'=>$rows], JSON_UNESCAPED_UNICODE);
    exit;
}


if ($action === 'update_jmap_source') {
    $id = (int)($_GET['id'] ?? 0);
    $published = (int)($_GET['published'] ?? 0);
    if (!$id) { echo json_encode(['error'=>'id required']); exit; }
    $stmt = $pdo->prepare("UPDATE {$pfx}jmap SET published=? WHERE id=?");
    $stmt->execute([$published, $id]);
    echo json_encode(['ok'=>true,'rows'=>$stmt->rowCount()]);
    exit;
}


if ($action === 'update_jmap_source_params') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $id = (int)($body['id'] ?? 0);
    $params = $body['params'] ?? null;
    if (!$id || $params === null) { echo json_encode(['error'=>'id and params required']); exit; }
    $stmt = $pdo->prepare("UPDATE {$pfx}jmap SET params=? WHERE id=?");
    $stmt->execute([$params, $id]);
    echo json_encode(['ok'=>true,'rows'=>$stmt->rowCount()]);
    exit;
}


if ($action === 'patch_config') {
    // Applique des remplacements regex dans configuration.php
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    if (!$body || !isset($body['patches'])) { echo json_encode(['error'=>'patches required']); exit; }
    $config_path = dirname(__DIR__) . '/configuration.php';
    $config = file_get_contents($config_path);
    if (!$config) { echo json_encode(['error'=>'cannot read configuration.php']); exit; }
    $applied = [];
    foreach ($body['patches'] as $patch) {
        $new = preg_replace($patch['pattern'], $patch['replacement'], $config, 1, $count);
        if ($count > 0) {
            $config = $new;
            $applied[] = $patch['label'] ?? $patch['pattern'];
        }
    }
    if (file_put_contents($config_path, $config) === false) {
        echo json_encode(['error'=>'cannot write configuration.php']);
        exit;
    }
    echo json_encode(['ok'=>true,'applied'=>$applied]);
    exit;
}


if ($action === 'set_plugin_enabled') {
    $ext_id = (int)($_GET['ext_id'] ?? 0);
    $enabled = (int)($_GET['enabled'] ?? 0);
    if (!$ext_id) { echo json_encode(['error'=>'ext_id required']); exit; }
    $stmt = $pdo->prepare("UPDATE {$pfx}extensions SET enabled=? WHERE extension_id=?");
    $stmt->execute([$enabled, $ext_id]);
    echo json_encode(['ok'=>true,'rows'=>$stmt->rowCount()]);
    exit;
}

if ($action === 'set_falang_qacache') {
    $ext_id = (int)($_GET['ext_id'] ?? 10140);
    $stmt = $pdo->prepare("SELECT params FROM {$pfx}extensions WHERE extension_id=?");
    $stmt->execute([$ext_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['error'=>'not found']); exit; }
    $params = json_decode($row['params'], true) ?: [];
    $params['qacaching'] = '1';
    $params['update_caching'] = '1';
    $new_params = json_encode($params, JSON_UNESCAPED_UNICODE);
    $stmt2 = $pdo->prepare("UPDATE {$pfx}extensions SET params=? WHERE extension_id=?");
    $stmt2->execute([$new_params, $ext_id]);
    echo json_encode(['ok'=>true,'rows'=>$stmt2->rowCount(),'params_preview'=>array_intersect_key($params, array_flip(['qacaching','update_caching']))]);
    exit;
}


if ($action === 'update_menu_params') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $id = (int)($body['id'] ?? 0);
    $params = $body['params'] ?? null;
    if (!$id || $params === null) { echo json_encode(['error'=>'id and params required']); exit; }
    $stmt = $pdo->prepare("UPDATE {$pfx}menu SET params=? WHERE id=?");
    $stmt->execute([$params, $id]);
    echo json_encode(['ok'=>true,'rows'=>$stmt->rowCount()]);
    exit;
}


if ($action === 'update_sppb_page') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);
    $id = (int)($body['id'] ?? 0);
    if (!$id) { echo json_encode(['error'=>'id required']); exit; }
    $stmt = $pdo->prepare("SELECT og_title, attribs FROM {$pfx}sppagebuilder WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['error'=>'not found']); exit; }
    $attribs = json_decode($row['attribs'] ?: '{}', true);
    // Appliquer les patches
    if (isset($body['og_title'])) {
        $og_title = $body['og_title'];
    } else {
        $og_title = $row['og_title'];
    }
    if (isset($body['attribs'])) {
        foreach ($body['attribs'] as $k => $v) {
            $attribs[$k] = $v;
        }
    }
    $new_attribs = json_encode($attribs, JSON_UNESCAPED_UNICODE);
    $stmt2 = $pdo->prepare("UPDATE {$pfx}sppagebuilder SET og_title=?, attribs=?, modified=NOW() WHERE id=?");
    $stmt2->execute([$og_title, $new_attribs, $id]);
    echo json_encode(['ok'=>true,'rows'=>$stmt2->rowCount(),'og_title'=>$og_title,'attribs'=>$attribs]);
    exit;
}

// Action : exécuter une requête UPDATE/INSERT/DELETE
if ($action === 'write_query') {
    $body = json_decode(file_get_contents('php://input'), true);
    $sql = isset($body['sql']) ? $body['sql'] : '';
    if (empty($sql)) {
        echo json_encode(['error'=>'sql required']);
        exit;
    }
    // Sécurité : n'autoriser que UPDATE, INSERT, DELETE
    $sqlTrim = ltrim($sql);
    if (!preg_match('/^(UPDATE|INSERT|DELETE|REPLACE)\b/i', $sqlTrim)) {
        echo json_encode(['error'=>'Only UPDATE/INSERT/DELETE/REPLACE allowed']);
        exit;
    }
    // Remplacer ## par le préfixe
    $sqlReady = str_replace('##', $pfx, $sql);
    $stmt = $pdo->prepare($sqlReady);
    $stmt->execute();
    echo json_encode(['ok'=>true,'rows'=>$stmt->rowCount()]);
    exit;
}

// Action : enregistrer un module Joomla
if ($action === 'register_module') {
    $body = json_decode(file_get_contents('php://input'), true);
    $pdo->exec("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
    $t = $pfx . 'modules';
    $stmt = $pdo->prepare(
        "INSERT INTO {$t} (title, note, content, ordering, position, checked_out, checked_out_time, published, module, access, showtitle, params, client_id, language, publish_up, publish_down) "
       ."VALUES (?, '', '', ?, ?, 0, '0000-00-00 00:00:00', 1, ?, 1, 1, ?, 0, '*', '0000-00-00 00:00:00', '0000-00-00 00:00:00') "
       ."ON DUPLICATE KEY UPDATE title=VALUES(title)"
    );
    $stmt->execute([
        $body['title'] ?? 'Module',
        (int)($body['ordering'] ?? 1),
        $body['position'] ?? 'pagebuilder',
        $body['module'],
        json_encode($body['params'] ?? new stdClass()),
    ]);
    $id = $pdo->lastInsertId();
    // Assigner à toutes les pages
    if ($id) {
        $t2 = $pfx . 'modules_menu';
        $stmt2 = $pdo->prepare("INSERT IGNORE INTO {$t2} (moduleid, menuid) VALUES (?, 0)");
        $stmt2->execute([$id]);
    }
    echo json_encode(['ok'=>true,'id'=>$id]);
    exit;
}

// Action : insérer des traductions Falang en masse
if ($action === 'insert_falang') {
    $body = json_decode(file_get_contents('php://input'), true);
    $rows = isset($body['rows']) ? $body['rows'] : [];
    if (empty($rows)) { echo json_encode(['error'=>'rows required']); exit; }
    $table = 'bwhwo_falang_content';
    $done = 0; $errors = [];
    foreach ($rows as $r) {
        try {
            $orig_value = md5($r['original_source'] ?? $r['original_text']);
            $stmt = $pdo->prepare(
                "INSERT INTO {$table} (language_id, reference_table, reference_id, reference_field, value, original_text, original_value, published, modified, modified_by) "
               ."VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), ?) "
               ."ON DUPLICATE KEY UPDATE value=VALUES(value), original_text=VALUES(original_text), original_value=VALUES(original_value), modified=NOW()"
            );
            $stmt->execute([
                (int)$r['language_id'],
                $r['reference_table'],
                (int)$r['reference_id'],
                $r['reference_field'],
                $r['original_text'],   // value = texte traduit
                $r['original_source'] ?? '', // original_text = source FR
                $orig_value,           // original_value = md5(source)
                (int)($r['modified_by'] ?? 898),
            ]);
            $done++;
        } catch (Exception $e) {
            $errors[] = ['ref' => $r['reference_id'].':'.$r['reference_field'], 'error' => $e->getMessage()];
        }
    }
    echo json_encode(['ok'=>true,'inserted'=>$done,'errors'=>$errors]);
    exit;
}

// Action : renommer des événements RSEvents! en masse
if ($action === 'rename_rsevents') {
    $body = json_decode(file_get_contents('php://input'), true);
    $updates = isset($body['updates']) ? $body['updates'] : [];
    if (empty($updates)) { echo json_encode(['error'=>'updates array required']); exit; }
    $table = 'bwhwo_rseventspro_events';
    $done = []; $errors = [];
    foreach ($updates as $u) {
        try {
            $stmt = $pdo->prepare("UPDATE {$table} SET name=? WHERE id=?");
            $stmt->execute([$u['name'], (int)$u['id']]);
            $done[] = ['id' => $u['id'], 'name' => $u['name']];
        } catch (Exception $e) {
            $errors[] = ['id' => $u['id'], 'error' => $e->getMessage()];
        }
    }
    echo json_encode(['ok'=>true,'updated'=>$done,'errors'=>$errors]);
    exit;
}

// Action : insérer des événements RSEvents! Pro en masse
if ($action === 'insert_rsevents') {
    $body = json_decode(file_get_contents('php://input'), true);
    $events = isset($body['events']) ? $body['events'] : [];
    if (empty($events)) { echo json_encode(['error'=>'events array required']); exit; }
    $inserted = [];
    $errors = [];
    $evtTable = 'bwhwo_rseventspro_events';
    $taxTable = 'bwhwo_rseventspro_taxonomy';
    // Desactiver strict mode pour accepter 0000-00-00 datetime defaults
    $pdo->exec("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
    foreach ($events as $ev) {
        try {
            $endDt = $ev['end'];
            $stmt = $pdo->prepare(
                "INSERT INTO {$evtTable} (name, start, end, end_registration, repeat_end, early_fee_end, late_fee_start, unsubscribe_date, rsvp_start, rsvp_end, published, disable_registration, language, owner, created, sid, small_description, description, metadescription, metakeywords) "
               ."VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, '*', ?, NOW(), ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $ev['name'],
                $ev['start'],
                $endDt,
                $ev['deadline'],
                $endDt, // repeat_end
                $endDt, // early_fee_end
                $endDt, // late_fee_start
                $endDt, // unsubscribe_date
                $ev['start'], // rsvp_start
                $endDt, // rsvp_end
                (int)($ev['owner'] ?? 898),
                $ev['sid'],
                $ev['small_desc'] ?? '',
                $ev['description'] ?? '',
                $ev['metadescription'] ?? '',
                $ev['metakeywords'] ?? '',
            ]);
            $event_id = $pdo->lastInsertId();
            // Lier à la catégorie
            if (!empty($ev['cat_id'])) {
                $stmtTax = $pdo->prepare("INSERT INTO {$taxTable} (ide, type, extra) VALUES (?, 'category', ?)");
                $stmtTax->execute([$event_id, (int)$ev['cat_id']]);
            }
            $inserted[] = ['id' => $event_id, 'name' => $ev['name']];
        } catch (Exception $e) {
            $errors[] = ['name' => $ev['name'], 'error' => $e->getMessage()];
        }
    }
    echo json_encode(['ok' => true, 'inserted' => $inserted, 'errors' => $errors]);
    exit;
}

// Action : mettre à jour les params d'un template style
if ($action === 'set_template_style_params') {
    $body = json_decode(file_get_contents('php://input'), true);
    $style_id = isset($body['style_id']) ? (int)$body['style_id'] : 0;
    $params_patch = isset($body['params']) ? $body['params'] : [];
    if (!$style_id || empty($params_patch)) {
        echo json_encode(['error'=>'style_id and params required']);
        exit;
    }
    // Lire les params actuels
    $stmt = $pdo->prepare("SELECT params FROM {$pfx}template_styles WHERE id=?");
    $stmt->execute([$style_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo json_encode(['error'=>'style not found']);
        exit;
    }
    $current = json_decode($row['params'] ?: '{}', true);
    foreach ($params_patch as $k => $v) {
        $current[$k] = $v;
    }
    $new_params = json_encode($current, JSON_UNESCAPED_UNICODE);
    $stmt2 = $pdo->prepare("UPDATE {$pfx}template_styles SET params=? WHERE id=?");
    $stmt2->execute([$new_params, $style_id]);
    echo json_encode(['ok'=>true,'rows'=>$stmt2->rowCount(),'updated_keys'=>array_keys($params_patch)]);
    exit;
}

// Action : reset PHP OPcache
if ($action === 'reset_opcache') {
    if (function_exists('opcache_reset')) {
        $ok = opcache_reset();
        echo json_encode(['ok'=>$ok,'opcache'=>true]);
    } else {
        echo json_encode(['ok'=>true,'opcache'=>false,'note'=>'opcache not available']);
    }
    exit;
}

// Action : lire un fichier PHP du serveur
if ($action === 'read_file') {
    $path = $_GET['path'] ?? '';
    if (!$path || strpos($path,'..') !== false) { echo json_encode(['error'=>'invalid']); exit; }
    $full = dirname(__DIR__) . '/' . ltrim($path,'/');
    if (!file_exists($full)) { echo json_encode(['error'=>'not found','path'=>$full]); exit; }
    echo json_encode(['content'=>file_get_contents($full)], JSON_UNESCAPED_UNICODE);
    exit;
}

// Action : écrire un fichier (chemins restreints aux language/overrides et language/*/*.ini)
if ($action === 'write_file') {
    $body = json_decode(file_get_contents('php://input'), true);
    $path = isset($body['path']) ? $body['path'] : '';
    $content = isset($body['content']) ? $body['content'] : '';
    if (!$path || strpos($path,'..') !== false) { echo json_encode(['error'=>'invalid path']); exit; }
    // Restreindre aux fichiers de langue et overrides de templates
    $allowed = ['#^/language/#', '#^/templates/shaper_languageschool/html/#', '#^/templates/shaper_languageschool/css/#', '#^/components/com_sppagebuilder/assets/css/#', '#^/templates/shaper_languageschool/css/#'];
    $ok = false;
    foreach ($allowed as $pat) { if (preg_match($pat, $path)) { $ok = true; break; } }
    if (!$ok) { echo json_encode(['error'=>'path not allowed']); exit; }
    $full = dirname(__DIR__) . '/' . ltrim($path,'/');
    $dir = dirname($full);
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    $bytes = file_put_contents($full, $content);
    echo json_encode(['ok'=>true,'bytes'=>$bytes,'path'=>$full]);
    exit;
}

// Action : inspecter structure table locations
if ($action === 'inspect_location') {
    $cols = $pdo->query("SHOW COLUMNS FROM bwhwo_rseventspro_locations")->fetchAll(PDO::FETCH_ASSOC);
    $row = $pdo->query("SELECT * FROM bwhwo_rseventspro_locations WHERE id=2")->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['columns'=>array_column($cols,'Field'),'row'=>$row], JSON_UNESCAPED_UNICODE);
    exit;
}

// Action : inspecter falang_tableinfo
if ($action === 'falang_tableinfo') {
    try {
        $plugins = $pdo->query("SELECT folder, element, enabled FROM {$pfx}extensions WHERE type='plugin' AND (element LIKE '%falang%' OR folder LIKE '%falang%') ORDER BY folder,element")->fetchAll(PDO::FETCH_ASSOC);
        $sys_falang = $pdo->query("SELECT folder, element, enabled, params FROM {$pfx}extensions WHERE type='plugin' AND element='falang' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $rows = ['falang_plugins'=>$plugins,'sys_falang'=>$sys_falang];
        echo json_encode(['count'=>count($rows),'rows'=>$rows], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode(['error'=>$e->getMessage()]);
    }
    exit;
}

// Action : inspecter les entrées Falang pour RSEvents!
if ($action === 'falang_inspect') {
    $table = $pfx . 'falang_content';
    $ref_table = $_GET['ref_table'] ?? 'rseventspro_events';
    $ref_id = isset($_GET['ref_id']) ? (int)$_GET['ref_id'] : null;
    if ($ref_id) {
        $stmt = $pdo->prepare("SELECT id, language_id, reference_table, reference_field, reference_id, value FROM {$table} WHERE reference_table=? AND reference_id=? LIMIT 20");
        $stmt->execute([$ref_table, $ref_id]);
    } else {
        $stmt = $pdo->prepare("SELECT DISTINCT reference_table, reference_field FROM {$table} WHERE reference_table LIKE '%rseventspro%' LIMIT 50");
        $stmt->execute();
    }
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}

// Action : lire les noms des événements RSEvents!
if ($action === 'get_rse_event_names') {
    $stmt = $pdo->prepare("SELECT id, name FROM bwhwo_rseventspro_events WHERE id BETWEEN 4 AND 31 ORDER BY id");
    $stmt->execute();
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
}

// Action : corriger les événements RSEvents! — assigner catégories + localisation
if ($action === 'fix_rse_events') {
    $evtTable = 'bwhwo_rseventspro_events';
    $taxTable = 'bwhwo_rseventspro_taxonomy';
    $locTable = 'bwhwo_rseventspro_locations';

    // Désactiver strict mode
    $pdo->exec("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");

    // 1. Créer ou retrouver la localisation
    $stmt = $pdo->prepare("SELECT id FROM {$locTable} WHERE name=? LIMIT 1");
    $stmt->execute(['Alliance Française de Kunming']);
    $loc = $stmt->fetchColumn();
    if (!$loc) {
        $stmt = $pdo->prepare("INSERT INTO {$locTable} (name, published) VALUES (?, 1)");
        $stmt->execute(['Alliance Française de Kunming']);
        $loc = (int)$pdo->lastInsertId();
    } else {
        $loc = (int)$loc;
    }

    // 2. Récupérer les événements 4-31
    $stmt = $pdo->prepare("SELECT id, name FROM {$evtTable} WHERE id BETWEEN 4 AND 31");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($events as $ev) {
        $eid = (int)$ev['id'];
        $name = $ev['name'];

        // Déterminer la catégorie selon le nom
        if (stripos($name, 'TEFAQ') !== false) {
            $cat_id = 48;
        } elseif (stripos($name, 'TEF Canada') !== false) {
            $cat_id = 47;
        } else {
            $cat_id = 45; // TCF Canada par défaut
        }

        // Supprimer anciens liens catégorie et localisation pour cet événement
        $pdo->prepare("DELETE FROM {$taxTable} WHERE ide=? AND type IN ('category','location')")->execute([$eid]);

        // Insérer catégorie
        $pdo->prepare("INSERT INTO {$taxTable} (ide, type, extra) VALUES (?, 'category', ?)")->execute([$eid, $cat_id]);

        // Insérer localisation
        $pdo->prepare("INSERT INTO {$taxTable} (ide, type, extra) VALUES (?, 'location', ?)")->execute([$eid, $loc]);

        $results[] = ['id'=>$eid, 'name'=>$name, 'cat_id'=>$cat_id, 'loc_id'=>$loc];
    }

    echo json_encode(['ok'=>true, 'location_id'=>$loc, 'events'=>$results]);
    exit;
}


// ─── menu_metakey : update meta keywords for menu items ───────────────
if ($action === 'menu_metakey') {
    try {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        $ids = isset($body['ids']) ? $body['ids'] : [];
        $metakey = isset($body['metakey']) ? $body['metakey'] : '';
        if (empty($ids) || $metakey === '') {
            echo json_encode(['error'=>'ids and metakey required','raw'=>$raw]);
            exit;
        }
        $updated = [];
        foreach ($ids as $id) {
            $id = (int)$id;
            $stmt = $pdo->prepare("SELECT params FROM {$pfx}menu WHERE id=?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) { $updated[] = ['id'=>$id,'status'=>'not_found']; continue; }
            $params = json_decode($row['params'], true) ?: [];
            $params['menu-meta_keywords'] = $metakey;
            $newparams = json_encode($params, JSON_UNESCAPED_UNICODE);
            $pdo->prepare("UPDATE {$pfx}menu SET params=? WHERE id=?")->execute([$newparams, $id]);
            $updated[] = ['id'=>$id,'status'=>'ok'];
        }
        echo json_encode(['ok'=>true,'updated'=>$updated]);
    } catch (Throwable $e) {
        http_response_code(200);
        echo json_encode(['error'=>$e->getMessage(),'trace'=>$e->getTraceAsString()]);
    }
    exit;
}


// ─── menu_list : list menu items by language and path ─────────────────
if ($action === 'menu_list') {
    $lang = $_GET['lang'] ?? '';
    $path = $_GET['path'] ?? '';
    $sql = "SELECT id, title, language, path, params FROM {$pfx}menu WHERE client_id=0 AND published=1";
    if ($lang) $sql .= $pdo->quote($lang) === false ? "" : " AND language=" . $pdo->quote($lang);
    if ($path) $sql .= " AND path LIKE " . $pdo->quote($path . '%');
    $sql .= " ORDER BY id";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) { unset($r['params']); }
    echo json_encode(['ok'=>true,'items'=>$rows]);
    exit;
}


// ─── get_template_style_params : read params for a template style ─────
if ($action === 'get_template_style_params') {
    $id = (int)($_GET['id'] ?? 10);
    $stmt = $pdo->prepare("SELECT params FROM {$pfx}template_styles WHERE id=?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { echo json_encode(['error'=>'not found']); exit; }
    $params = json_decode($row['params'] ?: '{}', true);
    echo json_encode(['ok'=>true,'params'=>$params]);
    exit;
}


// ─── list_plugins : list system plugins ─────────────────────────────
if ($action === 'list_plugins') {
    $folder = $_GET['folder'] ?? 'system';
    $stmt = $pdo->query("SELECT extension_id, name, element, enabled, params FROM {$pfx}extensions WHERE type='plugin' AND folder=" . $pdo->quote($folder) . " ORDER BY ordering");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $p = json_decode($r['params'] ?: '{}', true);
        $r['params_keys'] = array_keys($p);
        unset($r['params']);
    }
    echo json_encode(['ok'=>true,'plugins'=>$rows]);
    exit;
}

echo json_encode(['error'=>'unknown action']);
