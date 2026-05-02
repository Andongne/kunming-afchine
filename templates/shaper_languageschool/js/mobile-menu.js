/**
 * AFK Mobile Menu Fix
 * Assure l'ouverture du menu offcanvas et les sous-menus sur mobile.
 */
(function () {
  'use strict';

  function initMobileMenu() {
    // 1. Ouverture du menu
    var toggler = document.getElementById('offcanvas-toggler');
    if (toggler) {
      toggler.addEventListener('click', function (e) {
        e.preventDefault();
        document.body.classList.add('offcanvas-active');
      });
    }

    // 2. Fermeture via bouton X et overlay
    document.querySelectorAll('.close-offcanvas, .offcanvas-overlay').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        document.body.classList.remove('offcanvas-active');
      });
    });

    // 3. Sous-menus via les boutons +
    // Délégation sur .offcanvas-inner pour capturer les clics sur .menu-toggler
    var offcanvasInner = document.querySelector('.offcanvas-inner');
    if (offcanvasInner) {
      offcanvasInner.addEventListener('click', function (e) {
        var btn = e.target.closest('.menu-toggler');
        if (!btn) return;
        e.preventDefault();
        var parent = btn.closest('.menu-parent');
        if (!parent) return;
        var child = parent.querySelector('.menu-child');
        if (!child) return;
        // Toggle
        var isOpen = parent.classList.contains('menu-parent-open');
        parent.classList.toggle('menu-parent-open', !isOpen);
        child.style.display = isOpen ? 'none' : 'block';
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileMenu);
  } else {
    initMobileMenu();
  }
})();

/**
 * Neutralise le min-height:580px de SP Builder sur la section cards row 3
 */
(function () {
  var TARGET_IDS = [
    'section-id-113b9392-0de7-4ad3-acfd-d47455b7e771',
    'section-id-681188c7-eb59-4b2f-8e7b-766b19bcda47',
    'section-id-73084a51-9be6-4f44-83ed-415ecddc194e'
  ];

  function applyFix() {
    if (window.innerWidth > 767) return;
    var pb = document.querySelector('#sp-page-builder.page-173');
    if (!pb) return;
    TARGET_IDS.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) {
        el.style.cssText += ';min-height:0!important;height:auto!important;';
      }
    });
  }

  /* Lance au chargement et après un délai pour contrer SP Builder JS */
  ['DOMContentLoaded', 'load'].forEach(function (evt) {
    window.addEventListener(evt, applyFix);
  });
  [100, 300, 600, 1000, 2000].forEach(function (ms) {
    setTimeout(applyFix, ms);
  });
})();
