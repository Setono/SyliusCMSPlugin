/* PROTOTYPE (throwaway): fixture data standing in for the server and for what developers define in code.
   Terms follow v2's GLOSSARY.md on the 2.x branch. Nothing in here is a spec. Today is 13 Nov 2026. */

const LOCALES = { en_US: 'EN', da_DK: 'DA', de_DE: 'DE' };
const LOCALE_NAMES = { en_US: 'English', da_DK: 'Dansk', de_DE: 'Deutsch' };
const CHANNELS = [
  { code: 'FASHION_WEB', name: 'Fashion Web Store', locales: ['en_US', 'de_DE'] },
  { code: 'NORDIC', name: 'Nordic Store', locales: ['da_DK', 'en_US'] },
];
const ALL_CH = CHANNELS.map(c => c.code);
const GROUPS = ['Retail', 'Wholesale', 'Guest', 'Signed in without a group'];

/* Content types, defined in code. Page and Snippet are built in; Blog and FAQ ship; Store locations is the agency's own. */
const CT = {
  page: { label: 'Page', plural: 'Pages', icon: 'file alternate outline', url: true, prefix: '', tree: true, builtIn: true, layouts: ['Default', 'Landing'] },
  snippet: { label: 'Snippet', plural: 'Snippets', icon: 'puzzle piece', url: false, tree: true, builtIn: true },
  blog_post: { label: 'Blog post', plural: 'Posts', icon: 'newspaper outline', url: true, prefix: 'blog', tree: true, collection: 'blog', layouts: ['Post'] },
  blog_category: { label: 'Blog category', plural: 'Categories', icon: 'folder outline', url: true, prefix: 'blog', tree: true, collection: 'blog', categoryOf: 'blog_post', layouts: ['Archive'] },
  faq_item: { label: 'FAQ item', plural: 'Questions', icon: 'question circle outline', url: false, tree: false, collection: 'faq', ordered: true },
  faq_category: { label: 'FAQ category', plural: 'Categories', icon: 'folder outline', url: false, tree: false, collection: 'faq', categoryOf: 'faq_item' },
  store_location: { label: 'Store location', plural: 'Stores', icon: 'map marker alternate', url: true, prefix: 'stores', tree: true, collection: 'stores', agency: true, layouts: ['Store'] },
};
const COLLECTIONS = {
  blog: { label: 'Blog', icon: 'newspaper outline', types: ['blog_post', 'blog_category'] },
  faq: { label: 'FAQ', icon: 'question circle outline', types: ['faq_item', 'faq_category'] },
  stores: { label: 'Store locations', icon: 'map marker alternate', types: ['store_location'], agency: true },
};

/* Translation state per locale, in the order en_US, da_DK, de_DE:
   'ok' complete · 'uN' N fields untranslated · 'oN' N fields outdated · 'held' held back · '-' no translation */
function TR(en, da, de) { return { en_US: en, da_DK: da, de_DE: de }; }

/* pub: 'live' published, no draft · 'changes' published + draft · 'never' draft only · 'scheduled' · 'overdue' · 'failed' */
function E(uuid, type, title, o = {}) {
  return {
    uuid, type, title, slug: o.slug ?? null, channels: o.channels ?? ALL_CH, enabled: o.enabled ?? true,
    pub: o.pub ?? 'live', when: o.when ?? null, reason: o.reason ?? null, window: o.window ?? null,
    tr: o.tr ?? TR('ok', 'ok', 'ok'), audience: o.audience ?? 'Everyone', layout: o.layout ?? (CT[type].layouts ? CT[type].layouts[0] : null),
    homepageOf: o.homepageOf ?? [], oldSlugs: o.oldSlugs ?? [], context: o.context ?? null, categories: o.categories ?? [],
    edited: o.edited ?? '2 Nov, Anna', blocks: o.blocks ?? ['Hero', 'Rich text', 'Product carousel'], problems: o.problems ?? [],
    usedIn: o.usedIn ?? [], order: o.order ?? null, routeOverride: o.routeOverride ?? null,
  };
}

const ENTRIES = [
  // Pages
  E('home', 'page', 'Home', { slug: '', homepageOf: ['FASHION_WEB'], channels: ['FASHION_WEB'], tr: TR('ok', '-', 'ok'), edited: '12 Nov, Anna', blocks: ['Banner carousel', 'Product carousel · Bestsellers', 'Columns', 'Snippet: Newsletter sign-up'] }),
  E('hjem', 'page', 'Hjem', { slug: '', homepageOf: ['NORDIC'], channels: ['NORDIC'], tr: TR('held', 'ok', '-'), edited: '10 Nov, Mads', blocks: ['Hero', 'Product carousel · Taxon: Summer'] }),
  E('about', 'page', 'About us', { slug: 'about-us', tr: TR('ok', 'o2', 'u4'), edited: '28 Oct, Anna' }),
  E('bf', 'page', 'Black Friday', { slug: 'black-friday', pub: 'scheduled', when: '26 Nov, 18:00', window: { start: '27 Nov', end: '30 Nov', state: 'upcoming' }, tr: TR('ok', 'ok', 'u2'), layout: 'Landing', edited: '13 Nov, Anna', blocks: ['Hero', 'Snippet: Black Friday teaser', 'Product carousel · Hand-picked'] }),
  E('xmas', 'page', 'Christmas gift guide', { slug: 'gift-guide', pub: 'never', tr: TR('ok', 'u7', '-'), layout: 'Landing', edited: '13 Nov, Mads' }),
  E('shipping', 'page', 'Shipping & returns', { slug: 'shipping-returns', pub: 'changes', oldSlugs: ['shipping', 'delivery'], edited: '13 Nov, Mads (draft)' }),
  E('size', 'page', 'Size guide', { slug: 'size-guide', pub: 'overdue', when: '12 Nov, 09:00', problems: [{ sev: 'error', kind: 'overdue', text: 'Scheduled publish was due 12 Nov, 09:00 and hasn\'t run. Is the Messenger worker running?' }] }),
  E('press', 'page', 'Press', { slug: 'press', pub: 'failed', when: '11 Nov, 12:00', reason: 'Stopped by App\\EventListener\\LegalReviewListener: "Press pages need legal sign-off."', problems: [{ sev: 'error', kind: 'failed', text: 'Scheduled publish failed: stopped by LegalReviewListener ("Press pages need legal sign-off."). The draft is unlocked again.' }] }),
  E('summer', 'page', 'Summer sale', { slug: 'summer-sale', window: { start: '1 Jun', end: '31 Aug', state: 'ended' }, layout: 'Landing', edited: '30 May, Anna' }),
  E('lookbook', 'page', 'Lookbook AW26', { slug: 'lookbook-aw26', layout: 'Lookbook', edited: '3 Oct, Anna', problems: [{ sev: 'warning', kind: 'layout', text: 'Layout "Lookbook" was removed from code. The page renders with the default layout.' }] }),
  E('careers', 'page', 'Careers', { slug: 'careers', enabled: false, tr: TR('ok', 'ok', '-') }),
  E('wholesale', 'page', 'Wholesale', { slug: 'wholesale', audience: 'Only Wholesale' }),
  E('cart-page', 'page', 'Cart', { slug: 'cart', routeOverride: 'sylius_shop_cart_summary', problems: [{ sev: 'warning', kind: 'route', text: 'URL /cart is taken by a Sylius route (sylius_shop_cart_summary). Shoppers never see this page.' }] }),
  E('blog-page', 'page', 'Blog', { slug: 'blog', blocks: ['Heading', 'Entries · Blog posts, newest first, 12 per page'] }),
  E('faq-page', 'page', 'FAQ', { slug: 'faq', blocks: ['Heading', 'Entries · FAQ items, by category'] }),
  E('stores-page', 'page', 'Our stores', { slug: 'stores', tr: TR('ok', 'ok', 'ok'), blocks: ['Heading', 'Entries · Store locations'] }),

  // Snippets
  E('s-usp', 'snippet', 'USP bar', { blocks: ['USP bar'] }),
  E('s-wholesale-notice', 'snippet', 'Wholesale notice', { audience: 'Only Wholesale', blocks: ['Rich text'] }),
  E('s-sizefit', 'snippet', 'Size & fit', { context: 'product', blocks: ['Heading ⚡ product.name', 'Product facts'], tr: TR('ok', 'u1', 'ok') }),
  E('s-freeship', 'snippet', 'Free shipping banner', { audience: 'Only Retail, Guest', blocks: ['Image'] }),
  E('s-wholesale-terms', 'snippet', 'Wholesale terms', { audience: 'Only Wholesale', blocks: ['Rich text'] }),
  E('s-carthelp', 'snippet', 'Cart help', { blocks: ['Rich text'] }),
  E('s-bfteaser', 'snippet', 'Black Friday teaser', { window: { start: '20 Nov', end: '30 Nov', state: 'upcoming' }, blocks: ['Hero'], usedIn: ['bf'] }),
  E('s-newsletter', 'snippet', 'Newsletter sign-up', { blocks: ['Heading', 'Newsletter form'], usedIn: ['home'] }),
  E('s-care', 'snippet', 'Care instructions', { context: 'product', blocks: ['Rich text ⚡ product.material'], problems: [{ sev: 'error', kind: 'reference', text: 'A visibility condition uses customer group "VIP 2025", which was deleted' }] }),
  E('s-hours', 'snippet', 'Holiday opening hours', { blocks: ['Rich text'], usedIn: ['st-cph', 'st-aar'] }),
  E('s-countdown', 'snippet', 'Launch countdown', { pub: 'never', blocks: ['Countdown'], tr: TR('ok', '-', '-') }),

  // Blog
  E('bc-news', 'blog_category', 'News', { slug: 'blog/news', blocks: ['Heading', 'Entries · this category'] }),
  E('bc-guides', 'blog_category', 'Guides', { slug: 'blog/guides', blocks: ['Heading', 'Rich text'], problems: [{ sev: 'warning', kind: 'archive', text: 'The category\'s block tree no longer holds an Entries block, so its archive page lists no posts' }] }),
  E('bc-sustain', 'blog_category', 'Sustainability', { slug: 'blog/sustainability', tr: TR('ok', 'ok', 'u1'), blocks: ['Heading', 'Entries · this category'] }),
  E('bp-linen', 'blog_post', 'How to style linen', { slug: 'blog/how-to-style-linen', categories: ['bc-guides'], edited: '5 Nov, Mads' }),
  E('bp-seams', 'blog_post', 'Behind the seams', { slug: 'blog/behind-the-seams', categories: ['bc-news'], tr: TR('ok', '-', 'ok') }),
  E('bp-cotton', 'blog_post', 'Our cotton, traced', { slug: 'blog/our-cotton-traced', pub: 'changes', categories: ['bc-sustain', 'bc-news'] }),
  E('bp-aw26', 'blog_post', 'The AW26 drop', { slug: 'blog/aw26-drop', pub: 'scheduled', when: '20 Nov, 08:00', categories: ['bc-news'] }),
  E('bp-popup', 'blog_post', 'Pop-up shop in Aarhus', { slug: 'blog/pop-up-aarhus', categories: ['bc-news', 'deleted:Events'], channels: ['NORDIC'], tr: TR('ok', 'ok', '-'), problems: [{ sev: 'error', kind: 'reference', text: 'Category "Events" was deleted. The post still points at it.' }] }),

  // FAQ
  E('fc-shipping', 'faq_category', 'Shipping', { blocks: [] }),
  E('fc-returns', 'faq_category', 'Returns', { blocks: [] }),
  E('fc-sizing', 'faq_category', 'Sizing', { blocks: [] }),
  E('fq-1', 'faq_item', 'How long does shipping take?', { categories: ['fc-shipping'], order: 1, blocks: [] }),
  E('fq-2', 'faq_item', 'Do you ship to Norway?', { categories: ['fc-shipping'], order: 2, channels: ['NORDIC'], tr: TR('ok', 'ok', '-'), blocks: [] }),
  E('fq-3', 'faq_item', 'How do I return an item?', { categories: ['fc-returns'], order: 3, blocks: [] }),
  E('fq-4', 'faq_item', 'Are returns free?', { categories: ['fc-returns'], order: 4, pub: 'changes', blocks: [] }),
  E('fq-5', 'faq_item', 'How do your sizes run?', { categories: ['fc-sizing'], order: 5, tr: TR('ok', 'o1', 'ok'), blocks: [] }),
  E('fq-6', 'faq_item', 'Can I change my order?', { categories: [], order: 6, pub: 'never', blocks: [] }),

  // Store locations (the agency's own content type)
  E('st-cph', 'store_location', 'Copenhagen', { slug: 'stores/copenhagen', channels: ['NORDIC'], tr: TR('ok', 'ok', '-'), blocks: ['Store details', 'Map', 'Snippet: Holiday opening hours'] }),
  E('st-aar', 'store_location', 'Aarhus', { slug: 'stores/aarhus', channels: ['NORDIC'], tr: TR('ok', 'ok', '-'), blocks: ['Store details', 'Map', 'Snippet: Holiday opening hours'] }),
  E('st-ham', 'store_location', 'Hamburg', { slug: 'stores/hamburg', channels: ['FASHION_WEB'], pub: 'never', tr: TR('u3', '-', 'ok'), blocks: ['Store details', 'Map'] }),
];
const byId = Object.fromEntries(ENTRIES.map(e => [e.uuid, e]));

/* Slots declared in code (ADR 0004), grouped by the shop page they sit in. The plugin ships about 20. */
const SLOTS = [
  { id: 'layout.top', page: 'Every page', label: 'Top of every page', ctx: [] },
  { id: 'layout.before_footer', page: 'Every page', label: 'Above the footer', ctx: [] },
  { id: 'homepage.hero', page: 'Sylius homepage', label: 'Hero', ctx: [], cap: 1, homepage: true },
  { id: 'homepage.after_hero', page: 'Sylius homepage', label: 'Below the hero', ctx: [], homepage: true },
  { id: 'product.under_price', page: 'Product page', label: 'Under the price', ctx: ['product'], cap: 1 },
  { id: 'product.below_description', page: 'Product page', label: 'Below the description', ctx: ['product'] },
  { id: 'product.below_associations', page: 'Product page', label: 'Below associations', ctx: ['product'] },
  { id: 'taxon.above_grid', page: 'Taxon page', label: 'Above the product grid', ctx: ['taxon'], cap: 2 },
  { id: 'taxon.below_grid', page: 'Taxon page', label: 'Below the product grid', ctx: ['taxon'] },
  { id: 'cart.above_items', page: 'Cart', label: 'Above the items', ctx: [], cap: 1 },
  { id: 'cart.below_summary', page: 'Cart', label: 'Below the summary', ctx: [] },
  { id: 'checkout.above_address', page: 'Checkout', label: 'Above the address form', ctx: [] },
  { id: 'thankyou.below_summary', page: 'Order thank-you page', label: 'Below the summary', ctx: [] },
  { id: 'account.dashboard_top', page: 'Account', label: 'Top of the dashboard', ctx: [] },
  { id: 'layout:Landing.sidebar', page: 'Layout "Landing" (pages)', label: 'Sidebar', ctx: ['entry'] },
];
const SHOP_PAGES = [...new Set(SLOTS.map(s => s.page))];

/* Placements: one snippet in one slot, limited by a page scope. Order is editor order within the slot. */
let PLACEMENTS = [
  { id: 'p1', snippet: 's-usp', slot: 'layout.top', scope: 'All pages' },
  { id: 'p2', snippet: 's-wholesale-notice', slot: 'layout.top', scope: 'All pages' },
  { id: 'p3', snippet: 's-newsletter', slot: 'layout.before_footer', scope: 'All pages' },
  { id: 'p4', snippet: 's-sizefit', slot: 'product.under_price', scope: 'Product is in Women › Dresses' },
  { id: 'p5', snippet: 's-care', slot: 'product.below_description', scope: 'Product is in Women' },
  { id: 'p6', snippet: 's-freeship', slot: 'cart.above_items', scope: 'All pages' },
  { id: 'p7', snippet: 's-wholesale-terms', slot: 'cart.above_items', scope: 'All pages' },
  { id: 'p8', snippet: 's-carthelp', slot: 'cart.above_items', scope: 'All pages' },
  { id: 'p9', snippet: 's-bfteaser', slot: 'homepage.hero', scope: 'All pages' },
  { id: 'p10', snippet: 's-bfteaser', slot: 'taxon.above_grid', scope: 'Taxon is Women or Men' },
];

/* Media library */
const FOLDERS = [
  { id: 'f-camp', name: 'Campaigns', parent: null }, { id: 'f-summer', name: 'Summer 2026', parent: 'f-camp' }, { id: 'f-bf', name: 'Black Friday', parent: 'f-camp' },
  { id: 'f-blog', name: 'Blog', parent: null }, { id: 'f-stores', name: 'Stores', parent: null }, { id: 'f-docs', name: 'Documents', parent: null },
];
function M(uuid, name, folder, kind, hue, alt, usedIn, extra = {}) { return { uuid, name, folder, kind, hue, alt, usedIn, ...extra }; }
const MEDIA = [
  M('m-beach', 'summer-beach.jpg', 'f-summer', 'image', 35, TR('ok', 'ok', 'ok'), ['summer', 'hjem'], { dims: '2400×1600', size: '820 KB' }),
  M('m-linen', 'linen-rail.jpg', 'f-summer', 'image', 45, TR('ok', '-', 'ok'), ['bp-linen', 'summer'], { dims: '1800×1200', size: '540 KB' }),
  M('m-bfhero', 'black-friday-hero.jpg', 'f-bf', 'image', 0, TR('ok', '-', '-'), ['bf', 's-bfteaser'], { dims: '2400×1000', size: '1.1 MB' }),
  M('m-bfvideo', 'black-friday-teaser.mp4', 'f-bf', 'video', 280, TR('-', '-', '-'), ['bf'], { dims: '1920×1080', size: '14 MB' }),
  M('m-seams', 'sewing-room.jpg', 'f-blog', 'image', 200, TR('ok', 'ok', '-'), ['bp-seams'], { dims: '2000×1333', size: '610 KB' }),
  M('m-cotton', 'cotton-field.jpg', 'f-blog', 'image', 90, TR('ok', 'ok', 'ok'), ['bp-cotton'], { dims: '2000×1333', size: '700 KB' }),
  M('m-cph', 'store-copenhagen.jpg', 'f-stores', 'image', 210, TR('ok', 'ok', '-'), ['st-cph'], { dims: '1600×1066', size: '480 KB' }),
  M('m-aar', 'store-aarhus.jpg', 'f-stores', 'image', 230, TR('ok', 'ok', '-'), ['st-aar'], { dims: '1600×1066', size: '470 KB' }),
  M('m-ship', 'free-shipping.png', null, 'image', 190, TR('ok', 'ok', 'ok'), ['s-freeship'], { dims: '1200×200', size: '90 KB' }),
  M('m-returns', 'returns-form.pdf', 'f-docs', 'document', 120, null, ['shipping'], { size: '120 KB' }),
  M('m-sizechart', 'size-chart.pdf', 'f-docs', 'document', 300, null, ['size'], { size: '210 KB' }),
  M('m-old', 'spring-2025-banner.jpg', null, 'image', 150, TR('-', '-', '-'), [], { dims: '2400×800', size: '900 KB' }),
];
const mediaById = Object.fromEntries(MEDIA.map(m => [m.uuid, m]));

/* Presets: shipped in code (JSON files, ADR 0012) or saved by editors with ★ */
const PRESETS = [
  { id: 'pr-campaign', name: 'Campaign landing', src: 'code', kind: 'start', ct: 'page', layout: 'Landing', blocks: 'Hero, Product carousel, Columns' },
  { id: 'pr-info', name: 'Info page', src: 'code', kind: 'start', ct: 'page', layout: 'Default', blocks: 'Heading, Rich text' },
  { id: 'pr-hero', name: 'Hero with button', src: 'code', kind: 'block', blocks: 'Hero' },
  { id: 'pr-usp3', name: 'Three USPs', src: 'code', kind: 'block', blocks: 'Columns ×3' },
  { id: 'pr-bests', name: 'Bestsellers row', src: 'code', kind: 'block', blocks: 'Product carousel · Bestsellers' },
  { id: 'pr-faqacc', name: 'FAQ accordion', src: 'code', kind: 'block', blocks: 'Entries · FAQ items' },
  { id: 'pr-bfstart', name: 'Black Friday landing', src: 'editor', kind: 'start', ct: 'page', layout: 'Landing', blocks: 'Hero, Countdown, Product carousel', by: 'Anna', at: '1 Nov', invalid: 'Block type "Countdown" no longer exists in code' },
  { id: 'pr-brandrow', name: 'Brand story row', src: 'editor', kind: 'block', blocks: 'Columns, Image, Rich text', by: 'Mads', at: '22 Oct' },
  { id: 'pr-gift', name: 'Gift-card teaser', src: 'editor', kind: 'block', blocks: 'Hero', by: 'Anna', at: '9 Nov' },
  { id: 'pr-poststart', name: 'Guide post', src: 'editor', kind: 'start', ct: 'blog_post', layout: 'Post', blocks: 'Heading, Rich text, Product carousel', by: 'Mads', at: '2 Nov' },
];

/* Per-entry publishing detail, shown wherever an entry's revisions and share links live. */
const REVISIONS = [
  { n: 7, at: '12 Nov, 14:02', by: 'Anna', note: 'Published' },
  { n: 6, at: '4 Nov, 09:15', by: 'Mads', note: 'Scheduled publish' },
  { n: 5, at: '28 Oct, 16:40', by: 'Anna', note: 'Published' },
  { n: 4, at: '20 Oct, 11:03', by: 'Anna', note: 'Imported, then published' },
];
let SHARE_LINKS = [
  { id: 'sl1', entry: 'bf', channel: 'FASHION_WEB', locale: 'en_US', shownTo: 'Guest', date: '27 Nov, 10:00', by: 'Anna', created: '13 Nov' },
  { id: 'sl2', entry: 'bf', channel: 'NORDIC', locale: 'da_DK', shownTo: 'Wholesale', date: 'Now', by: 'Mads', created: '13 Nov' },
  { id: 'sl3', entry: 'xmas', channel: 'FASHION_WEB', locale: 'en_US', shownTo: 'Guest', date: 'Now', by: 'Mads', created: '12 Nov' },
];

/* Things coming up: scheduled publishes and date windows that start or end soon. */
const UPCOMING = [
  { at: '20 Nov, 08:00', what: 'Scheduled publish', entry: 'bp-aw26' },
  { at: '20 Nov, 00:00', what: 'Date window starts', entry: 's-bfteaser' },
  { at: '26 Nov, 18:00', what: 'Scheduled publish', entry: 'bf' },
  { at: '27 Nov, 00:00', what: 'Date window starts', entry: 'bf' },
  { at: '30 Nov, 23:59', what: 'Date window ends', entry: 'bf' },
];

const ME = 'Anna';
