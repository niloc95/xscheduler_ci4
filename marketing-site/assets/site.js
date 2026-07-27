/* WebScheduler marketing site — theme toggle + mobile nav.
   Uses the SAME localStorage key ('xs-theme') as the app so the theme is
   consistent when a visitor moves between the marketing site and the product. */
(function () {
  'use strict';

  function applyTheme(theme) {
    var isDark = theme === 'dark';
    document.documentElement.classList.toggle('dark', isDark);
    document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';
    try { localStorage.setItem('xs-theme', theme); } catch (e) { /* ignore */ }
  }

  function currentTheme() {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Theme toggle(s)
    document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
      });
    });

    // Mobile menu
    var menuBtn = document.querySelector('[data-menu-toggle]');
    var menu = document.querySelector('[data-mobile-menu]');
    if (menuBtn && menu) {
      menuBtn.addEventListener('click', function () {
        menu.classList.toggle('hidden');
      });
      menu.querySelectorAll('a').forEach(function (a) {
        a.addEventListener('click', function () { menu.classList.add('hidden'); });
      });
    }

    // Current-year stamp
    document.querySelectorAll('[data-year]').forEach(function (el) {
      el.textContent = String(new Date().getFullYear());
    });
  });
})();
