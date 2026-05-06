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
 * Regroupe les 6 cards homepage en un seul conteneur 2-colonnes sur mobile
 * (les 6 colonnes sont dans 2 sections séparées de 3 colonnes chacune)
 */
(function () {
  var COL_IDS = [
    'column-wrap-id-f6532a75-d1c9-4b87-9c4b-fbc037ebcad9',
    'column-wrap-id-75c55a92-5c35-469f-817a-ec46505cc0c6',
    'column-wrap-id-eb353f01-c701-4c12-8691-dda8e365a3a2',
    'column-wrap-id-adae435c-32b6-4d67-8445-0bcc108e3408',
    'column-wrap-id-0cc04406-1e6e-4c36-9599-a0e920772385',
    'column-wrap-id-41c59e95-316d-47ef-a016-aaa82d14b301',
    'column-wrap-id-06214299-0bd0-4f18-bb67-66d79b30f265'
  ];
  var SEC1 = 'section-id-90427b43-0b92-4538-964b-79192deb738d';
  var SEC2 = 'section-id-681188c7-eb59-4b2f-8e7b-766b19bcda47';

  function reorganizeCards() {
    if (window.innerWidth > 767) return;
    if (document.getElementById('afk-cards-grid')) return; // déjà fait

    var cols = COL_IDS.map(function (id) { return document.getElementById(id); });
    if (cols.some(function (c) { return !c; })) return; // pas encore chargé

    var sec1 = document.getElementById(SEC1);
    var sec2 = document.getElementById(SEC2);
    if (!sec1 || !sec2) return;

    // Créer un conteneur flex 2-colonnes
    var grid = document.createElement('div');
    grid.id = 'afk-cards-grid';
    grid.style.cssText = 'display:flex;flex-wrap:wrap;padding:3px 10px;background:transparent;';

    // Déplacer les 6 colonnes dans ce conteneur
    cols.forEach(function (col) {
      grid.appendChild(col);
    });

    // Insérer avant sec1 et masquer les 2 sections vides
    sec1.parentNode.insertBefore(grid, sec1);
    sec1.style.display = 'none';
    sec2.style.display = 'none';
  }

  ['DOMContentLoaded', 'load'].forEach(function (evt) {
    window.addEventListener(evt, reorganizeCards);
  });
  [100, 300, 600, 1000].forEach(function (ms) {
    setTimeout(reorganizeCards, ms);
  });
})();

/**
 * Neutralise le min-height:580px de SP Builder sur la section cards row 3
 */
(function () {
  var TARGET_IDS = [
    'section-id-90427b43-0b92-4538-964b-79192deb738d',
    'section-id-681188c7-eb59-4b2f-8e7b-766b19bcda47'
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
