<?php
/**
 * RSForm! Pro — Override default.php
 * AF Kunming : layout avec sidebar rse-exams-sidebar + titres FaLang
 */
defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Factory;

// Résoudre language_id FaLang (SEF /zh/ ou ?lang=zh-CN)
$_afkLangMap   = ['zh-CN' => 4, 'en-GB' => 1];
$_afkLangTag   = Factory::getLanguage()->getTag();
$_afkLangGet   = Factory::getApplication()->input->getString('lang', '');
$_afkFalangLid = $_afkLangMap[$_afkLangGet] ?? $_afkLangMap[$_afkLangTag] ?? null;
$_afkFalangTitles = [];
if ($_afkFalangLid) {
    $db = Factory::getDbo();
    $q  = $db->getQuery(true)
             ->select($db->quoteName(['reference_id','value']))
             ->from($db->quoteName('#__falang_content'))
             ->where($db->quoteName('language_id').'='.(int)$_afkFalangLid)
             ->where($db->quoteName('reference_table').'='.$db->quote('modules'))
             ->where($db->quoteName('reference_field').'='.$db->quote('title'))
             ->where($db->quoteName('published').'=1');
    $db->setQuery($q);
    foreach ($db->loadObjectList() as $_r) {
        $_afkFalangTitles[(int)$_r->reference_id] = $_r->value;
    }
}

// Sidebar modules
$_afkSidebarModules = ModuleHelper::getModules('rse-exams-sidebar');
$_afkHasSidebar     = !empty($_afkSidebarModules);
?>

<?php if ($_afkHasSidebar): ?>
<div class="row rse-with-sidebar g-0">
<div class="col-lg-9 col-md-12">
<?php endif; ?>

<?php if ($this->params->get('show_page_heading', 0)): ?>
<h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
<?php endif; ?>

<?php echo RSFormProHelper::displayForm($this->formId); ?>

<?php if ($_afkHasSidebar): ?>
</div><!-- /col-lg-9 -->
<div class="col-lg-3 col-md-12 rse-sidebar-col">
    <?php foreach ($_afkSidebarModules as $_afkMod): ?>
    <?php $_afkModClass = (strpos($_afkMod->module, 'mod_menu') !== false) ? 'afk-sidebar-module afk-sidebar-menu' : 'afk-sidebar-module afk-sidebar-card'; ?>
    <?php $_afkTitle = $_afkFalangTitles[(int)$_afkMod->id] ?? $_afkMod->title; ?>
    <div class="<?php echo $_afkModClass; ?>">
        <?php if ($_afkMod->showtitle): ?>
        <div class="afk-sidebar-title"><?php echo htmlspecialchars($_afkTitle); ?></div>
        <?php endif; ?>
        <div class="afk-sidebar-content">
            <?php echo ModuleHelper::renderModule($_afkMod, ['style' => 'none']); ?>
        </div>
    </div>
    <?php endforeach; ?>
</div><!-- /rse-sidebar-col -->
</div><!-- /row rse-with-sidebar -->
<?php endif; ?>
