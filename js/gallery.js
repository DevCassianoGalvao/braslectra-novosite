/*!
 * Grupo Braslectra — vehicle photo gallery / lightbox
 *
 * Real, working replacement for the "Ver Fotos" placeholder in the Cloud
 * Design prototype (which only showed a single static image-slot). Each
 * vehicle card carries data-gallery-trigger="<vehicle-slug>"; the slug must
 * match a key in window.BRASLECTRA_VEHICLES (see vehicles-data.js). Every
 * vehicle's gallery is scoped strictly to its own slug — there is no shared
 * index or path-based lookup, so two vehicles can never bleed into each
 * other's photos.
 */
(function () {
  'use strict';

  var lightbox, dialog, stage, counterEl, titleEl, thumbsEl, prevBtn, nextBtn, closeBtn;
  var currentImages = [];
  var currentIndex = 0;
  var lastFocusedTrigger = null;
  var touchStartX = null;

  function buildDOM() {
    lightbox = document.createElement('div');
    lightbox.className = 'lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', 'Galeria de fotos do veículo');
    lightbox.innerHTML =
      '<div class="lightbox__dialog" tabindex="-1">' +
        '<div class="lightbox__stage">' +
          '<button type="button" class="lightbox__nav lightbox__nav--prev" aria-label="Foto anterior">' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"></path></svg>' +
          '</button>' +
          '<button type="button" class="lightbox__nav lightbox__nav--next" aria-label="Próxima foto">' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"></path></svg>' +
          '</button>' +
          '<div class="lightbox__counter" data-lightbox-counter></div>' +
        '</div>' +
        '<div class="lightbox__thumbs" data-lightbox-thumbs></div>' +
        '<div class="lightbox__footer">' +
          '<div class="lightbox__title" data-lightbox-title></div>' +
          '<button type="button" class="btn-icon" aria-label="Fechar galeria" data-lightbox-close>' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1E1B18" stroke-width="1.8" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line></svg>' +
          '</button>' +
        '</div>' +
      '</div>';
    document.body.appendChild(lightbox);

    dialog = lightbox.querySelector('.lightbox__dialog');
    stage = lightbox.querySelector('.lightbox__stage');
    counterEl = lightbox.querySelector('[data-lightbox-counter]');
    titleEl = lightbox.querySelector('[data-lightbox-title]');
    thumbsEl = lightbox.querySelector('[data-lightbox-thumbs]');
    prevBtn = lightbox.querySelector('.lightbox__nav--prev');
    nextBtn = lightbox.querySelector('.lightbox__nav--next');
    closeBtn = lightbox.querySelector('[data-lightbox-close]');

    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox) close();
    });
    closeBtn.addEventListener('click', close);
    prevBtn.addEventListener('click', function () { go(currentIndex - 1); });
    nextBtn.addEventListener('click', function () { go(currentIndex + 1); });

    stage.addEventListener('touchstart', function (e) {
      touchStartX = e.touches[0].clientX;
    }, { passive: true });
    stage.addEventListener('touchend', function (e) {
      if (touchStartX === null) return;
      var dx = e.changedTouches[0].clientX - touchStartX;
      if (Math.abs(dx) > 40) go(currentIndex + (dx < 0 ? 1 : -1));
      touchStartX = null;
    }, { passive: true });

    document.addEventListener('keydown', function (e) {
      if (!lightbox.classList.contains('is-open')) return;
      if (e.key === 'Escape') close();
      else if (e.key === 'ArrowLeft') go(currentIndex - 1);
      else if (e.key === 'ArrowRight') go(currentIndex + 1);
      else if (e.key === 'Tab') trapFocus(e);
    });
  }

  function trapFocus(e) {
    var focusable = dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault(); last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault(); first.focus();
    }
  }

  function render() {
    stage.querySelectorAll('img').forEach(function (img) { img.remove(); });
    currentImages.forEach(function (photo, i) {
      var img = document.createElement('img');
      img.src = photo.src;
      img.alt = photo.alt || '';
      img.loading = i === 0 ? 'eager' : 'lazy';
      if (i === currentIndex) img.classList.add('is-active');
      stage.insertBefore(img, stage.firstChild);
    });

    thumbsEl.innerHTML = '';
    if (currentImages.length > 1) {
      currentImages.forEach(function (photo, i) {
        var t = document.createElement('img');
        t.src = photo.src;
        t.alt = '';
        t.loading = 'lazy';
        if (i === currentIndex) t.classList.add('is-active');
        t.addEventListener('click', function () { go(i); });
        thumbsEl.appendChild(t);
      });
    }

    counterEl.textContent = currentImages.length > 1 ? (currentIndex + 1) + ' / ' + currentImages.length : '';
    counterEl.style.display = currentImages.length > 1 ? '' : 'none';
    prevBtn.style.display = currentImages.length > 1 ? '' : 'none';
    nextBtn.style.display = currentImages.length > 1 ? '' : 'none';
    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex === currentImages.length - 1;
  }

  function go(index) {
    if (index < 0 || index >= currentImages.length) return;
    currentIndex = index;
    var imgs = stage.querySelectorAll('img');
    imgs.forEach(function (img, i) { img.classList.toggle('is-active', i === currentIndex); });
    var thumbs = thumbsEl.querySelectorAll('img');
    thumbs.forEach(function (t, i) { t.classList.toggle('is-active', i === currentIndex); });
    counterEl.textContent = currentIndex + 1 + ' / ' + currentImages.length;
    prevBtn.disabled = currentIndex === 0;
    nextBtn.disabled = currentIndex === currentImages.length - 1;
  }

  function open(slug, triggerEl) {
    var data = window.BRASLECTRA_VEHICLES && window.BRASLECTRA_VEHICLES[slug];
    if (!data) {
      console.warn('[gallery] Unknown vehicle slug:', slug);
      return;
    }
    currentImages = (data.gallery && data.gallery.length ? data.gallery : [{ src: data.mainImage, alt: data.name }]);
    currentIndex = 0;
    titleEl.textContent = data.name + (data.year ? ' ' + data.year : '');
    lastFocusedTrigger = triggerEl || document.activeElement;

    render();
    lightbox.classList.add('is-open');
    document.body.classList.add('lightbox-open');
    dialog.focus();
  }

  function close() {
    lightbox.classList.remove('is-open');
    document.body.classList.remove('lightbox-open');
    if (lastFocusedTrigger && typeof lastFocusedTrigger.focus === 'function') {
      lastFocusedTrigger.focus();
    }
  }

  function initTriggers() {
    document.querySelectorAll('[data-gallery-trigger]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        open(btn.getAttribute('data-gallery-trigger'), btn);
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    buildDOM();
    initTriggers();
  });

  window.BraslectraGallery = { open: open, close: close };
})();
