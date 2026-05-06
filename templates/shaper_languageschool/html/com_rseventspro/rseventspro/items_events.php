<?php
/**
 * RSEvents!Pro — Override items_events.php
 * AF Kunming : card cliquable vers formulaire + badge tarif
 */
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Itemid du formulaire d'inscription aux examens (lang=*)
$afkFormItemId = 776;
$afkFormBase   = Route::_('index.php?Itemid=' . $afkFormItemId);

// Extrait le type d'examen depuis le nom de l'evenement
// "TCF Canada 🍁 📄 — 8 mai 2026" → "TCF Canada"
function afkExamType(string $name): string {
    // Lettre ASCII + espaces, avant emoji ou tiret long
    if (preg_match('/^([A-Za-z][A-Za-z\s]+?)(?:\s+[^\x00-\x7F]|\s+\xe2\x80\x94|$)/u', $name, $m)) {
        return trim($m[1]);
    }
    $parts = explode(' —', $name);
    return trim(preg_replace('/[^\x00-\x7F]+/u', '', $parts[0]));
}

?>

<?php if (!empty($this->events)) { ?>
<?php $eventIds = rseventsproHelper::getEventIds($this->events, 'id'); ?>
<?php $this->events = rseventsproHelper::details($eventIds); ?>
<?php foreach($this->events as $details) { ?>
<?php if (isset($details['event']) && !empty($details['event'])) $event = $details['event']; else continue; ?>
<?php if (!rseventsproHelper::canview($event->id) && $event->owner != $this->user) continue; ?>
<?php $full       = rseventsproHelper::eventisfull($event->id); ?>
<?php $ongoing    = rseventsproHelper::ongoing($event->id); ?>
<?php $incomplete = !$event->completed ? ' rs_incomplete' : ''; ?>
<?php $featured   = $event->featured   ? ' rs_featured'   : ''; ?>
<?php $canceled   = $event->published == 3 ? ' rsepro_canceled_event_block' : ''; ?>
<?php $lastMY     = rseventsproHelper::showdate($event->start, 'mY'); ?>

<?php
// URL formulaire avec pré-remplissage type d'examen + date
$examType    = afkExamType($event->name);
$sessionDate = date('d/m/Y', strtotime($event->start));
$sep         = (strpos($afkFormBase, '?') !== false) ? '&' : '?';
$formUrl     = $afkFormBase . $sep
             . 'form[Choix_exam][]=' . urlencode($examType)
             . '&form[Session][]='   . urlencode($sessionDate);

// Badge tarif : small_description si contient un montant, sinon valeur par défaut
$tarif = '2 700 ¥';
if (!empty($event->small_description)) {
    $stripped = strip_tags($event->small_description);
    if (preg_match('/[\d\s]+\s*[¥€$￥]|[¥€$￥]\s*[\d\s]+/u', $stripped, $tm)) {
        $tarif = trim($tm[0]);
    }
}

// Badge inscription fermée
$regClosed = !empty($event->registration_closed);
?>

<?php if ($monthYear = rseventsproHelper::showMonthYear($event->start, 'events'.$this->fid, 'items')) { ?>
<li class="rsepro-my-grouped <?php echo rseventsproHelper::layout('event-grouped-by'); ?>">
    <span><?php echo $monthYear; ?></span>
</li>
<?php } ?>

<li class="<?php echo rseventsproHelper::layout('item-container'); ?> afk-event-card<?php echo $incomplete.$featured.$canceled; ?>"
    id="rs_event<?php echo $event->id; ?>"
    itemscope itemtype="http://schema.org/Event">

    <!-- Badge tarif -->
    <span class="afk-tarif-badge"><?php echo htmlspecialchars($tarif, ENT_QUOTES, 'UTF-8'); ?></span>

    <!-- Card entièrement cliquable vers le formulaire -->
    <a href="<?php echo htmlspecialchars($formUrl, ENT_QUOTES, 'UTF-8'); ?>"
       class="afk-card-link"
       itemprop="url"
       aria-label="<?php echo htmlspecialchars($event->name, ENT_QUOTES, 'UTF-8'); ?>">

        <?php if (!empty($event->options['show_icon_list'])) { ?>
        <div class="<?php echo rseventsproHelper::layout('image-container'); ?>" itemprop="image">
            <img src="<?php echo rseventsproHelper::thumb($event->id, rseventsproHelper::layout('image-width')); ?>"
                 alt="" width="<?php echo rseventsproHelper::layout('image-width'); ?>" />
        </div>
        <?php } ?>

        <div class="<?php echo rseventsproHelper::layout('event-details-container'); ?>">

            <!-- Titre -->
            <div itemprop="name" class="<?php echo rseventsproHelper::layout('event-title'); ?>">
                <span class="rs_event_link<?php echo $full ? ' rs_event_full' : ''; ?><?php echo $ongoing ? ' rs_event_ongoing' : ''; ?>">
                    <?php echo $event->name; ?>
                </span>
                <?php if ($regClosed) { ?>
                <span class="rsepro-reg-closed"
                      style="display:inline-block;margin-left:8px;padding:2px 8px;background:#da002e;color:#fff;border-radius:3px;font-size:0.78rem;font-weight:600;vertical-align:middle;">
                    <?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_REGISTRATION_CLOSED'); ?>
                </span>
                <?php } ?>
            </div>

            <!-- Date -->
            <div class="<?php echo rseventsproHelper::layout('event-date'); ?>">
                <?php if ($event->allday) { ?>
                    <?php if (!empty($event->options['start_date_list'])) { ?>
                    <span class="rsepro-event-on-block">
                        <?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_ON'); ?>
                        <b><?php echo rseventsproHelper::showdate($event->start, $this->config->global_date, true); ?></b>
                    </span>
                    <?php } ?>
                <?php } else { ?>
                    <?php if (!empty($event->options['start_date_list']) || !empty($event->options['start_time_list'])) { ?>
                    <span class="rsepro-event-on-block">
                        <?php echo Text::_('COM_RSEVENTSPRO_GLOBAL_ON'); ?>
                        <b><?php echo rseventsproHelper::showdate($event->start, rseventsproHelper::showMask('list_start', $event->options), true); ?></b>
                    </span>
                    <?php } ?>
                <?php } ?>
            </div>

            <!-- Description courte -->
            <?php if (!empty($event->small_description)) { ?>
            <div class="<?php echo rseventsproHelper::layout('event-description'); ?>">
                <?php echo $event->small_description; ?>
            </div>
            <?php } ?>

        </div><!-- /event-details -->

    </a><!-- /afk-card-link -->

    <meta content="<?php echo rseventsproHelper::showdate($event->start, 'Y-m-d H:i:s'); ?>" itemprop="startDate" />

</li>
<?php } ?>
<?php } ?>
<?php rseventsproHelper::clearMonthYear('events'.$this->fid, @$lastMY, 'items'); ?>
