<?php
/**
* @package RSEvents!Pro
* @copyright (C) 2020 www.rsjoomla.com
* @license GPL, http://www.gnu.org/copyleft/gpl.html
*/

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

?>
<?php echo 'RS_DELIMITER0'; ?>
<table cellpadding="0" cellspacing="2" border="0" width="100%" class="rs_table" style="width:100%;">
	<tr>
		<td align="left">
			<a rel="nofollow" href="javascript:void(0);" onclick="rs_calendar('<?php echo Uri::root(true); ?>/','<?php echo $this->calendar->getPrevMonth(); ?>','<?php echo $this->calendar->getPrevYear(); ?>','<?php echo $this->module; ?>')" class="rs_calendar_arrows_module" id="rs_calendar_arrow_left_module">&laquo;</a>
		</td>
		<td align="center">
			<?php $current = Factory::getDate($this->calendar->unixdate); ?>
			<span id="rscalendarmonth<?php echo $this->module; ?>"><?php $_tag = \Joomla\CMS\Factory::getApplication()->getLanguage()->getTag();
if (strpos($_tag, 'zh') === 0) {
    echo $current->format('Y') . '年' . ltrim($current->format('m'), '0') . '月';
} else {
    echo \Joomla\CMS\HTML\HTMLHelper::_('date', $this->calendar->unixdate, 'F Y');
} ?></span>
			<?php echo HTMLHelper::image('com_rseventspro/loader.gif', '', array('id' => 'rscalendar'.$this->module, 'style' => 'vertical-align: middle; display: none;'), true); ?>
		</td>
		<td align="right">
			<a rel="nofollow" href="javascript:void(0);" onclick="rs_calendar('<?php echo Uri::root(true); ?>/','<?php echo $this->calendar->getNextMonth(); ?>','<?php echo $this->calendar->getNextYear(); ?>','<?php echo $this->module; ?>')" class="rs_calendar_arrows_module" id="rs_calendar_arrow_right_module">&raquo;</a>
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
foreach ($this->calendar->days->weekdays as $weekday) {
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
	<?php foreach ($this->calendar->days->days as $day) { ?>
	<?php $unixdate = Factory::getDate($day->unixdate); ?>
	<?php if ($day->day == $this->calendar->weekstart) { ?>
		<tr>
	<?php } ?>
			<td class="<?php echo $day->class; ?>">
				<a <?php echo $this->nofollow; ?> href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&view=calendar&layout=day&date='.$unixdate->format('m-d-Y').'&mid='.$this->module,true,$this->itemid);?>" class="<?php echo rseventsproHelper::tooltipClass(); ?>" title="<?php echo rseventsproHelper::tooltipText(modRseventsProCalendar::getDetailsSmall($day->events)); ?>">
					<span class="rs_calendar_date"><?php echo $unixdate->format('j'); ?></span>
				</a>
			</td>
		<?php if ($day->day == $this->calendar->weekend) { ?></tr><?php } ?>
		<?php } ?>
	</tbody>
</table>
<?php echo 'RS_DELIMITER1'; ?>
