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

// Sidebar modules — rse-calendar-sidebar pour cours (FormId=6), rse-exams-sidebar pour examens
$_afkFormId = (int) Factory::getApplication()->input->getInt('formId',
    Factory::getApplication()->input->getInt('Itemid') == 1015 ? 6 : 0);
// Détecter aussi depuis l'objet formulaire si disponible
if (isset($this->form) && isset($this->form->FormId)) $_afkFormId = (int)$this->form->FormId;
$_afkSidebarPos     = $_afkFormId === 6 ? 'rse-calendar-sidebar' : 'rse-exams-sidebar';
$_afkSidebarModules = ModuleHelper::getModules($_afkSidebarPos);
$_afkHasSidebar     = !empty($_afkSidebarModules);

// Charger les sessions de cours disponibles (formulaire cours uniquement)
$_afkCourseSessions = [];
if ($_afkFormId === 6) {
    $_afkDb = \Joomla\CMS\Factory::getDbo();
    $_afkCourseRows = $_afkDb->setQuery(
        "SELECT id, name, start, description FROM #__rseventspro_events
         WHERE published=1 AND start > NOW()
         AND name NOT LIKE '%TCF%' AND name NOT LIKE '%TEF%'
         AND name NOT LIKE '%Porte ouverte%'
         ORDER BY start ASC LIMIT 60"
    )->loadAssocList();
    $__afkFmtMap = [
        '/VIP\s*3|trio/i'    => 'VIP 3 cours avec 3 étudiants',
        '/VIP\s*2|duo/i'     => 'VIP 2 cours avec 2 étudiants',
        '/VIP/i'             => 'VIP 1 cours individuel',
        '/4.?5|Petits/i'     => 'Petits groupes (de 4 à 5 personnes)',
    ];
    foreach ($_afkCourseRows as $_afkCR) {
        $_afkDesc = strip_tags($_afkCR['description'] ?? '');
        $_afkTch  = '';
        if (preg_match('/Enseignant[^:]*:\s*(.+?)(?=Tarif|VooV|Dur)/u', $_afkDesc, $_afkTm))
            $_afkTch = trim($_afkTm[1]);
        $_afkFmt = 'Groupes (de 6 à 12 personnes)';
        foreach ($__afkFmtMap as $pat => $val) {
            if (preg_match($pat, $_afkCR['name'])) { $_afkFmt = $val; break; }
        }
        $_afkCourseSessions[] = [
            'label'   => $_afkCR['name'] . ' — ' . date('d/m/Y H\hi', strtotime($_afkCR['start'])),
            'date'    => date('d/m/Y', strtotime($_afkCR['start'])),
            'format'  => $_afkFmt,
            'teacher' => $_afkTch,
        ];
    }
}
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

<?php if (!empty($_afkCourseSessions)): ?>
<div class="rsform-block rsform-type-freetext" style="margin-bottom:16px">
  <label class="formControlLabel" for="afk-session-sel">
    <?php
    $__lbl = ['zh'=>'选择课程时间 (*)','en'=>'Choose a session (*)','fr'=>'Choisir une séance (*)'];
    $__l = substr(\Joomla\CMS\Factory::getLanguage()->getTag(),0,2);
    echo $__lbl[$__l] ?? $__lbl['fr'];
    ?>
  </label>
  <select id="afk-session-sel" class="form-select" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:4px">
    <option value="">—</option>
    <?php foreach ($_afkCourseSessions as $_afkS): ?>
    <option value="<?php echo htmlspecialchars(json_encode($_afkS),ENT_QUOTES); ?>">
      <?php echo htmlspecialchars($_afkS['label']); ?>
    </option>
    <?php endforeach; ?>
  </select>
</div>
<script>
document.getElementById('afk-session-sel').addEventListener('change', function() {
  var v = this.value; if (!v) return;
  try { var d = JSON.parse(v); } catch(e){return;}
  // Session
  var sField = document.querySelector("input[name='form[Session][]'],input[name*='Session']");
  if (sField) sField.value = d.date;
  // Format_cours
  var fSel = document.querySelector("select[name='form[Format_cours][]'],select[name*='Format_cours']");
  if (fSel) {
    for (var i=0;i<fSel.options.length;i++) {
      if (fSel.options[i].value === d.format) { fSel.selectedIndex=i; break; }
    }
    fSel.dispatchEvent(new Event('change'));
  }
  // Professeur
  var pField = document.querySelector("input[name='form[Professeur][]'],input[name*='Professeur'],select[name*='Professeur']");
  if (pField) pField.value = d.teacher;
});
</script>
<?php endif; ?>
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

<script>
/* Remplacement du prix dans le template thank-you selon l'examen choisi */
(function(){
  function updateTYTemplate() {
    var tpl = document.getElementById('rsf-ty-template');
    if (!tpl) return;
    // Remplacer la chaîne exacte stockée dans le HTML (entités non décodées)
    var html = tpl.innerHTML;
    // Toutes les formes possibles du prix fixe
    html = html.split('2&#160;700 RMB').join('PRIX_CALCULE');
    html = html.split('2\u00a0700 RMB').join('PRIX_CALCULE');
    html = html.split('2 700 RMB').join('PRIX_CALCULE');
    html = html.split('2700 RMB').join('PRIX_CALCULE');
    tpl.innerHTML = html;
  }
  // Exécuter immédiatement — le script est après displayForm(), le DOM RSForm est prêt
  updateTYTemplate();
})();
</script>

<script>
/* Traduction des titres de section du formulaire cours (data-i18n-*) */
(function () {
  var _lang = (document.documentElement.lang || 'fr').toLowerCase().slice(0, 2);
  if (_lang === 'fr') return;
  document.querySelectorAll('[data-i18n-' + _lang + ']').forEach(function (el) {
    var t = el.getAttribute('data-i18n-' + _lang);
    if (t) el.textContent = t;
  });
})();
</script>
