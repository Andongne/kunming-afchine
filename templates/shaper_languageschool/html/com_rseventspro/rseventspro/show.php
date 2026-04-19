<?php
/**
* @package RSEvents!Pro
* @copyright (C) 2020 www.rsjoomla.com
* @license GPL, http://www.gnu.org/copyleft/gpl.html
*/

defined( '_JEXEC' ) or die( 'Restricted access' );

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Factory;

$details	= rseventsproHelper::details($this->event->id, null, true);
$event		= $details['event'];
$categories = $details['categories'];
$tags		= $details['tags'];
$files		= $details['files'];
$repeats	= $details['repeats'];
$speakers	= $details['speakers'];
$sponsors	= $details['sponsors'];
$faq		= $details['faq'];
$itinerary	= $details['itinerary'];
$full		= rseventsproHelper::eventisfull($this->event->id);
$ongoing	= rseventsproHelper::ongoing($this->event->id); 
$featured 	= $event->featured ? ' rs_featured_event' : ''; 
$description= empty($event->description) ? $event->small_description : $event->description;
$links		= rseventsproHelper::getConfig('modal','int');
$modal		= rseventsproHelper::getConfig('modaltype','int');
$tmpl		= $links == 0 ? '' : '&tmpl=component';

$subscribeURL	= $links == 1 && $modal == 1 ? 'javascript:void(0);' : rseventsproHelper::route('index.php?option=com_rseventspro&layout=subscribe&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl);
$waitinglistURL	= $links == 1 && $modal == 1 ? 'javascript:void(0);' : rseventsproHelper::route('index.php?option=com_rseventspro&layout=waiting&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl);
$inviteURL		= $links == 1 && $modal == 1 ? 'javascript:void(0);' : rseventsproHelper::route('index.php?option=com_rseventspro&layout=invite&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl);
$messageURL		= $links == 1 && $modal == 1 ? 'javascript:void(0);' : rseventsproHelper::route('index.php?option=com_rseventspro&layout=message&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl);
$feedbackURL	= $links == 1 && $modal == 1 ? 'javascript:void(0);' : rseventsproHelper::route('index.php?option=com_rseventspro&layout=feedback&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl);
$unsubscribeURL	= $modal == 1 ? 'javascript:void(0);' : rseventsproHelper::route('index.php?option=com_rseventspro&layout=unsubscribe&id='.rseventsproHelper::sef($event->id,$event->name).'&tmpl=component');
$reportURL		= $modal == 1 ? 'javascript:void(0);' : rseventsproHelper::route('index.php?option=com_rseventspro&layout=report&tmpl=component&id='.rseventsproHelper::sef($event->id,$event->name));
$imageURL		= $modal == 1 ? 'javascript:void(0);' : $details['image'];

rseventsproHelper::richSnippet($details); ?>

<?php if (!empty($this->options['show_counter'])) { ?>
<script type="text/javascript">
jQuery(document).ready(function($) {
	RSEventsPro.Counter.options = {
		userTime: <?php echo !empty($this->options['counter_utc']) ? 'true' : 'false';  ?>,
		counterID: 'rsepro-counter',
		containerID: 'rsepro-counter-container',
		deadlineUTC: '<?php echo rseventsproHelper::showdate($event->start,'c',false,'UTC'); ?>',
		deadline: '<?php echo rseventsproHelper::showdate($event->start,'Y-m-d\TH:i:s',false); ?>+00:00'
	}
	
	RSEventsPro.Counter.init();
});
</script>
<?php } ?>

<?php if (!empty($this->options['show_map']) && !empty($event->coordinates) && rseventsproHelper::getConfig('map')) {
$marker = array(
	'title' => $event->name,
	'position' => $event->coordinates,
	'content' => '<div id="content"><b>'.$event->name.'</b> <br /> '.Text::_('COM_RSEVENTSPRO_LOCATION_ADDRESS',true).': '.$event->address.(!empty($event->locationlink) ? '<br /><a target="_blank" href="'.$event->locationlink.'">'.$event->locationlink.'</a>' : '').'</div>'
);

if ($event->marker) $marker['icon'] = rseventsproHelper::showMarker($event->marker);

$mapParams = array(
	'id' => 'map-canvas',
	'zoom' => (int) $this->config->google_map_zoom,
	'center' => $this->config->google_maps_center,
	'markerDraggable' => 'false',
	'markers' => array($marker)
);

rseventsproMapHelper::loadMap($mapParams);
} ?>

<?php Factory::getApplication()->triggerEvent('onrsepro_onBeforeEventDisplay',array(array('event' => &$event, 'categories' => &$categories, 'tags' => &$tags))); ?>

<div id="rs_event_show"<?php echo $event->published == 3 ? ' class="rsepro_canceled_event_show"' : ''; ?>>
	
	<?php if ($full && $event->event_full && !$this->eventended) { ?><div class="alert alert-info rse_event_message"><?php echo $event->event_full; ?></div><?php } ?>
	<?php if ($this->eventended && $event->event_ended) { ?><div class="alert alert-info rse_event_message"><?php echo $event->event_ended; ?></div><?php } ?>
	
	<div id="rsepro-event-title" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mb-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<h1 class="<?php echo $full ? ' rs_event_full' : ''; ?><?php echo $ongoing ? ' rs_event_ongoing' : ''; ?><?php echo $featured; ?>">
				<?php echo $this->escape($event->name); ?>
				<?php if ($event->published == 3) { ?><small class="text-danger">(<?php echo Text::_('COM_RSEVENTSPRO_EVENT_CANCELED_TEXT'); ?>)</small><?php } ?>
			</h1>
		</div>
	</div>
	
	<div id="rsepro-event-controls" class="<?php echo RSEventsproAdapterGrid::styles(array('row')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<?php if (false) /* AFK: hidden */ { ?>
			<div class="btn-group">
				<button data-toggle="dropdown" data-bs-toggle="dropdown" class="<?php echo RSEventsproAdapterGrid::styles(array('btn')); ?> dropdown-toggle"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_ADMIN_OPTIONS'); ?> <span class="caret"></span></button>
				<ul class="dropdown-menu">
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=edit&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-edit fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_EDIT'); ?>
						</a>
					</li>
					<?php if ($event->rsvp) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=rsvp&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-users fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_RSVP_GUESTS'); ?>
						</a>
					</li>
					<?php } else if ($event->registration) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=subscribers&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-users fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_SUBSCRIBERS'); ?>
						</a>
					</li>
					<?php if ($this->eventtickets) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=subscriberstickets&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-ticket-alt fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_SUBSCRIBERS_TICKETS'); ?>
						</a>
					</li>
					<?php } ?>
					<?php } ?>
					
					<?php if (rseventsproHelper::hasUnsubscribers($event->id)) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=unsubscribers&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-users fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_UNSUBSCRIBERS'); ?>
						</a>
					</li>
					<?php } ?>
					
					<?php if ($event->waitinglist) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=waitinglist&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-users fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_WAITINGLIST'); ?>
						</a>
					</li>
					<?php } ?>
					
					<?php if ($this->hasFeedbacks($event)) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=feedbacks&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-bullhorn fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_FEEDBACKS'); ?>
						</a>
					</li>
					<?php } ?>
					
					<?php if ($event->rsvp || $event->registration) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo $messageURL; ?>" rel="rs_message"<?php if ($links == 1 && $modal == 1) echo ' onclick="jQuery(\'#rseMessageModal\').modal(\'show\');"'; ?>>
							<i class="fa fa-envelope fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_MESSAGE_TO_GUESTS'); ?>
						</a>
					</li>
					<?php } ?>
					<?php if ($event->rsvp || $event->registration) { ?>
					<?php if (!$this->eventended) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=reminder&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-envelope fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_SEND_REMINDER'); ?>
						</a>
					</li>
					<?php } else { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=postreminder&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-envelope fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_SEND_POST_REMINDER'); ?>
						</a>
					</li>
					<?php } ?>
					<?php } ?>
					<?php if ($this->report) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=reports&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-flag fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_REPORTS'); ?>
						</a>
					</li>
					<?php } ?>
					<?php if ($event->registration) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=scan&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-barcode fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_SCAN_TICKET'); ?>
						</a>
					</li>
					<?php } ?>
					<?php if ($event->published != 3) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=rseventspro.cancelevent&id='.rseventsproHelper::sef($event->id,$event->name)); ?>" onclick="return confirm('<?php echo Text::_('COM_RSEVENTSPRO_EVENT_CANCEL_CONFIRMATION', true); ?>');">
							<i class="fa fa-times fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_CANCEL_EVENT'); ?>
						</a>
					</li>
					<?php } ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=rseventspro.remove&id='.rseventsproHelper::sef($event->id,$event->name)); ?>" onclick="return confirm('<?php echo Text::_('COM_RSEVENTSPRO_EVENT_DELETE_CONFIRMATION'); ?>');">
							<i class="fa fa-trash fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_DELETE_EVENT'); ?>
						</a>
					</li>
				</ul>
			</div>
			<?php } ?>

			<?php if (!($this->admin || $event->owner == $this->user || $event->sid == $this->user) && $this->permissions['can_edit_events']) { ?>
			<div class="btn-group">
				<a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=edit&id='.rseventsproHelper::sef($event->id,$event->name)); ?>" class="<?php echo RSEventsproAdapterGrid::styles(array('btn')); ?>">
					<i class="fa fa-edit fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_EDIT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (!($this->admin || $event->owner == $this->user || $event->sid == $this->user) && $this->permissions['can_delete_events']) { ?>
			<div class="btn-group">
				<a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=rseventspro.remove&id='.rseventsproHelper::sef($event->id,$event->name)); ?>" class="<?php echo RSEventsproAdapterGrid::styles(array('btn')); ?>" onclick="return confirm('<?php echo Text::_('COM_RSEVENTSPRO_EVENT_DELETE_CONFIRMATION'); ?>');">
					<i class="fa fa-trash fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_DELETE_EVENT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if ($this->cansubscribe['status'] || rseventsproHelper::validWaitingList($event->id)) { ?>
			<div class="btn-group">
				<a href="<?php echo $subscribeURL; ?>" class="<?php echo RSEventsproAdapterGrid::styles(array('btn')); ?>" rel="rs_subscribe"<?php if ($links == 1 && $modal == 1) echo ' onclick="jQuery(\'#rseSubscribeModal\').modal(\'show\');"'; ?>>
					<i class="fa fa-check fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_JOIN'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php $fullEvent = rseventsproHelper::eventisfull($this->event->id, false); ?>
			<?php $fullEvent = $this->event->overbooking ? $fullEvent && !$this->cansubscribe['status'] : $fullEvent; ?>
			<?php if ($fullEvent && !$this->eventended && rseventsproHelper::hasWaitingList($event->id)) { ?>
			<div class="btn-group">
				<a href="<?php echo $waitinglistURL; ?>" class="<?php echo RSEventsproAdapterGrid::styles(array('btn')); ?>" rel="rs_subscribe"<?php if ($links == 1 && $modal == 1) echo ' onclick="jQuery(\'#rseWaitingModal\').modal(\'show\');"'; ?>>
					<i class="fa fa-check fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_WAITING_LIST'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (!$this->eventended) { ?>
			<?php if ($this->issubscribed) { ?>
			<?php if ($this->canunsubscribe) { ?>
			<?php if ($this->issubscribed == 1) { ?>
			<div class="btn-group">
				<a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=rseventspro.unsubscribe&id='.rseventsproHelper::sef($event->id,$event->name)); ?>" class="<?php echo RSEventsproAdapterGrid::styles(array('btn')); ?>">
					<i class="fa fa-times fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_UNSUBSCRIBE'); ?>
				</a>
			</div>
			<?php } else { ?>
			<div class="btn-group">
				<a href="<?php echo $unsubscribeURL; ?>" class="<?php echo RSEventsproAdapterGrid::styles(array('btn')); ?>" <?php echo $modal == 2 ? 'rel="rs_unsubscribe"' : 'onclick="jQuery(\'#rseUnsubscribeModal\').modal(\'show\');"'; ?>>
					<i class="fa fa-times fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_UNSUBSCRIBE'); ?>
				</a>
			</div>
			<?php } ?>
			<?php } ?>
			<?php } ?>
			<?php } ?>
			
			<?php if ((!$this->eventended && !empty($this->options['show_invite'])) || $this->report || !empty($this->options['show_print']) || !empty($this->options['show_export']) || ($event->owner && !empty($this->options['contact'])) || $this->config->timezone || ($this->eventended && $event->feedback)) { ?>
			<div class="btn-group">
				<button data-toggle="dropdown" data-bs-toggle="dropdown" class="<?php echo RSEventsproAdapterGrid::styles(array('btn')); ?> dropdown-toggle"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_USER_OPTIONS'); ?> <span class="caret"></span></button>
				<ul class="dropdown-menu">
					<?php if (!empty($this->options['contact']) && $event->owner) { ?>
					<li>
						<a class="dropdown-item" rel="rs_contact"<?php if ($modal == 1) echo ' href="#contactModal" data-toggle="modal" data-bs-toggle="modal"'; else echo ' href="javascript:void(0)"'; ?>>
							<i class="fa fa-envelope fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_CONTACT_OWNER'); ?>
						</a>
					</li>
					<?php } ?>
					
					<?php if ($this->canSubmitFeedback === true) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo $feedbackURL; ?>" rel="rs_feedback"<?php if ($links == 1 && $modal == 1) echo ' onclick="jQuery(\'#rseFeedbackModal\').modal(\'show\');"'; ?>>
							<i class="fa fa-bullhorn fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_FEEDBACK'); ?>
						</a>
					</li>
					<?php } ?>
					
					<?php if (!$this->eventended && !empty($this->options['show_invite'])) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo $inviteURL; ?>" rel="rs_invite"<?php if ($links == 1 && $modal == 1) echo ' onclick="jQuery(\'#rseInviteModal\').modal(\'show\');"'; ?>>
							<i class="fa fa-plus fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_INVITE'); ?>
						</a>
					</li>
					<?php } ?>
					
					<?php if ($this->report) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo $reportURL; ?>" rel="rs_report"<?php if ($modal == 1) echo ' onclick="jQuery(\'#rseReportModal\').modal(\'show\');"'; ?>>
							<i class="fa fa-flag fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_REPORT'); ?>
						</a>
					</li>
					<?php } ?>
					
					<?php if (!empty($this->options['show_print'])) { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=print&tmpl=component&id='.rseventsproHelper::sef($event->id,$event->name)); ?>" onclick="window.open(this.href,'print','status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,top=200,left=200,directories=no,location=no'); return false;">
							<i class="fa fa-print fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EVENT_PRINT'); ?>
						</a>
					</li>
					<?php } ?>
					
					<?php if (false) /* AFK: hidden */ { ?>
					<li>
						<a class="dropdown-item" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=rseventspro.export&id='.rseventsproHelper::sef($event->id,$event->name)); ?>">
							<i class="fa fa-calendar fa-fw"></i> <?php echo Text::_('COM_RSEVENTSPRO_EXPORT_EVENT'); ?>
						</a> 
					</li> 
					<?php } ?>
					
					<?php if ($this->config->timezone) { ?>
					<li>
						<a class="dropdown-item" rel="rs_timezone"<?php if ($modal == 1) echo ' href="#timezoneModal" data-toggle="modal" data-bs-toggle="modal"'; else echo ' href="javascript:void(0)"'; ?>>
							<i class="fa fa-clock fa-fw"></i> <?php echo rseventsproHelper::getTimezone(); ?>
						</a> 
					</li>
					<?php } ?>
				</ul>
			</div>
			<?php } ?>
			
			<?php if ($event->rsvp) { ?>
			<?php $rsvpOptions = rseventsproHelper::getRSVPOptions($event->id); ?>
			<?php if (rseventsproHelper::canRSVP($event->id)) { ?>
			<?php if (!$this->eventended) { ?>
			<div id="rsepro_rsvp" class="btn-group">
				<a id="rsepro_going" class="btn <?php echo $rsvpOptions->rsvp == 'going' ? 'btn-success hasTooltip' : 'btn-secondary'; ?><?php echo $rsvpOptions->offClass; ?>" title="<?php if ($rsvpOptions->rsvp == 'going') echo Text::_('COM_RSEVENTSPRO_RSVP_INFO'); ?> <?php echo $rsvpOptions->offTitle; ?>" <?php if ($rsvpOptions->canRSVP) { ?>onclick="rsepro_rsvp(<?php echo $event->id; ?>, 'going');"<?php } ?>>
					<?php echo Text::_('COM_RSEVENTSPRO_RSVP_GOING'); ?>
				</a>
				<a id="rsepro_interested" class="btn <?php echo $rsvpOptions->rsvp == 'interested' ? 'btn-success hasTooltip' : 'btn-secondary'; ?><?php echo $rsvpOptions->offClass; ?>" title="<?php if ($rsvpOptions->rsvp == 'interested') echo Text::_('COM_RSEVENTSPRO_RSVP_INFO'); ?> <?php echo $rsvpOptions->offTitle; ?>" <?php if ($rsvpOptions->canRSVP) { ?>onclick="rsepro_rsvp(<?php echo $event->id; ?>, 'interested');"<?php } ?>>
					<?php echo Text::_('COM_RSEVENTSPRO_RSVP_INTERESTED'); ?>
				</a>
				<a id="rsepro_notgoing" class="btn <?php echo $rsvpOptions->rsvp == 'notgoing' ? 'btn-success hasTooltip' : 'btn-secondary'; ?><?php echo $rsvpOptions->offClass; ?>" title="<?php if ($rsvpOptions->rsvp == 'notgoing') echo Text::_('COM_RSEVENTSPRO_RSVP_INFO'); ?> <?php echo $rsvpOptions->offTitle; ?>" <?php if ($rsvpOptions->canRSVP) { ?>onclick="rsepro_rsvp(<?php echo $event->id; ?>, 'notgoing')"<?php } ?>>
					<?php echo Text::_('COM_RSEVENTSPRO_RSVP_NOT_GOING'); ?>
				</a>
			</div>
			<?php } else { ?>
			<?php if (isset($rsvpOptions->rsvp)) { ?>
			<div id="rsepro_rsvp" class="btn-group">
				<button class="btn btn-success disabled"><?php echo rseventsproHelper::RSVPStatus($rsvpOptions->rsvp); ?></button>
			</div>
			<?php } ?>
			<?php } ?>
			<?php } ?>
			<?php } ?>
			
			<?php if (false) /* AFK: hidden */ { ?>
			<?php echo rseventsproHelper::rating($event->id); ?>
			<?php } ?>
		</div>
	</div>
	
	<?php if (!empty($this->options['show_counter'])) { ?>
	<div id="rsepro-counter-container" class="rsepro-counter">
		<div id="rsepro-counter">
			<div>
				<span class="rsepro-counter-days"></span>
				<div class="rsepro-counter-text"><?php echo Text::_('COM_RSEVENTSPRO_COUNTER_DAYS'); ?></div>
			</div>
			<div>
				<span class="rsepro-counter-hours"></span>
				<div class="rsepro-counter-text"><?php echo Text::_('COM_RSEVENTSPRO_COUNTER_HOURS'); ?></div>
			</div>
			<div>
				<span class="rsepro-counter-minutes"></span>
				<div class="rsepro-counter-text"><?php echo Text::_('COM_RSEVENTSPRO_COUNTER_MINUTES'); ?></div>
			</div>
			<div>
				<span class="rsepro-counter-seconds"></span>
				<div class="rsepro-counter-text"><?php echo Text::_('COM_RSEVENTSPRO_COUNTER_SECONDS'); ?></div>
			</div>
		</div>
	</div>
	<?php } ?>
	
	<div id="rsepro-event-details" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<?php if (!empty($details['image_b'])) { ?>
			<div id="rsepro-event-details-right">
				<div id="rsepro-event-image">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<a href="<?php echo $imageURL; ?>" rel="rs_image"<?php if ($modal == 1) echo ' onclick=" rsepro_show_image(\''.$details['image'].'\');"' ?> class="thumbnail" aria-label="<?php echo $event->name; ?>">
							<img src="<?php echo $details['image_b']; ?>" alt="<?php echo $this->escape($event->name); ?>" width="<?php echo rseventsproHelper::getConfig('icon_big_width','int'); ?>px" />
						</a>
					</div>
				</div>
			</div>
			<?php } ?>
			
			<div id="rsepro-event-details-left">
				<?php if (($event->allday && !empty($this->options['start_date'])) || (!$event->allday && (!empty($this->options['start_date']) || !empty($this->options['start_time']) || !empty($this->options['end_date']) || !empty($this->options['end_time'])))) { ?>
				<div id="rsepro-event-date" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-calendar fa-fw"></i> 
						<?php if ($event->allday) { ?>
						<?php echo 'le'; ?> <?php echo rseventsproHelper::showdate($event->start,$this->config->global_date,true); ?>
						<?php } else { ?>
						<?php if (!empty($this->options['start_date']) || !empty($this->options['start_time'])) { ?>
						<?php if ((!empty($this->options['start_date']) || !empty($this->options['start_time'])) && empty($this->options['end_date']) && empty($this->options['end_time'])) { ?>
						<?php echo Text::_('COM_RSEVENTSPRO_EVENT_STARTING_ON'); ?>
						<?php } else { ?>
						<?php echo Text::_('COM_RSEVENTSPRO_EVENT_FROM'); ?> 
						<?php } ?>
						<?php echo rseventsproHelper::showdate($event->start,rseventsproHelper::showMask('start',$this->options),true); ?>
					<?php } ?>
					
					<?php if (!empty($this->options['end_date']) || !empty($this->options['end_time'])) { ?>
						<?php if ((!empty($this->options['end_date']) || !empty($this->options['end_time'])) && empty($this->options['start_date']) && empty($this->options['start_time'])) { ?>
						<?php echo Text::_('COM_RSEVENTSPRO_EVENT_ENDING_ON'); ?>
						<?php } else { ?>
						<?php echo Text::_('COM_RSEVENTSPRO_EVENT_UNTIL'); ?>
						<?php } ?>
						<?php echo rseventsproHelper::showdate($event->end,rseventsproHelper::showMask('end',$this->options),true); ?>
					<?php } ?>
						<?php } ?>
					</div>
				</div>
				<?php } ?>
				
				<?php if (!empty($event->lpublished) && !empty($this->options['show_location'])) { ?>
				<div id="rsepro-event-location" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-map fa-fw"></i> 
						<?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_AT'); ?> <a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=location&id='.rseventsproHelper::sef($event->locationid,$event->location)); ?>"><?php echo $event->location; ?></a>
					</div>
				</div>
				<?php } ?>
				
				<?php if (false) /* AFK: hidden */ { ?>
				<div id="rsepro-event-owner" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-user fa-fw"></i> 
						<?php echo Text::_('COM_RSEVENTSPRO_EVENT_POSTED_BY'); ?> 
						<?php if (!empty($event->ownerprofile)) { ?><a href="<?php echo $event->ownerprofile; ?>"><?php } ?>
						<?php echo $event->ownername; ?>
						<?php if (!empty($event->ownerprofile)) { ?></a><?php } ?>
					</div>
				</div>
				<?php } ?>
				
				<?php if (!empty($this->options['show_contact'])) { ?>
				<?php if (!empty($event->email)) { ?>
				<div id="rsepro-event-email" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-envelope fa-fw"></i> 
						<a href="mailto:<?php echo $event->email; ?>"><?php echo $event->email; ?></a>
					</div>
				</div>
				<?php } ?>
				
				<?php if (!empty($event->phone)) { ?>
				<div id="rsepro-event-phone" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-phone fa-fw"></i> 
						<a href="tel:<?php echo $event->phone; ?>"><?php echo $event->phone; ?></a>
					</div>
				</div>
				<?php } ?>
				
				<?php if (!empty($event->URL)) { ?>
				<div id="rsepro-event-url" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-globe fa-fw"></i> 
						<a href="<?php echo $event->URL; ?>" target="_blank"><?php echo $event->URL; ?></a>
					</div>
				</div>
				<?php } ?>
				<?php } ?>
				
				<?php if (!empty($categories) && !empty($this->options['show_categories'])) { ?>
				<div id="rsepro-event-categories" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-folder fa-fw"></i> 
						<?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_CATEGORIES'); ?>: <?php echo $categories; ?>
					</div>
				</div>
				<?php } ?>
				
				<?php if (!empty($tags) && !empty($this->options['show_tags'])) { ?>
				<div id="rsepro-event-tags" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-tags fa-fw"></i> 
						<?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_TAGS'); ?>: <?php echo $tags; ?>
					</div>
				</div>
				<?php } ?>
				
				<?php if (false) /* AFK: hidden */ { ?>
				<div id="rsepro-event-hits" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'g-0')); ?>">
					<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
						<i class="fa fa-eye fa-fw"></i> 
						<?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_HITS'); ?>: <?php echo $event->hits; ?>
					</div>
				</div>
				<?php } ?>
			</div>
		</div>
	</div>
	
	<?php if (false) /* AFK: hidden */ { ?>
	<div id="rsepro-event-sharing" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			
			<?php if (in_array('facebook', $this->options['sharing'])) { ?>
			<div class="rsepro-event-sharing-button rsepro-event-sharing-facebook">
				<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(rseventsproHelper::shareURL($event->id,$event->name)); ?>" target="_blank">
					<i class="fab fa-facebook-f"></i> <?php echo Text::_('COM_RSEVENTSPRO_SHARE_TEXT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (in_array('twitter', $this->options['sharing'])) { ?>
			<div class="rsepro-event-sharing-button rsepro-event-sharing-twitter">
				<a href="https://twitter.com/intent/tweet?text=<?php echo urlencode($event->name); ?>&url=<?php echo urlencode(rseventsproHelper::shareURL($event->id,$event->name)); ?>" target="_blank">
					<i class="fab fa-twitter"></i> <?php echo Text::_('COM_RSEVENTSPRO_TWEET_TEXT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (in_array('linkedin', $this->options['sharing'])) { ?>
			<div class="rsepro-event-sharing-button rsepro-event-sharing-linkedin">
				<a href="https://www.linkedin.com/cws/share?url=<?php echo urlencode(rseventsproHelper::shareURL($event->id,$event->name)); ?>" target="_blank">
					<i class="fab fa-linkedin-in"></i> <?php echo Text::_('COM_RSEVENTSPRO_SHARE_TEXT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (in_array('pinterest', $this->options['sharing'])) { ?>
			<div class="rsepro-event-sharing-button rsepro-event-sharing-pinterest">
				<a href="https://pinterest.com/pin/create/button/?url=<?php echo urlencode(rseventsproHelper::shareURL($event->id,$event->name)); ?>" target="_blank">
					<i class="fab fa-pinterest-p"></i> <?php echo Text::_('COM_RSEVENTSPRO_PIN_TEXT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (in_array('reddit', $this->options['sharing'])) { ?>
			<div class="rsepro-event-sharing-button rsepro-event-sharing-reddit">
				<a href="http://reddit.com/submit?url=<?php echo urlencode(rseventsproHelper::shareURL($event->id,$event->name)); ?>&title=<?php echo urlencode($event->name); ?>" target="_blank">
					<i class="fab fa-reddit-alien"></i> <?php echo Text::_('COM_RSEVENTSPRO_SHARE_TEXT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (in_array('whatsapp', $this->options['sharing'])) { ?>
			<div class="rsepro-event-sharing-button rsepro-event-sharing-whatsapp">
				<a class="social-rocket-button-anchor" href="https://api.whatsapp.com/send?text=<?php echo urlencode(rseventsproHelper::shareURL($event->id,$event->name)); ?>" target="_blank">
					<i class="fab fa-whatsapp"></i> <?php echo Text::_('COM_RSEVENTSPRO_SHARE_TEXT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (in_array('buffer', $this->options['sharing'])) { ?>
			<div class="rsepro-event-sharing-button rsepro-event-sharing-buffer">
				<a href="https://bufferapp.com/add?url=<?php echo urlencode(rseventsproHelper::shareURL($event->id,$event->name)); ?>" target="_blank">
					<i class="fab fa-buffer"></i> <?php echo Text::_('COM_RSEVENTSPRO_SHARE_TEXT'); ?>
				</a>
			</div>
			<?php } ?>
			
			<?php if (in_array('mix', $this->options['sharing'])) { ?>
			<div class="rsepro-event-sharing-button rsepro-event-sharing-mix">
				<a href="https://mix.com/mixit?url=<?php echo urlencode(rseventsproHelper::shareURL($event->id,$event->name)); ?>" target="_blank">
					<i class="fab fa-mix"></i> <?php echo Text::_('COM_RSEVENTSPRO_SHARE_TEXT'); ?>
				</a>
			</div>
			<?php } ?>
		</div>
	</div>
	<?php } ?>
	
	<?php if (!empty($this->options['show_description']) && !empty($description)) { ?>
	<div id="rsepro-event-description" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?> description">
			<?php echo $description; ?>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>
	
	<?php if ($itinerary) { ?>
	<div id="rsepro-event-itinerary" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<h3 class="<?php echo RSEventsproAdapterGrid::styles(array('mb-3')); ?>"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_ITINERARY_TITLE'); ?></h3>
			<?php 
				foreach ($itinerary as $itineraryItem) {
					$this->tabs->addTitle($itineraryItem->name, 'itinerary'.$itineraryItem->id);
					$content = '';
				
					foreach ($itineraryItem->timeline as $timeline) {
						$content .= RSEventsproAdapterItinerary::addTimeline($timeline, true);
					}
					
					$this->tabs->addContent($content);
				}
				echo $this->tabs->render(); 
			?>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>
	
	<?php if ($sponsors) { ?>
	<div id="rsepro-event-sponsors" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<h3 class="<?php echo RSEventsproAdapterGrid::styles(array('mb-3')); ?>"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_LIST_SPONSORS'); ?></h3>
			<?php $schunks = array_chunk($sponsors, 4); ?>
			<?php foreach ($schunks as $sponsors) { ?>
			<ul class="thumbnails rsepro-sponsors list-inline d-flex clearfix">
			<?php foreach($sponsors as $sponsor) { ?>
				<li class="<?php echo RSEventsproAdapterGrid::column(3); ?> list-inline-item">
					<div class="thumbnail center">
						<?php if ($sponsor->url) { ?><a href="<?php echo $sponsor->url; ?>" target="_blank"><?php } ?>
						<?php if ($sponsor->image) { ?>
						<img class="rsepro-sponsor-image" src="<?php echo Uri::root(); ?>components/com_rseventspro/assets/images/sponsors/<?php echo $sponsor->image; ?>" alt="<?php echo $sponsor->name; ?>" />
						<?php } else { ?>
						<?php echo $sponsor->name; ?>
						<?php } ?>
						<?php if ($sponsor->url) { ?></a><?php } ?>
					</div>
				</li>
			<?php } ?>
			</ul>
			<?php } ?>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>
	
	<?php if ($speakers) { ?>
	<div id="rsepro-event-speakers" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<h3 class="<?php echo RSEventsproAdapterGrid::styles(array('mb-3')); ?>"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_LIST_SPEAKERS'); ?></h3>
			<?php $chunks = array_chunk($speakers, 4); ?>
			<?php foreach ($chunks as $speakers) { ?>
			<ul class="thumbnails rsepro-speakers list-inline d-flex clearfix">
			<?php foreach($speakers as $speaker) { ?>
				<li class="<?php echo RSEventsproAdapterGrid::column(3); ?> list-inline-item">
					<div class="thumbnail">
						<?php if ($speaker->image) { ?>
						<img class="rsepro-speaker-image" src="<?php echo Uri::root(); ?>components/com_rseventspro/assets/images/speakers/<?php echo $speaker->image; ?>" alt="<?php echo $speaker->name; ?>" width="<?php echo rseventsproHelper::getConfig('speaker_icon_width', 'int', 100); ?>" height="<?php echo rseventsproHelper::getConfig('speaker_icon_height', 'int', 150); ?>" />
						<?php } else { ?>
						<?php echo HTMLHelper::image('com_rseventspro/blankuser.png', $speaker->name, array('class' => 'rsepro-speaker-image', 'width' => rseventsproHelper::getConfig('speaker_icon_width', 'int', 100), 'height' => rseventsproHelper::getConfig('speaker_icon_height', 'int', 150)), true); ?>
						<?php } ?>
						<div class="caption">
							<p class="rsepro-speaker-name"><?php echo $speaker->name; ?></p>
							
							<ul class="rsepro-speaker-info">
								<?php if ($speaker->email) { ?>
								<li>
									<a href="mailto:<?php echo $speaker->email; ?>">
										<i class="fa fa-envelope"></i>
									</a>
								</li>
								<?php } ?>
								<?php if ($speaker->url) { ?>
								<li>
									<a href="<?php echo $speaker->url; ?>" target="_blank">
										<i class="fa fa-link"></i>
									</a>
								</li>
								<?php } ?>
								<?php if ($speaker->phone) { ?>
								<li>
									<a href="tel:<?php echo $speaker->phone; ?>">
										<i class="fa fa-phone"></i>
									</a>
								</li>
								<?php } ?>
								<?php if ($speaker->facebook) { ?>
								<li>
									<a href="<?php echo $speaker->facebook; ?>" target="_blank">
										<i class="fab fa-facebook-f"></i>
									</a>
								</li>
								<?php } ?>
								<?php if ($speaker->twitter) { ?>
								<li>
									<a href="<?php echo $speaker->twitter; ?>" target="_blank">
										<i class="fab fa-twitter"></i>
									</a>
								</li>
								<?php } ?>
								<?php if ($speaker->linkedin) { ?>
								<li>
									<a href="<?php echo $speaker->linkedin; ?>" target="_blank">
										<i class="fab fa-linkedin-in"></i>
									</a>
								</li>
								<?php } ?>
								<?php if ($speaker->custom) { ?>
								<?php foreach ($speaker->custom as $custom) { ?>
								<li>
									<a href="<?php echo $custom['link']; ?>" target="_blank">
										<i class="<?php echo $custom['class']; ?>"></i>
									</a>
								</li>
								<?php } ?>
								<?php } ?>
								<li></li>
							</ul>
						</div>
						<div class="rsepro-speaker-description"><?php echo $speaker->description; ?></div>
					</div>
				</li>
			<?php } ?>
			</ul>
			<?php } ?>
			
			<div id="rsepro-speaker-overlay" class="rsepro-speaker-overlay">
				<div class="rsepro-close">x</div>
				<div class="rsepro-speaker-overlay-container">
					<div id="rsepro-speaker-overlay-image"></div>
					<div id="rsepro-speaker-overlay-name"></div>
					<div id="rsepro-speaker-overlay-info"></div>
					<div id="rsepro-speaker-overlay-description"></div>
				</div>
			</div>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>
	
	<?php if ($faq) { ?>
	<div id="rsepro-event-faq" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<h3 class="<?php echo RSEventsproAdapterGrid::styles(array('mb-3')); ?>"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_FAQ_TITLE'); ?></h3>
			<?php echo HTMLHelper::_('bootstrap.startAccordion', 'faqaccordion', array('parent' => true, 'active' => 'faq0')); ?>
			<?php foreach ($faq as $i => $faqItem) { ?>
			<?php echo HTMLHelper::_('bootstrap.addSlide', 'faqaccordion', '<strong>'.$faqItem->question.'</strong>', 'faq'.$i); ?>
			<?php echo $faqItem->response; ?>
			<?php echo HTMLHelper::_('bootstrap.endSlide'); ?>
			<?php } ?>
			<?php echo HTMLHelper::_('bootstrap.endAccordion'); ?>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>
	
	<?php if (!empty($this->options['show_map']) && !empty($event->coordinates) && rseventsproHelper::getConfig('map')) { ?>
	<div id="rsepro-event-map" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<div id="map-canvas" style="width: 100%; height: 300px;"></div>
			<?php if (rseventsproHelper::getConfig('map') == 'google' && $this->config->google_map_direction) { ?>
			<br />
			<div class="rsepro-map-directions">
				<a target="_blank" class="hasTooltip" title="<?php echo Text::_('COM_RSEVENTSPRO_DIRECTIONS_CAR'); ?>" href="https://maps.google.com/?saddr=Current+Location&daddr=<?php echo $event->coordinates; ?>&driving">
					<i class="fa fa-car fa-fw"></i>
				</a>
				<a target="_blank" class="hasTooltip" title="<?php echo Text::_('COM_RSEVENTSPRO_DIRECTIONS_BUS'); ?>" href="https://maps.google.com/?saddr=Current+Location&dirflg=r&daddr=<?php echo $event->coordinates; ?>&mode=transit">
					<i class="fa fa-bus fa-fw"></i> 
				</a>
				<a target="_blank" class="hasTooltip" title="<?php echo Text::_('COM_RSEVENTSPRO_DIRECTIONS_WALKING'); ?>" href="https://maps.google.com/?saddr=Current+Location&dirflg=w&daddr=<?php echo $event->coordinates; ?>">
				  <i class="fa fa-child fa-fw"></i>
				</a>
				<a target="_blank" class="hasTooltip" title="<?php echo Text::_('COM_RSEVENTSPRO_DIRECTIONS_BICYCLE'); ?>" href="https://maps.google.com/?saddr=Current+Location&dirflg=b&daddr=<?php echo $event->coordinates; ?>&mode=bicycling">
					<i class="fa fa-bicycle fa-fw"></i>
				</a>
			</div>
			<?php } ?>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>

	<?php echo rseventsproHelper::gallery('event',$event->id); ?>
	
	<?php if (!empty($this->options['show_repeats']) && !empty($repeats)) { ?>
	<div id="rsepro-event-repeats" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<h3 class="<?php echo RSEventsproAdapterGrid::styles(array('mb-3')); ?>"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_REPEATS'); ?></h3>
			<ul class="rs_repeats" id="rs_repeats">
			<?php foreach ($repeats as $repeat) { ?>
			<?php if ($repeat->id == $event->id) continue; ?>
				<li>
					<a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id='.rseventsproHelper::sef($repeat->id,$repeat->name),false,rseventsproHelper::itemid($repeat->id)); ?>"><?php echo $repeat->name; ?></a>
					<?php $dateMask = $repeat->allday ? rseventsproHelper::getConfig('global_date') : null; ?>
					(<?php echo rseventsproHelper::showdate($repeat->start,$dateMask,true); ?>)
				</li>
			<?php } ?>
			</ul>
			<div class="rs_repeats_control" id="rs_repeats_control" style="display:none;">
				<a id="more" href="javascript:void(0)" onclick="show_more();"><?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_MORE') ?></a>
				<a id="less" href="javascript:void(0)" onclick="show_less();" style="display:none;"><?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_LESS') ?></a>
			</div>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>
	
	<?php if (!empty($this->options['show_files']) && !empty($files)) { ?>
	<div id="rsepro-event-files" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<h3 class="<?php echo RSEventsproAdapterGrid::styles(array('mb-3')); ?>"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_FILES'); ?></h3>
			<?php echo $files; ?>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>
	
	<?php if (($event->show_registered && !empty($this->guests)) || ($event->rsvp && $event->rsvp_guests && !empty($this->RSVPguests))) { ?>
	<div id="rsepro-event-guests" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<?php if (!$event->rsvp) { ?>
				<h3 class="<?php echo RSEventsproAdapterGrid::styles(array('mb-3')); ?>"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_GUESTS'); ?></h3>
				<ul class="rs_guests">
					<?php foreach ($this->guests as $guest) { ?>
					<li>
						<?php if (!empty($guest->url)) { ?><a href="<?php echo $guest->url; ?>"><?php } ?>
						<?php echo $guest->avatar; ?>
						<?php echo $guest->name; ?>
						<?php if (!empty($guest->url)) { ?></a><?php } ?>
					</li>
					<?php } ?>
				</ul>
			<?php } else { ?>
				<?php foreach ($this->RSVPguests as $type => $guests) { ?>
				<?php if (!empty($guests)) { ?>
				<h3 class="<?php echo RSEventsproAdapterGrid::styles(array('mb-3')); ?>"><?php echo Text::_('COM_RSEVENTSPRO_RSVP_EVENT_GUESTS_'.strtoupper($type)); ?></h3>
				<ul class="rs_guests">
				<?php foreach ($guests as $guest) { ?>
					<li>
						<?php if (!empty($guest->url)) { ?><a href="<?php echo $guest->url; ?>"><?php } ?>
						<?php echo $guest->avatar; ?>
						<p class="center"><?php echo $guest->name; ?></p>
						<?php if (!empty($guest->url)) { ?></a><?php } ?>
					</li>
				<?php } ?>
				</ul>
				<div class="clearfix"></div>
				<?php } ?>
				<?php } ?>
			<?php } ?>
		</div>
	</div>
	<hr class="<?php echo RSEventsproAdapterGrid::styles(array('g-0', 'my-5')); ?>" />
	<?php } ?>

	<?php Factory::getApplication()->triggerEvent('onrsepro_onAfterEventDisplay',array(array('event' => $event, 'categories' => $categories, 'tags' => $tags))); ?>
	
	<?php if ($event->comments) { ?>
	<div id="rsepro-event-comments" class="<?php echo RSEventsproAdapterGrid::styles(array('row', 'mt-3')); ?>">
		<div class="<?php echo RSEventsproAdapterGrid::column(12); ?>">
			<?php echo rseventsproHelper::comments($event->id,$event->name); ?>
		</div>
	</div>
	<?php } ?>

	<?php if ($event->comments && rseventsproHelper::getConfig('event_comment','int') == 1) { ?>
	<div id="fb-root"></div>
	<script type="text/javascript">
	(function(d, s, id) {
		var js, fjs = d.getElementsByTagName(s)[0];
		if (d.getElementById(id)) return;
		js = d.createElement(s); js.id = id;
		js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.4&appId=<?php echo $this->escape(rseventsproHelper::getConfig('facebook_app_id')); ?>";
		fjs.parentNode.insertBefore(js, fjs);
		}(document, 'script', 'facebook-jssdk'));
	</script>
	<?php } ?>
</div>

<?php if ($this->config->timezone) echo rseventsproHelper::timezoneModal(); ?>

<?php
if ($modal == 1) {
	echo HTMLHelper::_('bootstrap.renderModal', 'rseImageModal', array('title' => '&nbsp;', 'bodyHeight' => 70));
	
	if ($this->report) {
		echo HTMLHelper::_('bootstrap.renderModal', 'rseReportModal', array('title' => '&nbsp;', 'url' => rseventsproHelper::route('index.php?option=com_rseventspro&layout=report&tmpl=component&id='.rseventsproHelper::sef($event->id,$event->name)), 'bodyHeight' => 70));
	}
	
	if (!empty($this->options['contact'])) {
		echo HTMLHelper::_('bootstrap.renderModal', 'contactModal', array('title' => Text::_('COM_RSEVENTSPRO_CONTACT_OWNER'), 'bodyHeight' => 70), $this->loadTemplate('contact'));
	}
	
	if (!$this->eventended && $this->canunsubscribe && $this->issubscribed != 1) {
		echo HTMLHelper::_('bootstrap.renderModal', 'rseUnsubscribeModal', array('title' => Text::_('COM_RSEVENTSPRO_UNSUBSCRIBE_UNSUBSCRIBE'), 'url' => rseventsproHelper::route('index.php?option=com_rseventspro&layout=unsubscribe&id='.rseventsproHelper::sef($event->id,$event->name).'&tmpl=component'), 'bodyHeight' => 70));
	}
} else {
	if (!empty($this->options['contact'])) {
		echo '<div style="display:none;"><div id="contactModalInline">'.$this->loadTemplate('contact').'</div></div>';
	}
}

if ($links == 1 && $modal == 1) {
	if ($this->cansubscribe['status'] || rseventsproHelper::validWaitingList($event->id)) {
		echo HTMLHelper::_('bootstrap.renderModal', 'rseSubscribeModal', array('title' => Text::_('COM_RSEVENTSPRO_EVENT_JOIN'), 'url' => rseventsproHelper::route('index.php?option=com_rseventspro&layout=subscribe&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl), 'bodyHeight' => 70));
	}
	
	if ($this->admin || $event->owner == $this->user || $event->sid == $this->user) {
		echo HTMLHelper::_('bootstrap.renderModal', 'rseMessageModal', array('title' => Text::_('COM_RSEVENTSPRO_EVENT_MESSAGE_TO_GUESTS'), 'url' => rseventsproHelper::route('index.php?option=com_rseventspro&layout=message&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl), 'bodyHeight' => 70));
	}
	
	if (!$this->eventended && !empty($this->options['show_invite'])) {
		echo HTMLHelper::_('bootstrap.renderModal', 'rseInviteModal', array('title' => Text::_('COM_RSEVENTSPRO_EVENT_INVITE'), 'url' => rseventsproHelper::route('index.php?option=com_rseventspro&layout=invite&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl), 'bodyHeight' => 70));
	}
	
	if ($fullEvent && !$this->eventended && rseventsproHelper::hasWaitingList($event->id)) {
		echo HTMLHelper::_('bootstrap.renderModal', 'rseWaitingModal', array('title' => Text::_('COM_RSEVENTSPRO_EVENT_WAITING_LIST'), 'url' => rseventsproHelper::route('index.php?option=com_rseventspro&layout=waiting&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl), 'bodyHeight' => 70));
	}
	
	if ($this->canSubmitFeedback === true) {
		echo HTMLHelper::_('bootstrap.renderModal', 'rseFeedbackModal', array('title' => Text::_('COM_RSEVENTSPRO_FEEDBACK'), 'url' => rseventsproHelper::route('index.php?option=com_rseventspro&layout=feedback&id='.rseventsproHelper::sef($event->id,$event->name).$tmpl), 'bodyHeight' => 70));
	}
}
?>