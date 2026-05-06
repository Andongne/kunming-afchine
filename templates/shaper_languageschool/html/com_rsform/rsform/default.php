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
         AND (force_close IS NULL OR force_close=0)
         AND (registration_closed IS NULL OR registration_closed=0)
         ORDER BY start ASC LIMIT 60"
    )->loadAssocList();
    $__afkFmtMap = [
        '/VIP\s*3|trio/i'    => 'VIP 3 cours avec 3 étudiants',
        '/VIP\s*2|duo/i'     => 'VIP 2 cours avec 2 étudiants',
        '/VIP/i'             => 'VIP 1 cours individuel',
        '/4.?5|Petits/i'     => 'Petits groupes (de 4 à 5 personnes)',
    ];
    foreach ($_afkCourseRows as $_afkCR) {
        // Délai inscription : veille à 17h (vendredi si veille = sam/dim)
        $_afkEventTs  = strtotime($_afkCR['start']);
        $_afkEventDay = date('Y-m-d', $_afkEventTs);
        $_afkDeadline = strtotime($_afkEventDay . ' -1 day 17:00:00');
        $_afkDow = (int)date('N', $_afkDeadline);
        if ($_afkDow == 7) $_afkDeadline -= 2 * 86400;
        if ($_afkDow == 6) $_afkDeadline -= 1 * 86400;
        if (time() >= $_afkDeadline) continue;
        $_afkDesc = strip_tags($_afkCR['description'] ?? '');
        $_afkTch  = '';
        if (preg_match('/Enseignant[^:]*:\s*(.+?)(?=Tarif|VooV|Dur)/u', $_afkDesc, $_afkTm))
            $_afkTch = trim($_afkTm[1]);
        $_afkFmt = 'Groupes (de 6 à 12 personnes)';
        foreach ($__afkFmtMap as $pat => $val) {
            if (preg_match($pat, $_afkCR['name'])) { $_afkFmt = $val; break; }
        }
        // Tarif + Porte ouverte
        if (preg_match('/porte.ouverte|essai|gratuit/i', $_afkCR['name'])) {
            $_afkTarifStr = 'Gratuit'; $_afkFmt = "Cours d'essai (gratuit)";
        } elseif (preg_match('/VIP\\s*3|trio/i', $_afkCR['name']))  $_afkTarifStr = '98 ¥/h/pers.';
        elseif  (preg_match('/VIP\\s*2|duo/i', $_afkCR['name']))   $_afkTarifStr = '128 ¥/h/pers.';
        elseif  (preg_match('/VIP/i', $_afkCR['name']))               $_afkTarifStr = '208 ¥/h';
        elseif  (preg_match('/4.?5|Petits/i', $_afkCR['name']))      $_afkTarifStr = '78 ¥/h/pers.';
        else                                                           $_afkTarifStr = '49 ¥/h/pers.';
        $_afkCourseSessions[] = [
            'label'   => $_afkCR['name'] . ' — ' . date('d/m/Y H\hi', $_afkEventTs),
            'date'    => date('d/m/Y', $_afkEventTs),
            'format'  => $_afkFmt,
            'tarif'   => $_afkTarifStr,
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

<?php if (!empty($_afkCourseSessions)): ?>
<script>
(function(){
  var _sessions = <?php echo json_encode($_afkCourseSessions, JSON_UNESCAPED_UNICODE); ?>;
  var _lang = (document.documentElement.lang||'fr').slice(0,2);
  var _lbls = {fr:'Choisir une séance (*)',zh:'选择课程时间 (*)',en:'Choose a session (*)'};

  function buildWidget() {
    var target = document.querySelector('.rsform-block-format-cours');
    if (!target) { setTimeout(buildWidget, 200); return; }
    // Masquer le bloc Format_cours (soumis en caché)
    target.style.display = 'none';
    var tarifBlock = document.getElementById('afk-tarif-display');
    if (tarifBlock) tarifBlock.style.display = 'none'; // masquer aussi l'ancien bloc tarif
    // Insérer après le bloc format-cours complet (pas dans formControls)
    var tarifBlock = document.getElementById('afk-tarif-display');
    // S'assurer que tarifBlock est sibling de target (même parent)
    var insertAfter = (tarifBlock && tarifBlock.parentNode === target.parentNode)
        ? tarifBlock : target;

    var wrap = document.createElement('div');
    wrap.className = 'rsform-block rsform-type-freetext afk-session-block';
    wrap.style.cssText = 'margin-bottom:12px';

    var lbl = document.createElement('label');
    lbl.className = 'formControlLabel';
    lbl.setAttribute('for','afk-session-sel');
    lbl.textContent = _lbls[_lang] || _lbls.fr;
    wrap.appendChild(lbl);

    var sel = document.createElement('select');
    sel.id = 'afk-session-sel';
    sel.className = 'rsform-select-box';
    sel.style.cssText = 'width:100%;display:block;box-sizing:border-box';
    var opt0 = document.createElement('option');
    opt0.value=''; opt0.textContent='—';
    sel.appendChild(opt0);
    _sessions.forEach(function(s){
      var o = document.createElement('option');
      o.value = JSON.stringify(s);
      o.textContent = s.label;
      sel.appendChild(o);
    });

    // Sauvegarder tarif dans sessionStorage à la soumission
    var _afkSelData = null;
    document.addEventListener('submit', function(e){
      if (!_afkSelData) return;
      try { sessionStorage.setItem('afk_cours_data', JSON.stringify({tarif: _afkSelData.tarif})); } catch(ex){}
    }, true);

    // Bloc info type+tarif
    var infoDiv = document.createElement('div');
    infoDiv.id = 'afk-info-cours';
    infoDiv.style.cssText = 'display:none;background:#fff5f6;border:1px solid #f0cdd2;border-radius:6px;padding:10px 14px;margin-top:8px;font-size:0.92em;line-height:1.8';
    wrap.appendChild(infoDiv);

    sel.addEventListener('change', function(){
      if (!this.value) { infoDiv.style.display='none'; return; }
      var d = JSON.parse(this.value);
      _afkSelData = d;
      // Afficher type + tarif
      var lbl_type = _lang==='zh'?'课程类型：':(_lang==='en'?'Type: ':'Type : ');
      var lbl_tarif = _lang==='zh'?'费用：':(_lang==='en'?'Price: ':'Tarif : ');
      infoDiv.innerHTML = '<strong>'+lbl_type+'</strong>'+d.format+'<br><strong>'+lbl_tarif+'</strong>'+d.tarif;
      infoDiv.style.display = '';
      // Session (caché)
      var sf = document.querySelector("input[name='form[Session][]']");
      if (sf) sf.value = d.date;
      // Format_cours (caché)
      var ff = document.querySelector("select[name='form[Format_cours][]']");
      if (ff) {
        for (var i=0;i<ff.options.length;i++){
          if (ff.options[i].value===d.format){ff.selectedIndex=i;break;}
        }
      }
      // Professeur
      var pf = document.querySelector("select[name='form[Professeur][]'],input[name='form[Professeur][]']");
      if (pf && d.teacher) {
        if (pf.tagName==='SELECT') {
          var tl = d.teacher.toLowerCase().trim();
          for(var i=0;i<pf.options.length;i++){
            if(pf.options[i].value.toLowerCase()===tl||pf.options[i].text.toLowerCase()===tl){
              pf.selectedIndex=i; break;
            }
          }
        } else pf.value = d.teacher;
      }
    });

    wrap.appendChild(sel);
    insertAfter.parentNode.insertBefore(wrap, insertAfter.nextSibling);
  }

  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',buildWidget);
  else buildWidget();

  // Auto-sélection depuis URL (arrivée depuis le calendrier)
  function autoSelectFromUrl() {
    var sel = document.getElementById('afk-session-sel');
    if (!sel || sel.options.length <= 1) { setTimeout(autoSelectFromUrl, 300); return; }
    var sf = document.querySelector("input[name='form[Session][]']");
    var preDate = (sf ? sf.value : '').trim();
    if (!preDate) {
      var m = location.search.match(/form%5BSession%5D%5B%5D=([^&]+)/i);
      if (m) preDate = decodeURIComponent(m[1].replace(/\+/g,' '));
    }
    if (!preDate) return;
    for (var i = 1; i < sel.options.length; i++) {
      try { var d = JSON.parse(sel.options[i].value); if (d.date === preDate) { sel.selectedIndex=i; sel.dispatchEvent(new Event('change')); break; } } catch(e){}
    }
  }
  setTimeout(autoSelectFromUrl, 500);
})();
</script>
<?php endif; ?>
<?php if ($_afkHasSidebar): ?>
</div><!-- /col-lg-9 -->
<div class="col-lg-3 col-md-12 rse-sidebar-col">
<?php foreach ($_afkSidebarModules as $_afkMod): ?>
<?php
    $_afkTitle = $_afkFalangTitles[(int)$_afkMod->id] ?? $this->escape($_afkMod->title);
    $_afkModClass = (strpos($_afkMod->module, 'mod_menu') !== false)
        ? 'afk-sidebar-module afk-sidebar-menu'
        : 'afk-sidebar-module afk-sidebar-card';
?>
<div class="<?php echo $_afkModClass; ?>">
    <div class="afk-sidebar-title"><?php echo htmlspecialchars($_afkTitle); ?></div>
    <div class="afk-sidebar-content">
        <?php echo ModuleHelper::renderModule($_afkMod, ['style' => 'none']); ?>
    </div>
</div>
<?php endforeach; ?>
</div><!-- /rse-sidebar-col -->
</div><!-- /row rse-with-sidebar -->
<?php endif; ?>