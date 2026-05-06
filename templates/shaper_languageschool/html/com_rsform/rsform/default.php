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

<script>
/* Visibilité conditionnelle des champs tarif/compétences selon l'examen choisi */
(function(){
  var EXAM_BLOCKS = {
    'TCF Canada':  { show: ['tarif-tcf-canada'],         hide: ['tarif-tef-canada','competences-tcf-quebec','competences-tefaq'] },
    'TEF Canada':  { show: ['tarif-tef-canada'],         hide: ['tarif-tcf-canada','competences-tcf-quebec','competences-tefaq'] },
    'TCF Qu\u00e9bec': { show: ['competences-tcf-quebec'], hide: ['tarif-tcf-canada','tarif-tef-canada','competences-tefaq'] },
    'TEFAQ':       { show: ['competences-tefaq'],        hide: ['tarif-tcf-canada','tarif-tef-canada','competences-tcf-quebec'] }
  };
  function getBlock(alias) { return document.querySelector('.rsform-block-'+alias); }
  function applyExamVisibility(val) {
    // Cacher tous les blocs conditionnels par défaut
    ['tarif-tcf-canada','tarif-tef-canada','competences-tcf-quebec','competences-tefaq'].forEach(function(a){
      var b = getBlock(a); if(b) b.style.display = 'none';
    });
    // Afficher les blocs correspondants
    if (val && EXAM_BLOCKS[val]) {
      EXAM_BLOCKS[val].show.forEach(function(a){ var b = getBlock(a); if(b) b.style.display = ''; });
    }
  }
  function initExamVisibility() {
    var sel = document.querySelector('select[name="form[Choix_exam][]"]');
    if (!sel) return;
    applyExamVisibility(sel.value);
    sel.addEventListener('change', function(){ applyExamVisibility(this.value); });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initExamVisibility);
  } else {
    initExamVisibility();
  }
  // Aussi relancer après le prefill (100ms)
  window.addEventListener('load', function(){ setTimeout(function(){ var s=document.querySelector('select[name="form[Choix_exam][]"]'); if(s) applyExamVisibility(s.value); }, 100); });
})();

(function(){
  // Si l'URL contient des paramètres form[...], ré-appliquer après le sessionStorage
  var params = new URLSearchParams(window.location.search);
  var hasFormParams = false;
  params.forEach(function(v, k){ if(k.indexOf('form[') === 0 || k.indexOf('form%5B') === 0) hasFormParams = true; });
  if (!hasFormParams) return;

  function applyPrefill() {
    params.forEach(function(value, key) {
      // Décoder le nom du champ : form[Session][] -> form[Session][]
      var decoded = decodeURIComponent(key);
      var el = document.querySelector('[name="' + decoded + '"]');
      if (!el) return;
      if (el.tagName === 'SELECT') {
        for (var i = 0; i < el.options.length; i++) {
          if (el.options[i].value === decodeURIComponent(value)) {
            el.selectedIndex = i;
            el.dispatchEvent(new Event('change', { bubbles: true }));
            break;
          }
        }
      } else if (el.type === 'checkbox' || el.type === 'radio') {
        el.checked = (el.value === decodeURIComponent(value));
      } else {
        el.value = decodeURIComponent(value);
      }
    });
  }

  // Lancer après le chargement complet (après sessionStorage et RSForm JS)
  if (document.readyState === 'complete') {
    setTimeout(applyPrefill, 50);
  } else {
    window.addEventListener('load', function(){ setTimeout(applyPrefill, 50); });
  }
})();
</script>

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
