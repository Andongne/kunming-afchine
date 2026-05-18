<?php
/**
 * Plugin AFK Defer — v9.0 — 2026-05-18
 *
 * 1. onBeforeCompileHead : defer scripts SP Builder non-critiques
 * 2. onBeforeRender      : pré-rendu des modules sidebar (avant verrouillage WebAssetManager)
 * 3. onAfterRender       : injection du HTML pré-rendu dans la page
 *
 * Cause racine du bug v7-v8 :
 *   ModuleHelper::renderModule() sur mod_menu lève RuntimeException
 *   "WebAssetManager is locked, you came late" si appelé depuis onAfterRender
 *   (le <head> est déjà fermé). Solution : pré-rendre dans onBeforeRender.
 *
 * Pages sidebar (whitelist) :
 *   menu 742 = Niveau A1 → modules [293, 294, 295, 288, 289, 290]
 *
 * CSS associé (afk-styles.css) :
 *   .rse-with-sidebar, .rse-sidebar-col, .afk-sidebar-module, .afk-sidebar-title, .afk-sidebar-content
 *   #column-id-2e122ab6-7a1d-4fc1-87ca-78745b6a5ef1 { display:none } (cache SP Builder sidebar)
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
        742 => [293, 294, 295, 288, 289, 290],
    ];

    /** HTML pré-rendu de la sidebar, calculé dans onBeforeRender */
    private $afkPrerenderedSidebar = null;

    // ---------------------------------------------------------------
    // 1. Defer scripts SP Builder non-critiques
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
    // 2. Pré-rendu sidebar — AVANT verrouillage WebAssetManager
    // ---------------------------------------------------------------
    public function onBeforeRender()
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

        // Vider le cache com_modules (peut être stale)
        try {
            $cache = Factory::getContainer()
                ->get(CacheControllerFactoryInterface::class)
                ->createCacheController('callback', ['defaultgroup' => 'com_modules']);
            $cache->clean('com_modules', 'group');
        } catch (\Exception $e) {}

        // Charger les modules via getModuleList() (fresh SQL)
        $modIds  = $this->afkSidebarModules[$menuId];
        $indexed = [];
        try {
            foreach (ModuleHelper::getModuleList() as $m) {
                if (in_array((int)$m->id, $modIds)) {
                    $indexed[(int)$m->id] = $m;
                }
            }
        } catch (\Exception $e) {}

        if (empty($indexed)) return;

        // Titres FaLang
        $falangTitles = [];
        $langMap  = ['zh-CN' => 4, 'en-GB' => 1];
        $langTag  = Factory::getLanguage()->getTag();
        $langGet  = $app->input->getString('lang', '');
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

        // Rendre chaque module — WebAssetManager ouvert à ce stade
        $sidebarHtml = '';
        foreach ($modIds as $modId) {
            $mod = $indexed[$modId] ?? null;
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
            if ($title && $mod->showtitle) {
                $sidebarHtml .= '<div class="afk-sidebar-title">'
                              . htmlspecialchars($title, ENT_QUOTES, 'UTF-8')
                              . '</div>' . "\n";
            }
            $sidebarHtml .= '<div class="afk-sidebar-content">' . $rendered . '</div>' . "\n";
            $sidebarHtml .= '</div>' . "\n";
        }

        $this->afkPrerenderedSidebar = $sidebarHtml ?: null;
    }

    // ---------------------------------------------------------------
    // 3. Injection sidebar — après rendu complet de la page
    // ---------------------------------------------------------------

    /** Trouve le </div> fermant équilibré du div débutant à $openPos */
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
        if ($this->afkPrerenderedSidebar === null) return;

        $app  = Factory::getApplication();
        $body = $app->getBody();

        if (strpos($body, 'rse-sidebar-col') !== false) return; // anti-doublon

        $spbMarker = '<div id="sp-page-builder"';
        $spbStart  = strpos($body, $spbMarker);
        if ($spbStart === false) return;

        $spbClose = $this->afkFindClosingDiv($body, $spbStart);
        if ($spbClose === false) return;
        $spbEnd = $spbClose + 6;

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
            . $this->afkPrerenderedSidebar
            . '</div><!-- /rse-sidebar-col -->'
            . '</div><!-- /row rse-with-sidebar -->'
            . $after
        );
    }
}
