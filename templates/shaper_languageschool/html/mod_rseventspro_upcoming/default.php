<?php
/**
 * @package RSEvents!Pro — Template override AF Kunming
 * v2 : Carte événement avec image, date, lieu, description
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$_lang = Factory::getLanguage()->getTag();

// Traduction des mois FR→EN/ZH
function afk_localize_date_str($str, $lang) {
    $fr_long = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    if (strpos($lang, 'en') !== false) {
        $en = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        return str_ireplace($fr_long, $en, $str);
    }
    if (strpos($lang, 'zh') !== false) {
        $zh = ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];
        return str_ireplace($fr_long, $zh, $str);
    }
    return $str;
}

// Formater date + heure selon langue
function afk_format_event_date($start, $end, $lang) {
    $ts_start = strtotime($start);
    $ts_end   = $end ? strtotime($end) : null;
    if (strpos($lang, 'zh') !== false) {
        $date_str = date('Y年n月j日', $ts_start);
        $time_str = date('G:i', $ts_start);
        $time_end = $ts_end ? '–' . date('G:i', $ts_end) : '';
        return $date_str . ' ' . $time_str . $time_end;
    }
    if (strpos($lang, 'en') !== false) {
        $date_str = date('F j, Y', $ts_start);
        $time_str = date('g:ia', $ts_start);
        $time_end = $ts_end ? '–' . date('g:ia', $ts_end) : '';
        return $date_str . ', ' . $time_str . $time_end;
    }
    // FR
    $months = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    $date_str = intval(date('j', $ts_start)) . ' ' . $months[intval(date('n', $ts_start))-1] . ' ' . date('Y', $ts_start);
    $time_str = date('G\hi', $ts_start);
    $time_end = $ts_end ? '–' . date('G\hi', $ts_end) : '';
    return $date_str . ' · ' . $time_str . $time_end;
}

// URL de l'événement → page actualités si disponible
function afk_event_url($event, $itemid, $lang) {
    $_sef  = rseventsproHelper::sef($event->id, $event->name);
    $_base = rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id=' . $_sef, true, $itemid);
    $_pfx  = strpos($lang,'zh')!==false ? 'zh' : (strpos($lang,'en')!==false ? 'en' : '');
    return $_pfx ? '/' . $_pfx . preg_replace('#^(/[a-zA-Z-]{2,5})/#','/',$_base) : $_base;
}

$base_url = Uri::root(true) . '/components/com_rseventspro/assets/images/events/';
?>

<?php if ($items) : ?>
<style>
.afk-events-upcoming { display: flex; flex-wrap: wrap; gap: 20px; margin: 0; padding: 0; list-style: none; }
.afk-event-card { flex: 1 1 calc(33.333% - 20px); min-width: 260px; background: #fff; border: 1.5px solid rgba(192,57,90,0.3); border-radius: 6px; overflow: hidden; display: flex; flex-direction: column; transition: box-shadow .2s; }
.afk-event-card:hover { box-shadow: 0 4px 18px rgba(192,57,90,0.15); }
.afk-event-card__img { width: 100%; height: 180px; object-fit: cover; display: block; }
.afk-event-card__img-placeholder { width: 100%; height: 180px; background: linear-gradient(135deg,#f5e8ec,#e8d0d7); display: flex; align-items: center; justify-content: center; }
.afk-event-card__img-placeholder span { font-size: 2.5rem; opacity: .4; }
.afk-event-card__body { padding: 14px 16px; flex: 1; display: flex; flex-direction: column; gap: 6px; }
.afk-event-card__title { font-size: 1rem; font-weight: 700; color: #1a171b; line-height: 1.35; margin: 0; }
.afk-event-card__title a { color: inherit; text-decoration: none; }
.afk-event-card__title a:hover { color: rgba(192,57,90,0.92); }
.afk-event-card__meta { font-size: .82rem; color: #5a5a5a; display: flex; flex-direction: column; gap: 3px; margin: 0; }
.afk-event-card__meta-row { display: flex; align-items: flex-start; gap: 6px; }
.afk-event-card__meta-row svg { flex-shrink: 0; margin-top: 1px; }
.afk-event-card__desc { font-size: .83rem; color: #444; line-height: 1.5; margin: 4px 0 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.afk-event-card__footer { padding: 10px 16px 14px; }
.afk-event-card__btn { display: inline-block; padding: 6px 14px; background: rgba(192,57,90,0.92); color: #fff !important; border-radius: 4px; font-size: .82rem; font-weight: 600; text-decoration: none; transition: background .2s; }
.afk-event-card__btn:hover { background: rgba(160,40,70,1); }
@media (max-width: 767px) { .afk-event-card { flex: 1 1 100%; } }
</style>

<ul class="afk-events-upcoming">
<?php foreach ($items as $block => $events_ids) :
    foreach ($events_ids as $id) :
        $details = rseventsproHelper::details($id);
        if (empty($details['event'])) continue;
        $event = $details['event'];

        $img_url   = $event->icon ? $base_url . htmlspecialchars($event->icon) : null;
        $title     = htmlspecialchars($event->name);
        $date_str  = afk_format_event_date($event->start, $event->end ?? '', $_lang);
        $location  = htmlspecialchars(trim(preg_replace('/\s+/', ' ', $event->location ?? '')));
        $desc      = strip_tags($event->small_description ?? '');
        $url       = afk_event_url($event, $itemid, $_lang);

        $lbl_more = strpos($_lang,'zh')!==false ? '了解更多' : (strpos($_lang,'en')!==false ? 'Learn more' : 'En savoir plus');
?>
<li class="afk-event-card">
    <?php if ($img_url) : ?>
    <a href="<?= $url ?>"><img class="afk-event-card__img" src="<?= $img_url ?>" alt="<?= $title ?>" loading="lazy" /></a>
    <?php else : ?>
    <div class="afk-event-card__img-placeholder"><span>📅</span></div>
    <?php endif; ?>

    <div class="afk-event-card__body">
        <h3 class="afk-event-card__title"><a href="<?= $url ?>"><?= $title ?></a></h3>
        <ul class="afk-event-card__meta">
            <li class="afk-event-card__meta-row">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(192,57,90,0.85)" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <?= $date_str ?>
            </li>
            <?php if ($location) : ?>
            <li class="afk-event-card__meta-row">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="rgba(192,57,90,0.85)" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                <?= $location ?>
            </li>
            <?php endif; ?>
        </ul>
        <?php if ($desc) : ?>
        <p class="afk-event-card__desc"><?= htmlspecialchars($desc) ?></p>
        <?php endif; ?>
    </div>

    <div class="afk-event-card__footer">
        <a class="afk-event-card__btn" href="<?= $url ?>"><?= $lbl_more ?></a>
    </div>
</li>
<?php endforeach; endforeach; ?>
</ul>
<?php endif; ?>
