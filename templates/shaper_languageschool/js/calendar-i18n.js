/* ════════════════════════════════════════════════════════════════
   RSEvents Pro Calendar — i18n + noms d'événements stylisés
   ════════════════════════════════════════════════════════════════ */
(function() {
  var monthMap = {
    en: {'01':'January','02':'February','03':'March','04':'April','05':'May','06':'June',
         '07':'July','08':'August','09':'September','10':'October','11':'November','12':'December'},
    zh: {'01':'一月','02':'二月','03':'三月','04':'四月','05':'五月','06':'六月',
         '07':'七月','08':'八月','09':'九月','10':'十月','11':'十一月','12':'十二月'}
  };

  function applyI18n() {
    if (!document.querySelector('.rsepro-calendar')) return;
    var raw  = (document.documentElement.lang || navigator.language || 'fr').toLowerCase();
    var lang = raw.startsWith('zh') ? 'zh' : (raw.startsWith('en') ? 'en' : 'fr');

    // Mois dropdown (EN/ZH seulement)
    var mMap = monthMap[lang] || null;
    if (mMap) {
      var monthSel = document.querySelector('select[name="month"]');
      if (monthSel) Array.from(monthSel.options).forEach(function(opt) {
        if (mMap[opt.value]) opt.text = mMap[opt.value];
      });
    }

    // Fix dates FR→EN dans les tooltips (translatedate() retourne toujours fr-FR côté serveur)
    if (lang === 'en') {
      var frToEn = {
        'janvier':'January','février':'February','mars':'March','avril':'April',
        'mai':'May','juin':'June','juillet':'July','août':'August',
        'septembre':'September','octobre':'October','novembre':'November','décembre':'December'
      };
      document.querySelectorAll('[data-bs-content],[data-content]').forEach(function(el) {
        var attr = el.hasAttribute('data-bs-content') ? 'data-bs-content' : 'data-content';
        var val = el.getAttribute(attr);
        if (!val) return;
        var updated = val;
        Object.keys(frToEn).forEach(function(fr) {
          updated = updated.replace(new RegExp(fr, 'gi'), frToEn[fr]);
        });
        if (updated !== val) el.setAttribute(attr, updated);
      });
    }

    // Italique sur la partie (groupe) dans les noms d'événements
    document.querySelectorAll('.rsepro-calendar .event-name, .rsepro-calendar .rse_event_link').forEach(function(el) {
      if (!el.querySelector('em') && (el.textContent||'').indexOf('(') > -1) {
        el.innerHTML = el.innerHTML.replace(/\(([^)]+)\)/g,
          '<em style="font-style:italic;opacity:0.8">($1)</em>');
      }
    });
  }

  function init() {
    applyI18n();
    var cal = document.querySelector('.rsepro-calendar');
    if (cal) new MutationObserver(function() { setTimeout(applyI18n, 50); })
              .observe(cal, {childList:true, subtree:true});
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
