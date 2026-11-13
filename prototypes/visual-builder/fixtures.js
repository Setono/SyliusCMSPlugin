/* PROTOTYPE (throwaway): fixture data standing in for the server and for what developers define in code.
   Terms follow v2's GLOSSARY.md on the 2.x branch. Nothing in here is a spec. */

const LOCALES = [
  { code: 'en_US', name: 'English', short: 'EN' },
  { code: 'da_DK', name: 'Dansk', short: 'DA' },
  { code: 'de_DE', name: 'Deutsch', short: 'DE' },
];
const BASE_LOCALE = 'en_US'; // Sylius's fallback locale; translations are made from it
const FALLBACK_CHAIN = { en_US: [], da_DK: ['en_US'], de_DE: ['en_US'] };

const CHANNELS = [
  { code: 'FASHION_WEB', name: 'Fashion Web Store', currency: 'USD' },
  { code: 'NORDIC', name: 'Nordic Store', currency: 'DKK' },
];

const TAXONS = {
  women: { name: { en_US: 'Women', da_DK: 'Kvinder', de_DE: 'Damen' } },
  women_tshirts: { parent: 'women', name: { en_US: 'T-shirts', da_DK: 'T-shirts', de_DE: 'T-Shirts' } },
  women_dresses: { parent: 'women', name: { en_US: 'Dresses', da_DK: 'Kjoler', de_DE: 'Kleider' } },
  women_jeans: { parent: 'women', name: { en_US: 'Jeans', da_DK: 'Jeans', de_DE: 'Jeans' } },
  women_shoes: { parent: 'women', name: { en_US: 'Shoes', da_DK: 'Sko', de_DE: 'Schuhe' } },
  men: { name: { en_US: 'Men', da_DK: 'Mænd', de_DE: 'Herren' } },
  men_tshirts: { parent: 'men', name: { en_US: 'T-shirts', da_DK: 'T-shirts', de_DE: 'T-Shirts' } },
  men_shirts: { parent: 'men', name: { en_US: 'Shirts', da_DK: 'Skjorter', de_DE: 'Hemden' } },
  men_shoes: { parent: 'men', name: { en_US: 'Shoes', da_DK: 'Sko', de_DE: 'Schuhe' } },
  accessories: { name: { en_US: 'Accessories', da_DK: 'Tilbehør', de_DE: 'Accessoires' } },
  bags: { parent: 'accessories', name: { en_US: 'Bags', da_DK: 'Tasker', de_DE: 'Taschen' } },
  summer: { name: { en_US: 'Summer', da_DK: 'Sommer', de_DE: 'Sommer' } },
};

function P(code, en, da, de, usd, taxons, hue, material, sold, assoc) {
  return {
    code, price: usd, taxons, hue, material, sold, assoc: assoc || {},
    name: { en_US: en, da_DK: da, de_DE: de },
    short: { en_US: `${material} ${en.toLowerCase()}, made to be worn a lot.` },
  };
}
const PRODUCTS = [
  P('EVERYDAY_TEE', 'Everyday Tee', 'Hverdags-T-shirt', 'Alltags-T-Shirt', 29, ['women_tshirts', 'summer'], 200, 'Organic cotton', 940, { similar: ['BOXY_TEE', 'STRIPED_TEE'], accessories: ['CANVAS_TOTE', 'SUN_CAP', 'STRAW_HAT'] }),
  P('BOXY_TEE', 'Boxy Tee', 'Boxy T-shirt', 'Boxy-T-Shirt', 35, ['women_tshirts'], 170, 'Organic cotton', 610, { similar: ['EVERYDAY_TEE'] }),
  P('STRIPED_TEE', 'Breton Striped Tee', 'Stribet T-shirt', 'Gestreiftes T-Shirt', 39, ['women_tshirts', 'summer'], 220, 'Cotton', 480, {}),
  P('LINEN_DRESS', 'Linen Summer Dress', 'Sommerkjole i hør', 'Leinen-Sommerkleid', 89, ['women_dresses', 'summer'], 30, 'Linen', 520, { similar: ['WRAP_DRESS'], accessories: ['STRAW_HAT', 'CANVAS_TOTE'] }),
  P('WRAP_DRESS', 'Wrap Dress', 'Slå-om-kjole', 'Wickelkleid', 95, ['women_dresses'], 340, 'Viscose', 300, {}),
  P('MOM_JEANS', 'Mom Jeans', 'Mom jeans', 'Mom-Jeans', 79, ['women_jeans'], 215, 'Denim', 700, {}),
  P('CANVAS_SNEAKER', 'Canvas Sneaker', 'Lærredssneaker', 'Canvas-Sneaker', 65, ['women_shoes', 'summer'], 0, 'Canvas', 820, {}),
  P('OXFORD_SHIRT', 'Oxford Shirt', 'Oxfordskjorte', 'Oxford-Hemd', 69, ['men_shirts'], 210, 'Cotton', 410, {}),
  P('LINEN_SHIRT', 'Linen Shirt', 'Hørskjorte', 'Leinenhemd', 75, ['men_shirts', 'summer'], 45, 'Linen', 560, {}),
  P('BASIC_TEE_M', 'Basic Tee', 'Basis-T-shirt', 'Basic-T-Shirt', 25, ['men_tshirts', 'summer'], 190, 'Organic cotton', 990, { similar: ['EVERYDAY_TEE'], accessories: ['SUN_CAP'] }),
  P('SUN_CAP', 'Sun Cap', 'Solkasket', 'Sonnenkappe', 22, ['accessories', 'summer'], 50, 'Cotton twill', 350, {}),
  P('STRAW_HAT', 'Straw Hat', 'Stråhat', 'Strohhut', 34, ['accessories', 'summer'], 45, 'Straw', 260, {}),
  P('CANVAS_TOTE', 'Canvas Tote', 'Mulepose', 'Canvas-Tasche', 29, ['bags', 'summer'], 90, 'Canvas', 640, {}),
  P('LEATHER_BOOT', 'Leather Boot', 'Læderstøvle', 'Lederstiefel', 189, ['men_shoes'], 20, 'Leather', 150, {}),
  P('GIFT_CARD', 'Gift card', 'Gavekort', 'Geschenkkarte', 50, [], 280, 'Paper', 100, {}),
];

const MEDIA = [
  { uuid: 'm-beach', name: 'summer-beach.jpg', label: 'Beach', hue: 35 },
  { uuid: 'm-linen', name: 'linen-rail.jpg', label: 'Linen rail', hue: 45 },
  { uuid: 'm-dress', name: 'dress-field.jpg', label: 'Dress', hue: 330 },
  { uuid: 'm-ship-en', name: 'free-shipping-en.png', label: 'Free shipping', hue: 190 },
  { uuid: 'm-ship-da', name: 'fri-fragt-da.png', label: 'Fri fragt', hue: 190 },
  { uuid: 'm-nordic', name: 'nordic-club.jpg', label: 'Nordic club', hue: 260 },
  { uuid: 'm-returns-de', name: 'ruecksendung-de.png', label: 'Gratis Rückversand', hue: 120 },
  { uuid: 'm-sizechart', name: 'size-chart.png', label: 'Size chart', hue: 0 },
];

/* Host-page context objects, and the bindable properties declared in code for each (ADR 0006). */
const CONTEXT_OBJECTS = { product: 'Product', taxon: 'Taxon', entry: 'Entry', channel: 'Channel' };
const BINDABLE = [
  { path: 'product.name', obj: 'product', label: 'Name', type: 'text' },
  { path: 'product.code', obj: 'product', label: 'Code', type: 'text' },
  { path: 'product.shortDescription', obj: 'product', label: 'Short description', type: 'text' },
  { path: 'product.mainTaxon', obj: 'product', label: 'Main taxon', type: 'text' },
  { path: 'product.material', obj: 'product', label: 'Material', type: 'text', note: 'Product attribute, declared by the agency' },
  { path: 'product.price', obj: 'product', label: 'Price', type: 'text' },
  { path: 'channel.name', obj: 'channel', label: 'Name', type: 'text' },
];

/* Slots declared in code (ADR 0004). */
const SLOTS = {
  'layout.top': { label: 'Every page: top', provides: [] },
  'product.under_price': { label: 'Product page: under the price', provides: ['product'] },
  'product.below_associations': { label: 'Product page: below associations', provides: ['product'] },
};

/* Sources defined in code. */
const SOURCES = [
  { id: 'manual', label: 'Hand-picked' },
  { id: 'taxon', label: 'Products in a taxon' },
  { id: 'bestsellers', label: 'Bestsellers' },
  { id: 'associations', label: "Current product's associations", needs: ['product'] },
];
const SORTS = { bestselling: 'Bestselling first', newest: 'Newest first', price_asc: 'Price, low to high', name: 'Name, A to Z' };
const ASSOC_TYPES = { similar: 'Similar products', accessories: 'Accessories' };

/* Block types, defined in code. `children` = block types it may contain; `needs` = host-page context. */
function F(name, label, kind, o = {}) { return { name, label, kind, ...o }; }
const GENERAL = ['hero', 'heading', 'richText', 'image', 'columns', 'productCarousel', 'bannerCarousel', 'blockCarousel', 'uspBar', 'snippetRef', 'productFacts'];
const BT = {
  hero: { label: 'Hero', icon: 'image outline', desc: 'Large image with a heading, text and a button.', fields: [
    F('heading', 'Heading', 'text', { tr: true, bindable: true }),
    F('text', 'Text', 'richtext', { tr: true }),
    F('image', 'Image', 'media'),
    F('ctaLabel', 'Button label', 'text', { tr: true }),
    F('ctaTarget', 'Button link', 'target'),
    F('align', 'Text position', 'choice', { options: ['Left', 'Center'] }),
  ] },
  heading: { label: 'Heading', icon: 'heading', desc: 'A heading on its own.', fields: [
    F('text', 'Text', 'text', { tr: true, bindable: true }),
    F('level', 'Level', 'choice', { options: ['H2', 'H3'] }),
  ] },
  richText: { label: 'Rich text', icon: 'align left', desc: 'Formatted text with links and bindings.', fields: [
    F('body', 'Text', 'richtext', { tr: true }),
  ] },
  image: { label: 'Image', icon: 'file image outline', desc: 'One image, optionally linked.', fields: [
    F('image', 'Image', 'media'),
    F('alt', 'Alt text', 'text', { tr: true }),
    F('link', 'Link', 'target'),
  ] },
  columns: { label: 'Columns', icon: 'columns', desc: 'Two to four columns side by side.', children: ['column'], min: 2, max: 4, fields: [
    F('gap', 'Gap', 'choice', { options: ['Small', 'Large'] }),
  ] },
  column: { label: 'Column', icon: 'square outline', desc: '', inner: true, children: GENERAL.filter(t => t !== 'columns'), fields: [
    F('width', 'Width', 'choice', { options: ['Auto', '1/3', '1/2', '2/3'] }),
  ] },
  productCarousel: { label: 'Product carousel', icon: 'shopping bag', desc: 'A row of products, hand-picked or filled by a source.', fields: [
    F('heading', 'Heading', 'text', { tr: true, bindable: true }),
    F('products', 'Products', 'products'),
  ] },
  bannerCarousel: { label: 'Banner carousel', icon: 'images outline', desc: 'Slides with an image, a heading and a link.', children: ['slide'], min: 1, fields: [
    F('autoplay', 'Autoplay', 'toggle'),
  ] },
  slide: { label: 'Slide', icon: 'image', desc: '', inner: true, fields: [
    F('image', 'Image', 'media', { tr: true, note: 'Translatable for this block type, because text is baked into the image.' }),
    F('heading', 'Heading', 'text', { tr: true }),
    F('ctaTarget', 'Link', 'target'),
  ] },
  blockCarousel: { label: 'Carousel of blocks', icon: 'film', desc: 'Any blocks as slides.', children: GENERAL.filter(t => !['bannerCarousel', 'blockCarousel', 'columns'].includes(t)), fields: [
    F('perView', 'Slides per view', 'choice', { options: ['2', '1', '3'] }),
  ] },
  uspBar: { label: 'USP bar', icon: 'check circle outline', desc: 'A row of short selling points.', children: ['usp'], max: 4, fields: [] },
  usp: { label: 'USP', icon: 'check', desc: '', inner: true, fields: [
    F('icon', 'Icon', 'choice', { options: ['truck', 'undo', 'leaf', 'lock'] }),
    F('text', 'Text', 'text', { tr: true }),
  ] },
  snippetRef: { label: 'Snippet', icon: 'clone outline', desc: 'Show a snippet. It stays in sync everywhere it is used.', fields: [
    F('snippet', 'Snippet', 'snippet'),
  ] },
  productFacts: { label: 'Product facts', icon: 'list alternate outline', desc: 'Facts about the current product.', needs: ['product'], fields: [
    F('title', 'Title', 'text', { tr: true, bindable: true }),
    F('showCare', 'Show care instructions', 'toggle'),
  ] },
  menuLink: { label: 'Link', icon: 'linkify', desc: 'A menu link.', fields: [
    F('label', 'Label', 'text', { tr: true, note: "Leave empty to use the target's own name." }),
    F('target', 'Target', 'target'),
    F('newTab', 'Open in a new tab', 'toggle'),
  ] },
  menuGroup: { label: 'Group', icon: 'folder outline', desc: 'A group of links. A top-level group opens a mega menu.', children: ['menuLink', 'menuGroup', 'menuPromo'], fields: [
    F('label', 'Label', 'text', { tr: true }),
    F('target', 'Link (optional)', 'target'),
  ] },
  menuPromo: { label: 'Promo', icon: 'bullhorn', desc: 'An image teaser inside a mega menu.', fields: [
    F('image', 'Image', 'media'),
    F('heading', 'Heading', 'text', { tr: true }),
    F('target', 'Link', 'target'),
  ] },
};
const ROOT_TYPES = { page: GENERAL, snippet: GENERAL, menu: ['menuLink', 'menuGroup'] };
const ENTRY_FIELDS = {
  page: [F('title', 'Title', 'text', { tr: true }), F('slug', 'Slug', 'text', { tr: true }), F('layout', 'Layout', 'choice', { options: ['Default', 'Full width', 'With sidebar'] })],
  snippet: [],
  menu: [],
};

/* ---------- builders for fixture content ---------- */

function fp(s) { let h = 7; for (const c of String(s ?? '')) h = (h * 31 + c.charCodeAt(0)) | 0; return (h >>> 0).toString(16).slice(0, 6); }
let _kseq = 0;
function newKey() { _kseq++; return 'b' + _kseq.toString(36).padStart(3, '0') + Math.random().toString(36).slice(2, 4); }

/* A translatable value: per-locale values, plus where each translation came from (ADR 0007).
   `stale` = { da_DK: 'old English text' } makes that translation outdated. */
function T(en, da, de, stale = {}) {
  const v = { translations: {}, translatedFrom: {} };
  if (en != null) v.translations.en_US = en;
  [['da_DK', da], ['de_DE', de]].forEach(([loc, val]) => {
    if (val == null) return;
    v.translations[loc] = val;
    v.translatedFrom[loc] = { locale: 'en_US', fingerprint: fp(stale[loc] ?? en) };
  });
  return v;
}
function defaultFor(f) {
  switch (f.kind) {
    case 'text': case 'richtext': return f.tr ? T() : '';
    case 'media': return f.tr ? T() : null;
    case 'choice': return f.options[0];
    case 'toggle': return false;
    case 'products': return { source: 'manual', config: { items: [] } };
    default: return null;
  }
}
function B(type, fields = {}, children, conditions) {
  const b = { key: newKey(), type, version: 1, fields };
  for (const f of BT[type].fields) if (!(f.name in fields)) fields[f.name] = defaultFor(f);
  if (conditions) b.conditions = conditions;
  if (BT[type].children) b.children = children || [];
  return b;
}

function makeEntries() {
  const ALL_CH = CHANNELS.map(c => c.code);
  const page = (uuid, name, translations) => ({ uuid, contentType: 'page', code: uuid.slice(2), name, channels: ALL_CH, enabled: true, translations, fields: { title: T(name), slug: T(uuid.slice(2)), layout: 'Default' }, tree: [] });

  const summer = {
    uuid: 'e-summer', contentType: 'page', code: 'summer-sale', name: 'Summer sale', channels: ALL_CH, enabled: true,
    translations: ['en_US', 'da_DK', 'de_DE'],
    fields: { title: T('Summer sale', 'Sommerudsalg', 'Sommerschlussverkauf'), slug: T('summer-sale', 'sommerudsalg', 'sommerschlussverkauf'), layout: 'Default' },
    tree: [
      B('hero', {
        heading: T('Summer sale', 'Sommerudsalg', 'Sommerschlussverkauf'),
        text: T('<p>Up to 50% off summer favourites. Ends Sunday.</p>', '<p>Op til 40% på sommerfavoritter. Slutter søndag.</p>', null, { da_DK: '<p>Up to 40% off summer favourites. Ends Sunday.</p>' }),
        image: 'm-beach', ctaLabel: T('Shop the sale', 'Shop udsalget', 'Jetzt shoppen'), ctaTarget: { type: 'taxon', ref: 'summer' }, align: 'Left',
      }),
      B('snippetRef', { snippet: 'e-usp' }),
      B('productCarousel', { heading: T('Summer favourites', 'Sommerfavoritter', null), products: { source: 'taxon', config: { taxon: 'summer', children: true, sort: 'bestselling', limit: 8 } } }),
      B('columns', { gap: 'Large' }, [
        B('column', { width: '1/2' }, [B('image', { image: 'm-linen', alt: T('Linen shirts on a clothes rail', 'Hørskjorter på et stativ', null), link: { type: 'taxon', ref: 'men_shirts' } })]),
        B('column', { width: '1/2' }, [
          B('heading', { text: T('Made for warm days', 'Lavet til varme dage', 'Gemacht für warme Tage'), level: 'H2' }),
          B('richText', { body: T('<p>Breathable <b>linen</b> and organic cotton, cut loose so the air can move. Pair them with a straw hat.</p>', '<p>Åndbar <b>hør</b> og økologisk bomuld, skåret løst så luften kan bevæge sig. Kombinér med en stråhat.</p>', null) }),
        ]),
      ]),
      B('bannerCarousel', { autoplay: true }, [
        B('slide', { image: T('m-ship-en', 'm-ship-da', null), heading: T('Free shipping over $75', 'Fri fragt over 500 kr.', 'Kostenloser Versand ab 75 $'), ctaTarget: { type: 'page', ref: 'e-about' } }),
        B('slide', { image: T('m-nordic', null, null), heading: T('Club members get 10% extra', 'Klubmedlemmer får 10% ekstra', null), ctaTarget: null }, undefined, [{ kind: 'channel', values: ['NORDIC'] }]),
        B('slide', { image: T('m-returns-de', null, 'm-returns-de'), heading: T('Free returns in Germany', null, 'Kostenloser Rückversand in Deutschland'), ctaTarget: null }, undefined, [{ kind: 'locale', values: ['de_DE'] }]),
      ]),
      B('richText', { body: T('<p><small>Discounts apply to selected items while stock lasts.</small></p>', '<p><small>Rabatter gælder udvalgte varer, så længe lager haves.</small></p>', null) }),
    ],
  };

  const usp = {
    uuid: 'e-usp', contentType: 'snippet', code: 'usp-bar', name: 'USP bar', context: null, channels: ALL_CH, enabled: true,
    translations: ['en_US', 'da_DK', 'de_DE'], fields: {},
    placements: [{ id: 'p-usp-top', slot: 'layout.top', scope: [], position: 1 }],
    usedIn: 3,
    tree: [B('uspBar', {}, [
      B('usp', { icon: 'truck', text: T('Free shipping over $75', 'Fri fragt over 500 kr.', 'Kostenloser Versand ab 75 $') }),
      B('usp', { icon: 'undo', text: T('30-day free returns', '30 dages gratis retur', '30 Tage kostenlose Rückgabe') }),
      B('usp', { icon: 'leaf', text: T('Organic cotton', 'Økologisk bomuld', null) }),
    ])],
  };

  const sizefit = {
    uuid: 'e-sizefit', contentType: 'snippet', code: 'size-fit', name: 'Size & fit', context: 'product', channels: ALL_CH, enabled: true,
    translations: ['en_US', 'da_DK'], fields: {},
    placements: [
      { id: 'p-sf-1', slot: 'product.under_price', scope: [{ kind: 'productInTaxon', values: ['women_tshirts', 'men_tshirts'], children: true }], position: 1 },
      { id: 'p-sf-2', slot: 'product.below_associations', scope: [{ kind: 'productIs', values: ['LINEN_DRESS'] }], position: 1 },
    ],
    usedIn: 2,
    tree: [
      B('heading', { text: T('Size & fit', 'Størrelse og pasform', null), level: 'H3' }),
      B('richText', { body: T(
        '<p>Our <span data-bind="product.name">Product · Name</span> is made from <span data-bind="product.material">Product · Material</span> and fits true to size. Between sizes? Go one down for a closer fit.</p>',
        '<p>Vores <span data-bind="product.name">Product · Name</span> er lavet af <span data-bind="product.material">Product · Material</span> og passer normalt i størrelsen.</p>', null) }),
      B('productFacts', { title: { binding: 'product.name', fallback: 'Product facts' }, showCare: true }),
      B('productCarousel', { heading: T('Complete the look', 'Fuldend looket', null), products: { source: 'associations', config: { type: 'accessories', limit: 4 } } }),
    ],
  };

  const newsletter = {
    uuid: 'e-newsletter', contentType: 'snippet', code: 'newsletter', name: 'Newsletter sign-up', context: null, channels: ALL_CH, enabled: true,
    translations: ['en_US', 'da_DK', 'de_DE'], fields: {}, placements: [], usedIn: 1,
    tree: [
      B('heading', { text: T('Get 10% off your first order', 'Få 10% på din første ordre', '10% auf deine erste Bestellung'), level: 'H3' }),
      B('richText', { body: T('<p>Join the newsletter for early access to sales.</p>', '<p>Tilmeld dig nyhedsbrevet og få adgang til udsalg før alle andre.</p>', null) }),
    ],
  };

  const L = (label, target) => B('menuLink', { label: label || T(), target, newTab: false });
  const G = (label, target, children) => B('menuGroup', { label, target }, children);
  const menu = {
    uuid: 'e-menu', contentType: 'menu', code: 'main', name: 'Main menu', channels: ALL_CH, enabled: true,
    translations: ['en_US', 'da_DK', 'de_DE'], fields: {},
    tree: [
      G(T('Women', 'Kvinder', 'Damen'), { type: 'taxon', ref: 'women' }, [
        G(T('Clothing', 'Tøj', 'Kleidung'), null, [L(null, { type: 'taxon', ref: 'women_tshirts' }), L(null, { type: 'taxon', ref: 'women_dresses' }), L(null, { type: 'taxon', ref: 'women_jeans' })]),
        G(T('Shoes & bags', 'Sko og tasker', 'Schuhe & Taschen'), null, [L(null, { type: 'taxon', ref: 'women_shoes' }), L(null, { type: 'taxon', ref: 'bags' }), L(T('Straw hats', 'Stråhatte', null), { type: 'product', ref: 'STRAW_HAT' })]),
        B('menuPromo', { image: 'm-dress', heading: T('Linen season is here', 'Hørsæsonen er her', 'Die Leinensaison ist da'), target: { type: 'page', ref: 'e-summer' } }),
      ]),
      G(T('Men', 'Mænd', 'Herren'), { type: 'taxon', ref: 'men' }, [
        G(T('Clothing', 'Tøj', 'Kleidung'), null, [L(null, { type: 'taxon', ref: 'men_tshirts' }), L(null, { type: 'taxon', ref: 'men_shirts' })]),
        G(T('Shoes', 'Sko', 'Schuhe'), null, [L(null, { type: 'taxon', ref: 'men_shoes' })]),
      ]),
      L(T('Summer sale', 'Sommerudsalg', 'Sommerschlussverkauf'), { type: 'page', ref: 'e-summer' }),
      L(T('Spring campaign', 'Forårskampagne', null), { type: 'page', ref: 'e-spring' }),
      L(T('About us', 'Om os', 'Über uns'), { type: 'page', ref: 'e-about' }),
      L(T('Journal', 'Journal', null), { type: 'url', ref: 'https://journal.example.com' }),
    ],
  };

  const list = [summer, usp, sizefit, newsletter, menu, page('e-about', 'About us', ['en_US', 'da_DK', 'de_DE']), page('e-spring', 'Spring campaign 2026', ['en_US'])];
  return Object.fromEntries(list.map(e => [e.uuid, e]));
}

function makePresets() {
  return [
    { id: 'pr-campaign', name: 'Campaign landing', origin: 'code', desc: 'Hero, the USP bar and bestsellers.', blocks: [
      B('hero', { heading: T('Campaign heading'), text: T('<p>One line about the campaign.</p>'), image: 'm-beach', ctaLabel: T('Shop now'), ctaTarget: null, align: 'Left' }),
      B('snippetRef', { snippet: 'e-usp' }),
      B('productCarousel', { heading: T('Bestsellers'), products: { source: 'bestsellers', config: { period: '30', limit: 8 } } }),
    ] },
    { id: 'pr-launch', name: 'Product launch', origin: 'code', desc: 'Image and story side by side, then hand-picked products.', blocks: [
      B('columns', { gap: 'Large' }, [
        B('column', { width: '1/2' }, [B('image', { image: 'm-dress' })]),
        B('column', { width: '1/2' }, [B('heading', { text: T('Meet the new collection'), level: 'H2' }), B('richText', { body: T('<p>Tell the story behind it.</p>') })]),
      ]),
      B('productCarousel', { heading: T('The collection'), products: { source: 'manual', config: { items: ['LINEN_DRESS', 'WRAP_DRESS'] } } }),
    ] },
    { id: 'pr-article', name: 'Article', origin: 'code', desc: 'Heading, text, image, text.', blocks: [
      B('heading', { text: T('Article heading'), level: 'H2' }), B('richText', { body: T('<p>Intro paragraph.</p>') }), B('image', { image: 'm-linen' }), B('richText', { body: T('<p>More text.</p>') }),
    ] },
    { id: 'pr-slides', name: 'Three-slide banner', origin: 'code', desc: 'A banner carousel with three slides.', blocks: [
      B('bannerCarousel', { autoplay: true }, [B('slide', { image: T('m-beach'), heading: T('Slide one') }), B('slide', { image: T('m-dress'), heading: T('Slide two') }), B('slide', { image: T('m-linen'), heading: T('Slide three') })]),
    ] },
    { id: 'pr-ed-1', name: 'Summer heading + favourites', origin: 'admin', desc: 'Saved from Summer sale.', blocks: [
      B('heading', { text: T('Summer favourites', 'Sommerfavoritter'), level: 'H2' }),
      B('productCarousel', { heading: T(''), products: { source: 'taxon', config: { taxon: 'summer', children: true, sort: 'bestselling', limit: 8 } } }),
    ] },
  ];
}
