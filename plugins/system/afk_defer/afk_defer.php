<?php
/**
 * Plugin AFK Defer — v1.0 — 2026-05-09
 * Ajoute defer aux scripts SP Builder non-critiques
 */
defined('_JEXEC') or die;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
class PlgSystemAfk_defer extends CMSPlugin
{
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
}
