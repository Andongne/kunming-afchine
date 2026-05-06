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
        $_afkDate     = date('d/m/Y', strtotime($event->start));
        $_afkSep      = (strpos($_afkFormBase, '?') !== false) ? '&' : '?';
        $_afkFormUrl  = $_afkFormBase . $_afkSep
                      . 'form[Choix_exam][]=' . urlencode($_afkExamType)
                      . '&form[Session][]='   . urlencode($_afkDate);
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

        <li class="<?php echo rseventsproHelper::layout('item-container'); ?> afk-event-card<?php echo $incomplete.$featured.$canceled; ?>" id="rs_event<?php echo $event->id; ?>" itemscope itemtype="http://schema.org/Event">

            <!-- Badge tarif -->
            <span class="afk-tarif-badge"><?php echo htmlspecialchars($_afkTarif, ENT_QUOTES, 'UTF-8'); ?></span>

            <!-- Lien overlay : toute la card pointe vers le formulaire -->
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
                    <span class="rsepro-event-location-block" itemprop="location" itemscope itemtype="http://schema.org/Place"><?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_AT'); ?> <a itemprop="url" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=location&id='.rseventsproHelper::sef($event->locationid,$event->location)); ?>"><span itemprop="name"><?php echo $event->location; ?></span></a>
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
        <?php } ?>
    </ul>

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
        <div class="afk-sidebar-title"><?php echo htmlspecialchars($_afkMod->title); ?></div>
        <?php endif; ?>
        <div class="afk-sidebar-content">
            <?php echo ModuleHelper::renderModule($_afkMod, ['style'=>'none']); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div><!-- /rse-sidebar-col -->
</div><!-- /row rse-with-sidebar -->
<?php endif; ?>