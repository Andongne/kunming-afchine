<?php
/**
 * @package RSEvents!Pro — Layout "cards" AF Kunming
 * Catégorie Événements (id=54) : liste enrichie avec date, lieu, icône calendrier
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$_lang = Factory::getLanguage()->getTag();

function afk_cards_fmt($start, $end, $lang) {
    $ts_s = strtotime($start);
    $ts_e = $end ? strtotime($end) : null;
    if (strpos($lang,'zh') !== false) {
        return date('Y',$ts_s).'年'.intval(date('n',$ts_s)).'月'.intval(date('j',$ts_s)).'日 '
             . date('G:i',$ts_s).($ts_e ? '–'.date('G:i',$ts_e) : '');
    }
    if (strpos($lang,'en') !== false) {
        $m = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
        return $m[intval(date('n',$ts_s))].' '.date('j, Y',$ts_s).', '.date('g:ia',$ts_s).($ts_e ? '–'.date('g:ia',$ts_e) : '');
    }
    $m = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    return intval(date('j',$ts_s)).' '.$m[intval(date('n',$ts_s))].' '.date('Y',$ts_s)
         .' · '.date('G\hi',$ts_s).($ts_e ? '–'.date('G\hi',$ts_e) : '');
}

$base_img = Uri::root(true).'/components/com_rseventspro/assets/images/events/';
?>

<?php if ($items) : ?>
<style>
.afk-upcoming-list { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:12px; }
.afk-upcoming-item { display:flex; align-items:stretch; gap:0; background:#fff; border:1.5px solid rgba(192,57,90,0.25); border-radius:7px; overflow:hidden; transition:box-shadow .2s; }
.afk-upcoming-item:hover { box-shadow:0 3px 14px rgba(192,57,90,0.12); }
.afk-upcoming-item__thumb { width:90px; flex-shrink:0; }
.afk-upcoming-item__thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.afk-upcoming-item__thumb-ph { width:90px; height:100%; background:linear-gradient(135deg,#f5e8ec,#e8d0d7); display:flex; align-items:center; justify-content:center; font-size:1.6rem; }
.afk-upcoming-item__body { flex:1; padding:12px 14px; display:flex; flex-direction:column; gap:4px; min-width:0; }
.afk-upcoming-item__title { font-size:.95rem; font-weight:700; color:#1a171b; line-height:1.3; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.afk-upcoming-item__meta { font-size:.78rem; color:#5a5a5a; display:flex; flex-direction:column; gap:2px; margin:0; }
.afk-upcoming-item__meta-row { display:flex; align-items:flex-start; gap:5px; }
.afk-upcoming-item__meta-row svg { flex-shrink:0; margin-top:1px; }
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

        $img_url  = $ev->icon ? $base_img . htmlspecialchars($ev->icon) : null;
        $date_str = afk_cards_fmt($ev->start, $ev->end ?? '', $_lang);
        $loc      = htmlspecialchars(trim(preg_replace('/\s+/',' ', $ev->location ?? '')));
        $ts       = strtotime($ev->start);

        // Icône calendrier : mois + jour
        if (strpos($_lang,'zh') !== false) {
            $cal_month = intval(date('n',$ts)).'月';
        } elseif (strpos($_lang,'en') !== false) {
            $cal_month = strtoupper(date('M',$ts));
        } else {
            $cal_months = ['','JAN','FÉV','MAR','AVR','MAI','JUN','JUL','AOÛ','SEP','OCT','NOV','DÉC'];
            $cal_month = $cal_months[intval(date('n',$ts))];
        }
        $cal_day = date('j',$ts);
?>
<li class="afk-upcoming-item">
    <?php if ($img_url) : ?>
    <div class="afk-upcoming-item__thumb"><img src="<?= $img_url ?>" alt="<?= htmlspecialchars($ev->name) ?>" loading="lazy" /></div>
    <?php else : ?>
    <div class="afk-upcoming-item__thumb-ph">📅</div>
    <?php endif; ?>

    <div class="afk-upcoming-item__body">
        <p class="afk-upcoming-item__title"><?= htmlspecialchars($ev->name) ?></p>
        <ul class="afk-upcoming-item__meta">
            <li class="afk-upcoming-item__meta-row">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="rgba(192,57,90,0.8)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= $date_str ?>
            </li>
            <?php if ($loc) : ?>
            <li class="afk-upcoming-item__meta-row">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="rgba(192,57,90,0.8)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= $loc ?>
            </li>
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
