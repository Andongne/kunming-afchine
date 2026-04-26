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

    <div class="rs_rss">
        <?php Factory::getApplication()->triggerEvent('onrsepro_showCartIcon'); ?>

        <?php if (!empty($this->permissions['can_scan'])) { ?>
            <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=scan'); ?>" class="<?php echo rseventsproHelper::tooltipClass(); ?>" title="<?php echo rseventsproHelper::tooltipText(Text::_('COM_RSEVENTSPRO_SCAN_TICKETS')); ?>">
                <i class="fa fa-barcode"></i>
            </a>
        <?php } ?>

        <?php if (!empty($this->permissions['can_edit_subscriptions'])) { ?>
        <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&view=subscriptions'); ?>" class="<?php echo rseventsproHelper::tooltipClass(); ?>" title="<?php echo rseventsproHelper::tooltipText(Text::_('COM_RSEVENTSPRO_VIEW_ALL_SUBSCRIPTIONS')); ?>">
            <i class="fa fa-user"></i>
        </a>
        <?php } ?>

        <?php if ($this->config->mysubscriptions) { ?>
        <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=subscriptions'); ?>" class="<?php echo rseventsproHelper::tooltipClass(); ?>" title="<?php echo rseventsproHelper::tooltipText(Text::_('COM_RSEVENTSPRO_VIEW_USER_SUBSCRIPTIONS')); ?>">
            <i class="fa fa-user"></i>
        </a>
        <?php } ?>

        <?php if ($rss || $ical || $this->config->timezone) { ?>
        <?php if ($this->config->timezone) { ?>
        <a rel="rs_timezone" <?php if (rseventsproHelper::getConfig('modaltype','int') == 1) echo ' href="#timezoneModal" data-toggle="modal" data-bs-toggle="modal"'; else echo ' href="javascript:void(0)"'; ?> class="<?php echo rseventsproHelper::tooltipClass(); ?> rsepro-timezone" title="<?php echo rseventsproHelper::tooltipText(Text::_('COM_RSEVENTSPRO_CHANGE_TIMEZONE')); ?>">
            <i class="fa fa-clock"></i>
        </a>
        <?php } ?>

        <?php if ($rss) { ?>
        <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&format=feed&type=rss'); ?>" class="<?php echo rseventsproHelper::tooltipClass(); ?> rsepro-rss" title="<?php echo rseventsproHelper::tooltipText(Text::_('COM_RSEVENTSPRO_RSS')); ?>">
            <i class="fa fa-rss-square"></i>
        </a>
        <?php } ?>
        <?php if ($ical) { ?>
        <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&format=raw&type=ical'); ?>" class="<?php echo rseventsproHelper::tooltipClass(); ?> rsepro-ical" title="<?php echo rseventsproHelper::tooltipText(Text::_('COM_RSEVENTSPRO_ICS')); ?>">
            <i class="fa fa-calendar"></i>
        </a>
        <?php } ?>
        <?php } ?>
    </div>

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

        <?php if ($monthYear = rseventsproHelper::showMonthYear($event->start, 'events'.$this->fid)) { ?>
            <li class="rsepro-my-grouped <?php echo rseventsproHelper::layout('event-grouped-by'); ?>"><span><?php echo $monthYear; ?></span></li>
        <?php } ?>

        <li class="<?php echo rseventsproHelper::layout('item-container'); ?><?php echo $incomplete.$featured.$canceled; ?>" id="rs_event<?php echo $event->id; ?>" itemscope itemtype="http://schema.org/Event">

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
                <a href="<?php echo afk_event_url($event->id,$event->name,$_afk_list_lang); ?>" class="thumbnail" aria-label="<?php echo $event->name; ?>">
                    <img src="<?php echo rseventsproHelper::thumb($event->id, rseventsproHelper::layout('image-width')); ?>" alt="" width="<?php echo rseventsproHelper::layout('image-width'); ?>" />
                </a>
            </div>
            <?php } ?>

            <div class="<?php echo rseventsproHelper::layout('event-details-container'); ?>">
                <div itemprop="name" class="<?php echo rseventsproHelper::layout('event-title'); ?>">
                    <a itemprop="url" href="<?php echo afk_event_url($event->id,$event->name,$_afk_list_lang); ?>" class="<?php echo $full ? ' rs_event_full' : ''; ?><?php echo $ongoing ? ' rs_event_ongoing' : ''; ?>"><?php echo preg_replace('/\s*\x{2014}.*$/u', '', $event->name); ?></a>
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