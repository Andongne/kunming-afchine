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
 * Cards mobiles homepage — class CSS à poser dans SP Builder sur chaque colonne-card :
 *   afk-mobile-card
 * Aucun UUID codé en dur, résistant à toute réorganisation.
 */
(function () {

  function reorganizeCards() {
    if (window.innerWidth > 767) return;
    if (document.getElementById('afk-cards-grid')) return;

    // Sélectionner uniquement les colonnes marquées afk-mobile-card
    var cardCols = Array.from(document.querySelectorAll('.afk-mobile-card'));
    if (cardCols.length < 2) return;

    // Créer la grille flex 2 colonnes
    var grid = document.createElement('div');
    grid.id = 'afk-cards-grid';
    grid.style.cssText = 'display:flex;flex-wrap:wrap;padding:3px 10px;background:transparent;';

    // Insérer avant la première card et déplacer toutes les cards dedans
    var firstCol = cardCols[0];
    var firstSection = firstCol.closest('[id^="section-id-"]');
    if (firstSection) firstSection.parentNode.insertBefore(grid, firstSection);
    else firstCol.parentNode.insertBefore(grid, firstCol);

    var sectionsToHide = new Set();
    cardCols.forEach(function (col) {
      var sec = col.closest('[id^="section-id-"]');
      if (sec) sectionsToHide.add(sec);
      grid.appendChild(col);
    });

    sectionsToHide.forEach(function (sec) {
      sec.style.cssText += ';display:none!important;min-height:0!important;';
    });
  }

  ['DOMContentLoaded', 'load'].forEach(function (evt) {
    window.addEventListener(evt, reorganizeCards);
  });
  [100, 300, 600, 1000].forEach(function (ms) {
    setTimeout(reorganizeCards, ms);
  });
})();

(function () {
  /* Stub vide — neutralisation min-height désormais gérée dans reorganizeCards */
  function applyFix() {
    if (window.innerWidth > 767) return;
  }
  ['DOMContentLoaded', 'load'].forEach(function (evt) {
    window.addEventListener(evt, applyFix);
  });
  [100, 300, 600, 1000, 2000].forEach(function (ms) {
    setTimeout(applyFix, ms);
  });
})();
