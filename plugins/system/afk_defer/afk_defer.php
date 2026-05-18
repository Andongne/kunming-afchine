<?php
/**
 * Plugin AFK Defer — defer scripts SP Builder + injection sidebar native
 * @version 3.0 — 2026-05-18
 */
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;

class PlgSystemAfk_defer extends CMSPlugin
{
    public function onBeforeCompileHead()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) return;

        $doc = $app->getDocument();
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

    /**
     * Trouve la position de la balise fermante </div> équilibrée
     * à partir de la position d'ouverture d'un <div>.
     */
    private function findClosingDiv($html, $openPos)
    {
        $depth = 0;
        $pos   = $openPos;
        $len   = strlen($html);

        while ($pos < $len) {
            $nextOpen  = strpos($html, '<div', $pos);
            $nextClose = strpos($html, '</div>', $pos);

            if ($nextClose === false) break;

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + 4;
            } else {
                if ($depth === 0) {
                    return $nextClose;
                }
                $depth--;
                $pos = $nextClose + 6;
            }
        }

        return false;
    }

    /**
     * AFK Sidebar Injection — v3.0 — 2026-05-18
     * Injecte sidebar native (cours-sidebar / certif-sidebar) via onAfterRender.
     * Compatible avec le cache Vue Joomla (ViewController).
     */
    public function onAfterRender()
    {
        $app = Factory::getApplication();
        if (!$app->isClient('site')) return;

        $input  = $app->input;
        $option = $input->get('option', '', 'CMD');
        $view   = $input->get('view', '', 'CMD');

        if ($option !== 'com_sppagebuilder' || $view !== 'page') return;

        // Détecter le chemin de menu actif
        $menuHelper = $app->getMenu();
        $menuItem   = $menuHelper ? $menuHelper->getActive() : null;
        $menuPath   = ($menuItem && isset($menuItem->route)) ? trim($menuItem->route, '/') : '';

        $sidebarPos = null;
        if (strpos($menuPath, 'cours-de-francais') === 0) {
            $sidebarPos = 'cours-sidebar';
        } elseif (strpos($menuPath, 'certifications-et-diplomes') === 0) {
            $sidebarPos = 'certif-sidebar';
        }

        if (!$sidebarPos) return;

        $sidebarMods = ModuleHelper::getModules($sidebarPos);

        if ($sidebarPos === 'cours-sidebar' && preg_match('#notre-methode/niveau-#', $menuPath)) {
            $extra       = ModuleHelper::getModules('certif-sidebar');
            $sidebarMods = array_merge($sidebarMods, $extra);
        }

        if (empty($sidebarMods)) return;

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

        // Rendre les modules sidebar
        $sidebarHtml = '';
        foreach ($sidebarMods as $mod) {
            $title    = $falangTitles[$mod->id] ?? $mod->title;
            $modClass = (strpos($mod->module, 'mod_menu') !== false)
                      ? 'afk-sidebar-module afk-sidebar-menu'
                      : 'afk-sidebar-module afk-sidebar-card';
            try {
                $rendered = ModuleHelper::renderModule($mod);
            } catch (\Exception $e) {
                $rendered = '';
            }
            $sidebarHtml .= '<div class="' . $modClass . '">';
            if ($title) {
                $sidebarHtml .= '<div class="afk-sidebar-title">' . htmlspecialchars($title) . '</div>';
            }
            $sidebarHtml .= '<div class="afk-sidebar-content">' . $rendered . '</div>';
            $sidebarHtml .= '</div>';
        }

        // Injecter dans le body
        $body     = $app->getBody();
        $spbStart = strpos($body, '<div id="sp-page-builder"');
        if ($spbStart === false) return;

        // Trouver la fermeture équilibrée du div sp-page-builder
        $spbClose = $this->findClosingDiv($body, $spbStart);
        if ($spbClose === false) return;
        $spbCloseEnd = $spbClose + 6; // longueur de </div>

        // Construire le HTML modifié
        $before   = substr($body, 0, $spbStart);
        $spbBlock = substr($body, $spbStart, $spbCloseEnd - $spbStart);
        $after    = substr($body, $spbCloseEnd);

        $newBody = $before
            . '<div class="row rse-with-sidebar g-0">'
            . '<div class="col-lg-9 col-md-12">'
            . $spbBlock
            . '</div><!-- /col-lg-9 -->'
            . '<div class="col-lg-3 col-md-12 rse-sidebar-col">'
            . $sidebarHtml
            . '</div><!-- /rse-sidebar-col -->'
            . '</div><!-- /row rse-with-sidebar -->'
            . $after;

        $app->setBody($newBody);
    }
}
