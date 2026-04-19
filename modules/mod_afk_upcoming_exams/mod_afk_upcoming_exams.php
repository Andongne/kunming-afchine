<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Language\Text;

$db     = Factory::getDbo();
$lang   = Factory::getLanguage()->getTag();
$isZh   = (strpos($lang, 'zh') !== false);
$count  = (int) ($params->get('count', 6));
$showDL = (int) ($params->get('show_deadline', 1));
$catIds = $params->get('cat_ids', '');

// Mois localisés
$enMonths = ['January','February','March','April','May','June','July','August','September','October','November','December'];
$frMonths = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$zhMonths = ['1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];

function afk_fmt_date($ts, $isZh, $enM, $frM, $zhM) {
    $day   = (int) date('j', $ts);
    $month = (int) date('n', $ts) - 1;
    $year  = date('Y', $ts);
    if ($isZh) {
        return $day . '日 ' . $zhM[$month] . ' ' . $year;
    }
    return $day . ' ' . $frM[$month] . ' ' . $year;
}

// Requête événements à venir
$query = $db->getQuery(true);
$query->select('e.id, e.name, e.start, e.end_registration, e.sid')
      ->from('#__rseventspro_events AS e')
      ->where('e.published = 1')
      ->where('e.start >= ' . $db->quote(date('Y-m-d H:i:s')))
      ->order('e.start ASC')
      ->setLimit($count);

// Filtre par catégorie
if (!empty($catIds)) {
    $ids = array_map('intval', explode(',', $catIds));
    $query->join('INNER', '#__rseventspro_taxonomy AS t ON t.ide = e.id AND t.type = ' . $db->quote('category'))
          ->where('t.extra IN (' . implode(',', $ids) . ')');
}

$db->setQuery($query);
$events = $db->loadObjectList();

// Récupérer traductions Falang si zh-CN
$translations = [];
if ($isZh && !empty($events)) {
    $eIds = array_column($events, 'id');
    $qTr = $db->getQuery(true);
    $qTr->select('reference_id, reference_field, value')
        ->from('#__falang_content')
        ->where('language_id = 4')
        ->where('reference_table = ' . $db->quote('rseventspro_events'))
        ->where('reference_id IN (' . implode(',', $eIds) . ')')
        ->where('reference_field IN (' . $db->quote('name') . ',' . $db->quote('small_description') . ')');
    $db->setQuery($qTr);
    foreach ($db->loadObjectList() as $tr) {
        $translations[$tr->reference_id][$tr->reference_field] = $tr->value;
    }
}

// Générer l'URL de l'événement
function afk_event_url($id, $name) {
    $alias = preg_replace('/[^a-z0-9\-]/i', '-', strtolower($name));
    $alias = preg_replace('/-+/', '-', $alias);
    return Route::_('index.php?option=com_rseventspro&id=' . $id . ':' . $alias);
}

require JModuleHelper::getLayoutPath('mod_afk_upcoming_exams', 'default');
