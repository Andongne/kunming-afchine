<?php
/**
 * mod_falang — Override AFK
 * Nettoie les segments RSEvents Pro (jour/day/COM_RSEVENTSPRO_*) des URLs du switcher de langue
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

// Pattern : supprimer les segments de vue RSEvents Pro après le menu item de base
$rsePattern = '#/(daily|monthly|yearly|day|jour|jours|week|semaine|year|ann[eé]e|COM_RSEVENTSPRO_[A-Z_]+_SEF)(/[^?#]*)?(?=[?#]|$)#i';

?>
<ul class="<?php echo $params->get('inline', 1) ? 'lang-inline' : 'lang-block';?>">
    <?php foreach($list as $language): ?>
        <?php if ($params->get('show_active', 0) || !$language->active): ?>
            <?php
            // Nettoyer l'URL des segments RSEvents Pro
            $cleanLink = preg_replace($rsePattern, '', $language->link);
            ?>
            <li class="<?php echo $language->active ? 'lang-active' : ''; ?>" dir="<?php echo $language->rtl ? 'rtl' : 'ltr'; ?>">
                <?php if ($language->display): ?>
                    <a href="<?php echo $cleanLink; ?>">
                        <?php if ($params->get('image', 1)): ?>
                            <?php echo HTMLHelper::_('image', $imagesPath.$language->image.'.'.$imagesType, $language->title_native, ['title' => $language->title_native], $relativePath); ?>
                        <?php endif; ?>
                        <?php if ($params->get('show_name', 1)): ?>
                            <?php echo $params->get('full_name', 1) ? $language->title_native : strtoupper($language->sef); ?>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <?php if ($params->get('image', 1)): ?>
                        <?php echo HTMLHelper::_('image', $imagesPath.$language->image.'.'.$imagesType, $language->title_native, ['title' => $language->title_native, 'style' => 'opacity:0.5'], $relativePath); ?>
                    <?php endif; ?>
                    <?php if ($params->get('show_name', 1)): ?>
                        <?php echo $params->get('full_name', 1) ? $language->title_native : strtoupper($language->sef); ?>
                    <?php endif; ?>
                <?php endif; ?>
            </li>
        <?php endif; ?>
    <?php endforeach; ?>
</ul>
