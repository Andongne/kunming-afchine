<?php
/**
* @package RSEvents!Pro — Template override AF Kunming
* Suppression de la date redondante entre parenthèses
* Lien événement → formulaire pré-rempli (type examen + date session)
*/

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

$open = !$links ? 'target="_blank"' : '';
$_lang = Factory::getLanguage()->getTag();
$_lang_param = ($_lang && $_lang !== 'fr-FR' && $_lang !== '*') ? '&lang=' . htmlspecialchars($_lang) : '';

// AFK: URL du formulaire d'inscription selon la langue
if (!function_exists('afk_form_base_url')) {
    function afk_form_base_url($lang) {
        $base = '/certifications-et-diplomes/inscription-aux-tests-et-certifications/formulaire-inscription-aux-examen';
        if (strpos($lang, 'zh') !== false) return '/zh' . $base;
        if (strpos($lang, 'en') !== false) return '/en' . $base;
        return $base;
    }
}

// AFK: extraire le type d'examen depuis le nom de l'événement
if (!function_exists('afk_exam_type_from_name')) {
    function afk_exam_type_from_name($name) {
        if (preg_match('/TCF\s+Qu[eé]bec/iu', $name)) return 'TCF Québec';
        if (preg_match('/TCF\s+Canada/iu', $name))   return 'TCF Canada';
        if (preg_match('/TEF\s+Canada/iu', $name))   return 'TEF Canada';
        if (preg_match('/TEFAQ/iu', $name))           return 'TEFAQ';
        return null;
    }
}

// AFK: construire l'URL formulaire pré-remplie
if (!function_exists('afk_form_url')) {
    function afk_form_url($exam_type, $start_date, $lang) {
        if (!$exam_type) return null;
        // Formater la date : YYYY-MM-DD HH:MM:SS → DD/MM/YYYY
        $dt = $start_date ? date('d/m/Y', strtotime($start_date)) : '';
        $url = afk_form_base_url($lang)
             . '?form%5BChoix_exam%5D%5B%5D=' . urlencode($exam_type);
        if ($dt) $url .= '&form%5BSession%5D%5B%5D=' . urlencode($dt);
        return $url;
    }
}

// AFK: traduction depuis rseventspro_translations
if (!function_exists('afk_upcoming_translate')) {
    function afk_upcoming_translate($event_id, $field, $default, $lang) {
        if (!$lang || $lang === 'fr-FR') return $default;
        static $_db = null;
        if ($_db === null) $_db = JFactory::getDbo();
        $q = $_db->getQuery(true)
            ->select('value')
            ->from('#__rseventspro_translations')
            ->where($_db->quoteName('reference') . '=' . $_db->quote('rseventspro_events'))
            ->where($_db->quoteName('reference_id') . '=' . (int)$event_id)
            ->where($_db->quoteName('property') . '=' . $_db->quote($field))
            ->where($_db->quoteName('language') . '=' . $_db->quote($lang));
        $_db->setQuery($q);
        $val = $_db->loadResult();
        return ($val !== null && $val !== '') ? $val : $default;
    }
}

// AFK: localiser les mois dans les dates
if (!function_exists('afk_upcoming_localize_date')) {
    function afk_upcoming_localize_date($str, $lang) {
        $fr = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre',
               'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
        if (strpos($lang, 'zh') !== false) {
            $zh = ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月',
                   '1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];
            return str_replace($fr, $zh, $str);
        }
        if (strpos($lang, 'en') !== false) {
            $en = ['January','February','March','April','May','June','July','August','September','October','November','December',
                   'January','February','March','April','May','June','July','August','September','October','November','December'];
            return str_replace($fr, $en, $str);
        }
        return $str;
    }
} ?>

<?php if ($items) { ?>
<style>#rsepro-upcoming-module ul.rsepro_upcoming{margin-bottom:10px!important}#rsepro-upcoming-module ul.rsepro_upcoming li a{font-size:1.05rem!important;line-height:1.6}#rsepro-upcoming-module .reg-closed-badge{display:inline-block;margin-left:6px;padding:1px 7px;background:#da002e;color:#fff;border-radius:3px;font-size:0.75rem;font-weight:600;vertical-align:middle;white-space:nowrap}#rsepro-upcoming-module .reg-closed-item{opacity:.55;pointer-events:none;cursor:default}</style>
<div id="rsepro-upcoming-module">
	<?php foreach ($items as $block => $events) { ?>
	<ul class="rsepro_upcoming<?php echo $suffix; ?> <?php echo RSEventsproAdapterGrid::row(); ?>">
		<?php foreach ($events as $id) { ?>
		<?php $details = rseventsproHelper::details($id); ?>
		<?php if (isset($details['event']) && !empty($details['event'])) $event = $details['event']; else continue; ?>
		<?php
		$_endReg   = $event->end_registration ?? '';
		$_regClosed = (!empty($_endReg) && $_endReg !== '0000-00-00 00:00:00' && strtotime($_endReg) < time());

		// Nom traduit + localisation date
		$_event_name = afk_upcoming_translate($event->id, 'name', $event->name, $_lang);
		$_event_name = afk_upcoming_localize_date($_event_name, $_lang);

		// URL : formulaire pré-rempli si type d'examen reconnu, sinon lien RSEvents standard
		$_exam_type = afk_exam_type_from_name($event->name); // toujours depuis nom FR
		if ($_exam_type) {
		    $_href = afk_form_url($_exam_type, $event->start ?? '', $_lang);
		} else {
		    $_sef  = rseventsproHelper::sef($event->id, $event->name);
		    $_base = rseventsproHelper::route('index.php?option=com_rseventspro&layout=show&id=' . $_sef, true, $itemid);
		    $_pfx  = strpos($_lang,'zh')!==false ? 'zh' : (strpos($_lang,'en')!==false ? 'en' : '');
		    $_href = $_pfx ? '/' . $_pfx . preg_replace('#^(/[a-zA-Z-]{2,5})/#','/',$_base) : $_base;
		}

		// Badge inscription fermée localisé
		$_badge_closed = strpos($_lang,'zh')!==false ? '报名已截止' : (strpos($_lang,'en')!==false ? 'Registration closed' : 'Inscriptions fermées');
		?>
		<li class="<?php echo RSEventsproAdapterGrid::column(12 / $columns); ?><?php echo $_regClosed ? ' reg-closed-item' : ''; ?>">
			<a <?php echo $open; ?> href="<?php echo htmlspecialchars($_href); ?>"><?php echo $_event_name; ?></a><?php if ($_regClosed): ?><span class="reg-closed-badge"><?php echo $_badge_closed; ?></span><?php endif; ?> <?php if ($event->published == 3) { ?><small class="text-error">(<?php echo Text::_('MOD_RSEVENTSPRO_UPCOMING_CANCELED'); ?>)</small><?php } ?>
		</li>
		<?php } ?>
	</ul>
	<?php } ?>
</div>
<?php } ?>
