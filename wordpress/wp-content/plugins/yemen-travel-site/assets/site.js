(function(){
  'use strict';
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.yemen-travel-site').forEach(function(site){
      var toggle = site.querySelector('.yt-nav-toggle');
      var nav = site.querySelector('.yt-nav');
      if (!toggle || !nav) return;
      toggle.addEventListener('click', function(){
        var isOpen = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!isOpen));
        nav.classList.toggle('is-open', !isOpen);
      });
      nav.querySelectorAll('a').forEach(function(link){
        link.addEventListener('click', function(){
          toggle.setAttribute('aria-expanded', 'false');
          nav.classList.remove('is-open');
        });
      });
    });
  });
}());

