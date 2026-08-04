# Shared markup every page must reuse verbatim

No server-side includes (plain static HTML, no build step) — so header, mobile
menu and footer are duplicated per page. Copy these blocks exactly, only
changing the nav `is-active` class and the relative asset paths if the page
lives in a subfolder (none currently do — all pages are flat in `site/`).

Load order at the bottom of `<body>`, before the page's own inline `<script>`
(if any):
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="js/main.js"></script>
<script src="js/gallery.js"></script>   <!-- only on pages that render vehicle cards / "VER FOTOS" -->
<script src="js/vehicles-data.js"></script> <!-- only on pages that render vehicle cards / "VER FOTOS", load BEFORE gallery.js -->
```

`<head>` boilerplate every page needs:
```html
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
```

## Header + mobile menu

```html
<header class="site-header" data-header>
  <div class="site-header__bar">
    <a href="index.html" class="site-header__logo" aria-label="Grupo Braslectra">
      <img src="assets/images/brand/logo-grupo-braslectra.webp" alt="Grupo Braslectra" height="38">
    </a>
    <nav class="site-nav" aria-label="Menu principal">
      <a href="index.html">Home</a>
      <a href="sobre.html">Sobre</a>
      <a href="frota.html">Frota</a>
      <a href="servicos.html">Serviços</a>
      <a href="contato.html">Contato</a>
      <a href="treinamentos.html">Treinamentos</a>
      <a href="blog.html">Blog</a>
    </nav>
    <div class="site-header__actions">
      <a href="index.html#orcamento" class="btn btn--gold btn--gold-sm">Orçamento</a>
      <button class="btn-icon mobile-menu-toggle" data-mobile-menu-toggle aria-label="Abrir menu">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#1E1B18" stroke-width="1.8" stroke-linecap="round"><line x1="4" y1="7" x2="20" y2="7"></line><line x1="4" y1="12" x2="20" y2="12"></line><line x1="4" y1="17" x2="20" y2="17"></line></svg>
      </button>
    </div>
  </div>
</header>

<div class="mobile-menu" data-mobile-menu>
  <div class="mobile-menu__top">
    <img src="assets/images/brand/logo-grupo-braslectra.webp" alt="Grupo Braslectra" height="34">
    <button class="btn-icon" data-mobile-menu-close aria-label="Fechar menu">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1E1B18" stroke-width="1.8" stroke-linecap="round"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line></svg>
    </button>
  </div>
  <nav>
    <a href="index.html">Home</a>
    <a href="sobre.html">Sobre</a>
    <a href="frota.html">Frota</a>
    <a href="servicos.html">Serviços</a>
    <a href="contato.html">Contato</a>
    <a href="treinamentos.html">Treinamentos</a>
    <a href="blog.html">Blog</a>
  </nav>
  <a href="index.html#orcamento" class="btn btn--gold">Solicitar orçamento</a>
</div>
```
Add `class="is-active"` to the `<a>` matching the current page in BOTH the
desktop `.site-nav` and the `.mobile-menu nav`. Frota category pages
(frota-carros.html etc.) should mark "Frota" active.

## Footer

```html
<footer class="site-footer">
  <div class="container">
    <div class="site-footer__grid">
      <div class="site-footer__brand">
        <img src="assets/images/brand/logo-grupo-braslectra.webp" alt="Grupo Braslectra" style="height:34px">
        <p>Transporte executivo de pessoas com segurança, conforto e pontualidade há mais de 25 anos.</p>
        <div class="site-footer__social">
          <a href="#" aria-label="Instagram"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"></circle></svg></a>
          <a href="#" aria-label="Facebook"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 8.5h2.5V5.4c-.44-.06-1.94-.19-3.14-.19-3.1 0-4.36 1.87-4.36 4.6V12H6.5v3.4H9V22h3.6v-6.6h2.9l.45-3.4h-3.35V9.9c0-1 .3-1.4 1.4-1.4z"></path></svg></a>
          <a href="#" aria-label="LinkedIn"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="4"></rect><line x1="7.5" y1="10" x2="7.5" y2="17"></line><circle cx="7.5" cy="7" r="0.4" fill="currentColor"></circle><path d="M12 17v-4.2c0-1.5 1-2.6 2.4-2.6 1.4 0 2.1 1 2.1 2.6V17"></path></svg></a>
        </div>
      </div>
      <div>
        <h4>Contato</h4>
        <div class="site-footer__col">
          <a href="mailto:orcamento@braslectra.com.br"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2.5"></rect><path d="M4 6.5l8 6 8-6"></path></svg>orcamento@braslectra.com.br</a>
          <a href="tel:+552297586858"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 3.5c1 0 2.4 2.6 2.4 3.4 0 .8-1.6 1.5-1.6 2.3 0 1.6 3.5 5.1 5.1 5.1.8 0 1.5-1.6 2.3-1.6.8 0 3.4 1.4 3.4 2.4 0 1.5-1.4 2.9-2.8 2.9-4 0-11.1-7.1-11.1-11.1 0-1.4 1.4-2.8 2.9-2.8z"></path></svg>(22) 9.9758-6858</a>
          <a href="tel:+552227732800"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6.5 3.5c1 0 2.4 2.6 2.4 3.4 0 .8-1.6 1.5-1.6 2.3 0 1.6 3.5 5.1 5.1 5.1.8 0 1.5-1.6 2.3-1.6.8 0 3.4 1.4 3.4 2.4 0 1.5-1.4 2.9-2.8 2.9-4 0-11.1-7.1-11.1-11.1 0-1.4 1.4-2.8 2.9-2.8z"></path></svg>(22) 2773-2800</a>
        </div>
      </div>
      <div>
        <h4>Mapa do Site</h4>
        <div class="site-footer__col">
          <a href="index.html">Home</a>
          <a href="sobre.html">Sobre o Grupo</a>
          <a href="frota.html">Frota</a>
          <a href="servicos.html">Serviços</a>
          <a href="sustentabilidade.html">Sustentabilidade</a>
          <a href="contato.html">Contato</a>
          <a href="#">Política de Privacidade</a>
        </div>
      </div>
      <div>
        <h4>Links Úteis</h4>
        <div class="site-footer__col">
          <a href="treinamentos.html">Nossos Cursos</a>
          <a href="trabalhe-conosco.html">Trabalhe Conosco</a>
          <a href="sustentabilidade.html">Sustentabilidade</a>
        </div>
      </div>
    </div>
    <div class="site-footer__bottom">
      <span>© 2026 Grupo Braslectra. Todos os direitos reservados.</span>
      <span>Diretriz · Braslectra · Eco Polo Brasil · B.A.J Transportes</span>
    </div>
  </div>
</footer>
```

## Page filename map (dc.html source → static output)

| Cloud Design source | Static file |
|---|---|
| Braslectra Home.dc.html | index.html |
| Sobre.dc.html | sobre.html |
| Frota.dc.html | frota.html |
| Frota-Carros.dc.html | frota-carros.html |
| Frota-Vans.dc.html | frota-vans.html |
| Frota-Micro-Onibus.dc.html | frota-micro-onibus.html |
| Frota-Onibus.dc.html | frota-onibus.html |
| Servicos.dc.html | servicos.html |
| Contato.dc.html | contato.html |
| Trabalhe-Conosco.dc.html | trabalhe-conosco.html |
| Treinamentos.dc.html | treinamentos.html |
| Blog.dc.html | blog.html |
| Blog-Post.dc.html | blog-post.html |
| Sustentabilidade.dc.html | sustentabilidade.html |

All internal links between pages must use these static filenames, not the
`.dc.html` ones from the source.

## Reveal animations

Wrap a section's inner content wrapper with `data-reveal-group` and put
`data-reveal-item` on each direct child that should fade/slide in. `main.js`
handles the GSAP wiring (and no-JS / reduced-motion fallback) automatically —
do not write per-page GSAP code.

## Counters

`<div class="counter" data-counter-target="28" data-counter-prefix="+">0</div>`
— `main.js` animates it in view automatically via IntersectionObserver.

## Vehicle cards / galleries (Frota-* pages only)

```html
<div class="vehicle-card" data-reveal-item>
  <div class="vehicle-card__media"><img src="{{vehicle.mainImage}}" alt="{{vehicle.name}}" loading="lazy"></div>
  <div class="vehicle-card__body">
    <div class="card__title">{{vehicle.name}}</div>
    <div class="card__meta">{{vehicle.year}}</div>
    <div class="card__chips"> ... chip spans for classe/lotação/banheiro ... </div>
    <button class="btn--link-gold" data-gallery-trigger="{{vehicle-slug}}">VER FOTOS
      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="12" x2="19" y2="12"></line><path d="M13 6l6 6-6 6"></path></svg>
    </button>
  </div>
</div>
```
`{{vehicle-slug}}` MUST match a key in `js/vehicles-data.js` exactly — that's
the only thing that scopes a gallery to its own vehicle. Do not build a
lightbox by hand; `gallery.js` renders it once per page and reads from
`window.BRASLECTRA_VEHICLES`.
