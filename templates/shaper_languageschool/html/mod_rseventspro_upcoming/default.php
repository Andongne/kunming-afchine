<?php
/**
* @package RSEvents!Pro — Template override AF Kunming
* Suppression de la date redondante entre parenthèses
* Événements à inscription fermée : affichés avec badge, non masqués
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

$open = !$links ? 'target="_blank"' : '';
$_lang = Factory::getLanguage()->getTag();
$_lang_param = ($_lang && $_lang !== 'fr-FR' && $_lang !== '*') ? '&lang=' . htmlspecialchars($_lang) : ''; ?>

<?php if ($items) { ?>
<style>#rsepro-upcoming-module ul.rsepro_upcoming{margin-bottom:10px!important}#rsepro-upcoming-module ul.rsepro_upcoming li a{font-size:1.05rem!important;line-height:1.6}#rsepro-upcoming-module .reg-closed-badge{display:inline-block;margin-left:6px;padding:1px 7px;background:#da002e;color:#fff;border-radius:3px;font-size:0.75rem;font-weight:600;vertical-align:middle;white-space:nowrap}</style>
<div id="rsepro-upcoming-module">
	<?php foreach ($items as $block => $events) { ?>
	<ul class="rsepro_upcoming<?php echo $suffix; ?> <?php echo RSEventsproAdapterGrid::row(); ?>">
		<?php foreach ($events as $id) { ?>
		<?php $details = rseventsproHelper::details($id); ?>
		<?php if (isset($details['event']) && !empty($details['event'])) $event = $details['event']; else continue; ?>
		<?php
		$_endReg = $event->end_registration ?? '';
		$_regClosed = (!empty($_endReg) && $_endReg !== '0000-00-00 00:00:00' && strtotime($_endReg) < time());
		?>
		<li class="<?php echo RSEventsproAdapterGrid::column(12 / $columns); ?>">
			<?php
			$_sef = rseventsproHelper::sef($event->id, $event->name);
			if ($_lang && $_lang !== 'fr-FR' && $_lang !== '*') {
				$_base_url = rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id=' . $_sef, true, $itemid);
				// Retirer le préfixe langue /zh-cn/ ou /en/ qui cause un 404, ajouter &lang= à la place
				$_base_url = preg_replace('#^(/[a-z]{2}(-[A-Z]{2})?/)#', '/', $_base_url);
				$_event_url = $_base_url . (strpos($_base_url, '?') !== false ? '&' : '?') . 'lang=' . htmlspecialchars($_lang);
			} else {
				$_event_url = rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id=' . $_sef, true, $itemid);
			}
			?>
			<a <?php echo $open; ?> href="<?php echo $_event_url; ?>"><?php echo $event->name; ?></a><?php if ($_regClosed): ?><span class="reg-closed-badge">Inscriptions fermées</span><?php endif; ?> <?php if ($event->published == 3) { ?><small class="text-error">(<?php echo Text::_('MOD_RSEVENTSPRO_UPCOMING_CANCELED'); ?>)</small><?php } ?>
		</li>
		<?php } ?>
	</ul>
	<?php } ?>
</div>
<?php } ?>
