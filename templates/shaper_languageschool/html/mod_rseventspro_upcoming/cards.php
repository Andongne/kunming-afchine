<?php
/**
 * @package RSEvents!Pro — Layout "cards" AF Kunming
 * Catégorie Événements (id=54) : liste enrichie avec date, lieu, icône calendrier
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$_lang = Factory::getLanguage()->getTag();

// Charger tous les lieux RSEvents Pro en une seule requête
$_db = Factory::getDbo();
$_db->setQuery('SELECT id, name FROM #__rseventspro_locations WHERE published=1');
$_loc_map = [];
foreach (($_db->loadObjectList() ?: []) as $_lr) {
    $_loc_map[(int)$_lr->id] = $_lr->name;
}

function afk_cards_fmt($start, $end, $lang) {
    $tz  = new DateTimeZone('Asia/Shanghai');
    $utc = new DateTimeZone('UTC');
    $dt_s = new DateTime($start, $utc); $dt_s->setTimezone($tz);
    $dt_e = $end ? (new DateTime($end, $utc))->setTimezone($tz) : null;
    if (strpos($lang,'zh') !== false) {
        $d = $dt_s->format('Y').'年'.(int)$dt_s->format('n').'月'.(int)$dt_s->format('j').'日';
        $h = $dt_s->format('G:i').($dt_e ? '–'.$dt_e->format('G:i') : '');
    } elseif (strpos($lang,'en') !== false) {
        $m = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        $d = $m[(int)$dt_s->format('n')].' '.$dt_s->format('j, Y');
        $h = $dt_s->format('g:ia').($dt_e ? '–'.$dt_e->format('g:ia') : '');
    } else {
        $m = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        $d = (int)$dt_s->format('j').' '.$m[(int)$dt_s->format('n')].' '.$dt_s->format('Y');
        $h = $dt_s->format('G\\hi').($dt_e ? '–'.$dt_e->format('G\\hi') : '');
    }
    return ['date' => $d, 'time' => $h];
}

$base_img = Uri::root(true).'/components/com_rseventspro/assets/images/events/';

// URL de la page Actualités selon la langue (menu 1036)
if (strpos($_lang,'zh') !== false) {
    $_actu_url = '/zh/wen-hua-huo-dong/huo-dong-zi-xun';
} elseif (strpos($_lang,'en') !== false) {
    $_actu_url = '/en/events/news-events';
} else {
    $_actu_url = '/evenements/actualites-evenements';
}
?>

<?php if ($items) : ?>
<style>
.afk-upcoming-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:12px; }
.afk-upcoming-item { position:relative; display:flex; align-items:stretch; gap:0; background:#fff; border:1.5px solid rgba(192,57,90,0.25); border-radius:7px; overflow:hidden; transition:box-shadow .2s; }
.afk-upcoming-item:hover { box-shadow:0 3px 14px rgba(192,57,90,0.12); cursor:pointer; }
.afk-upcoming-item__link { position:absolute; inset:0; z-index:1; }
.afk-upcoming-item__thumb { width:90px; flex-shrink:0; }
.afk-upcoming-item__thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.afk-upcoming-item__thumb-ph { width:90px; height:100%; background:linear-gradient(135deg,#f5e8ec,#e8d0d7); display:flex; align-items:center; justify-content:center; font-size:1.6rem; }
.afk-upcoming-item__body { flex:1; padding:12px 14px; display:flex; flex-direction:column; gap:4px; min-width:0; }
.afk-upcoming-item__title { font-size:.95rem; font-weight:700; color:#1a171b; line-height:1.3; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.afk-upcoming-item__meta { font-size:.75rem; color:#5a5a5a; display:flex; flex-direction:column; gap:3px; margin:4px 0 0; }
.afk-upcoming-item__meta-row { display:flex; align-items:flex-start; gap:4px; line-height:1.35; }
.afk-upcoming-item__meta-row svg { flex-shrink:0; margin-top:2px; }
.afk-upcoming-item__meta-datetime { display:flex; flex-direction:column; gap:2px; padding:0; align-items:flex-start !important; text-align:left !important; }
.afk-dt-date { color:#5a5a5a; white-space:nowrap; text-align:left !important; display:block; }
.afk-dt-time { color:rgba(192,57,90,0.85); font-weight:600; white-space:nowrap; text-align:left !important; display:block; }
.afk-upcoming-item__cal { flex-shrink:0; width:52px; background:rgba(192,57,90,0.06); display:flex; flex-direction:column; align-items:center; justify-content:center; padding:8px 4px; gap:0; border-left:1px solid rgba(192,57,90,0.15); }
.afk-upcoming-item__cal-month { font-size:.62rem; font-weight:700; text-transform:uppercase; color:rgba(192,57,90,0.9); letter-spacing:.04em; }
.afk-upcoming-item__cal-day { font-size:1.5rem; font-weight:800; color:#1a171b; line-height:1; }
</style>

<ul class="afk-upcoming-list">
<?php foreach ($items as $block => $events_ids) :
    foreach ($events_ids as $id) :
        $details = rseventsproHelper::details($id);
        if (empty($details['event'])) continue;
        $ev = $details['event'];

        // Préférer .webp si disponible (même nom, extension remplacée)
        $_icon_raw = $ev->icon ? htmlspecialchars($ev->icon) : null;
        if ($_icon_raw) {
            $_webp = preg_replace('/\.(png|jpe?g|gif)$/i', '.webp', $_icon_raw);
            $_webp_path = JPATH_SITE . '/components/com_rseventspro/assets/images/events/' . $_webp;
            $img_url = $base_img . (file_exists($_webp_path) ? $_webp : $_icon_raw);
        } else {
            $img_url = null;
        }
        $_fmt     = afk_cards_fmt($ev->start, $ev->end ?? '', $_lang);
        $_date_str = $_fmt['date'];
        $_time_str = $_fmt['time'];
        $loc      = htmlspecialchars(trim($_loc_map[intval($ev->location)] ?? ''));
        // Icône calendrier en heure Kunming
        $_tz_km = new DateTimeZone('Asia/Shanghai');
        $_dt_km = new DateTime($ev->start, new DateTimeZone('UTC')); $_dt_km->setTimezone($_tz_km);
        if (strpos($_lang,'zh') !== false) {
            $cal_month = (int)$_dt_km->format('n').'月';
        } elseif (strpos($_lang,'en') !== false) {
            $cal_month = strtoupper($_dt_km->format('M'));
        } else {
            $cal_months = ['','JAN','FÉV','MAR','AVR','MAI','JUN','JUL','AOÛ','SEP','OCT','NOV','DÉC'];
            $cal_month = $cal_months[(int)$_dt_km->format('n')];
        }
        $cal_day = (int)$_dt_km->format('j');
?>
<li class="afk-upcoming-item">
    <a href="<?= $_actu_url ?>" class="afk-upcoming-item__link" aria-label="<?= htmlspecialchars($ev->name) ?>"></a>
    <?php if ($img_url) : ?>
    <div class="afk-upcoming-item__thumb"><img src="<?= $img_url ?>" alt="<?= htmlspecialchars($ev->name) ?>" loading="lazy" /></div>
    <?php else : ?>
    <div class="afk-upcoming-item__thumb-ph">📅</div>
    <?php endif; ?>

    <div class="afk-upcoming-item__body">
        <p class="afk-upcoming-item__title"><?= htmlspecialchars($ev->name) ?></p>
        <ul class="afk-upcoming-item__meta">
            <li class="afk-upcoming-item__meta-datetime">
                <span class="afk-dt-date"><?= $_date_str ?></span>
                <span class="afk-dt-time"><?= $_time_str ?></span>
            </li>
            <?php if ($loc) : ?>
            <li class="afk-upcoming-item__meta-row" style="color:#5a5a5a;"><?= $loc ?></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="afk-upcoming-item__cal">
        <span class="afk-upcoming-item__cal-month"><?= $cal_month ?></span>
        <span class="afk-upcoming-item__cal-day"><?= $cal_day ?></span>
    </div>
</li>
<?php endforeach; endforeach; ?>
</ul>
<?php endif; ?>
