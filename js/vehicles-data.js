/*!
 * Grupo Braslectra — fleet data
 *
 * Single source of truth consumed by every Frota page and by gallery.js.
 * Specs (model, year, class, capacity, banheiro) are transcribed verbatim
 * from the approved copy document.
 *
 * Image provenance: all 24 vehicles have real photos. 13 were recovered
 * from a 2023-09..12 site backup; the other 11 (2024-2026 uploads) were
 * pulled directly from the live site (braslectra.com.br), matched against
 * the WordPress DB's vehicle-gallery templates. Hilux SW4 SRX has only a
 * single card photo (no gallery existed for it, unlike every other vehicle).
 */
window.BRASLECTRA_VEHICLES = {

  /* ---------------- Carros de passeio ---------------- */
  'carro-virtus-tsi-2022': {
    name: 'Virtus TSI', year: '2022/2023', category: 'carros', subcategory: 'passeio',
    mainImage: 'assets/images/fleet/cars/carro-virtus-tsi-2022-01.jpg',
    gallery: [1,2,3,4,5].map(n => ({ src: `assets/images/fleet/cars/carro-virtus-tsi-2022-0${n}.jpg`, alt: 'Virtus TSI 2022/2023' }))
  },
  'carro-virtus-tsi-2025': {
    name: 'Virtus TSI', year: '2025/2026', category: 'carros', subcategory: 'passeio',
    mainImage: 'assets/images/fleet/cars/carro-virtus-tsi-2025-01.jpg',
    gallery: [1,2,3,4,5,6,7].map(n => ({ src: `assets/images/fleet/cars/carro-virtus-tsi-2025-0${n}.jpg`, alt: 'Virtus TSI 2025/2026' }))
  },
  'carro-corolla-xei-2023-passeio': {
    name: 'Corolla XEI', year: '2023/2023', category: 'carros', subcategory: 'passeio',
    mainImage: 'assets/images/fleet/cars/carro-corolla-xei-2023-passeio-01.jpg',
    gallery: [1,2,3,4,5].map(n => ({ src: `assets/images/fleet/cars/carro-corolla-xei-2023-passeio-0${n}.jpg`, alt: 'Corolla XEI 2023/2023' }))
  },
  'carro-corolla-xei-2024-passeio': {
    name: 'Corolla XEI', year: '2024/2024', category: 'carros', subcategory: 'passeio',
    mainImage: 'assets/images/fleet/cars/carro-corolla-xei-2024-passeio-01.jpg',
    gallery: [1,2,3,4,5,6,7].map(n => ({ src: `assets/images/fleet/cars/carro-corolla-xei-2024-passeio-0${n}.jpg`, alt: 'Corolla XEI 2024/2024' }))
  },

  /* ---------------- Carros de carga ---------------- */
  'carro-hilux-cd-4x4-2020': {
    name: 'Hilux CD 4X4', year: '2020/2021', category: 'carros', subcategory: 'carga',
    mainImage: 'assets/images/fleet/cars/carro-hilux-cd-4x4-2020-01.jpg',
    gallery: [1,2,3,4,5,6,7].map(n => ({ src: `assets/images/fleet/cars/carro-hilux-cd-4x4-2020-0${n}.jpg`, alt: 'Hilux CD 4X4 2020/2021' }))
  },

  /* ---------------- Carros blindados ---------------- */
  'carro-tiguan-r-line-2019': {
    name: 'Tiguan R-Line', year: '2019/2020', category: 'carros', subcategory: 'blindados',
    mainImage: 'assets/images/fleet/cars/carro-tiguan-r-line-2019-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/cars/carro-tiguan-r-line-2019-0${n}.jpg`, alt: 'Tiguan R-Line 2019/2020' }))
  },
  'carro-hilux-sw4-srx-2023': {
    name: 'Hilux SW4 SRX', year: '2023/2024', category: 'carros', subcategory: 'blindados',
    mainImage: 'assets/images/fleet/cars/carro-hilux-sw4-srx-2023-01.png',
    gallery: [{ src: 'assets/images/fleet/cars/carro-hilux-sw4-srx-2023-01.png', alt: 'Hilux SW4 SRX 2023/2024' }],
    photoStatus: 'single-photo-only-no-db-gallery'
  },
  'carro-corolla-xei-2023-blindado': {
    name: 'Corolla XEI', year: '2023/2024', category: 'carros', subcategory: 'blindados',
    mainImage: 'assets/images/fleet/cars/carro-corolla-xei-2023-blindado-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/cars/carro-corolla-xei-2023-blindado-0${n}.jpg`, alt: 'Corolla XEI 2023/2024 (blindado)' }))
  },
  'carro-corolla-xei-2024-blindado': {
    name: 'Corolla XEI', year: '2024/2025', category: 'carros', subcategory: 'blindados',
    mainImage: 'assets/images/fleet/cars/carro-corolla-xei-2024-blindado-01.jpg',
    gallery: [
      { src: 'assets/images/fleet/cars/carro-corolla-xei-2024-blindado-01.jpg', alt: 'Corolla XEI 2024/2025 (blindado)' },
      { src: 'assets/images/fleet/cars/carro-corolla-xei-2024-blindado-02.jpg', alt: 'Corolla XEI 2024/2025 (blindado)' },
      { src: 'assets/images/fleet/cars/carro-corolla-xei-2024-blindado-03.jpeg', alt: 'Corolla XEI 2024/2025 (blindado)' },
      { src: 'assets/images/fleet/cars/carro-corolla-xei-2024-blindado-04.jpeg', alt: 'Corolla XEI 2024/2025 (blindado)' },
      { src: 'assets/images/fleet/cars/carro-corolla-xei-2024-blindado-05.jpeg', alt: 'Corolla XEI 2024/2025 (blindado)' }
    ]
  },

  /* ---------------- Vans ---------------- */
  'van-416-sprinter-2021': {
    name: '416 Sprinter', year: '2021/2022', category: 'vans', classe: 'Executiva', lotacao: 15,
    mainImage: 'assets/images/fleet/vans/van-416-sprinter-2021-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/vans/van-416-sprinter-2021-0${n}.jpg`, alt: '416 Sprinter 2021/2022' }))
  },
  'van-master-minibus-2023': {
    name: 'Master Minibus', year: '2023/2024', category: 'vans', classe: 'Executiva', lotacao: 15,
    mainImage: 'assets/images/fleet/vans/van-master-minibus-2023-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/vans/van-master-minibus-2023-0${n}.jpg`, alt: 'Master Minibus 2023/2024' }))
  },
  'van-transit-460-2022': {
    name: 'Transit 460', year: '2022/2023', category: 'vans', classe: 'Executiva', lotacao: 17,
    mainImage: 'assets/images/fleet/vans/van-transit-460-2022-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/vans/van-transit-460-2022-0${n}.jpg`, alt: 'Transit 460 2022/2023' }))
  },
  'van-transit-460-2025': {
    name: 'Transit 460', year: '2025/2026', category: 'vans', classe: 'Executiva', lotacao: 17,
    mainImage: 'assets/images/fleet/vans/van-transit-460-2025-01.jpg',
    gallery: [1,2,3,4,5,6,7].map(n => ({ src: `assets/images/fleet/vans/van-transit-460-2025-0${n}.jpg`, alt: 'Transit 460 2025/2026' }))
  },

  /* ---------------- Micro-ônibus ---------------- */
  'micro-volare-fly-10-2023': {
    name: 'Volare Fly 10', year: '2023/2024', category: 'micro-onibus', classe: 'Rodoviário', lotacao: 30, banheiro: true,
    mainImage: 'assets/images/fleet/minibuses/micro-volare-fly-10-2023-01.jpg',
    gallery: [1,2,3,4,5,6,7].map(n => ({ src: `assets/images/fleet/minibuses/micro-volare-fly-10-2023-0${n}.jpg`, alt: 'Volare Fly 10 2023/2024' }))
  },
  'micro-volare-fly-9-2024': {
    name: 'Volare Fly 9', year: '2024/2025', category: 'micro-onibus', classe: 'Rodoviário', lotacao: 30, banheiro: false,
    mainImage: 'assets/images/fleet/minibuses/micro-volare-fly-9-2024-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/minibuses/micro-volare-fly-9-2024-0${n}.jpg`, alt: 'Volare Fly 9 2024/2025' }))
  },
  'micro-marcopolo-senior-2021': {
    name: 'Marcopolo Senior', year: '2021/2022', category: 'micro-onibus', classe: 'Rodoviário', lotacao: 26, banheiro: true,
    mainImage: 'assets/images/fleet/minibuses/micro-marcopolo-senior-2021-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/minibuses/micro-marcopolo-senior-2021-0${n}.jpg`, alt: 'Marcopolo Senior 2021/2022' }))
  },
  'micro-volare-w9-2022': {
    name: 'Volare W9', year: '2022/2023', category: 'micro-onibus', classe: 'Rodoviário', lotacao: 30, banheiro: false,
    mainImage: 'assets/images/fleet/minibuses/micro-volare-w9-2022-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/minibuses/micro-volare-w9-2022-0${n}.jpg`, alt: 'Volare W9 2022/2023' }))
  },

  /* ---------------- Ônibus ---------------- */
  'onibus-mascarello-elo-2024': {
    name: 'Mascarello Elo', year: '2024/2025', category: 'onibus', classe: 'Executiva', lotacao: 46,
    mainImage: 'assets/images/fleet/buses/onibus-mascarello-elo-2024-01.jpg',
    gallery: [1,2,3,4,5,6,7,8].map(n => ({ src: `assets/images/fleet/buses/onibus-mascarello-elo-2024-0${n}.jpg`, alt: 'Mascarello Elo 2024/2025' }))
      .concat([{ src: 'assets/images/fleet/buses/onibus-mascarello-elo-2024-09.webp', alt: 'Mascarello Elo 2024/2025 — banheiro' }])
  },
  'onibus-marcopolo-ideale-2024': {
    name: 'Marcopolo Ideale', year: '2024/2025', category: 'onibus', classe: 'Executiva', lotacao: 45,
    mainImage: 'assets/images/fleet/buses/onibus-marcopolo-ideale-2024-01.jpg',
    gallery: [1,2,3,4,5].map(n => ({ src: `assets/images/fleet/buses/onibus-marcopolo-ideale-2024-0${n}.jpg`, alt: 'Marcopolo Ideale 2024/2025' }))
  },
  'onibus-busscar-el-340-2022': {
    name: 'Busscar EL 340', year: '2022/2023', category: 'onibus', classe: 'Executiva', lotacao: 46,
    mainImage: 'assets/images/fleet/buses/onibus-busscar-el-340-2022-01.jpeg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/buses/onibus-busscar-el-340-2022-0${n}.jpeg`, alt: 'Busscar EL 340 2022/2023' }))
  },
  'onibus-marcopolo-viaggio-r-2022': {
    name: 'Marcopolo Viaggio R', year: '2022/2023', category: 'onibus', classe: 'Executiva', lotacao: 46,
    mainImage: 'assets/images/fleet/buses/onibus-marcopolo-viaggio-r-2022-01.jpeg',
    gallery: [1,2,3,4,5].map(n => ({ src: `assets/images/fleet/buses/onibus-marcopolo-viaggio-r-2022-0${n}.jpeg`, alt: 'Marcopolo Viaggio R 2022/2023' }))
  },
  'onibus-marcopolo-ideale-r-2021': {
    name: 'Marcopolo Ideale R', year: '2021/2022', category: 'onibus', classe: 'Executiva', lotacao: 45,
    mainImage: 'assets/images/fleet/buses/onibus-marcopolo-ideale-r-2021-01.jpeg',
    gallery: [1,2,3,4,5].map(n => ({ src: `assets/images/fleet/buses/onibus-marcopolo-ideale-r-2021-0${n}.jpeg`, alt: 'Marcopolo Ideale R 2021/2022' }))
  },
  'onibus-comil-campione-r-2014': {
    name: 'Comil Campione R', year: '2014/2014', category: 'onibus', classe: 'Executiva', lotacao: 46,
    mainImage: 'assets/images/fleet/buses/onibus-comil-campione-r-2014-01.jpg',
    gallery: [1,2,3,4,5,6].map(n => ({ src: `assets/images/fleet/buses/onibus-comil-campione-r-2014-0${n}.jpg`, alt: 'Comil Campione R 2014/2014' }))
  },
  'onibus-irizar-i6-370-2013': {
    name: 'Irizar I6 370', year: '2013/2014', category: 'onibus', classe: 'Executiva', lotacao: 46,
    mainImage: 'assets/images/fleet/buses/onibus-irizar-i6-370-2013-01.jpeg',
    gallery: [1,2,3,4,5].map(n => ({ src: `assets/images/fleet/buses/onibus-irizar-i6-370-2013-0${n}.jpeg`, alt: 'Irizar I6 370 2013/2014' }))
  }
};
