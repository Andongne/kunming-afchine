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


<script>
/* Tooltip calendrier : nom de groupe en gras + traduction dates en ZH */
(function () {
  var _lang = document.documentElement.lang ? document.documentElement.lang.toLowerCase() : '';

  var _monthsEN = ['January','February','March','April','May','June','July',
                   'August','September','October','November','December'];
  var _monthsZH = ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];
  var _daysEN   = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday',
                   'Mon','Tue','Wed','Thu','Fri','Sat','Sun',
                   'Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
  var _daysZH   = ['星期一','星期二','星期三','星期四','星期五','星期六','星期日',
                   '周一','周二','周三','周四','周五','周六','周日',
                   '周一','周二','周三','周四','周五','周六','周日'];

  function localizeText(text) {
    if (_lang.indexOf('zh') !== 0) return text;
    _monthsEN.forEach(function (m, i) {
      text = text.split(m).join(_monthsZH[i]);
    });
    _daysEN.forEach(function (d, i) {
      text = text.split(d).join(_daysZH[i]);
    });
    return text;
  }

  // Tarif selon nom d'événement
  function detectTarif(name) {
    if (/VIP\s*3|trio/i.test(name))  return '98 \u00a5/h/pers.';
    if (/VIP\s*2|duo/i.test(name))   return '128 \u00a5/h/pers.';
    if (/VIP/i.test(name))            return '208 \u00a5/h';
    if (/4.?5\s*pers|Groupe\s*4|Petits/i.test(name)) return '78 \u00a5/h/pers.';
    return '49 \u00a5/h/pers.';
  }

  function processTooltip(pop) {
    // Traduire les dates dans le corps
    var content = pop.querySelector('.rsepro-calendar-tooltip-content');
    if (content && _lang.indexOf('zh') === 0) {
      content.innerHTML = localizeText(content.innerHTML);
    }
    // Toute la ligne description en gras
    pop.querySelectorAll('.rsepro-calendar-tooltip-description').forEach(function (el) {
      if (el.querySelector('strong')) return;
      var text = el.innerHTML.trim();
      el.innerHTML = '<strong>' + text + '</strong>';
    });
    // Ajouter prof + tarif dans le corps du popover
    var body = pop.querySelector('.popover-body');
    var header = pop.querySelector('.popover-header');
    if (body && header && !pop.getAttribute('data-afk-meta')) {
      pop.setAttribute('data-afk-meta', '1');
      var eventName = header.textContent.trim();
      // Extraire enseignant depuis le texte du body
      var bodyText = body.innerText || body.textContent;
      var teacher = '';
      var tm = bodyText.match(/Enseignant[^:]*:\s*([^\n\r]+)/);
      if (tm) teacher = tm[1].trim();
      var tarif = detectTarif(eventName);
      // Injecter en bas du body
      var meta = document.createElement('div');
      meta.className = 'afk-tooltip-meta';
      meta.style.cssText = 'margin-top:8px;padding-top:8px;border-top:1px solid #f0cdd2;font-size:0.85em;line-height:1.6';
      if (teacher) meta.innerHTML += '<span style="color:#DA002E">&#9632;</span> ' + teacher + '<br>';
      meta.innerHTML += '<span style="color:#DA002E">&#9632;</span> <strong>' + tarif + '</strong>';
      body.appendChild(meta);
    }
  }

  // Polling léger : surveille le popover actif toutes les 200ms
  setInterval(function () {
    var pop = document.querySelector('.popover.show');
    if (pop && !pop.getAttribute('data-afk-done')) {
      pop.setAttribute('data-afk-done', '1');
      processTooltip(pop);
    }
  }, 200);
})();
</script>


<?php
// Générer l'URL du formulaire cours (Itemid=1015) côté PHP pour toutes les langues
use Joomla\CMS\Router\Route;
$_afkCoursFormUrl = Route::_('index.php?option=com_rsform&view=rsform&formId=6&Itemid=1015', false);
$_afkCoursFormUrl = strtok($_afkCoursFormUrl, '?'); // garder chemin SEF seulement
?>
<script>
/* === CALENDRIER COURS : liens événements → formulaire pré-rempli === */
(function () {
  var FORM_URL = '<?php echo htmlspecialchars($_afkCoursFormUrl, ENT_QUOTES); ?>';
  var LANG = document.documentElement.lang ? document.documentElement.lang.toLowerCase() : 'fr';

  // Détecter le format de cours depuis le nom de l'événement
  function detectFormat(name) {
    if (/VIP\s*3|trio/i.test(name))  return 'VIP3 trio';
    if (/VIP\s*2|duo/i.test(name))   return 'VIP2 duo';
    if (/VIP/i.test(name))            return 'VIP1 individuel';
    if (/Groupe\s*4|4.?5\s*pers/i.test(name)) return 'Groupe 4-5 pers.';
    return 'Groupe 6-12 pers.'; // défaut cours de groupe
  }

  // Extraire la date depuis data-content du tooltip : "Ven 08/05/2026"
  function extractDate(dataContent) {
    if (!dataContent) return '';
    var m = dataContent.match(/(\d{2}\/\d{2}\/\d{4})/);
    return m ? m[1] : '';
  }

  function rewriteLinks() {
    // Cibler uniquement le calendrier cours (itemid-1018)
    if (!document.body.classList.contains('itemid-1018')) return;
    document.querySelectorAll('.rsepro-calendar-events a').forEach(function (a) {
      if (a.getAttribute('data-afk-rewritten')) return;
      a.setAttribute('data-afk-rewritten', '1');
      var name = a.textContent.trim();
      var format = detectFormat(name);
      var dc = a.getAttribute('data-content') || a.getAttribute('data-bs-content') || '';
      var date = extractDate(dc);
      var url = FORM_URL
        + '?form%5BFormat_cours%5D%5B%5D=' + encodeURIComponent(format)
        + (date ? '&form%5BSession%5D%5B%5D=' + encodeURIComponent(date) : '');
      if (LANG.indexOf('zh') === 0 || LANG.indexOf('en') === 0) url += '&lang=' + encodeURIComponent(LANG);
      a.href = url;
      a.removeAttribute('data-bs-toggle');  // désactiver le tooltip sur clic
      a.removeAttribute('data-bs-content');
    });
  }

  document.addEventListener('DOMContentLoaded', rewriteLinks);
  [300, 800, 1500].forEach(function(ms){ setTimeout(rewriteLinks, ms); });
})();
</script>
