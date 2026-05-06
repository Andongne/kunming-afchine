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
    return colWrap.querySelector('.addon-root-image') &&
           colWrap.querySelector('.addon-root-text-block, .addon-root-custom-html') &&
           colWrap.querySelector('.addon-root-button');
  }

  function reorganizeCards() {
    if (window.innerWidth > 767) return;
    if (document.getElementById('afk-cards-grid')) return;

    // Trouver toutes les colonnes-cards sur la page d'accueil
    var pb = document.querySelector('#sp-page-builder.page-173');
    if (!pb) return;

    var allColWraps = pb.querySelectorAll('[id^="column-wrap-id-"]');
    var cardCols = [];
    allColWraps.forEach(function (cw) {
      if (isCard(cw)) cardCols.push(cw);
    });
    if (cardCols.length < 2) return;

    // Créer la grille 2 colonnes
    var grid = document.createElement('div');
    grid.id = 'afk-cards-grid';
    grid.style.cssText = 'display:flex;flex-wrap:wrap;padding:3px 10px;background:transparent;';

    // Trouver la première section parente des cards et insérer avant elle
    var firstSection = cardCols[0].closest('[id^="section-id-"]');
    if (!firstSection) return;

    // Déplacer toutes les cards dans la grille
    var sectionsToHide = new Set();
    cardCols.forEach(function (col) {
      var sec = col.closest('[id^="section-id-"]');
      if (sec) sectionsToHide.add(sec);
      grid.appendChild(col);
    });

    // Insérer la grille avant la première section cards
    firstSection.parentNode.insertBefore(grid, firstSection);

    // Masquer les sections devenues vides + neutraliser min-height
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
