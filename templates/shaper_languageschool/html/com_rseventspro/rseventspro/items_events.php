<?php
/**
 * @package RSEvents!Pro — Override AF Kunming
 * items_events.php : supprime tarif + liens loc/date pour catégorie Événements (54)
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

// Détecter si on est sur la page Événements (cat 54 uniquement)
$_active_cats = (array) $this->params->get('categories', []);
$_is_evenements = (count($_active_cats) === 1 && in_array('54', array_map('strval', $_active_cats)))
                || (count($_active_cats) === 1 && in_array(54, $_active_cats));
?>

<?php if (!empty($this->events)) { ?>
<?php $eventIds = rseventsproHelper::getEventIds($this->events, 'id'); ?>
<?php $this->events = rseventsproHelper::details($eventIds); ?>
<?php foreach($this->events as $details) { ?>
<?php if (isset($details['event']) && !empty($details['event'])) $event = $details['event']; else continue; ?>
<?php if (!rseventsproHelper::canview($event->id) && $event->owner != $this->user) continue; ?>
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

<?php if ($monthYear = rseventsproHelper::showMonthYear($event->start, 'events'.$this->fid, 'items')) { ?>
	<li class="rsepro-my-grouped <?php echo rseventsproHelper::layout('event-grouped-by'); ?>"><span><?php echo $monthYear; ?></span></li>
<?php } ?>

<li class="<?php echo rseventsproHelper::layout('item-container'); ?><?php echo $incomplete.$featured.$canceled; ?>" id="rs_event<?php echo $event->id; ?>" itemscope itemtype="http://schema.org/Event">

	<?php if ($canEdit || $canDelete) { ?>
	<div class="rsepro-event-options <?php echo rseventsproHelper::layout('options'); ?>" style="display:none;">
		<?php if ($canEdit) { ?><a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=edit&id='.rseventsproHelper::sef($event->id,$event->name)); ?>"><i class="fa fa-edit"></i></a><?php } ?>
		<?php if ($canDelete) { ?><a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&task=rseventspro.remove&id='.rseventsproHelper::sef($event->id,$event->name)); ?>" onclick="return confirm('<?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_DELETE_CONFIRMATION'); ?>');"><i class="fa fa-trash"></i></a><?php } ?>
	</div>
	<?php } ?>

	<?php if (!empty($event->options['show_icon_list'])) { ?>
	<div class="<?php echo rseventsproHelper::layout('image-container'); ?>" itemprop="image">
		<a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id='.rseventsproHelper::sef($event->id,$event->name),false,rseventsproHelper::itemid($event->id)); ?>" class="rs_event_link thumbnail" aria-label="<?php echo $event->name; ?>">
			<img src="<?php echo rseventsproHelper::thumb($event->id, rseventsproHelper::layout('image-width')); ?>" alt="" width="<?php echo rseventsproHelper::layout('image-width'); ?>" />
		</a>
	</div>
	<?php } ?>

	<div class="<?php echo rseventsproHelper::layout('event-details-container'); ?>">
		<div itemprop="name" class="<?php echo rseventsproHelper::layout('event-title'); ?>">
			<a itemprop="url" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id='.rseventsproHelper::sef($event->id,$event->name),false,rseventsproHelper::itemid($event->id)); ?>" class="rs_event_link<?php echo $full ? ' rs_event_full' : ''; ?><?php echo $ongoing ? ' rs_event_ongoing' : ''; ?>"><?php echo $event->name; ?></a>
			<?php if (!$event->completed) echo Text::_('COM_RSEVENTSPRO_GLOBAL_INCOMPLETE_EVENT'); ?>
			<?php if (!$event->published) echo Text::_('COM_RSEVENTSPRO_GLOBAL_UNPUBLISHED_EVENT'); ?>
			<?php if ($event->published == 3) echo '<small class="text-error">('.Text::_('COM_RSEVENTSPRO_EVENT_CANCELED_TEXT').')</small>'; ?>
		</div>

		<?php if (!$_is_evenements) : // — DATE : affiché normalement pour examens — ?>
		<div class="<?php echo rseventsproHelper::layout('event-date'); ?>">
			<?php if ($event->allday) { ?>
			<?php if (!empty($event->options['start_date_list'])) { ?>
			<span class="rsepro-event-on-block"><?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_ON'); ?> <b><?php echo rseventsproHelper::showdate($event->start,$this->config->global_date,true); ?></b></span>
			<?php } ?>
			<?php } else { ?>
			<?php if (!empty($event->options['start_date_list']) || !empty($event->options['start_time_list'])) { ?>
			<span class="rsepro-event-from-block"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_FROM'); ?> <b><?php echo rseventsproHelper::showdate($event->start,rseventsproHelper::showMask('list_start',$event->options),true); ?></b></span>
			<?php } ?>
			<?php if (!empty($event->options['end_date_list']) || !empty($event->options['end_time_list'])) { ?>
			<span class="rsepro-event-until-block"><?php echo Text::_('COM_RSEVENTSPRO_EVENT_UNTIL'); ?> <b><?php echo rseventsproHelper::showdate($event->end,rseventsproHelper::showMask('list_end',$event->options),true); ?></b></span>
			<?php } ?>
			<?php } ?>
		</div>
		<?php else : // — DATE : texte simple pour Événements, sans lien — ?>
		<div class="rsepro-event-date-plain" style="font-size:.88rem;color:#5a5a5a;margin:.25rem 0;">
			<span>📅 </span><span><?php echo rseventsproHelper::showdate($event->start, 'd F Y · H\hi', true); ?></span>
			<?php if ($event->end && $event->end !== $event->start) : ?>
			<span> – <?php echo rseventsproHelper::showdate($event->end, 'H\hi', true); ?></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php if (!empty($event->options['show_location_list']) || !empty($event->options['show_categories_list']) || !empty($event->options['show_tags_list'])) { ?>
		<div class="<?php echo rseventsproHelper::layout('event-taxonomy'); ?>">
			<?php if ($event->locationid && $event->lpublished && !empty($event->options['show_location_list'])) { ?>
			<span class="rsepro-event-location-block" itemprop="location" itemscope itemtype="http://schema.org/Place">
				<?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_AT'); ?>
				<?php if (!$_is_evenements) : // lien pour examens ?>
				<a itemprop="url" href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=location&id='.rseventsproHelper::sef($event->locationid,$event->location)); ?>"><span itemprop="name"><?php echo $event->location; ?></span></a>
				<?php else : // texte simple pour Événements ?>
				<span itemprop="name"><?php echo $event->location; ?></span>
				<?php endif; ?>
				<span itemprop="address" style="display:none;"><?php echo $event->address; ?></span>
			</span>
			<?php } ?>
			<?php if (!empty($event->options['show_categories_list'])) { ?>
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

		<?php // Tarif : masqué pour catégorie Événements ?>
		<?php if (!$_is_evenements && $this->params->get('repeatcounter',1) && $repeats) { ?>
		<div class="<?php echo rseventsproHelper::layout('event-repeats'); ?>">
			(<a href="<?php echo rseventsproHelper::route('index.php?option=com_rseventspro&layout=default&parent='.rseventsproHelper::sef($event->id,$event->name)); ?>"><?php echo Text::sprintf('COM_RSEVENTSPRO_GLOBAL_REPEATS',$repeats); ?></a>)
		</div>
		<?php } ?>
	</div>

	<meta content="<?php echo rseventsproHelper::showdate($event->start,'Y-m-d H:i:s'); ?>" itemprop="startDate" />
	<?php if (!$event->allday) { ?><meta content="<?php echo rseventsproHelper::showdate($event->end,'Y-m-d H:i:s'); ?>" itemprop="endDate" /><?php } ?>
</li>
<?php } ?>
<?php } ?>
<?php rseventsproHelper::clearMonthYear('events'.$this->fid, @$lastMY, 'items'); ?>
