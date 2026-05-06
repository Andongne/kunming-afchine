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
 * Détection automatique des cards par structure (image + texte + bouton)
 * Aucun ID codé en dur — résistant aux réorganisations SP Builder
 */
(function () {

  /**
   * Détecte si un column-wrap est une "card" (contient image + texte + bouton)
   */
  function isCard(colWrap) {
    // Les classes addon-root-* sont sur les sppb-addon-wrapper enfants
    return colWrap.querySelector('.sppb-addon-wrapper.addon-root-image') &&
           colWrap.querySelector('.sppb-addon-wrapper.addon-root-text-block, .sppb-addon-wrapper.addon-root-custom-html') &&
           colWrap.querySelector('.sppb-addon-wrapper.addon-root-button, .sppb-btn');
  }

  function reorganizeCards() {
    if (window.innerWidth > 767) return;
    if (document.getElementById('afk-cards-grid')) return;

    var pb = document.querySelector('#sp-page-builder.page-173');
    if (!pb) return;

    // Borner la détection entre les marqueurs hp-cards-row-1 et hp-cards-row-3
    // Ces classes sont posées manuellement dans SP Builder — stables aux réorganisations
    var marker1 = pb.querySelector('.hp-cards-row-1');
    var marker3 = pb.querySelector('.hp-cards-row-3');
    if (!marker1 || !marker3) return;

    var sec1 = marker1.closest('[id^="section-id-"]'); // section du marqueur début
    var sec3 = marker3.closest('[id^="section-id-"]'); // section du marqueur fin
    if (!sec1 || !sec3) return;

    // Collecter toutes les sections entre sec1 (exclu) et sec3 (exclu)
    var allSections = Array.from(pb.querySelectorAll('[id^="section-id-"]'));
    var idx1 = allSections.indexOf(sec1);
    var idx3 = allSections.indexOf(sec3);
    if (idx1 < 0 || idx3 <= idx1) return;

    var cardSections = allSections.slice(idx1 + 1, idx3);
    if (!cardSections.length) return;

    // Extraire les colonnes-cards dans ces sections uniquement
    var cardCols = [];
    cardSections.forEach(function (sec) {
      sec.querySelectorAll('[id^="column-wrap-id-"]').forEach(function (cw) {
        if (isCard(cw)) cardCols.push(cw);
      });
    });
    if (cardCols.length < 2) return;

    // Créer la grille 2 colonnes
    var grid = document.createElement('div');
    grid.id = 'afk-cards-grid';
    grid.style.cssText = 'display:flex;flex-wrap:wrap;padding:3px 10px;background:transparent;';

    var firstCardSection = cardSections[0];
    cardCols.forEach(function (col) { grid.appendChild(col); });

    // Insérer avant la première section cards, masquer les sections vides
    firstCardSection.parentNode.insertBefore(grid, firstCardSection);
    cardSections.forEach(function (sec) {
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
