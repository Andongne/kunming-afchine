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
