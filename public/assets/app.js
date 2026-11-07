(function () {
  var root = document.documentElement;
  var toggle = document.getElementById('theme-toggle');

  function showTheme() {
    var dark = root.getAttribute('data-theme') === 'dark';
    toggle.textContent = dark ? 'Light mode' : 'Dark mode';
    toggle.setAttribute('aria-pressed', String(dark));
  }

  if (toggle) {
    showTheme();
    toggle.addEventListener('click', function () {
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('theme', next); } catch (e) {}
      showTheme();
    });
  }

  var menu = document.getElementById('menu-toggle');
  var nav = document.getElementById('site-nav');
  if (menu && nav) {
    menu.addEventListener('click', function () {
      var open = nav.classList.toggle('open');
      menu.setAttribute('aria-expanded', String(open));
    });
  }

  document.querySelectorAll('[data-confirm]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      if (!window.confirm(button.getAttribute('data-confirm'))) {
        event.preventDefault();
      }
    });
  });
})();
