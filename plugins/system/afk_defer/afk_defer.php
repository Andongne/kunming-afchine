<?php
/**
 * Plugin AFK Defer — v9.1 — 2026-05-18
 *
 * 1. onBeforeCompileHead : defer scripts SP Builder
 * 2. onBeforeRender      : pré-rendu sidebar (avant verrouillage WebAssetManager)
 * 3. onAfterRender       : injection HTML sidebar dans la page
 *
 * Pages sidebar : menu 742 = Niveau A1
 * Modules       : [293, 294, 295, 296, 288, 289, 290]
 *
 * SP Builder page 196 patchée (JSON) :
 *   col0 width 75% → 100% (xl/lg/md)
 *   col1 hidden_xl/lg/md/sm = 1
 *   Backup : /tmp/spb_196_backup_before_patch.json
 *
 * Retour arrière :
 *   Plugin  : SFTP remplacer par v1 (onBeforeCompileHead seul)
 *   SPB 196 : REPLACE() SQL pour remettre "100%" → "75%" et hidden = ""
 *   SQL mods : DELETE FROM bwhwo_modules WHERE id IN (288..296)
 */
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;

class PlgSystemAfk_defer extends CMSPlugin
{
    private $afkSidebarModules = [
        742 => [293, 294, 295, 296, 288, 289, 290],
    ];

    private $afkPrerenderedSidebar = null;

    // ---------------------------------------------------------------
    // 1. Defer scripts SP Builder
    // ---------------------------------------------------------------
    public function onBeforeCompileHead()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) return;
        $doc = $app->getDocument();
        $deferrable = [
            'com_sppagebuilder/assets/js/common.js',
            'com_sppagebuilder/assets/js/dynamic-content.js',
            'com_sppagebuilder/assets/js/jquery.parallax.js',
            'com_sppagebuilder/assets/js/addons/text_block.js',
            'com_sppagebuilder/assets/js/jquery.magnific-popup.min.js',
            'com_sppagebuilder/assets/js/addons/image.js',
            'com_sppagebuilder/assets/js/js_slider.js',
        ];
        foreach ($doc->_scripts ?? [] as $url => $attrs) {
            foreach ($deferrable as $p) {
                if (strpos($url, $p) !== false) { $doc->_scripts[$url]['defer'] = true; break; }
            }
        }
    }

    // ---------------------------------------------------------------
    // 2. Pré-rendu sidebar — avant verrouillage WebAssetManager
    // ---------------------------------------------------------------
    public function onBeforeRender()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) return;
        if ($app->input->get('option','','CMD') !== 'com_sppagebuilder') return;
        if ($app->input->get('view','','CMD') !== 'page') return;

        $menuItem = $app->getMenu() ? $app->getMenu()->getActive() : null;
        if (!$menuItem) return;
        $menuId = (int)$menuItem->id;
        if (!isset($this->afkSidebarModules[$menuId])) return;

        // Vider cache com_modules (peut être stale)
        try {
            Factory::getContainer()->get(CacheControllerFactoryInterface::class)
                ->createCacheController('callback', ['defaultgroup' => 'com_modules'])
                ->clean('com_modules', 'group');
        } catch (\Exception $e) {}

        // Charger les modules via getModuleList() (fresh SQL)
        $modIds  = $this->afkSidebarModules[$menuId];
        $indexed = [];
        try {
            foreach (ModuleHelper::getModuleList() as $m) {
                if (in_array((int)$m->id, $modIds)) $indexed[(int)$m->id] = $m;
            }
        } catch (\Exception $e) {}
        if (empty($indexed)) return;

        // Titres FaLang
        $falangTitles = [];
        $langMap  = ['zh-CN' => 4, 'en-GB' => 1];
        $langTag  = Factory::getLanguage()->getTag();
        $langGet  = $app->input->getString('lang', '');
        $lid = $langMap[$langGet] ?? $langMap[$langTag] ?? null;
        if ($lid) {
            try {
                $db = Factory::getDbo();
                $db->setQuery("SELECT reference_id, value FROM #__falang_content WHERE language_id={$lid} AND reference_table='modules' AND reference_field='title' AND published=1");
                foreach ($db->loadObjectList() as $r) $falangTitles[(int)$r->reference_id] = $r->value;
            } catch (\Exception $e) {}
        }

        // Rendre chaque module
        $html = '';
        foreach ($modIds as $id) {
            $mod = $indexed[$id] ?? null;
            if (!$mod || empty($mod->id)) continue;
            $title    = $falangTitles[$mod->id] ?? $mod->title;
            $modClass = strpos($mod->module, 'mod_menu') !== false
                      ? 'afk-sidebar-module afk-sidebar-menu'
                      : 'afk-sidebar-module afk-sidebar-card';
            try { $rendered = ModuleHelper::renderModule($mod); }
            catch (\Exception $e) { $rendered = ''; }

            $html .= '<div class="' . $modClass . '">';
            if ($title && $mod->showtitle)
                $html .= '<div class="afk-sidebar-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</div>';
            $html .= '<div class="afk-sidebar-content">' . $rendered . '</div></div>' . "\n";
        }
        $this->afkPrerenderedSidebar = $html ?: null;
    }

    // ---------------------------------------------------------------
    // 3. Injection sidebar dans le body final
    // ---------------------------------------------------------------
    private function afkFindClosingDiv(string $html, int $openPos): int|false
    {
        $depth = 1; $pos = $openPos + 4; $len = strlen($html);
        while ($pos < $len) {
            $o = strpos($html, '<div', $pos);
            $c = strpos($html, '</div>', $pos);
            if ($c === false) break;
            if ($o !== false && $o < $c) { $depth++; $pos = $o + 4; }
            else { $depth--; if ($depth === 0) return $c; $pos = $c + 6; }
        }
        return false;
    }

    public function onAfterRender()
    {
        if ($this->afkPrerenderedSidebar === null) return;
        $app  = Factory::getApplication();
        $body = $app->getBody();
        if (strpos($body, 'rse-sidebar-col') !== false) return;

        $marker = '<div id="sp-page-builder"';
        $start  = strpos($body, $marker);
        if ($start === false) return;
        $close = $this->afkFindClosingDiv($body, $start);
        if ($close === false) return;
        $end = $close + 6;

        $app->setBody(
            substr($body, 0, $start)
            . '<div class="row rse-with-sidebar g-0">'
            . '<div class="col-lg-9 col-md-12">'
            . substr($body, $start, $end - $start)
            . '</div>'
            . '<div class="col-lg-3 col-md-12 rse-sidebar-col">'
            . $this->afkPrerenderedSidebar
            . '</div>'
            . '</div>'
            . substr($body, $end)
        );
    }
}
