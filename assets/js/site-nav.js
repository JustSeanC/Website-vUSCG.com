/*
  vUSCG shared navigation
  Keeps the same nav links across all pages in one place.
*/
(function () {
  var navMarkup = [
    '<ul>',
    '  <li class="submenu"><a href="#">About</a>',
    '    <ul>',
    '      <li><a href="about.html">About Us</a></li>',
    '      <li><a href="staff.html">Staff</a></li>',
    '      <li><a href="partners.html">Partners</a></li>',
    '      <li class="submenu"><a href="#">Policy</a>',
    '        <ul>',
    '          <li><a href="sop.html">General SOP</a></li>',
    '          <li><a href="https://docs.google.com/document/d/1dULzrl4F9T-Hg8KkAth8GAA5x-krxLjPcFlS4YDioRM/edit?usp=sharing">Privacy Policy</a></li>',
    '          <li><a href="training.html">Training</a></li>',
    '        </ul>',
    '      </li>',
    '    </ul>',
    '  </li>',
    '  <li class="submenu"><a href="#">Operations</a>',
    '    <ul>',
    '      <li><a href="fleet.html">Fleet</a></li>',
    '      <li><a href="districts.html">Districts</a></li>',
    '      <li><a href="2025.html">2025 Stats</a></li>',
    '    </ul>',
    '  </li>',
    '  <li><a href="https://crew.vuscg.com/pilots">Roster</a></li>',
    '  <li><a href="https://crew.vuscg.com/register" class="button primary">Join Us</a></li>',
    '</ul>'
  ].join('');

  function injectNav() {
    var navNodes = document.querySelectorAll('nav#nav');
    for (var i = 0; i < navNodes.length; i++) {
      navNodes[i].innerHTML = navMarkup;
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', injectNav);
  } else {
    injectNav();
  }
})();
