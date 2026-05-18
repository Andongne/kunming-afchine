<?php
/**
 * Plugin AFK Defer — v5.0 — 2026-05-18
 *
 * 1. onBeforeCompileHead : defer scripts SP Builder non-critiques
 * 2. onAfterRender       : sidebar native pages test (389=TCF Canada, 742=Niveau A1)
 *
 * Modules sidebar : chargés directement par SQL (bypass ModuleHelper cache)
 * Positions       : certif-sidebar (389+742) / cours-sidebar (742)
 *
 * Retour arrière :
 *   SFTP : remplacer par la version v1 (sans onAfterRender)
 *   SQL  : DELETE FROM bwhwo_modules WHERE id IN (288,289,290,291,292,293,294,295)
 *          DELETE FROM bwhwo_modules_menu WHERE moduleid IN (288,289,290,291,292,293,294,295)
 */
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

class PlgSystemAfk_defer extends CMSPlugin
{
    /** Whitelist des menu item IDs et positions associées */
    private $afkSidebarMenuMap = [
        389 => ['certif-sidebar'],
        742 => ['cours-sidebar', 'certif-sidebar'],
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
     * Charge les modules d'une position directement via SQL.
     * Bypass de ModuleHelper::load() pour éviter les problèmes de cache.
     */
    private function afkLoadModulesByPosition(string $position, int $itemId): array
    {
        try {
            $db  = Factory::getDbo();
            $db->setQuery(
                "SELECT m.*, mm.menuid"
                . " FROM #__modules m"
                . " LEFT JOIN #__modules_menu mm ON mm.moduleid = m.id"
                . " WHERE m.published = 1"
                . " AND m.client_id = 0"
                . " AND m.position = " . $db->quote($position)
                . " AND (mm.menuid = " . (int)$itemId . " OR mm.menuid = 0 OR mm.menuid IS NULL)"
                . " ORDER BY m.ordering ASC"
            );
            $rows = $db->loadObjectList();
            if (empty($rows)) return [];
            // Dédoublonner par module id
            $seen = [];
            $mods = [];
            foreach ($rows as $row) {
                if (!isset($seen[$row->id])) {
                    $seen[$row->id] = true;
                    $mods[] = $row;
                }
            }
            return $mods;
        } catch (\Exception $e) {
            return [];
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

    /**
     * Rend un module en HTML.
     */
    private function afkRenderModule(object $mod, array $falangTitles): string
    {
        $title    = $falangTitles[$mod->id] ?? $mod->title;
        $modClass = (strpos($mod->module, 'mod_menu') !== false)
                  ? 'afk-sidebar-module afk-sidebar-menu'
                  : 'afk-sidebar-module afk-sidebar-card';
        try {
            // Convertir l'objet brut SQL en objet compatible ModuleHelper::renderModule()
            if (!isset($mod->name))    $mod->name    = substr($mod->module, 4);
            if (!isset($mod->content)) $mod->content = '';
            $rendered = ModuleHelper::renderModule($mod);
        } catch (\Exception $e) {
            $rendered = '';
        }
        $html  = '<div class="' . $modClass . '">';
        if ($title) {
            $html .= '<div class="afk-sidebar-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
        }
        $html .= '<div class="afk-sidebar-content">' . $rendered . '</div>';
        $html .= '</div>';
        return $html;
    }

    public function onAfterRender()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) return;

        $option = $app->input->get('option', '', 'CMD');
        $view   = $app->input->get('view', '', 'CMD');
        if ($option !== 'com_sppagebuilder' || $view !== 'page') return;

        $menuItem = ($app->getMenu()) ? $app->getMenu()->getActive() : null;
        if (!$menuItem) return;
        $menuId = (int)$menuItem->id;

        if (!array_key_exists($menuId, $this->afkSidebarMenuMap)) return;

        $body = $app->getBody();
        if (strpos($body, 'rse-with-sidebar') !== false) return; // anti-doublon

        $spbMarker = '<div id="sp-page-builder"';
        $spbStart  = strpos($body, $spbMarker);
        if ($spbStart === false) return;

        $spbClose = $this->afkFindClosingDiv($body, $spbStart);
        if ($spbClose === false) return;
        $spbEnd = $spbClose + 6;

        // Titres FaLang
        $falangTitles = [];
        $langMap      = ['zh-CN' => 4, 'en-GB' => 1];
        $langTag      = Factory::getLanguage()->getTag();
        $langGet      = $app->input->getString('lang', '');
        $falangLid    = $langMap[$langGet] ?? $langMap[$langTag] ?? null;
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

        // Charger et rendre les modules sidebar (SQL direct)
        $sidebarContent = '';
        foreach ($this->afkSidebarMenuMap[$menuId] as $pos) {
            $mods = $this->afkLoadModulesByPosition($pos, $menuId);
            foreach ($mods as $mod) {
                $sidebarContent .= $this->afkRenderModule($mod, $falangTitles);
            }
        }

        if (empty($sidebarContent)) return;

        // Injection
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
            . $sidebarContent
            . '</div><!-- /rse-sidebar-col -->'
            . '</div><!-- /row rse-with-sidebar -->'
            . $after
        );
    }
}
