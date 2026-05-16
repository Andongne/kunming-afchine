<?php
/**
* @package RSEvents!Pro
* @copyright (C) 2020 www.rsjoomla.com
* @license GPL, http://www.gnu.org/copyleft/gpl.html
*/

defined('_JEXEC') or die('Restricted access'); echo '<!-- RSE_OVERRIDE_LOADED -->';

use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Helper\ModuleHelper;

// AFK: détecter langue URL param ou cookie FaLang
$_afk_list_lang = Factory::getApplication()->input->get('lang', '') ?: Factory::getLanguage()->getTag();

// AFK: localiser les mois dans les chaînes de date
function afk_localize_date_list($str, $lang) {
    $fr = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre',
           'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
    if (strpos($lang,'zh') !== false) {
        $zh = ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月',
               '1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];
        return str_replace($fr, $zh, $str);
    }
    if (strpos($lang,'en') !== false) {
        $en = ['January','February','March','April','May','June','July','August','September','October','November','December',
               'January','February','March','April','May','June','July','August','September','October','November','December'];
        return str_replace($fr, $en, $str);
    }
    return $str;
}

// Retourne l'image associée à un type d'examen
function afk_exam_image(string $name): string {
    $name_upper = mb_strtoupper($name, 'UTF-8');
    // TCF Canada — FR, ZH (TCF + 加拿大), EN
    if (str_contains($name_upper, 'TCF CANADA') || (str_contains($name_upper, 'TCF') && str_contains($name, '加拿大'))) return '/images/TCF-Canada.png';
    // TEFAQ — FR, ZH (魁北克 ou TEFAQ), EN
    if (str_contains($name_upper, 'TEFAQ') || str_contains($name, 'TEFAQ')) return '/images/TEFAQ.png';
    // TCF Québec — avant TEF pour éviter faux positif
    if (str_contains($name_upper, 'TCF QUÉBEC') || str_contains($name_upper, 'TCF QUEBEC') || str_contains($name, 'TCF魁') || str_contains($name, 'TCF 魁')) return '/images/TCF-Quebec.png';
    // TEF Canada — FR, ZH, EN
    if (str_contains($name_upper, 'TEF CANADA') || str_contains($name_upper, 'TEF ') || str_contains($name, 'TEF')) return '/images/TEF.png';
    return '/components/com_rseventspro/assets/images/default/blank.png';
}

// Extrait le type d'examen depuis le nom de l'événement
// "TCF Canada 🍁 📄 — 8 mai 2026" → "TCF Canada"
function afk_exam_type(string $name): string {
    if (preg_match('/^([A-Za-z][A-Za-z\s]+?)(?:\s+[^\x00-\x7F]|\s+—|$)/u', $name, $m)) {
        return trim($m[1]);
    }
    $parts = explode(' —', $name);
    return trim(preg_replace('/[^\x00-\x7F]+/u', '', $parts[0]));
}

// Map lang tag → préfixe URL court
function afk_lang_prefix($lang) {
    $map = ['zh-CN' => 'zh', 'en-GB' => 'en', 'en' => 'en'];
    return $map[$lang] ?? '';
}
function afk_event_url($event_id, $event_name, $lang) {
    $sef = rseventsproHelper::sef($event_id, $event_name);
    $itemid = rseventsproHelper::itemid($event_id);
    $url = rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id='.$sef, false, $itemid);
    $prefix = afk_lang_prefix($lang);
    if ($prefix) {
        // Retirer préfixe existant /xx-XX/ ou /xx/ puis ajouter le bon
        $url = preg_replace('#^(/[a-zA-Z-]{2,5})/#', '/', $url);
        $url = '/' . $prefix . $url;
    }
    return $url;
}

Text::script('COM_RSEVENTSPRO_GLOBAL_FREE');
$count = count($this->events);
$rss = $this->params->get('rss',1);
$ical = $this->params->get('ical',1); ?>

<script type="text/javascript">
	var rseproMask 		= '<?php echo $this->escape($this->mask); ?>';
	var rseproCurrency  = '<?php echo $this->escape($this->currency); ?>';
	var rseproDecimals	= '<?php echo $this->escape($this->decimals); ?>';
	var rseproDecimal 	= '<?php echo $this->escape($this->decimal); ?>';
	var rseproThousands	= '<?php echo $this->escape($this->thousands); ?>';
</script>

<?php
// Résoudre language_id FaLang (SEF /zh/ ou ?lang=zh-CN)
$_afkLangMap   = ['zh-CN' => 4, 'en-GB' => 1];
$_afkLangTag   = Factory::getLanguage()->getTag();
$_afkLangGet   = Factory::getApplication()->input->getString('lang', '');
$_afkFalangLid = $_afkLangMap[$_afkLangGet] ?? $_afkLangMap[$_afkLangTag] ?? null;
$_afkFalangTitles = [];
if ($_afkFalangLid) {
    $db = Factory::getDbo();
    $q  = $db->getQuery(true)
             ->select($db->quoteName(['reference_id','value']))
             ->from($db->quoteName('#__falang_content'))
             ->where($db->quoteName('language_id').'='.(int)$_afkFalangLid)
             ->where($db->quoteName('reference_table').'='.$db->quote('modules'))
             ->where($db->quoteName('reference_field').'='.$db->quote('title'))
             ->where($db->quoteName('published').'=1');
    $db->setQuery($q);
    foreach ($db->loadObjectList() as $_r) {
        $_afkFalangTitles[(int)$_r->reference_id] = $_r->value;
    }
}
// Sidebar modules
$_afkSidebarModules = ModuleHelper::getModules('rse-exams-sidebar');
$_afkHasSidebar = !empty($_afkSidebarModules);
?>

<?php if ($_afkHasSidebar): ?>
<div class="row rse-with-sidebar g-0">
<div class="col-lg-9 col-md-12">
<?php endif; ?>

<div class="rsepro-events-list-container">

    <?php // Titre de page masqué ?>
    <?php if ($this->params->get('show_category_title', 0) && $this->category) { ?><h2><span class="subheading-category"><?php echo $this->category->title; ?></span></h2><?php } ?>

    <?php if (($this->params->get('show_category_description', 0) || $this->params->def('show_category_image', 0)) && $this->category) { ?>
        <div class="category-desc">
        <?php if ($this->params->get('show_category_image') && $this->category->getParams()->get('image')) { ?>
            <img src="<?php echo $this->category->getParams()->get('image'); ?>" alt="" />
        <?php } ?>
        <?php if ($this->params->get('show_category_description') && $this->category->description) { ?>
            <?php echo HTMLHelper::_('content.prepare', $this->category->description, '', 'com_content.category'); ?>
        <?php } ?>
        <div class="clearfix"></div>
        </div>
    <?php } ?>



    <?php if ($this->params->get('search',1)) { ?>
    <form method="post" action="<?php echo $this->escape(Uri::getInstance()); ?>" name="adminForm" id="adminForm">
        <?php echo LayoutHelper::render('rseventspro.filter_'.(rseventsproHelper::isJ4() ? 'j4' : 'j3'), array('view' => $this)); ?>
    </form>
    <?php } else { ?>
    <?php if (!empty($this->columns)) { ?>
    <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=clear'); ?>" class="rs_filter_clear"><?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_CLEAR_FILTER'); ?></a>
    <div class="clearfix"></div>
    <?php } ?>
    <?php } ?>

    <?php
    // AFK: détecter page Événements (cat 54 uniquement) — pas de tarif, pas de liens loc/date
    $_afk_cats = (array) $this->params->get('categories', []);
    $_afk_is_evenements = (count($_afk_cats) === 1 && in_array('54', array_map('strval', $_afk_cats)));
    ?>
    <?php if (!empty($this->events)) { ?>
    <ul class="<?php echo rseventsproHelper::layout('container'); ?>" id="rs_events_container">
        <?php $eventIds = rseventsproHelper::getEventIds($this->events, 'id'); ?>
        <?php $this->events = rseventsproHelper::details($eventIds); ?>
        <?php foreach($this->events as $details) { ?>
        <?php if (isset($details['event']) && !empty($details['event'])) $event = $details['event']; else continue; ?>
        <?php if (!rseventsproHelper::canview($event->id) && $event->owner != $this->user) continue; ?>
        <?php
        // ─── Filtrage et état d'inscription ─────────────────────────
        $_now = time();
        $_start = !empty($event->start) ? strtotime($event->start) : 0;
        $_endReg = !empty($event->end_registration) ? strtotime($event->end_registration) : 0;
        // Masquer les événements dont l'examen est passé
        if ($_start > 0 && $_start < $_now) continue;
        // Inscription fermée si end_registration < now
        $_regClosed = ($_endReg > 0 && $_endReg < $_now);
        // ─────────────────────────────────────────────────────────────
        ?>
        <?php $full = rseventsproHelper::eventisfull($event->id); ?>
        <?php $ongoing = rseventsproHelper::ongoing($event->id); ?>
        <?php $categories = (isset($details['categories']) && !empty($details['categories'])) ? Text::_('COM_RSEVENTSPRO_GLOBAL_CATEGORIES').': '.$details['categories'] : '';  ?>
        <?php $tags = (isset($details['tags']) && !empty($details['tags'])) ? Text::_('COM_RSEVENTSPRO_GLOBAL_TAGS').': '.$details['tags'] : '';  ?>
        <?php $incomplete = !$event->completed ? ' rs_incomplete' : ''; ?>
        <?php $featured = $event->featured ? ' rs_featured' : ''; ?>
        <?php $canceled = $event->published == 3 ? ' rsepro_canceled_event_block' : ''; ?>
        <?php $repeats = rseventsproHelper::getRepeats($event->id); ?>
        <?php $lastMY = rseventsproHelper::showdate($event->start,'mY'); ?>
        <?php $canEdit = (!empty($this->permissions['can_edit_events']) || $event->owner == $this->user || $event->sid == $this->user || $this->admin) && !empty($this->user); ?>
        <?php $canDelete = (!empty($this->permissions['can_delete_events']) || $event->owner == $this->user || $event->sid == $this->user || $this->admin) && !empty($this->user); ?>
        <?php
        // AFK: URL formulaire avec pré-remplissage
        $_afkFormBase = Route::_('index.php?Itemid=776');
        $_afkExamType = afk_exam_type($event->name);
        $_afkDt = new DateTime($event->start, new DateTimeZone('UTC'));
        $_afkDt->setTimezone(new DateTimeZone('Asia/Shanghai'));
        $_afkDate = $_afkDt->format('d/m/Y');
        $_afkSep      = (strpos($_afkFormBase, '?') !== false) ? '&' : '?';
        $_afkFormUrl  = $_afkFormBase . $_afkSep
                      . 'form%5BChoix_exam%5D%5B%5D=' . urlencode($_afkExamType)
                      . '&form%5BSession%5D%5B%5D='   . urlencode($_afkDate);
        // AFK: ajouter le professeur pour distinguer deux cours le même jour
        $_afkTeacher = '';
        if (!empty($event->description)) {
            $_afkDescRaw = strip_tags($event->description);
            if (preg_match('/Enseignant[^:]*:\s*(.+?)(?=Tarif|VooV|Dur|\n|$)/u', $_afkDescRaw, $_afkTm))
                $_afkTeacher = trim($_afkTm[1]);
        }
        if ($_afkTeacher) $_afkFormUrl .= '&form%5BProfesseur%5D%5B%5D=' . urlencode($_afkTeacher);
        // AFK: Badge tarif (depuis small_description ou valeur par défaut)
        $_afkTarif = '2 700 ¥';
        if (!empty($event->small_description)) {
            $stripped = strip_tags($event->small_description);
            if (preg_match('/[\d\s]+\s*[¥€$￥]|[¥€$￥]\s*[\d\s]+/u', $stripped, $tm)) {
                $_afkTarif = trim($tm[0]);
            }
        }
        ?>

        <?php if ($monthYear = rseventsproHelper::showMonthYear($event->start, 'events'.$this->fid)) { ?>
            <li class="rsepro-my-grouped <?php echo rseventsproHelper::layout('event-grouped-by'); ?>"><span><?php echo $monthYear; ?></span></li>
        <?php } ?>

        <?php if ($_afk_is_evenements) :
        // ══════════════════════════════════════════════════════════
        // CARTE ÉVÉNEMENT (cat 54) — image + date plain + lieu plain
        // ══════════════════════════════════════════════════════════
        $_evt_img = !empty($event->icon)
            ? Uri::root(true) . '/components/com_rseventspro/assets/images/events/' . htmlspecialchars($event->icon)
            : null;
        $_evt_ts  = strtotime($event->start);
        $_evt_tse = $event->end ? strtotime($event->end) : null;
        $_evt_lang = Factory::getLanguage()->getTag();
        $_months_fr = ['','janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        $_evt_date_str = intval(date('j',$_evt_ts)) . ' ' . $_months_fr[intval(date('n',$_evt_ts))] . ' ' . date('Y',$_evt_ts)
            . ' · ' . date('G\\hi', $_evt_ts)
            . ($_evt_tse ? '–' . date('G\\hi', $_evt_tse) : '');
        if (strpos($_evt_lang,'en') !== false) {
            $en_months = ['','January','February','March','April','May','June','July','August','September','October','November','December'];
            $_evt_date_str = $en_months[intval(date('n',$_evt_ts))] . ' ' . date('j, Y',$_evt_ts)
                . ', ' . date('g:ia',$_evt_ts) . ($_evt_tse ? '–'.date('g:ia',$_evt_tse) : '');
        } elseif (strpos($_evt_lang,'zh') !== false) {
            $_evt_date_str = date('Y',$_evt_ts).'年'.intval(date('n',$_evt_ts)).'月'.intval(date('j',$_evt_ts)).'日 '
                . date('G:i',$_evt_ts) . ($_evt_tse ? '–'.date('G:i',$_evt_tse) : '');
        }
        $_evt_loc = htmlspecialchars(trim(preg_replace('/\s+/',' ', $event->location ?? '')));
        $_evt_desc = strip_tags($event->small_description ?? '');
        $_evt_lbl = strpos($_evt_lang,'zh')!==false ? '了解更多' : (strpos($_evt_lang,'en')!==false ? 'Learn more' : 'En savoir plus');
        $_evt_url = rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id='.rseventsproHelper::sef($event->id,$event->name),false,rseventsproHelper::itemid($event->id));
        ?>
        <li class="afk-evt-page-card<?php echo $canceled; ?>" id="rs_event<?php echo $event->id; ?>" itemscope itemtype="http://schema.org/Event">
            <?php if ($_evt_img) : ?>
            <div class="afk-evt-page-card__imgwrap">
                <img class="afk-evt-page-card__img" src="<?php echo $_evt_img; ?>" alt="<?php echo htmlspecialchars($event->name); ?>" loading="lazy" />
            </div>
            <?php endif; ?>
            <div class="afk-evt-page-card__body">
                <h3 class="afk-evt-page-card__title"><?php echo htmlspecialchars($event->name); ?></h3>
                <p class="afk-evt-page-card__date"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?php echo $_evt_date_str; ?></p>
                <?php if ($_evt_loc) : ?><p class="afk-evt-page-card__loc"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-2px;margin-right:4px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg><?php echo $_evt_loc; ?></p><?php endif; ?>
                <?php if ($_evt_desc) : ?><p class="afk-evt-page-card__desc"><?php echo htmlspecialchars($_evt_desc); ?></p><?php endif; ?>

            </div>
            <meta content="<?php echo rseventsproHelper::showdate($event->start,'Y-m-d H:i:s'); ?>" itemprop="startDate" />
        </li>
        <?php else :
        // ══════════════════════════════════════════════════════════
        // CARTE EXAMEN (comportement existant)
        // ══════════════════════════════════════════════════════════
        ?>
        <li class="<?php echo rseventsproHelper::layout('item-container'); ?> afk-event-card<?php echo $incomplete.$featured.$canceled; ?>" id="rs_event<?php echo $event->id; ?>" itemscope itemtype="http://schema.org/Event">

            <!-- Badge tarif -->
            <span class="afk-tarif-badge"><?php echo htmlspecialchars($_afkTarif, ENT_QUOTES, 'UTF-8'); ?></span>

            <!-- Lien overlay -->
            <a href="<?php echo htmlspecialchars($_afkFormUrl, ENT_QUOTES, 'UTF-8'); ?>" class="afk-card-overlay-link" aria-label="<?php echo htmlspecialchars($event->name, ENT_QUOTES, 'UTF-8'); ?>"></a>

            <?php if ($canEdit || $canDelete) { ?>
            <div class="rsepro-event-options <?php echo rseventsproHelper::layout('options'); ?>" style="display:none;">
                <?php if ($canEdit) { ?>
                    <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=edit&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
                        <i class="fa fa-edit"></i>
                    </a>
                <?php } ?>
                <?php if ($canDelete) { ?>
                    <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=rseventspro.remove&id='.rseventsproHelper::sef($event->id,$event->name)); ?>" onclick="return confirm('<?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_DELETE_CONFIRMATION'); ?>');">
                        <i class="fa fa-trash"></i>
                    </a>
                <?php } ?>
            </div>
            <?php } ?>

            <?php if (!empty($event->options['show_icon_list'])) { ?>
            <div class="<?php echo rseventsproHelper::layout('image-container'); ?>" itemprop="image">
                <img src="<?php echo afk_exam_image($event->name); ?>" alt="<?php echo htmlspecialchars(preg_replace('/\s*\x{2014}.*$/u','', $event->name), ENT_QUOTES, 'UTF-8'); ?>" style="width:<?php echo rseventsproHelper::layout('image-width'); ?>px; height:auto; object-fit:contain;" />
            </div>
            <?php } ?>

            <div class="<?php echo rseventsproHelper::layout('event-details-container'); ?>">
                <div itemprop="name" class="<?php echo rseventsproHelper::layout('event-title'); ?>">
                    <span class="rs_event_name<?php echo $full ? ' rs_event_full' : ''; ?><?php echo $ongoing ? ' rs_event_ongoing' : ''; ?>"><?php echo preg_replace('/\s*\x{2014}.*$/u', '', $event->name); ?></span>
                    <?php if ($_regClosed): ?>
                        <span class="rsepro-reg-closed" style="display:inline-block;margin-left:8px;padding:2px 8px;background:#da002e;color:#fff;border-radius:3px;font-size:0.78rem;font-weight:600;vertical-align:middle;"><?php echo (strpos(Factory::getApplication()->getLanguage()->getTag(),'zh')===0) ? '报名已截止' : 'Inscriptions fermées'; ?></span>
                    <?php elseif ($_endReg > 0): ?>
                        <span class="rsepro-reg-deadline" style="display:block;font-size:0.82rem;color:#888;margin-top:2px;">
                        <?php
                        $_tag = Factory::getApplication()->getLanguage()->getTag();
                        if (strpos($_tag,'zh')===0) {
                            echo '报名截止：' . date('Y', strtotime($event->end_registration)) . '年' . (int)date('n', strtotime($event->end_registration)) . '月' . (int)date('j', strtotime($event->end_registration)) . '日';
                        } else {
                            $_months_fr = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
                            echo 'Inscription avant le ' . (int)date('j', strtotime($event->end_registration)) . ' ' . $_months_fr[(int)date('n', strtotime($event->end_registration))] . ' ' . date('Y', strtotime($event->end_registration));
                        }
                        ?>
                        </span>
                    <?php endif; ?> <?php if (!$event->completed) echo Text::_('COM_RSEVENTSPRO_GLOBAL_INCOMPLETE_EVENT'); ?> <?php if (!$event->published) echo Text::_('COM_RSEVENTSPRO_GLOBAL_UNPUBLISHED_EVENT'); ?> <?php if ($event->published == 3) echo '<small class="text-error">('.Text::_('COM_RSEVENTSPRO_EVENT_CANCELED_TEXT').')</small>'; ?>
                </div>
                <div class="<?php echo rseventsproHelper::layout('event-date'); ?>">
                    <?php if ($event->allday) { ?>
                    <?php if (!empty($event->options['start_date_list'])) { ?>
                    <span class="rsepro-event-on-block">
                    <?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_ON'); ?> <b><?php
$_tag = \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
if (strpos($_tag, 'zh') === 0) {
    echo \Joomla\CMS\HTML\HTMLHelper::_('date', $event->start, 'Y年n月j日');
} else {
    echo \Joomla\CMS\HTML\HTMLHelper::_('date', $event->start, 'j F Y');
}
?></b>
                    </span>
                    <?php } ?>
                    <?php } else { ?>

                    <?php if (!empty($event->options['start_date_list']) || !empty($event->options['start_time_list']) || !empty($event->options['end_date_list']) || !empty($event->options['end_time_list'])) { ?>
                    <?php if (!empty($event->options['start_date_list']) || !empty($event->options['start_time_list'])) { ?>
                    <?php if ((!empty($event->options['start_date_list']) || !empty($event->options['start_time_list'])) && empty($event->options['end_date_list']) && empty($event->options['end_time_list'])) { ?>
                    <span class="rsepro-event-starting-block">
                    <?php echo Text::_('COM_RSEVENTSPRO_EVENT_STARTING_ON'); ?>
                    <?php } else { ?>
                    <span class="rsepro-event-from-block">
                    <?php echo Text::_('COM_RSEVENTSPRO_EVENT_FROM'); ?>
                    <?php } ?>
                    <b><?php echo rseventsproHelper::showdate($event->start,rseventsproHelper::showMask('list_start',$event->options),true); ?></b>
                    </span>
                    <?php } ?>
                    <?php if (!empty($event->options['end_date_list']) || !empty($event->options['end_time_list'])) { ?>
                    <?php if ((!empty($event->options['end_date_list']) || !empty($event->options['end_time_list'])) && empty($event->options['start_date_list']) && empty($event->options['start_time_list'])) { ?>
                    <span class="rsepro-event-ending-block">
                    <?php echo Text::_('COM_RSEVENTSPRO_EVENT_ENDING_ON'); ?>
                    <?php } else { ?>
                    <span class="rsepro-event-until-block">
                    <?php echo Text::_('COM_RSEVENTSPRO_EVENT_UNTIL'); ?>
                    <?php } ?>
                    <b><?php echo rseventsproHelper::showdate($event->end,rseventsproHelper::showMask('list_end',$event->options),true); ?></b>
                    </span>
                    <?php } ?>
                    <?php } ?>

                    <?php } ?>
                </div>

                <?php if (!empty($event->options['show_location_list']) || !empty($event->options['show_categories_list']) || !empty($event->options['show_tags_list'])) { ?>
                <div class="<?php echo rseventsproHelper::layout('event-taxonomy'); ?>">
                    <?php if ($event->locationid && $event->lpublished && !empty($event->options['show_location_list'])) { ?>
                    <span class="rsepro-event-location-block" itemprop="location" itemscope itemtype="http://schema.org/Place"><?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_AT'); ?> <?php if (!$_afk_is_evenements) : ?><a itemprop="url" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=location&id='.rseventsproHelper::sef($event->locationid,$event->location)); ?>"><span itemprop="name"><?php echo $event->location; ?></span></a><?php else : ?><span itemprop="name"><?php echo $event->location; ?></span><?php endif; ?>
                    <span itemprop="address" style="display:none;"><?php echo $event->address; ?></span>
                    </span>
                    <?php } ?>
                    <?php if (false) { // Catégories masquées ?>
                    <span class="rsepro-event-categories-block"><?php echo $categories; ?></span>
                    <?php } ?>
                    <?php if (!empty($event->options['show_tags_list'])) { ?>
                    <span class="rsepro-event-tags-block"><?php echo $tags; ?></span>
                    <?php } ?>
                </div>
                <?php } ?>

                <?php if (!empty($event->small_description)) { ?>
                <div class="<?php echo rseventsproHelper::layout('event-description'); ?>">
                    <?php echo $event->small_description; ?>
                </div>
                <?php } ?>

                <?php if ($this->params->get('repeatcounter',1) && $repeats) { ?>
                <div class="<?php echo rseventsproHelper::layout('event-repeats'); ?>">
                    <?php if ($repeats) { ?>
                    <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=default&parent='.rseventsproHelper::sef($event->id,$event->name)); ?>"><?php echo Text::sprintf('COM_RSEVENTSPRO_GLOBAL_REPEATS',$repeats); ?></a>
                    <?php } ?>
                </div>
                <?php } ?>
            </div>

            <meta content="<?php echo rseventsproHelper::showdate($event->start,'Y-m-d H:i:s'); ?>" itemprop="startDate" />
            <?php if (!$event->allday) { ?><meta content="<?php echo rseventsproHelper::showdate($event->end,'Y-m-d H:i:s'); ?>" itemprop="endDate" /><?php } ?>
        </li>
        <?php endif; // fin if/else cat54 vs examens ?>
        <?php } ?>
    </ul>

<?php if ($_afk_is_evenements) : ?>
<style>
/* — Grille cartes Événements page actualités — */
#rs_events_container { display:flex !important; flex-wrap:wrap; gap:24px; padding:0; list-style:none; margin:0; }
.afk-evt-page-card { flex:1 1 100%; min-width:260px; background:#fff; border:1.5px solid rgba(192,57,90,0.3); border-radius:8px; overflow:hidden; display:flex; flex-direction:column; transition:box-shadow .2s; }
.afk-evt-page-card:hover { box-shadow:0 4px 20px rgba(192,57,90,0.15); }
.afk-evt-page-card__imgwrap { display:block; }
.afk-evt-page-card__img { width:100%; height:200px; object-fit:cover; display:block; }
.afk-evt-page-card__body { padding:16px 18px; flex:1; display:flex; flex-direction:column; gap:8px; }
.afk-evt-page-card__title { font-size:1.05rem; font-weight:700; color:#1a171b; line-height:1.35; margin:0; }
.afk-evt-page-card__title a { color:inherit; text-decoration:none; }
.afk-evt-page-card__title a:hover { color:rgba(192,57,90,0.92); }
.afk-evt-page-card__date { font-size:.84rem; color:#444; margin:0; }
.afk-evt-page-card__loc { font-size:.84rem; color:#5a5a5a; margin:0; }
.afk-evt-page-card__desc { font-size:.83rem; color:#555; line-height:1.5; margin:4px 0 0; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
.afk-evt-page-card__btn { display:inline-block; margin-top:auto; padding:7px 16px; background:rgba(192,57,90,0.92); color:#fff !important; border-radius:4px; font-size:.83rem; font-weight:600; text-decoration:none; transition:background .2s; align-self:flex-start; }
.afk-evt-page-card__btn:hover { background:rgba(160,40,70,1); }
@media(max-width:767px){ .afk-evt-page-card { flex:1 1 100%; } }

</style>
<?php endif; ?>

    <?php rseventsproHelper::clearMonthYear('events'.$this->fid, @$lastMY); ?>

    <div class="rs_loader" id="rs_loader" style="display:none;">
        <?php echo HTMLHelper::image('com_rseventspro/loader.gif', '', array(), true); ?>
    </div>

    <?php if ($this->total > $count) { ?>
    <p id="rsepro_number_events"><?php echo Text::sprintf('COM_RSEVENTSPRO_SHOWING_EVENTS','<span>'.$count.'</span>',$this->total); ?></p>
    <a class="rs_read_more" id="rsepro_loadmore"><?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_LOAD_MORE'); ?></a>
    <?php } ?>

    <span id="total" class="rs_hidden"><?php echo $this->total; ?></span>
    <span id="Itemid" class="rs_hidden"><?php echo Factory::getApplication()->input->getInt('Itemid'); ?></span>
    <span id="langcode" class="rs_hidden"><?php echo rseventsproHelper::getLanguageCode(); ?></span>
    <span id="parent" class="rs_hidden"><?php echo Factory::getApplication()->input->getInt('parent'); ?></span>
    <span id="rsepro-prefix" class="rs_hidden"><?php echo 'events'.$this->fid; ?></span>
    <?php } else { ?>
    <div class="alert alert-warning"><?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_NO_EVENTS'); ?></div>
    <?php } ?>

    <?php if ($this->config->timezone) echo rseventsproHelper::timezoneModal(); ?>
</div>

<script type="text/javascript">	
	jQuery(document).ready(function() {
		<?php if ($this->total > $count) { ?>
		jQuery('#rsepro_loadmore').on('click', function() {
			rspagination('events',jQuery('#rs_events_container > li').not('.rsepro-my-grouped').length);
		});
		<?php } ?>
		
		<?php if (!empty($count)) { ?>
		jQuery('#rs_events_container > li').not('.rsepro-my-grouped').on({
			mouseenter: function() {
				jQuery(this).find('div.rsepro-event-options').css('display','');
			},
			mouseleave: function() {
				jQuery(this).find('div.rsepro-event-options').css('display','none');
			}
		});
		<?php } ?>
		
		<?php if ($this->params->get('search',1)) { ?>
		var options = {};
		options.condition = '.rsepro-filter-operator';
		options.events = [{'#rsepro-filter-from' : 'rsepro_select'}];
		jQuery().rsjoomlafilter(options);	
		<?php } ?>
	});
</script>

<?php if ($_afkHasSidebar): ?>
</div><!-- /col-lg-9 -->
<div class="col-lg-3 col-md-12 rse-sidebar-col">
    <?php foreach ($_afkSidebarModules as $_afkMod): ?>
    <?php $_afkModClass = (strpos($_afkMod->module, 'mod_menu') !== false) ? 'afk-sidebar-module afk-sidebar-menu' : 'afk-sidebar-module afk-sidebar-card'; ?>
    <div class="<?php echo $_afkModClass; ?>">
        <?php if ($_afkMod->showtitle): ?>
        <?php $_afkTitle = $_afkFalangTitles[(int)$_afkMod->id] ?? $_afkMod->title; ?>
        <div class="afk-sidebar-title"><?php echo htmlspecialchars($_afkTitle); ?></div>
        <?php endif; ?>
        <div class="afk-sidebar-content">
            <?php $_afkMod->showtitle = 0; // évite le double titre via chrome ?>
            <?php echo ModuleHelper::renderModule($_afkMod, ['style'=>'none']); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div><!-- /rse-sidebar-col -->
</div><!-- /row rse-with-sidebar -->
<?php endif; ?>
<?php
// Correction ordre + catégories pour ZH/EN (RSEvents bug multilingue)
$bodyLang = strtolower(Factory::getLanguage()->getTag()); // zh-cn ou en-gb
$isAltLang = in_array($bodyLang, ['zh-cn', 'en-gb']);
if ($isAltLang):
  // Charger les IDs d'événements appartenant aux catégories exam (45,47,48,49)
  $_afkExamCatIds = [45, 47, 48, 49];
  $_afkDb2 = Factory::getDbo();
  $_afkDb2->setQuery('SELECT DISTINCT tx.ide FROM #__rseventspro_taxonomy tx WHERE tx.type=' . $_afkDb2->quote('category') . ' AND tx.id IN (' . implode(',', $_afkExamCatIds) . ')');
  $_afkExamIds = array_map('intval', array_column($_afkDb2->loadObjectList(), 'ide'));
  // Générer les sélecteurs CSS pour masquer les événements hors catégorie exam
  // On cache tout #rs_events_container li.afk-event-card sauf ceux dans $_afkExamIds
  // Approche : cacher par exclusion via :not(#rs_eventXX) chainé
  // Récupérer les IDs exam triés par date ASC pour le CSS order
  $_afkDb2->setQuery('SELECT DISTINCT e.id FROM #__rseventspro_events e JOIN #__rseventspro_taxonomy tx ON tx.ide=e.id WHERE tx.type=' . $_afkDb2->quote('category') . ' AND tx.id IN (' . implode(',', $_afkExamCatIds) . ') AND e.published IN (1,3) ORDER BY e.start ASC');
  $_afkExamIdsSorted = array_map('intval', array_column($_afkDb2->loadObjectList(), 'id'));
  $_afkExamSelectors = implode(',', array_map(fn($id) => "#rs_events_container #rs_event{$id}", $_afkExamIdsSorted));
?>
<style>
/* ZH/EN : masquer les événements hors catégories exam par ID + ordre flexbox ASC */
body.<?php echo $bodyLang; ?> #rs_events_container { display: flex !important; flex-direction: column !important; }
body.<?php echo $bodyLang; ?> #rs_events_container li { order: 999; }
body.<?php echo $bodyLang; ?> #rs_events_container li.afk-event-card { display: none !important; }
body.<?php echo $bodyLang; ?> #rs_events_container .rsepro-my-grouped { display: none !important; }
<?php if ($_afkExamSelectors): ?>
body.<?php echo $bodyLang; ?> <?php echo $_afkExamSelectors; ?> { display: flex !important; }
<?php foreach ($_afkExamIdsSorted as $_afkIdx => $_afkEid): ?>
body.<?php echo $bodyLang; ?> #rs_events_container #rs_event<?php echo $_afkEid; ?> { order: <?php echo $_afkIdx + 1; ?>; }
<?php endforeach; ?>
<?php endif; ?>
</style>
<script>
(function(){
  function afkFixCalendar(){
    var list = document.querySelector('#rs_events_container, ul.rsepro-events-list, ul[class*="rsepro"]');
    if (!list) return;

    var items = [...list.querySelectorAll('li.afk-event-card')];
    if (!items.length) return;

    // 1. Masquer les events hors catégories exam (filtrage par ID)
    var examIds = <?php echo $_afkExamIds ?? '[]'; ?>;
    items.forEach(function(li){
      var m = li.id.match(/rs_event(\d+)/);
      var eid = m ? parseInt(m[1], 10) : -1;
      if (eid < 0 || examIds.indexOf(eid) === -1) {
        li.style.display = 'none';
        li.setAttribute('aria-hidden', 'true');
      }
    });

    // 2. Re-trier les events visibles par date ASC
    var groupItems = [...list.querySelectorAll('li')];
    var blocks = [];
    var current = [];
    groupItems.forEach(function(li){
      if (li.classList.contains('rsepro-my-grouped')) {
        if (current.length) blocks.push(current);
        current = [li];
      } else {
        current.push(li);
      }
    });
    if (current.length) blocks.push(current);

    // Extraire date depuis le contenu de la card
    function getDate(block) {
      var dateEl = block.find(function(el){ return el.querySelector && el.querySelector('.rsepro-event-on-block b'); });
      if (!dateEl) return '';
      return dateEl.querySelector('.rsepro-event-on-block b').textContent.trim();
    }

    // Re-trier les blocs par date ISO contenue dans le meta startDate
    blocks.sort(function(a, b){
      var ma = a.find ? a.find(function(el){ return el.querySelector && el.querySelector('meta[itemprop="startDate"]'); }) : null;
      var mb = b.find ? b.find(function(el){ return el.querySelector && el.querySelector('meta[itemprop="startDate"]'); }) : null;
      var da = ma ? (ma.querySelector('meta[itemprop="startDate"]') || {}).getAttribute('content') || '' : '';
      var db = mb ? (mb.querySelector('meta[itemprop="startDate"]') || {}).getAttribute('content') || '' : '';
      return da < db ? -1 : da > db ? 1 : 0;
    });

    // Remettre dans le DOM
    blocks.forEach(function(block){
      block.forEach(function(el){ list.appendChild(el); });
    });
  } // end afkFixCalendar

  // Exécuter après l'initialisation RSEvents (jQuery ready + délai)
  if (window.jQuery) {
    jQuery(function(){ setTimeout(afkFixCalendar, 300); });
  } else {
    window.addEventListener('load', function(){ setTimeout(afkFixCalendar, 300); });
  }
})();
</script>
<?php endif; ?>

<?php
// Badge tarif : label selon type d'examen + langue
$_afkTarifLang = $bodyLang;
?>
<script>
(function(){
  var lang = '<?php echo htmlspecialchars($_afkTarifLang ?? 'fr-fr'); ?>';
  var tarifs = {
    canada: {
      'fr-fr': '4 comp&eacute;tences &mdash; 2&thinsp;700&nbsp;&yen;',
      'zh-cn': '4项技能 &mdash; 2&thinsp;700&nbsp;&yen;',
      'en-gb': '4 skills &mdash; 2,700&nbsp;&yen;'
    },
    quebec: {
      'fr-fr': '&Agrave; partir de 675&nbsp;&yen;&thinsp;/&thinsp;comp&eacute;tence',
      'zh-cn': '起&nbsp;675&nbsp;&yen;&thinsp;/&thinsp;技能',
      'en-gb': 'From&nbsp;675&nbsp;&yen;&thinsp;/&thinsp;skill'
    }
  };
  function updateBadges() {
    document.querySelectorAll('li.afk-event-card').forEach(function(card) {
      var badge = card.querySelector('.afk-tarif-badge');
      if (!badge) return;
      var nameEl = card.querySelector('[itemprop="name"]');
      var name = nameEl ? nameEl.textContent : '';
      var key = null;
      if (/Canada|加拿大/i.test(name)) key = 'canada';
      else if (/Qu[eé]bec|TEFAQ|魁北克/i.test(name)) key = 'quebec';
      if (!key) return;
      var l = lang || 'fr-fr';
      badge.innerHTML = (tarifs[key][l] || tarifs[key]['fr-fr']);
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateBadges);
  } else {
    updateBadges();
  }
  window.addEventListener('load', function(){ setTimeout(updateBadges, 150); });
})();
</script>
