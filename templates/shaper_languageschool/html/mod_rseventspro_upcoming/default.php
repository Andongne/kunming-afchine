<?php
/**
* @package RSEvents!Pro — Template override AF Kunming
* Suppression de la date redondante entre parenthèses
* Masquage des événements dont l'inscription est fermée
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

$open = !$links ? 'target="_blank"' : ''; ?>

<?php if ($items) { ?>
<style>#rsepro-upcoming-module ul.rsepro_upcoming{margin-bottom:10px!important}#rsepro-upcoming-module ul.rsepro_upcoming li a{font-size:1.05rem!important;line-height:1.6}</style>
<div id="rsepro-upcoming-module">
	<?php foreach ($items as $block => $events) { ?>
	<ul class="rsepro_upcoming<?php echo $suffix; ?> <?php echo RSEventsproAdapterGrid::row(); ?>">
		<?php foreach ($events as $id) { ?>
		<?php $details = rseventsproHelper::details($id); ?>
		<?php if (isset($details['event']) && !empty($details['event'])) $event = $details['event']; else continue; ?>
		<?php
		// Masquer si inscription fermée
		$_endReg = $event->end_registration ?? '';
		if (!empty($_endReg) && $_endReg !== '0000-00-00 00:00:00' && strtotime($_endReg) < time()) continue;
		?>
		<li class="<?php echo RSEventsproAdapterGrid::column(12 / $columns); ?>">
			<a <?php echo $open; ?> href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id='.rseventsproHelper::sef($event->id,$event->name),true,$itemid); ?>"><?php echo $event->name; ?></a> <?php if ($event->published == 3) { ?><small class="text-error">(<?php echo Text::_('MOD_RSEVENTSPRO_UPCOMING_CANCELED'); ?>)</small><?php } ?>
		</li>
		<?php } ?>
	</ul>
	<?php } ?>
</div>
<?php } ?>
