<?php
/**
* @package RSEvents!Pro
* @copyright (C) 2020 www.rsjoomla.com
* @license GPL, http://www.gnu.org/copyleft/gpl.html
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

// AFK: tooltip sans le label COM_RSEVENTSPRO_CALENDAR_EVENTS
if (!function_exists('afk_calendar_tooltip_text')) {
    function afk_calendar_tooltip_text($ids) {
        $raw = modRseventsProCalendar::getDetailsSmall($ids);
        $pos = strpos($raw, '::');
        return $pos !== false ? substr($raw, $pos + 2) : $raw;
    }
}

// AFK: URL vers l'événement individuel (premier événement du jour)
// Si la date a plusieurs événements, redirige vers le premier.
if (!function_exists('afk_calendar_event_url')) {
    function afk_calendar_event_url($ids, $lang, $itemid) {
        if (empty($ids)) return '';
        $db = \Joomla\CMS\Factory::getDbo();
        $id = (int) reset($ids);
        $db->setQuery('SELECT id, name FROM #__rseventspro_events WHERE id=' . $id);
        $event = $db->loadObject();
        if (!$event) return '';
        // Appliquer la traduction RSEvents si disponible
        if (class_exists('RSEventsProTranslations')) {
            $t = RSEventsProTranslations::getTranslation('event', $event->id, 'name');
            if ($t) $event->name = $t;
        }
        $sef      = rseventsproHelper::sef($event->id, $event->name);
        $base_url = rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id=' . $sef, true, $itemid);
        // Injecter le préfixe de langue
        $_prefix_map = ['zh-CN' => 'zh', 'en-GB' => 'en', 'en' => 'en'];
        $_prefix = isset($_prefix_map[$lang]) ? $_prefix_map[$lang] : '';
        if ($_prefix) {
            $base_url = preg_replace('#^(/[a-zA-Z]{2,5})/#', '/', $base_url);
            return '/' . $_prefix . $base_url;
        }
        return $base_url;
    }
}

?>

<div id="rs_calendar_module<?php echo $module->id; ?>" class="rs_calendar_module<?php echo $calendar->class_suffix; ?>">

	<table cellpadding="0" cellspacing="2" border="0" width="100%" class="rs_table" style="width:100%;">
		<tr>
			<td align="left">
				<a rel="nofollow" href="javascript:void(0);" onclick="rs_calendar('<?php echo Uri::root(true); ?>/','<?php echo $calendar->getPrevMonth(); ?>','<?php echo $calendar->getPrevYear(); ?>','<?php echo $module->id; ?>')" class="rs_calendar_arrows_module" id="rs_calendar_arrow_left_module">&laquo;</a>
			</td>
			<td align="center">
				<?php $current = Factory::getDate($calendar->unixdate); ?>
				<span id="rscalendarmonth<?php echo $module->id; ?>"><?php $_tag = \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
if (strpos($_tag, 'zh') === 0) {
    echo $current->format('Y') . '年' . ltrim($current->format('m'), '0') . '月';
} else {
    // French locale-aware month name
    echo \Joomla\CMS\HTML\HTMLHelper::_('date', $calendar->unixdate, 'F Y');
} ?></span>
				<?php echo HTMLHelper::image('com_rseventspro/loader.gif', '', array('id' => 'rscalendar'.$module->id, 'style' => 'vertical-align: middle; display: none;'), true); ?>
			</td>
			<td align="right">
				<a rel="nofollow" href="javascript:void(0);" onclick="rs_calendar('<?php echo Uri::root(true); ?>/','<?php echo $calendar->getNextMonth(); ?>','<?php echo $calendar->getNextYear(); ?>','<?php echo $module->id; ?>')" class="rs_calendar_arrows_module" id="rs_calendar_arrow_right_module">&raquo;</a>
			</td>
		</tr>
	</table>

	<div class="rs_clear"></div>
	
	<table class="rs_calendar_module rs_table" cellpadding="0" cellspacing="0" width="100%">
		<thead>
			<tr>
				<?php
$_tag2 = \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
$_wdFR = ['Mo'=>'Lun','Tu'=>'Mar','We'=>'Mer','Th'=>'Jeu','Fr'=>'Ven','Sa'=>'Sam','Su'=>'Dim'];
$_wdZH = ['Mo'=>'一','Tu'=>'二','We'=>'三','Th'=>'四','Fr'=>'五','Sa'=>'六','Su'=>'日'];
foreach ($calendar->days->weekdays as $weekday) {
    if (strpos($_tag2, 'zh') === 0) {
        echo '<th>' . (isset($_wdZH[$weekday]) ? $_wdZH[$weekday] : $weekday) . '</th>';
    } elseif (strpos($_tag2, 'fr') === 0) {
        echo '<th>' . (isset($_wdFR[$weekday]) ? $_wdFR[$weekday] : $weekday) . '</th>';
    } else {
        echo '<th>' . $weekday . '</th>';
    }
}
?>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($calendar->days->days as $day) { ?>
		<?php $unixdate = Factory::getDate($day->unixdate); ?>
		<?php if ($day->day == $calendar->weekstart) { ?>
			<tr>
		<?php } ?>
				<td class="<?php echo $day->class; ?>">
					<?php
					$_cal_lang = \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
					$_cal_href = !empty($day->events)
					    ? afk_calendar_event_url($day->events, $_cal_lang, $itemid)
					    : rseventsproHelper::route('index.php?option=com_rseventspro&view=calendar&layout=day&date='.$unixdate->format('m-d-Y').'&mid='.$module->id, true, $itemid);
					?>
					<a <?php echo $nofollow; ?> href="<?php echo $_cal_href; ?>" class="<?php echo rseventsproHelper::tooltipClass(); ?>" title="<?php echo rseventsproHelper::tooltipText(afk_calendar_tooltip_text($day->events)); ?>">
						<span class="rs_calendar_date"><?php echo $unixdate->format('j'); ?></span>
					</a>
				</td>
			<?php if ($day->day == $calendar->weekend) { ?></tr><?php } ?>
			<?php } ?>
		</tbody>
	</table>
	
</div>
<div class="rs_clear"></div>