<?php
/**
 * @package     RSEvents!Pro - Community Builder integration
 * @copyright   (C) 2020 www.rsjoomla.com
 * @license     GPL, http://www.gnu.org/copyleft/gpl.html
 * @php         8.1+
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;

global $_PLUGINS;
$_PLUGINS->registerFunction('onBeforegetFieldRow', 'onBeforegetFieldRow', 'getRseproTab');
$_PLUGINS->registerUserFieldTypes(['rseprostatus' => 'CBfield_rseprostatus']);
$_PLUGINS->registerUserFieldParams();

class CBfield_rseprostatus extends cbFieldHandler
{
    /** @inheritDoc */
    public function getField(&$field, &$user, $output, $reason, $list_compare_types)
    {
        global $ueConfig;
        static $stats = [];
        $value = null;
        if (is_object($user) && $user->id) {
            if (!isset($stats[$user->id])) {
                global $_PLUGINS;
                $params = [&$user];
                $stats[$user->id] = $_PLUGINS->call($this->getPluginId(), '', 'getRseproTab', $params);
            }
            $value = $stats[$user->id][$field->name] ?? $ueConfig['emptyFieldsText'];
        }
        if ($output === 'htmledit') return null;
        return $this->_formatFieldOutput($field->name, $value, $output, false);
    }
}

class getRseproTab extends cbTabHandler
{
    public function __construct()
    {
        parent::__construct();
    }

    /** @inheritDoc */
    public function getDisplayTab($tab, $user, $ui)
    {
        if (!file_exists(JPATH_SITE . '/components/com_rseventspro/helpers/rseventspro.php')) return null;
        require_once JPATH_SITE . '/components/com_rseventspro/helpers/rseventspro.php';
        require_once JPATH_SITE . '/components/com_rseventspro/helpers/route.php';

        $params    = $this->params;
        $limit     = (int) $params->get('limit', 20);
        $ordering  = (string) $params->get('ordering', 'ASC');
        $target    = (bool) $params->get('target', 1);
        $opener    = $target ? '' : 'target="_blank"';
        $rawItemid = $params->get('itemid', '');
        $itemid    = ($rawItemid !== '') ? (int) $rawItemid : RseventsproHelperRoute::getEventsItemid();

        Factory::getLanguage()->load('com_rseventspro');
        Factory::getDocument()->addStyleSheet(
            Uri::root() . 'components/com_comprofiler/plugin/user/plug_rseventspro/style.css'
        );

        $ownedEvents      = $this->getOwnedEvents((int) $user->id, $limit, $ordering);
        $bookedByCategory = $this->getBookedEventsByCategory((int) $user->id, $limit);

        $html = '<div class="rsepro-events-container">';

        // Accès rapides (toujours affichés en tête)
        $ql_lang = Factory::getLanguage()->getTag();
        $ql_isZh = strpos($ql_lang, 'zh') !== false;
        $ql_isEn = strpos($ql_lang, 'en') !== false;
        $ql_title  = $ql_isZh ? '快速入口'   : ($ql_isEn ? 'Quick Access'      : 'Acc&egrave;s rapides');
        $ql_edit   = $ql_isZh ? '修改我的信息' : ($ql_isEn ? 'Edit My Profile'   : 'Modifier mes informations');
        $ql_msg    = $ql_isZh ? '我的消息'   : ($ql_isEn ? 'My Messages'       : 'Mes messages');
        $ql_pay    = $ql_isZh ? '我的付款'   : ($ql_isEn ? 'My Payments'       : 'Mes paiements');
        $ql_docs   = $ql_isZh ? '我的文件'   : ($ql_isEn ? 'My Documents'      : 'Mes documents');
        $html .= '<div class="rsepro-quicklinks">
            <h3 class="rsepro-quicklinks-title">' . $ql_title . '</h3>
            <div class="rsepro-quicklinks-grid">
                <a href="' . Route::_('index.php?option=com_comprofiler&view=userdetails&Itemid=1047') . '" class="rsepro-quicklink">
                    <span class="fa fa-edit"></span>
                    <span>' . $ql_edit . '</span>
                </a>
                <a href="' . Route::_('index.php?option=com_comprofiler&view=pluginclass&plugin=pms.mypmspro&action=messages') . '" class="rsepro-quicklink">
                    <span class="fa fa-envelope-o"></span>
                    <span>' . $ql_msg . '</span>
                </a>
                <a href="#" class="rsepro-quicklink">
                    <span class="fa fa-credit-card"></span>
                    <span>' . $ql_pay . '</span>
                </a>
                <a href="#" class="rsepro-quicklink">
                    <span class="fa fa-folder-open-o"></span>
                    <span>' . $ql_docs . '</span>
                </a>
            </div>
        </div>';

        // Titre section Mes inscriptions (une seule fois)
        $ms_title = $ql_isZh ? '我的报名' : ($ql_isEn ? 'My Registrations' : 'Mes inscriptions');
        $html .= '<h3 class="rsepro-section-title">' . $ms_title . '</h3>';

        if (empty($ownedEvents) && empty($bookedByCategory)) {
            $html .= '<p class="rsepro-no-events">' . Text::_('RSEPRO_CB_NO_EVENTS') . '</p>';
        }

        if (!empty($ownedEvents)) {
            // Pas de titre redondant — on affiche directement
            $html .= '';
            foreach ($this->groupByCategory($ownedEvents) as $catTitle => $eids) {
                foreach ($eids as $eid) {
                    $html .= $this->renderEventItem((int) $eid, $opener, (int) $itemid, $catTitle) ?? '';
                }
            }
        }

        if (!empty($bookedByCategory)) {
            // Exclure les événements culturels (cat 54 = "Événements") de l'espace inscrit
            $examCats = ['TCF Canada','TEF Canada','TEFAQ','TCF Québec','TCF Qu'];
            foreach ($bookedByCategory as $catTitle => $eids) {
                if (in_array($catTitle, ['Événements','Events','文化活动'], true)) continue;
                $isExam = false;
                foreach ($examCats as $ec) {
                    if (strpos($catTitle, $ec) !== false) { $isExam = true; break; }
                }
                foreach ($eids as $eid) {
                    $html .= $this->renderEventItem((int) $eid, $opener, (int) $itemid, $catTitle, $isExam) ?? '';
                }
            }
        }



        // Titre section "Informations personnelles" (bloc contact CB)
        $ct_lang = Factory::getLanguage()->getTag();
        $ct_title = strpos($ct_lang,'zh') !== false ? '个人信息'
                  : (strpos($ct_lang,'en') !== false ? 'Personal Information' : 'Informations personnelles');
        $html .= '<h3 class="rsepro-section-title rsepro-contact-title">' . $ct_title . '</h3>';

        $html .= '</div>';
        return $html;
    }

    // ── Private helpers ──────────────────────────────────────────────

    private static function translateCatTitle(string $title, string $lang): string
    {
        static $map = [
            'zh-CN' => [
                'Événements'     => '文化活动',
                'Cours groupes'  => '集体课',
                "Cours d'essai" => '试听课',
                'Cours VIP'      => 'VIP课',
                'Petits groupes' => '小班课',
                'TCF Canada'     => 'TCF加拿大',
                'TEF Canada'     => 'TEF加拿大',
                'TEFAQ'          => 'TEFAQ',
                'TCF Québec'     => 'TCF魁北克',
            ],
            'en-GB' => [
                'Événements'     => 'Events',
                "Cours d'essai" => 'Trial Classes',
                'Cours groupes'  => 'Group Classes',
                'Cours VIP'      => 'VIP Classes',
                'Petits groupes' => 'Small Groups',
            ],
        ];
        return $map[$lang][$title] ?? $title;
    }

    private function renderEventItem(int $eid, string $opener, int $itemid, string $catTitle = '', bool $isExam = false): ?string
    {
        $details = rseventsproHelper::details($eid);
        $event   = $details['event'] ?? null;
        if ($event === null) return null;

        $lang      = Factory::getLanguage()->getTag();
        $url       = rseventsproHelper::route(
            'index.php?option=com_rseventspro&layout=show&id=' . rseventsproHelper::sef($event->id, $event->name),
            true, $itemid
        );
        // Date : convertie en heure locale Kunming (UTC+8) pour éviter le décalage de jour
        // Heure : valeur brute stockée (saisie directement en heure Kunming dans RSEvents)
        $_tz      = new \DateTimeZone('Asia/Shanghai');
        $_dtS_loc = new \DateTime($event->start, new \DateTimeZone('UTC'));
        $_dtS_loc->setTimezone($_tz);
        $_dtE_raw = new \DateTime($event->end, new \DateTimeZone('UTC'));
        $_months_fr = ['','Jan','Fév','Mar','Avr','Mai','Jun','Jul','Aoû','Sep','Oct','Nov','Déc'];
        $_months_zh = ['','1月','2月','3月','4月','5月','6月','7月','8月','9月','10月','11月','12月'];
        $_months_en = ['','Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $_m = (int)$_dtS_loc->format('n');
        if (strpos($lang,'zh') !== false)      { $_mname = $_months_zh[$_m]; }
        elseif (strpos($lang,'en') !== false)  { $_mname = mb_strtoupper($_months_en[$_m]); }
        else                                   { $_mname = mb_strtoupper($_months_fr[$_m]); }
        $startDate = $_dtS_loc->format('d') . ' ' . $_months_fr[$_m] . ' ' . $_dtS_loc->format('Y');
        if (strpos($lang,'en') !== false) $startDate = $_dtS_loc->format('d') . ' ' . $_months_en[$_m] . ' ' . $_dtS_loc->format('Y');
        if (strpos($lang,'zh') !== false) $startDate = $_dtS_loc->format('Y') . '年' . $_m . '月' . $_dtS_loc->format('d') . '日';
        $startTime = (new \DateTime($event->start, new \DateTimeZone('UTC')))->format('H:i');
        $endTime   = (new \DateTime($event->end,   new \DateTimeZone('UTC')))->format('H:i');
        $dayNum    = $_dtS_loc->format('j');
        $monthAbbr = $_mname;
        $name      = htmlspecialchars((string) $event->name, ENT_QUOTES, 'UTF-8');
        $translated = self::translateCatTitle($catTitle, $lang);
        $catLabel  = htmlspecialchars($translated, ENT_QUOTES, 'UTF-8');

        // Image
        if (!empty($event->icon)) {
            $imgSrc  = Uri::root() . 'components/com_rseventspro/assets/images/events/' . $event->icon;
            $imgHtml = '<div class="rsepro-card-img"><img src="' . $imgSrc . '" alt="' . $name . '" loading="lazy"></div>';
        } else {
            $imgHtml = '<div class="rsepro-card-img"><div class="rsepro-card-img-placeholder"><span class="fa fa-calendar-check-o"></span></div></div>';
        }

        $catHtml = $catLabel ? '<span class="rsepro-badge-cat">' . $catLabel . '</span>' : '';

        return '<div class="rsepro-card">'
            . $imgHtml
            . '<div class="rsepro-card-body">'
            . $catHtml
            . '<div class="rsepro-card-title">' . $name . '</div>'
            . '<div class="rsepro-card-meta">'
            . '<span><span class="fa fa-calendar"></span> ' . $startDate . '</span>'
            . (!$isExam ? '<span class="rsepro-card-time-val"><span class="fa fa-clock-o"></span> ' . $startTime . ' &ndash; ' . $endTime . '</span>' : '')
            . '</div>'
            . '<a ' . $opener . ' href="' . $url . '" class="rsepro-card-link">' . (strpos($lang,'zh') !== false ? '查看详情' : (strpos($lang,'en') !== false ? 'See details' : 'Voir les d&eacute;tails')) . ' <span class="fa fa-chevron-right"></span></a>'
            . '</div>'
            . '<div class="rsepro-card-date-badge">'
            . '<span class="rsepro-card-date-month">' . $monthAbbr . '</span>'
            . '<span class="rsepro-card-date-day">' . $dayNum . '</span>'
            . '</div>'
            . '</div>';
    }

    private function groupByCategory(array $eids): array
    {
        if (empty($eids)) return [];
        $db    = Factory::getDbo();
        $ids   = implode(',', array_map('intval', $eids));
        $other = Text::_('JOTHER');
        $query = $db->getQuery(true)
            ->select([$db->qn('t.ide'), $db->qn('c.title')])
            ->from($db->qn('#__rseventspro_taxonomy', 't'))
            ->join('LEFT', $db->qn('#__categories', 'c') . ' ON ' . $db->qn('t.id') . ' = ' . $db->qn('c.id'))
            ->where($db->qn('t.type') . ' = ' . $db->q('category'))
            ->where($db->qn('t.ide') . ' IN (' . $ids . ')');
        $db->setQuery($query);
        $catMap = [];
        foreach ($db->loadObjectList() ?? [] as $row) {
            $catMap[(int) $row->ide] = $row->title ?: $other;
        }
        $grouped = [];
        foreach ($eids as $eid) {
            $grouped[$catMap[$eid] ?? $other][] = $eid;
        }
        return $grouped;
    }

    private function getOwnedEvents(int $userId, int $limit, string $order): array
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($db->qn('id'))
            ->from($db->qn('#__rseventspro_events'))
            ->where($db->qn('published') . ' = 1')
            ->where($db->qn('completed') . ' = 1')
            ->where($db->qn('owner') . ' = ' . $userId)
            ->order($db->qn('start') . ' ' . $db->escape($order));
        $db->setQuery($query);
        $result = [];
        foreach ($db->loadColumn() ?? [] as $eid) {
            if (count($result) >= $limit) break;
            if (rseventsproHelper::canview((int) $eid)) $result[] = (int) $eid;
        }
        return $result;
    }

    private function getBookedEventsByCategory(int $userId, int $limit): array
    {
        $db    = Factory::getDbo();
        $query = $db->getQuery(true);
        $events = [];
        $hasCart = PluginHelper::isEnabled('system', 'rseprocart')
            && file_exists(JPATH_SITE . '/plugins/system/rseprocart/rseprocart.php');
        if ($hasCart) {
            $query->select([$db->qn('u.ide'), $db->qn('c.events')])
                ->from($db->qn('#__rseventspro_users', 'u'))
                ->join('LEFT', $db->qn('#__rseventspro_cart', 'c') . ' ON ' . $db->qn('u.id') . ' = ' . $db->qn('c.ids'))
                ->where($db->qn('u.idu') . ' = ' . $userId)
                ->order($db->qn('u.date') . ' DESC');
            $db->setQuery($query);
            foreach ($db->loadObjectList() ?? [] as $s) {
                if (!empty($s->ide)) $events[] = (int) $s->ide;
                if (!empty($s->events)) foreach (explode(',', $s->events) as $id) $events[] = (int) $id;
            }
        } else {
            $userEmail = Factory::getUser($userId)->email;
            $cond = $db->qn('idu') . ' = ' . $userId;
            if ($userEmail) {
                $cond .= ' OR ' . $db->qn('email') . ' = ' . $db->q($userEmail);
            }
            $query->select('DISTINCT(' . $db->qn('ide') . ')')
                ->from($db->qn('#__rseventspro_users'))
                ->where('(' . $cond . ')')
                ->order($db->qn('date') . ' DESC');
            $db->setQuery($query);
            $events = array_map('intval', $db->loadColumn() ?? []);
        }
        if (empty($events)) return [];
        $valid = [];
        foreach (array_unique($events) as $eid) {
            if (count($valid) >= $limit) break;
            if (rseventsproHelper::canview($eid)) $valid[] = $eid;
        }
        return $this->groupByCategory($valid);
    }
}
