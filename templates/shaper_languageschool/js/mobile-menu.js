/**
 * AFK Mobile Menu Fix
 * Assure l'ouverture du menu offcanvas sur mobile
 * indépendamment du handler jQuery du template.
 */
(function () {
  'use strict';

  function initMobileMenu() {
    var toggler = document.getElementById('offcanvas-toggler');
    if (!toggler) return;

    toggler.addEventListener('click', function (e) {
      e.preventDefault();
      document.body.classList.add('offcanvas-active');
    });

    // Fermeture via bouton X et overlay
    document.querySelectorAll('.close-offcanvas, .offcanvas-overlay').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        document.body.classList.remove('offcanvas-active');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMobileMenu);
  } else {
    initMobileMenu();
  }
})();
