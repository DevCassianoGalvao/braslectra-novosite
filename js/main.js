/*!
 * Grupo Braslectra — shared site behavior
 * Header scroll state, mobile menu, scroll reveals (GSAP + reduced-motion
 * fallback), animated counters. Loaded on every page after GSAP/ScrollTrigger.
 */
(function () {
  'use strict';

  var REDUCED_MOTION = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function initHeader() {
    var header = document.querySelector('.site-header');
    if (!header) return;
    var onScroll = function () {
      var scrolled = window.scrollY > 40;
      header.classList.toggle('is-scrolled', scrolled);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  function initMobileMenu() {
    var toggle = document.querySelector('[data-mobile-menu-toggle]');
    var menu = document.querySelector('[data-mobile-menu]');
    var closeBtn = document.querySelector('[data-mobile-menu-close]');
    if (!toggle || !menu) return;

    function open() {
      menu.classList.add('is-open');
      document.body.style.overflow = 'hidden';
    }
    function close() {
      menu.classList.remove('is-open');
      document.body.style.overflow = '';
    }
    toggle.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    menu.querySelectorAll('a').forEach(function (a) {
      a.addEventListener('click', close);
    });
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 900) close();
    });
  }

  function initReveals() {
    // IMPORTANT: content must be visible by default (CSS never pre-hides
    // [data-reveal-item] — see style.css). GSAP's own .from() call sets the
    // starting opacity only on the exact elements it successfully animates,
    // so a partial CDN/plugin failure here can never leave real page copy
    // permanently invisible — worst case, animations just don't run.
    var groups = document.querySelectorAll('[data-reveal-group]');
    if (!groups.length) return;
    if (REDUCED_MOTION || !window.gsap || !window.ScrollTrigger) return;

    try {
      gsap.registerPlugin(ScrollTrigger);
    } catch (e) {
      console.warn('GSAP/ScrollTrigger failed to initialize, skipping reveal animations', e);
      return;
    }

    groups.forEach(function (group) {
      try {
        var items = group.querySelectorAll('[data-reveal-item]');
        var targets = items.length ? Array.prototype.slice.call(items) : [group];
        var alreadyVisible = group.getBoundingClientRect().top < window.innerHeight;
        var stagger = parseFloat(group.getAttribute('data-reveal-stagger')) || 0.08;

        if (alreadyVisible) {
          gsap.from(targets, { opacity: 0, y: 24, duration: 0.7, ease: 'power2.out', stagger: stagger });
        } else {
          gsap.from(targets, {
            opacity: 0, y: 24, duration: 0.7, ease: 'power2.out', stagger: stagger,
            scrollTrigger: { trigger: group, start: 'top 85%' }
          });
        }
      } catch (e) {
        console.warn('Reveal animation failed for a section, leaving it visible', e);
      }
    });

    try { ScrollTrigger.refresh(); } catch (e) { /* non-fatal */ }
  }

  function animateCounter(el) {
    var target = parseInt(el.getAttribute('data-counter-target'), 10) || 0;
    var prefix = el.getAttribute('data-counter-prefix') || '';
    if (REDUCED_MOTION) {
      el.textContent = prefix + target;
      return;
    }
    var duration = 1400;
    var start = performance.now();
    function tick(now) {
      var p = Math.min(1, (now - start) / duration);
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = prefix + Math.round(target * eased);
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  function initCounters() {
    var counters = document.querySelectorAll('[data-counter-target]');
    if (!counters.length) return;
    if (!('IntersectionObserver' in window)) {
      counters.forEach(animateCounter);
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) { io.observe(el); });
  }

  function initChoiceGroups() {
    // Generic single-select chip/button groups: [data-choice-group] wraps
    // buttons with [data-choice-value]; clicking toggles .is-selected and
    // updates a hidden input if [data-choice-input] is present.
    document.querySelectorAll('[data-choice-group]').forEach(function (group) {
      var multi = group.hasAttribute('data-choice-multi');
      var input = group.querySelector('[data-choice-input]');
      group.querySelectorAll('[data-choice-value]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          if (multi) {
            btn.classList.toggle('is-selected');
          } else {
            group.querySelectorAll('[data-choice-value]').forEach(function (b) {
              b.classList.remove('is-selected');
            });
            btn.classList.add('is-selected');
          }
          if (input) {
            var selected = Array.prototype.slice.call(group.querySelectorAll('.is-selected'))
              .map(function (b) { return b.getAttribute('data-choice-value'); });
            input.value = selected.join(',');
          }
          if (typeof group.onChoiceChange === 'function') group.onChoiceChange(btn);
        });
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initHeader();
    initMobileMenu();
    initReveals();
    initCounters();
    initChoiceGroups();
  });
})();
