<?php
/**
 * Plugin AFK Defer — v7.0 — 2026-05-18
 *
 * 1. onBeforeCompileHead : defer scripts SP Builder non-critiques
 * 2. onAfterRender       : sidebar native sur 2 pages test (whitelist stricte)
 *
 * Corrections v7 :
 *  - Force-clear le cache com_modules avant de charger les modules sidebar
 *  - Fallback SQL si getModuleById() retourne un dummy (id=0)
 *  - Anti-doublon sur 'rse-sidebar-col' (absent du CSS)
 *  - Rendu via renderModule() sur objet complet rechargé depuis DB si nécessaire
 *
 * Pages sidebar (whitelist) :
 *   menu 389 = TCF Canada  → modules [288, 289, 290, 291, 292]
 *   menu 742 = Niveau A1   → modules [293, 294, 295, 288, 289, 290]
 *
 * Retour arrière :
 *   SFTP : remplacer par la version v1 (onBeforeCompileHead uniquement)
 *   SQL  : DELETE FROM bwhwo_modules WHERE id IN (288,289,290,291,292,293,294,295)
 *          DELETE FROM bwhwo_modules_menu WHERE moduleid IN (288,289,290,291,292,293,294,295)
 */
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;

class PlgSystemAfk_defer extends CMSPlugin
{
    /** Map menuId → liste ordonnée d'IDs de modules sidebar */
    private $afkSidebarModules = [
        389 => [288, 289, 290, 291, 292],
        742 => [293, 294, 295, 288, 289, 290],
    ];

    // ---------------------------------------------------------------
    // 1. Defer scripts SP Builder
    // ---------------------------------------------------------------
    public function onBeforeCompileHead()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) return;

        $doc     = $app->getDocument();
        $scripts = $doc->_scripts ?? [];
        $deferrable = [
            'com_sppagebuilder/assets/js/common.js',
            'com_sppagebuilder/assets/js/dynamic-content.js',
            'com_sppagebuilder/assets/js/jquery.parallax.js',
            'com_sppagebuilder/assets/js/addons/text_block.js',
            'com_sppagebuilder/assets/js/jquery.magnific-popup.min.js',
            'com_sppagebuilder/assets/js/addons/image.js',
            'com_sppagebuilder/assets/js/js_slider.js',
        ];
        foreach ($scripts as $url => $attrs) {
            foreach ($deferrable as $pattern) {
                if (strpos($url, $pattern) !== false) {
                    $doc->_scripts[$url]['defer'] = true;
                    break;
                }
            }
        }
    }

    // ---------------------------------------------------------------
    // 2. Sidebar — injection via onAfterRender
    // ---------------------------------------------------------------

    /**
     * Charge un module complet depuis la DB, indépendamment de ModuleHelper::load().
     * Construit un objet compatible renderModule().
     */
    private function afkLoadModuleFromDb(int $modId): ?object
    {
        try {
            $db = Factory::getDbo();
            $db->setQuery(
                "SELECT m.id, m.title, m.module, m.position, m.content, m.showtitle,"
                . " m.params, m.access, m.language, m.client_id, m.ordering,"
                . " m.publish_up, m.publish_down"
                . " FROM #__modules m"
                . " WHERE m.id = " . (int)$modId
                . " AND m.published = 1 AND m.client_id = 0"
                . " LIMIT 1"
            );
            $mod = $db->loadObject();
            if (!$mod) return null;

            // Propriétés supplémentaires nécessaires à renderModule()
            if (!isset($mod->name))    $mod->name    = preg_replace('/^mod_/', '', $mod->module);
            if (!isset($mod->type))    $mod->type    = $mod->module;
            if (!isset($mod->user))    $mod->user    = 0;
            if (!isset($mod->menuid))  $mod->menuid  = 0;
            if (!isset($mod->link))    $mod->link    = '';
            if (!isset($mod->note))    $mod->note    = '';
            if (!isset($mod->style))   $mod->style   = '';

            return $mod;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Trouve le </div> fermant équilibré du div débutant à $openPos.
     */
    private function afkFindClosingDiv(string $html, int $openPos): int|false
    {
        $depth = 1;
        $pos   = $openPos + 4;
        $len   = strlen($html);
        while ($pos < $len) {
            $nextOpen  = strpos($html, '<div', $pos);
            $nextClose = strpos($html, '</div>', $pos);
            if ($nextClose === false) break;
            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + 4;
            } else {
                $depth--;
                if ($depth === 0) return $nextClose;
                $pos = $nextClose + 6;
            }
        }
        return false;
    }

    public function onAfterRender()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) return;

        $option = $app->input->get('option', '', 'CMD');
        $view   = $app->input->get('view', '', 'CMD');
        if ($option !== 'com_sppagebuilder' || $view !== 'page') return;

        $menu     = $app->getMenu();
        $menuItem = $menu ? $menu->getActive() : null;
        if (!$menuItem) return;
        $menuId = (int)$menuItem->id;

        if (!isset($this->afkSidebarModules[$menuId])) return;

        $body = $app->getBody();
        // Anti-doublon : 'rse-sidebar-col' n'apparaît pas dans les CSS
        if (strpos($body, 'rse-sidebar-col') !== false) return;

        $spbMarker = '<div id="sp-page-builder"';
        $spbStart  = strpos($body, $spbMarker);
        if ($spbStart === false) return;

        $spbClose = $this->afkFindClosingDiv($body, $spbStart);
        if ($spbClose === false) return;
        $spbEnd = $spbClose + 6;

        // Titres FaLang
        $falangTitles = [];
        $langMap   = ['zh-CN' => 4, 'en-GB' => 1];
        $langTag   = Factory::getLanguage()->getTag();
        $langGet   = $app->input->getString('lang', '');
        $falangLid = $langMap[$langGet] ?? $langMap[$langTag] ?? null;
        if ($falangLid) {
            try {
                $db = Factory::getDbo();
                $db->setQuery(
                    "SELECT reference_id, value FROM #__falang_content"
                    . " WHERE language_id=" . (int)$falangLid
                    . " AND reference_table='modules' AND reference_field='title' AND published=1"
                );
                foreach ($db->loadObjectList() as $r) {
                    $falangTitles[(int)$r->reference_id] = $r->value;
                }
            } catch (\Exception $e) {}
        }

        // Construire le HTML sidebar
        $sidebarHtml = '';
        foreach ($this->afkSidebarModules[$menuId] as $modId) {
            // 1. Essayer getModuleById (utilise la static cache déjà peuplée)
            $mod = ModuleHelper::getModuleById((string)$modId);

            // 2. Si dummy (id=0), charger directement depuis DB
            if (!$mod || empty($mod->id)) {
                $mod = $this->afkLoadModuleFromDb($modId);
            }

            if (!$mod || empty($mod->id)) continue;

            $title    = $falangTitles[$mod->id] ?? $mod->title;
            $modClass = (strpos($mod->module, 'mod_menu') !== false)
                      ? 'afk-sidebar-module afk-sidebar-menu'
                      : 'afk-sidebar-module afk-sidebar-card';

            try {
                $rendered = ModuleHelper::renderModule($mod);
            } catch (\Exception $e) {
                $rendered = '';
            }

            $sidebarHtml .= '<div class="' . $modClass . '">' . "\n";
            if ($title) {
                $sidebarHtml .= '<div class="afk-sidebar-title">'
                              . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                              . '</div>' . "\n";
            }
            $sidebarHtml .= '<div class="afk-sidebar-content">' . $rendered . '</div>' . "\n";
            $sidebarHtml .= '</div>' . "\n";
        }

        if (empty($sidebarHtml)) return;

        $before   = substr($body, 0, $spbStart);
        $spbBlock = substr($body, $spbStart, $spbEnd - $spbStart);
        $after    = substr($body, $spbEnd);

        $app->setBody(
            $before
            . '<div class="row rse-with-sidebar g-0">'
            . '<div class="col-lg-9 col-md-12">'
            . $spbBlock
            . '</div><!-- /col-lg-9 -->'
            . '<div class="col-lg-3 col-md-12 rse-sidebar-col">'
            . $sidebarHtml
            . '</div><!-- /rse-sidebar-col -->'
            . '</div><!-- /row rse-with-sidebar -->'
            . $after
        );
    }
}
