/* PROTOTYPE preview (throwaway): a fake Sylius shop page that renders the draft entry.
   In the real thing this would be server-rendered Twig in an iframe; here it's a div. */

const USP_ICONS = { truck: 'truck', undo: 'undo', leaf: 'leaf', lock: 'lock' };

function ctxFor(mode) { return { mode, locale: S.locale, channel: S.channel, product: sampleProduct(), selected: S.selected, multi: S.multi, keys: true }; }

function previewHTML(mode) {
  const e = entry();
  const ctx = ctxFor(mode);
  let main = '';
  if (e.contentType === 'page') main = pageMain(e, ctx);
  if (e.contentType === 'snippet') main = e.context === 'product' ? productPageMain(e, ctx) : homeMain(e, ctx);
  if (e.contentType === 'menu') main = homeMain(null, { ...ctx, keys: false, dim: true });
  const na = !e.translations.includes(S.locale)
    ? `<div class="pv-na">${icon('eye slash')}Shoppers browsing in ${localeName(S.locale)} ${e.contentType === 'page' ? 'get a 404 here' : `see nothing from this ${CT_LABEL[e.contentType].toLowerCase()}`}. You're looking at the draft with fallbacks.</div>` : '';
  return `<div class="shop ${S.device}">${na}${shopHeader(e, ctx)}${main}${shopFooter(ctx)}</div>`;
}

/* ---------- values ---------- */
function bindValue(path, ctx) {
  const p = ctx.product;
  switch (path) {
    case 'product.name': return p && pName(p, ctx.locale);
    case 'product.code': return p && p.code;
    case 'product.shortDescription': return p && (p.short[ctx.locale] || p.short.en_US);
    case 'product.mainTaxon': return p && p.taxons[0] && taxonName(p.taxons[0], ctx.locale);
    case 'product.material': return p && p.material;
    case 'product.price': return p && money(p.price, ctx.channel);
    case 'channel.name': return channelName(ctx.channel);
  }
  return null;
}
function textVal(v, ctx) {
  if (isBound(v)) { const x = bindValue(v.binding, ctx); return isEmptyVal(x) ? (v.fallback || '') : x; }
  return isTr(v) ? (trResolve(v, ctx.locale).value || '') : (v || '');
}
const mval = (v, ctx) => (isTr(v) ? trResolve(v, ctx.locale).value : v);
function resolveTokens(h, ctx) {
  return String(h || '').replace(TOKEN_RE, (m, p) => `<span class="pv-bound" title="Bound to ${esc(bindLabel(p))}">${esc(bindValue(p, ctx) ?? '')}</span>`);
}
function chipsResolved(h, ctx) {
  return String(h || '').replace(TOKEN_RE, (m, p) => { const x = bindValue(p, ctx); return `<span contenteditable="false" data-bind="${p}">${esc(isEmptyVal(x) ? bindLabel(p) : x)}</span>`; });
}

/* ---------- selection wrappers ---------- */
function wrap(b, ctx, cls, inner) {
  if (!ctx.keys) return `<div class="${cls}">${inner}</div>`;
  const sel = ctx.selected === b.key ? ' pv-sel' : (ctx.multi || []).includes(b.key) ? ' pv-multi' : '';
  const tb = ctx.selected === b.key && ctx.mode === 'B' ? bToolbar(b) : '';
  return `<div class="${cls}${sel}" data-pk="${b.key}" data-label="${esc(BT[b.type].label)}">${tb}${inner}</div>`;
}
function pvAttrs(b, ctx, cls) {
  if (!ctx.keys) return `class="${cls}"`;
  return `class="${cls}${ctx.selected === b.key ? ' pv-sel' : ''}" data-pk="${b.key}" data-label="${esc(BT[b.type].label)}"`;
}
function bToolbar(b) {
  const path = pathTo(b.key);
  const a = (act, ic, title) => `<a data-act="${act}" data-key="${b.key}" title="${title}">${icon(ic)}</a>`;
  return `<div class="btb" data-act="noop">${path.length > 1 ? `<a data-act="select" data-key="${path[path.length - 2].key}" title="Select the parent: ${esc(BT[path[path.length - 2].type].label)}">${icon('level up alternate')}</a>` : ''}<span class="btb-l">${icon(BT[b.type].icon)}${esc(BT[b.type].label)}</span>${a('up', 'arrow up', 'Move up')}${a('down', 'arrow down', 'Move down')}${a('dup', 'copy outline', 'Duplicate')}${a('preset-open', 'star outline', 'Save as preset')}<a data-act="drawer-open" title="Settings">${icon('sliders horizontal')}</a>${a('del', 'trash alternate outline', 'Delete')}</div>`;
}
const insBar = (parentKey, i) => `<div class="ins" data-act="insert" data-parent="${parentKey || ''}" data-index="${i}" title="Add a block here"><span>${icon('plus')}</span></div>`;

/* Text that variant B can edit in place. */
function inl(b, field, tag, ctx) {
  const v = b.fields[field];
  const val = textVal(v, ctx);
  if (isBound(v)) return `<${tag} class="pv-bound" title="Bound to ${esc(bindLabel(v.binding))}">${esc(val)}</${tag}>`;
  const own = isTr(v) ? (v.translations[ctx.locale] || '') : (v || '');
  const isFb = isTr(v) && isEmptyVal(own) && !isEmptyVal(val);
  if (ctx.mode === 'B' && ctx.keys) return `<${tag} class="${isFb ? 'pv-fb' : ''}" contenteditable="true" spellcheck="false" data-inline="text" data-f="block" data-key="${b.key}" data-field="${field}" ${isFb ? 'data-fallback="1" title="Fallback text. Type to translate."' : ''}>${esc(val)}</${tag}>`;
  return val ? `<${tag}>${esc(val)}</${tag}>` : '';
}
function rich(b, field, ctx) {
  const v = b.fields[field];
  const res = isTr(v) ? trResolve(v, ctx.locale) : { value: v || '' };
  const own = isTr(v) ? (v.translations[ctx.locale] || '') : (v || '');
  const isFb = isTr(v) && isEmptyVal(own) && !isEmptyVal(res.value);
  if (ctx.mode === 'B' && ctx.keys) return `<div class="pv-richtext ${isFb ? 'pv-fb' : ''}" contenteditable="true" spellcheck="false" data-inline="rich" data-f="block" data-key="${b.key}" data-field="${field}" ${isFb ? 'data-fallback="1"' : ''}>${chipsResolved(res.value, ctx)}</div>`;
  return `<div class="pv-richtext">${resolveTokens(res.value, ctx)}</div>`;
}

/* ---------- blocks ---------- */
function blocksHTML(list, ctx, parentKey) {
  const ins = ctx.mode === 'B' && ctx.keys;
  let h = '';
  list.forEach((b, i) => { if (ins) h += insBar(parentKey, i); if (hiddenBy(b, ctx)) return; h += blockHTML(b, ctx); });
  if (ins) h += insBar(parentKey, list.length);
  if (!list.length && ctx.keys && ctx.mode !== 'B') h += '<div class="pv-empty">Empty</div>';
  return h;
}
const visibleKids = (b, ctx) => b.children.filter(c => !hiddenBy(c, ctx));
function pcard(p, ctx) { return `<div class="pc"><img src="${ph(pName(p, ctx.locale), p.hue, 150, 180)}" alt=""><div class="pc-n">${esc(pName(p, ctx.locale))}</div><div class="pc-p">${money(p.price, ctx.channel)}</div></div>`; }

function blockHTML(b, ctx) {
  const f = b.fields;
  const tv = v => textVal(v, ctx);
  switch (b.type) {
    case 'hero': return wrap(b, ctx, 'pv-hero' + (f.align === 'Center' ? ' center' : ''), `<img class="bg" src="${mediaSrc(mval(f.image, ctx), 900, 320)}" alt=""><div class="hero-c">${inl(b, 'heading', 'h2', ctx)}${rich(b, 'text', ctx)}${tv(f.ctaLabel) ? `<span class="pv-btn">${esc(tv(f.ctaLabel))}</span>` : ''}</div>`);
    case 'heading': return wrap(b, ctx, 'pv-heading', inl(b, 'text', f.level === 'H3' ? 'h3' : 'h2', ctx));
    case 'richText': return wrap(b, ctx, 'pv-rich', rich(b, 'body', ctx));
    case 'image': return wrap(b, ctx, 'pv-image', `<img src="${mediaSrc(f.image, 600, 380)}" alt="${esc(tv(f.alt))}">`);
    case 'columns': return wrap(b, ctx, 'pv-cols' + (f.gap === 'Large' ? ' gap-l' : ''), visibleKids(b, ctx).map(c => blockHTML(c, ctx)).join(''));
    case 'column': return wrap(b, ctx, 'pv-col w-' + f.width.replace('/', '-'), blocksHTML(b.children, ctx, b.key));
    case 'productCarousel': {
      const ps = resolveProducts(f.products, ctx);
      const empty = ctx.keys ? `<div class="pv-empty">No products match${f.products.source === 'associations' && ctx.product ? `: ${esc(pName(ctx.product))} has no ${ASSOC_TYPES[f.products.config.type].toLowerCase()}` : ''}. Shoppers see nothing here.</div>` : '';
      return wrap(b, ctx, 'pv-pc', `${tv(f.heading) ? `<h3>${esc(tv(f.heading))}</h3>` : ''}<div class="pc-row">${ps.map(p => pcard(p, ctx)).join('') || empty}</div>`);
    }
    case 'bannerCarousel': {
      const slides = visibleKids(b, ctx);
      const i = Math.max(0, slides.findIndex(s => s.key === ctx.selected));
      const active = slides[i];
      return wrap(b, ctx, 'pv-banner', (active ? blockHTML(active, ctx) : (ctx.keys ? '<div class="pv-empty">No slides show in this channel and language</div>' : ''))
        + `<div class="dots">${slides.map((s, j) => `<span class="${j === i ? 'on' : ''}" ${ctx.keys ? `data-pk="${s.key}" data-label="Slide"` : ''}></span>`).join('')}</div>`);
    }
    case 'slide': return wrap(b, ctx, 'pv-slide', `<img src="${mediaSrc(mval(f.image, ctx), 900, 260)}" alt=""><div class="slide-c">${inl(b, 'heading', 'h3', ctx)}</div>`);
    case 'blockCarousel': return wrap(b, ctx, 'pv-bc per-' + f.perView, visibleKids(b, ctx).map(c => `<div class="bc-cell">${blockHTML(c, ctx)}</div>`).join('') || (ctx.keys ? '<div class="pv-empty">Add blocks to this carousel</div>' : ''));
    case 'uspBar': return wrap(b, ctx, 'pv-usps', visibleKids(b, ctx).map(c => blockHTML(c, ctx)).join(''));
    case 'usp': return wrap(b, ctx, 'pv-usp', `${icon(USP_ICONS[f.icon])}${inl(b, 'text', 'span', ctx)}`);
    case 'snippetRef': {
      const s = S.entries[f.snippet];
      let inner = '';
      if (!s) inner = ctx.keys ? '<div class="pv-empty">Choose a snippet</div>' : '';
      else if (!s.translations.includes(ctx.locale)) inner = ctx.keys ? `<div class="pv-empty">“${esc(s.name)}” has no ${localeName(ctx.locale)} translation, so it renders nothing here.</div>` : '';
      else inner = blocksHTML(s.tree, { ...ctx, keys: false, mode: null }, null);
      return wrap(b, ctx, 'pv-ref', inner + (ctx.keys ? `<span class="ref-tag">${icon('clone outline')}${esc(s?.name || 'Snippet')}</span>` : ''));
    }
    case 'productFacts': {
      const p = ctx.product;
      return wrap(b, ctx, 'pv-facts', p ? `${inl(b, 'title', 'h4', ctx)}<table><tr><td>Material</td><td>${esc(p.material)}</td></tr>${f.showCare ? '<tr><td>Care</td><td>Wash at 30°C</td></tr>' : ''}<tr><td>Product code</td><td>${p.code}</td></tr></table>` : '');
    }
  }
  return '';
}

/* ---------- shop chrome ---------- */
function shopHeader(e, ctx) {
  const editingMenu = e.contentType === 'menu';
  return `<div class="sh-top"><div class="sh-logo">${esc(channelName(ctx.channel))}</div><div class="sh-search">${icon('search')}Search products</div><div class="sh-icons">${icon('user')}${icon('shopping cart')}</div></div>${navHTML(S.entries['e-menu'], { ...ctx, keys: editingMenu, menuEdit: editingMenu, mode: editingMenu ? ctx.mode : null })}`;
}
function linkLabel(b, ctx) { return textVal(b.fields.label, ctx) || targetName(b.fields.target, ctx.locale) || '(no label)'; }
function navHTML(menu, ctx) {
  if (!menu.translations.includes(ctx.locale) && !ctx.menuEdit) return '<div class="nav"></div>';
  const items = menu.tree.filter(b => !hiddenBy(b, ctx));
  let openKey = null;
  if (ctx.menuEdit) {
    const top = S.selected ? pathTo(S.selected, menu.tree)[0] : null;
    openKey = top?.type === 'menuGroup' ? top.key : (S.ui.openGroup || menu.tree.find(b => b.type === 'menuGroup')?.key);
  }
  const top = items.map(b => {
    const na = b.type === 'menuLink' && targetUnavailable(b.fields.target, ctx.locale);
    if (na && !ctx.menuEdit) return '';
    const cls = `nav-i${b.key === openKey ? ' open' : ''}${na ? ' na' : ''}`;
    return `<div ${pvAttrs(b, ctx, cls)} ${na ? `title="${esc(na)}. Hidden for shoppers."` : ''}>${esc(linkLabel(b, ctx))}${b.type === 'menuGroup' ? ' ' + icon('caret down') : ''}${na ? ' ' + icon('exclamation triangle') : ''}</div>`;
  }).join('');
  const openB = openKey && find(openKey, menu.tree)?.block;
  return `<div class="nav">${top}</div>${openB && !hiddenBy(openB, ctx) ? megaHTML(openB, ctx) : ''}`;
}
function megaLink(l, ctx) {
  if (hiddenBy(l, ctx)) return '';
  if (l.type === 'menuGroup') return `<div ${pvAttrs(l, ctx, 'mega-g')}><div class="mega-h">${esc(linkLabel(l, ctx))}</div>${l.children.map(c => megaLink(c, ctx)).join('')}</div>`;
  if (l.type === 'menuPromo') return '';
  const na = targetUnavailable(l.fields.target, ctx.locale);
  if (na && !ctx.menuEdit) return '';
  return `<div ${pvAttrs(l, ctx, 'mega-l' + (na ? ' na' : ''))} ${na ? `title="${esc(na)}. Hidden for shoppers."` : ''}>${esc(linkLabel(l, ctx))}${na ? ' ' + icon('exclamation triangle') : ''}</div>`;
}
function megaHTML(g, ctx) {
  const kids = g.children.filter(b => !hiddenBy(b, ctx));
  const loose = kids.filter(b => b.type === 'menuLink');
  const cols = kids.filter(b => b.type === 'menuGroup').map(sub => `<div ${pvAttrs(sub, ctx, 'mega-col')}><div class="mega-h">${esc(linkLabel(sub, ctx))}</div>${sub.children.map(c => megaLink(c, ctx)).join('')}</div>`).join('');
  const promos = kids.filter(b => b.type === 'menuPromo').map(p => `<div ${pvAttrs(p, ctx, 'mega-promo')}><img src="${mediaSrc(p.fields.image, 220, 120)}" alt=""><div>${esc(textVal(p.fields.heading, ctx))}</div></div>`).join('');
  return `<div class="mega">${loose.length ? `<div class="mega-col">${loose.map(l => megaLink(l, ctx)).join('')}</div>` : ''}${cols}${promos}${ctx.menuEdit ? `<span class="mega-tag">Mega menu of “${esc(linkLabel(g, ctx))}”</span>` : ''}</div>`;
}
function shopFooter(ctx) {
  return `<div class="sh-foot"><div><b>Customer service</b>Shipping<br>Returns<br>Contact</div><div><b>About</b>Our story<br>Journal</div><div style="margin-left:auto">© ${esc(channelName(ctx.channel))}</div></div>`;
}

/* ---------- host pages ---------- */
function pageMain(e, ctx) {
  const title = textVal(e.fields.title, ctx);
  const body = blocksHTML(e.tree, ctx, null);
  const head = `<div class="pv-crumbs">Home / ${esc(title)}</div><h1 class="pv-title" data-act="select-entry" title="From the page's Title field. Click for page settings.">${esc(title)}</h1>`;
  if (e.fields.layout === 'With sidebar') {
    return `<div class="pv-main">${head}<div class="pv-side-l"><div class="pv-body">${body}</div><aside class="pv-slot empty"><span class="slot-tag">${icon('map marker alternate')}Slot · Page sidebar</span><div class="muted small">No snippets placed. Slots come from the layout.</div></aside></div></div>`;
  }
  return `<div class="pv-main ${e.fields.layout === 'Full width' ? 'full' : ''}">${head}<div class="pv-body">${body}</div></div>`;
}
function slotBox(e, ctx, warn) {
  const pl = currentPlacement(e);
  return `<div class="pv-slot on"><span class="slot-tag">${icon('map marker alternate')}Slot · ${esc(SLOTS[pl.slot].label)}</span>${warn || ''}${blocksHTML(e.tree, ctx, null)}</div>`;
}
function productPageMain(e, ctx) {
  const p = ctx.product;
  const pl = currentPlacement(e);
  const out = pl && !inScope(p, pl.scope) ? `<div class="pv-warn">${icon('exclamation triangle')}${esc(pName(p))} isn't in this placement's page scope (${esc(scopeLabel(pl.scope))}). Shoppers wouldn't see the snippet on this page.</div>` : '';
  const slot = id => (pl && pl.slot === id ? slotBox(e, ctx, out) : '');
  const similar = (p.assoc.similar || []).map(product).filter(Boolean);
  return `<div class="pv-main"><div class="pv-crumbs">Home / ${esc(p.taxons[0] ? taxonPath(p.taxons[0]) : 'Products')} / ${esc(pName(p))}</div>
    <div class="pp"><div class="pp-img"><img src="${ph(pName(p), p.hue, 420, 480)}" alt=""></div>
      <div class="pp-info"><h1>${esc(pName(p))}</h1><div class="pp-price">${money(p.price, ctx.channel)}</div>${slot('product.under_price')}
        <p class="muted">${esc(p.short[ctx.locale] || p.short.en_US)}</p><div class="pp-sizes">${['XS', 'S', 'M', 'L', 'XL'].map(s => `<span>${s}</span>`).join('')}</div><span class="pv-btn">Add to cart</span></div></div>
    <div class="pp-tabs"><span class="on">Details</span><span>Attributes</span><span>Reviews (3)</span></div><div class="pp-tab">${esc(p.material)}. Machine wash at 30°C.</div>
    <h3>You may also like</h3><div class="pc-row">${similar.map(x => pcard(x, ctx)).join('') || '<span class="muted">No associations</span>'}</div>
    ${slot('product.below_associations')}</div>`;
}
function homeMain(e, ctx) {
  const top = e && e.contentType === 'snippet' ? (currentPlacement(e) ? slotBox(e, ctx) : `<div class="pv-slot on"><span class="slot-tag">No placements: plain preview</span>${blocksHTML(e.tree, ctx, null)}</div>`) : '';
  return `${top ? `<div class="pv-main">${top}</div>` : ''}<div class="pv-main ${ctx.dim ? 'dim' : ''}"><div class="pv-hero"><img class="bg" src="${ph('New season', 200, 900, 300)}" alt=""><div class="hero-c"><h2>New season</h2><span class="pv-btn">Shop women</span></div></div>
    <h3>Latest products</h3><div class="pc-row">${PRODUCTS.slice(0, 5).map(p => pcard(p, ctx)).join('')}</div></div>`;
}
