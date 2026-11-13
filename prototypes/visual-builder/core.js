/* PROTOTYPE core (throwaway): state, tree operations, translation, and widgets shared by every variant.
   The variants differ in where these widgets sit, not in what they do. */

const CT_LABEL = { page: 'Page', snippet: 'Snippet', menu: 'Menu' };
const CT_ICON = { page: 'file alternate outline', snippet: 'clone outline', menu: 'bars' };

const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const TOKEN_RE = /<span[^>]*data-bind="([^"]+)"[^>]*>.*?<\/span>/g;
const strip = h => String(h ?? '').replace(TOKEN_RE, (m, p) => '{' + bindLabel(p) + '}').replace(/<[^>]+>/g, '').replace(/&nbsp;/g, ' ').trim();
const trunc = (s, n = 38) => (s.length > n ? s.slice(0, n - 1) + '…' : s);
const icon = n => `<i class="${n} icon"></i>`;
const opt = (v, l, sel) => `<option value="${esc(v)}" ${sel ? 'selected' : ''}>${esc(l)}</option>`;

let S;
function freshState() {
  return {
    variant: 'A', entryId: 'e-summer', locale: 'en_US', channel: 'FASHION_WEB', device: 'desktop',
    selected: null, multi: [],
    placement: { 'e-sizefit': 'p-sf-1', 'e-usp': 'p-usp-top' }, sample: {},
    entries: makeEntries(), presets: makePresets(),
    ui: { collapsed: {}, expanded: {}, modal: null, toast: null, json: false, layers: false, drawer: true, entrySettings: false, preview: true, openGroup: null, scrollTo: null, back: null, unbound: {} },
    undo: [], dirty: false, typingSnap: false,
  };
}

/* ---------- lookups ---------- */
const entry = () => S.entries[S.entryId];
const localeName = c => LOCALES.find(l => l.code === c)?.name || c;
const channelName = c => CHANNELS.find(x => x.code === c)?.name || c;
const taxonName = (c, loc = S.locale) => { const t = TAXONS[c]; return t ? (t.name[loc] || t.name.en_US) : c; };
const taxonPath = c => { const t = TAXONS[c]; return t?.parent ? taxonName(t.parent) + ' › ' + taxonName(c) : taxonName(c); };
const product = c => PRODUCTS.find(p => p.code === c);
const pName = (p, loc = S.locale) => (p ? (p.name[loc] || p.name.en_US) : '');
const money = (usd, ch = S.channel) => (CHANNELS.find(x => x.code === ch).currency === 'DKK' ? `${Math.round(usd * 6.9)},00 kr.` : `$${usd.toFixed(2)}`);
const media = u => MEDIA.find(m => m.uuid === u);
const bindLabel = p => { const b = BINDABLE.find(x => x.path === p); return b ? `${CONTEXT_OBJECTS[b.obj]} · ${b.label}` : p; };

const _ph = new Map();
function ph(label, hue, w = 400, h = 260, grey = false) {
  const k = [label, hue, w, h, grey].join('|');
  if (_ph.has(k)) return _ph.get(k);
  const L = String(label).replace(/[&<>"]/g, '');
  const sat = grey ? 8 : 55;
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}" viewBox="0 0 ${w} ${h}"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="hsl(${hue},${sat}%,78%)"/><stop offset="1" stop-color="hsl(${(hue + 35) % 360},${sat - 5}%,52%)"/></linearGradient></defs><rect width="100%" height="100%" fill="url(#g)"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-family="Helvetica,Arial,sans-serif" font-weight="700" font-size="${Math.round(Math.min(w, h) / 8)}" fill="rgba(255,255,255,.92)">${L}</text></svg>`;
  const u = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
  _ph.set(k, u);
  return u;
}
const mediaSrc = (u, w, h) => { const m = media(u); return m ? ph(m.label, m.hue, w, h) : ph('No image', 210, w, h, true); };

/* ---------- context ---------- */
function provided(e = entry()) {
  if (e.contentType === 'snippet') return e.context ? [e.context] : [];
  if (e.contentType === 'page') return ['entry'];
  return [];
}
const fits = needs => (needs || []).every(n => provided().includes(n));

/* ---------- tree operations ---------- */
function find(key, list = entry().tree, parent = null) {
  for (let i = 0; i < list.length; i++) {
    const b = list[i];
    if (b.key === key) return { block: b, parent, list, index: i };
    if (b.children) { const r = find(key, b.children, b); if (r) return r; }
  }
  return null;
}
function pathTo(key, list = entry().tree) {
  for (const b of list) {
    if (b.key === key) return [b];
    if (b.children) { const p = pathTo(key, b.children); if (p.length) return [b, ...p]; }
  }
  return [];
}
function walk(list, fn) { list.forEach(b => { fn(b); if (b.children) walk(b.children, fn); }); }
function countBlocks(list) { let n = 0; walk(list, () => n++); return n; }
const isDescendant = (key, ancestorKey) => pathTo(key).slice(0, -1).some(b => b.key === ancestorKey);

function allowedTypes(parent) {
  const list = parent ? (BT[parent.type].children || []) : ROOT_TYPES[entry().contentType];
  return list.filter(t => fits(BT[t].needs));
}
function excludedByContext(parent) {
  const list = parent ? (BT[parent.type].children || []) : ROOT_TYPES[entry().contentType];
  return list.filter(t => !fits(BT[t].needs));
}
const canContain = (parent, type) => allowedTypes(parent).includes(type);
function canAddTo(parent) {
  if (!parent) return true;
  const d = BT[parent.type];
  return !!d.children && (d.max == null || parent.children.length < d.max);
}
const addLabel = b => { const a = allowedTypes(b); return a.length === 1 ? BT[a[0]].label.toLowerCase() : 'block'; };

function listOf(parentKey) { return parentKey ? find(parentKey).block.children : entry().tree; }
function insertAt(parentKey, index, blocks) { listOf(parentKey).splice(index, 0, ...blocks); }
function cloneNewKeys(b) {
  const c = JSON.parse(JSON.stringify(b));
  const rekey = x => { x.key = newKey(); (x.children || []).forEach(rekey); };
  rekey(c);
  return c;
}
function newBlock(type) {
  const kids = { columns: () => [B('column'), B('column')], bannerCarousel: () => [B('slide')], uspBar: () => [B('usp'), B('usp'), B('usp')] }[type];
  return B(type, {}, kids ? kids() : undefined);
}
function moveBlock(key, parentKey, index) {
  const src = find(key);
  const dest = listOf(parentKey);
  src.list.splice(src.index, 1);
  if (src.list === dest && src.index < index) index--;
  dest.splice(index, 0, src.block);
}
function selectionKeys() {
  const ks = [S.selected, ...S.multi].filter(Boolean);
  const order = []; walk(entry().tree, b => order.push(b.key));
  return ks.filter(k => !ks.some(o => o !== k && isDescendant(k, o))).sort((a, b) => order.indexOf(a) - order.indexOf(b));
}

/* ---------- translation (ADR 0007) ---------- */
const isTr = v => !!v && typeof v === 'object' && 'translations' in v;
const isBound = v => !!v && typeof v === 'object' && 'binding' in v;
const isEmptyVal = x => x == null || strip(String(x)) === '';
function trResolve(v, loc) {
  if (!isTr(v)) return { value: v, from: null };
  for (const l of [loc, ...FALLBACK_CHAIN[loc]]) {
    const x = v.translations[l];
    if (!isEmptyVal(x)) return { value: x, from: l === loc ? null : l };
  }
  return { value: '', from: null };
}
function trSet(v, loc, val) {
  v.translations[loc] = val;
  if (loc !== BASE_LOCALE) v.translatedFrom[loc] = { locale: BASE_LOCALE, fingerprint: fp(v.translations[BASE_LOCALE] || '') };
}
function trState(v, loc) {
  if (!isTr(v)) return 'shared';
  if (loc === BASE_LOCALE) return 'source';
  const src = v.translations[BASE_LOCALE];
  if (isEmptyVal(src)) return 'ok';
  if (isEmptyVal(v.translations[loc])) return 'missing';
  const meta = v.translatedFrom[loc];
  if (meta && meta.fingerprint !== fp(src)) return 'outdated';
  return 'ok';
}
const hiddenInLocale = (b, loc) => (b.conditions || []).some(c => c.kind === 'locale' && !c.values.includes(loc));
function fieldStates(e, loc) {
  let missing = 0, outdated = 0;
  const count = (defs, vals) => defs.forEach(f => {
    const v = vals[f.name];
    if (!isTr(v)) return;
    const st = trState(v, loc);
    if (st === 'missing') missing++;
    if (st === 'outdated') outdated++;
  });
  count(ENTRY_FIELDS[e.contentType] || [], e.fields);
  const visit = list => list.forEach(b => { if (hiddenInLocale(b, loc)) return; count(BT[b.type].fields, b.fields); if (b.children) visit(b.children); });
  visit(e.tree);
  return { missing, outdated };
}
function blockFlags(b, loc = S.locale) {
  let m = 0, o = 0;
  if (hiddenInLocale(b, loc)) return { m, o };
  BT[b.type].fields.forEach(f => { const st = trState(b.fields[f.name], loc); if (st === 'missing') m++; if (st === 'outdated') o++; });
  return { m, o };
}

/* ---------- visibility, targets, sources ---------- */
function hiddenBy(b, ctx = { channel: S.channel, locale: S.locale }) {
  for (const c of b.conditions || []) {
    if (c.kind === 'channel' && !c.values.includes(ctx.channel)) return `Hidden in ${channelName(ctx.channel)}`;
    if (c.kind === 'locale' && !c.values.includes(ctx.locale)) return `Hidden in ${localeName(ctx.locale)}`;
  }
  return null;
}
function targetName(t, loc = S.locale) {
  if (!t) return '';
  switch (t.type) {
    case 'taxon': return taxonName(t.ref, loc);
    case 'product': return pName(product(t.ref), loc) || t.ref;
    case 'page': { const e = S.entries[t.ref]; return e ? (trResolve(e.fields.title, loc).value || e.name) : '(deleted page)'; }
    case 'url': return t.ref;
  }
  return '';
}
function targetUnavailable(t, loc = S.locale) {
  if (t?.type === 'page') {
    const e = S.entries[t.ref];
    if (!e) return 'The target page no longer exists';
    if (!e.translations.includes(loc)) return `The target page has no ${localeName(loc)} translation`;
  }
  return null;
}
const linkWarn = b => targetUnavailable(b.fields.target || b.fields.ctaTarget || b.fields.link);

function sourceDefaults(id) {
  return { manual: { items: [] }, taxon: { taxon: 'summer', children: true, sort: 'bestselling', limit: 8 }, bestsellers: { period: '30', limit: 8 }, associations: { type: 'similar', limit: 4 } }[id];
}
function resolveProducts(v, ctx) {
  const c = v.config;
  switch (v.source) {
    case 'manual': return c.items.map(product).filter(Boolean);
    case 'taxon': {
      const codes = new Set([c.taxon, ...(c.children ? Object.keys(TAXONS).filter(k => TAXONS[k].parent === c.taxon) : [])]);
      let list = PRODUCTS.filter(p => p.taxons.some(t => codes.has(t)));
      const sorts = { bestselling: (a, b) => b.sold - a.sold, newest: (a, b) => a.hue - b.hue, price_asc: (a, b) => a.price - b.price, name: (a, b) => pName(a).localeCompare(pName(b)) };
      return list.sort(sorts[c.sort]).slice(0, c.limit);
    }
    case 'bestsellers': return PRODUCTS.filter(p => p.code !== 'GIFT_CARD').sort((a, b) => b.sold - a.sold).slice(0, c.limit);
    case 'associations': return ctx.product ? (ctx.product.assoc[c.type] || []).map(product).filter(Boolean).slice(0, c.limit) : [];
  }
  return [];
}
function sourceSummary(v) {
  const c = v.config;
  switch (v.source) {
    case 'manual': return `Hand-picked · ${c.items.length}`;
    case 'taxon': return `In ${taxonName(c.taxon)}${c.children ? ' + children' : ''} · ${SORTS[c.sort].toLowerCase()} · max ${c.limit}`;
    case 'bestsellers': return `Bestsellers, ${c.period === 'all' ? 'all time' : 'last ' + c.period + ' days'} · max ${c.limit}`;
    case 'associations': return `${ASSOC_TYPES[c.type]} of the current product · max ${c.limit}`;
  }
  return '';
}

/* ---------- placements, sample object ---------- */
function currentPlacement(e = entry()) { return (e.placements || []).find(p => p.id === S.placement[e.uuid]) || e.placements?.[0] || null; }
function inScope(p, scope) {
  return (scope || []).every(c => {
    if (c.kind === 'productIs') return c.values.includes(p.code);
    if (c.kind === 'productInTaxon') return p.taxons.some(t => c.values.includes(t) || (c.children && c.values.includes(TAXONS[t]?.parent)));
    return true;
  });
}
function sampleProduct(e = entry()) {
  if (e.contentType !== 'snippet' || e.context !== 'product') return null;
  const code = S.sample[e.uuid];
  if (code) return product(code);
  const pl = currentPlacement(e);
  return PRODUCTS.find(p => inScope(p, pl?.scope)) || PRODUCTS[0];
}
function scopeLabel(scope) {
  if (!scope?.length) return 'every page';
  return scope.map(c => c.kind === 'productIs' ? 'product is ' + c.values.map(v => pName(product(v))).join(' or ')
    : c.kind === 'productInTaxon' ? 'product in ' + c.values.map(taxonPath).join(' or ') + (c.children ? ', incl. children' : '') : c.kind).join(' and ');
}

/* ---------- summaries ---------- */
function summary(b) {
  const f = b.fields, loc = S.locale;
  switch (b.type) {
    case 'snippetRef': return S.entries[f.snippet]?.name || 'No snippet chosen';
    case 'productCarousel': return (strip(trResolve(f.heading, loc).value) ? strip(trResolve(f.heading, loc).value) + ' · ' : '') + sourceSummary(f.products);
    case 'menuLink': case 'menuGroup': return strip(trResolve(f.label, loc).value) || targetName(f.target) || '';
    case 'columns': return `${b.children.length} columns`;
    case 'column': return `${f.width} · ${b.children.length} block${b.children.length === 1 ? '' : 's'}`;
    case 'bannerCarousel': return `${b.children.length} slides`;
    case 'image': return media(f.image)?.name || 'No image';
    case 'uspBar': return b.children.map(c => strip(trResolve(c.fields.text, loc).value)).join(' · ');
  }
  for (const d of BT[b.type].fields) {
    if (d.kind !== 'text' && d.kind !== 'richtext') continue;
    const v = f[d.name];
    if (isBound(v)) return '{' + bindLabel(v.binding) + '}';
    const s = strip(trResolve(v, loc).value);
    if (s) return trunc(s);
  }
  return '';
}

/* ---------- undo, toast ---------- */
function snapshot() { S.undo.push(JSON.stringify({ entries: S.entries, presets: S.presets })); if (S.undo.length > 80) S.undo.shift(); }
function snapshotOnce() { if (!S.typingSnap) { snapshot(); S.typingSnap = true; } }
function undo() {
  const s = S.undo.pop();
  if (!s) return toast('Nothing to undo.');
  const o = JSON.parse(s);
  S.entries = o.entries; S.presets = o.presets;
  if (!S.entries[S.entryId]) S.entryId = 'e-summer';
  if (S.selected && !find(S.selected)) S.selected = null;
  S.multi = S.multi.filter(k => find(k));
  render();
}
let toastTimer;
function toast(msg) {
  S.ui.toast = msg;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { S.ui.toast = null; document.querySelector('.toast')?.remove(); }, 4200);
  renderOverlay();
}

/* =================== shared widgets =================== */

const attrs = (owner, d, path) => `data-f="${owner.f}" data-key="${owner.key || ''}" data-field="${d.name}"${path ? ` data-path="${path}"` : ''}`;

function badgeHTML(d, v) {
  if (isBound(v)) return `<span class="bdg bound">${icon('bolt')}Bound</span>`;
  if (!d.tr) return `<span class="bdg all" title="Shared by all translations: changing it changes every language">${icon('globe')}All languages</span>`;
  const st = trState(v, S.locale);
  if (st === 'missing') return '<span class="bdg miss">Untranslated</span>';
  if (st === 'outdated') return '<span class="bdg out">Outdated</span>';
  return '';
}

function fieldsHTML(owner, defs, values, opts = {}) {
  return defs.filter(d => !(opts.skip || []).includes(d.name)).map(d => fieldHTML(owner, d, values[d.name])).join('');
}
function fieldHTML(owner, d, v) {
  let body = '';
  switch (d.kind) {
    case 'text': body = textField(owner, d, v); break;
    case 'richtext': body = richField(owner, d, v); break;
    case 'media': body = mediaField(owner, d, v); break;
    case 'choice': body = `<select class="inp" ${attrs(owner, d)}>${d.options.map(o => opt(o, o, o === v)).join('')}</select>`; break;
    case 'toggle': body = `<div class="row"><label class="tgl"><input type="checkbox" ${attrs(owner, d)} ${v ? 'checked' : ''}> ${esc(d.label)}</label>${badgeHTML(d, v)}</div>`; break;
    case 'products': body = productsField(owner, d, v); break;
    case 'target': body = targetField(owner, d, v); break;
    case 'snippet': body = snippetField(owner, d, v); break;
  }
  const label = d.kind === 'toggle' ? '' : `<div class="fl"><span>${esc(d.label)}</span>${badgeHTML(d, v)}</div>`;
  return `<div class="fld">${label}${body}${transHint(owner, d, v)}${d.note ? `<div class="fnote">${esc(d.note)}</div>` : ''}</div>`;
}

function transHint(owner, d, v) {
  if (!isTr(v) || S.locale === BASE_LOCALE) return '';
  const st = trState(v, S.locale);
  const show = x => d.kind === 'media' ? (media(x)?.name || x) : trunc(strip(x), 70);
  if (st === 'missing') {
    const r = trResolve(v, S.locale);
    return `<div class="hint miss">↩ Shoppers get the ${localeName(r.from || BASE_LOCALE)} ${d.kind === 'media' ? 'image' : 'text'} for now. <a data-act="copy-src" ${attrs(owner, d)}>Copy it here</a></div>`;
  }
  if (st === 'outdated') return `<div class="hint out">${icon('exclamation triangle')}The English source changed after this was translated. English now: “${esc(show(v.translations[BASE_LOCALE]))}” <a data-act="mark-current" ${attrs(owner, d)}>Mark as up to date</a></div>`;
  return '';
}

function bindableFor(type = 'text') { return BINDABLE.filter(b => b.type === type && (b.obj === 'channel' || provided().includes(b.obj))); }
function bindMenu(owner, d, mode) {
  const pid = `pop-${owner.key || 'entry'}-${d.name}-${mode}`;
  const groups = {};
  bindableFor('text').forEach(p => (groups[p.obj] ??= []).push(p));
  const list = Object.entries(groups).map(([obj, ps]) => `<div class="pop-h">${CONTEXT_OBJECTS[obj]}</div>` + ps.map(p => mode === 'field'
    ? `<a class="pop-i" data-act="bind" ${attrs(owner, d)} data-prop="${p.path}">${esc(p.label)}${p.note ? `<small>${esc(p.note)}</small>` : ''}</a>`
    : `<a class="pop-i" data-token="${p.path}" data-target="${owner.key || ''}.${d.name}">${esc(p.label)}${p.note ? `<small>${esc(p.note)}</small>` : ''}</a>`).join('')).join('');
  const note = !provided().includes('product') ? '<div class="pop-note">Product properties are offered in snippets declared for product pages.</div>' : '';
  return `<span class="popw"><button class="btn ${mode === 'field' ? 'icon-only' : 'sm ghost'}" data-toggle="${pid}" title="${mode === 'field' ? 'Bind this field to a property' : 'Insert a binding'}">${icon('bolt')}${mode === 'rich' ? 'Binding' : ''}</button><div class="pop" id="${pid}"><div class="pop-h">${mode === 'field' ? 'Show a property instead of fixed text' : 'Insert a property into the text'}</div>${list}${note}</div></span>`;
}

function textField(owner, d, v) {
  if (isBound(v)) return `<div class="bound"><span class="chip bind">${icon('bolt')}${esc(bindLabel(v.binding))}</span><a class="x" data-act="unbind" ${attrs(owner, d)} title="Remove the binding">${icon('times')}</a></div>
    <input class="inp sm" ${attrs(owner, d, 'fallback')} value="${esc(v.fallback)}" placeholder="Fallback when the property is empty (optional)">`;
  const val = isTr(v) ? (v.translations[S.locale] ?? '') : (v ?? '');
  let ph_ = '';
  if (isEmptyVal(val)) ph_ = isTr(v) && S.locale !== BASE_LOCALE ? strip(trResolve(v, S.locale).value) : '';
  if (isEmptyVal(val) && !ph_ && owner.f === 'block' && d.name === 'label') { const b = find(owner.key)?.block; if (b?.fields.target) ph_ = targetName(b.fields.target); }
  return `<div class="row"><input class="inp" ${attrs(owner, d)} value="${esc(val)}" placeholder="${esc(ph_)}">${d.bindable ? bindMenu(owner, d, 'field') : ''}</div>`;
}

function editorRich(h) { return String(h || '').replace(TOKEN_RE, (m, p) => `<span contenteditable="false" data-bind="${p}">${esc(bindLabel(p))}</span>`); }
function cleanRich(h) { return String(h).replace(TOKEN_RE, (m, p) => `<span data-bind="${p}">${esc(bindLabel(p))}</span>`).replace(/<br>$/, ''); }
function richField(owner, d, v) {
  const val = isTr(v) ? (v.translations[S.locale] ?? '') : (v ?? '');
  const fb = isTr(v) && isEmptyVal(val) && S.locale !== BASE_LOCALE ? strip(trResolve(v, S.locale).value) : '';
  return `<div class="rte"><div class="rte-bar"><button class="btn icon-only" data-cmd="bold" title="Bold"><b>B</b></button><button class="btn icon-only" data-cmd="italic" title="Italic"><i>I</i></button><button class="btn icon-only" data-cmd="link" title="Link">${icon('linkify')}</button><span class="sp"></span>${bindMenu(owner, d, 'rich')}</div>
    <div class="rte-body" contenteditable="true" ${attrs(owner, d)} data-placeholder="${esc(fb)}">${isEmptyVal(val) ? '' : editorRich(val)}</div></div>`;
}

function mediaField(owner, d, v) {
  const own = isTr(v) ? v.translations[S.locale] : v;
  const r = isTr(v) && !own ? trResolve(v, S.locale) : null;
  const show = own || r?.value;
  const m = media(show);
  return `<div class="media ${!own && show ? 'fallback' : ''}"><img src="${mediaSrc(show, 160, 100)}" alt=""><div><div class="mname">${m ? esc(m.name) : '<span class="muted">No image</span>'}${!own && show ? ` <span class="muted">(${localeName(r.from)})</span>` : ''}</div><button class="btn sm" data-act="media-open" ${attrs(owner, d)}>${icon('images outline')}Choose…</button>${own ? ` <button class="btn sm ghost" data-act="media-clear" ${attrs(owner, d)}>Remove</button>` : ''}</div></div>`;
}

function productsField(owner, d, v) {
  const srcs = SOURCES.filter(s => fits(s.needs));
  const hiddenSrc = SOURCES.filter(s => !fits(s.needs));
  const c = v.config;
  let cfg = '';
  switch (v.source) {
    case 'manual':
      cfg = `<div class="chips">${c.items.map((code, i) => `<span class="chip">${esc(pName(product(code)))} <a class="x" data-act="pick-remove" ${attrs(owner, d)} data-i="${i}">${icon('times')}</a></span>`).join('') || '<span class="muted">No products yet</span>'}</div>
        <select class="inp sm" data-change="pick-add" ${attrs(owner, d)}><option value="">Add a product…</option>${PRODUCTS.filter(p => !c.items.includes(p.code)).map(p => opt(p.code, pName(p), false)).join('')}</select>`;
      break;
    case 'taxon':
      cfg = `<div class="grid2"><label>Taxon<select class="inp sm" ${attrs(owner, d, 'config.taxon')}>${Object.keys(TAXONS).map(t => opt(t, taxonPath(t), t === c.taxon)).join('')}</select></label>
        <label>Sort<select class="inp sm" ${attrs(owner, d, 'config.sort')}>${Object.entries(SORTS).map(([k, l]) => opt(k, l, k === c.sort)).join('')}</select></label>
        <label>At most<input type="number" min="1" max="24" class="inp sm" ${attrs(owner, d, 'config.limit')} value="${c.limit}"></label>
        <label class="tgl"><input type="checkbox" ${attrs(owner, d, 'config.children')} ${c.children ? 'checked' : ''}> Include child taxons</label></div>`;
      break;
    case 'bestsellers':
      cfg = `<div class="grid2"><label>Period<select class="inp sm" ${attrs(owner, d, 'config.period')}>${[['30', 'Last 30 days'], ['90', 'Last 90 days'], ['all', 'All time']].map(([k, l]) => opt(k, l, k === c.period)).join('')}</select></label>
        <label>At most<input type="number" min="1" max="24" class="inp sm" ${attrs(owner, d, 'config.limit')} value="${c.limit}"></label></div>`;
      break;
    case 'associations':
      cfg = `<div class="grid2"><label>Association<select class="inp sm" ${attrs(owner, d, 'config.type')}>${Object.entries(ASSOC_TYPES).map(([k, l]) => opt(k, l, k === c.type)).join('')}</select></label>
        <label>At most<input type="number" min="1" max="24" class="inp sm" ${attrs(owner, d, 'config.limit')} value="${c.limit}"></label></div>`;
      break;
  }
  const sp = sampleProduct();
  const n = resolveProducts(v, { product: sp, channel: S.channel }).length;
  return `<div class="srcbox"><label class="sublabel">Filled by</label><select class="inp" ${attrs(owner, d, 'source')}>${srcs.map(s => opt(s.id, s.label, s.id === v.source)).join('')}</select>
    <div class="srccfg">${cfg}</div>
    <div class="srcres">${icon('eye')}${n} product${n === 1 ? '' : 's'} right now${v.source === 'associations' && sp ? ` for the sample ${esc(pName(sp))}. Resolved on each product page.` : v.source === 'manual' ? '' : '. Updates by itself as the catalog changes.'}</div>
    ${hiddenSrc.length ? `<div class="fnote">Not offered here: ${hiddenSrc.map(s => esc(s.label)).join(', ')}. It needs a product page.</div>` : ''}</div>`;
}

const TARGET_TYPES = { taxon: 'Taxon', product: 'Product', page: 'Page', url: 'External URL' };
function targetField(owner, d, v) {
  const t = v || { type: '', ref: '' };
  let ref = '';
  if (t.type === 'taxon') ref = `<select class="inp sm" ${attrs(owner, d, 'ref')}>${Object.keys(TAXONS).map(k => opt(k, taxonPath(k), k === t.ref)).join('')}</select>`;
  if (t.type === 'product') ref = `<select class="inp sm" ${attrs(owner, d, 'ref')}>${PRODUCTS.map(p => opt(p.code, pName(p), p.code === t.ref)).join('')}</select>`;
  if (t.type === 'page') ref = `<select class="inp sm" ${attrs(owner, d, 'ref')}>${Object.values(S.entries).filter(e => e.contentType === 'page').map(e => opt(e.uuid, e.name + (e.translations.length < LOCALES.length ? ` (${e.translations.map(l => l.slice(0, 2).toUpperCase()).join(', ')} only)` : ''), e.uuid === t.ref)).join('')}</select>`;
  if (t.type === 'url') ref = `<input class="inp sm" ${attrs(owner, d, 'ref')} value="${esc(t.ref)}" placeholder="https://">`;
  const warn = targetUnavailable(t);
  return `<div class="row"><select class="inp sm w-auto" ${attrs(owner, d, 'type')}><option value="">None</option>${Object.entries(TARGET_TYPES).map(([k, l]) => opt(k, l, k === t.type)).join('')}</select>${ref}</div>
    ${warn ? `<div class="hint out">${icon('exclamation triangle')}${esc(warn)}, so the link is hidden for those shoppers.</div>` : ''}`;
}

function snippetField(owner, d, v) {
  const all = Object.values(S.entries).filter(e => e.contentType === 'snippet' && e.uuid !== entry().uuid);
  const ok = all.filter(s => fits(s.context ? [s.context] : []));
  const no = all.filter(s => !ok.includes(s));
  const s = S.entries[v];
  return `<select class="inp" ${attrs(owner, d)}><option value="">Choose a snippet…</option>${ok.map(e => opt(e.uuid, e.name, e.uuid === v)).join('')}</select>
    ${no.length ? `<div class="fnote">Not offered here: ${no.map(e => esc(e.name)).join(', ')}. ${no.length === 1 ? 'It needs' : 'They need'} a product page.</div>` : ''}
    ${s ? `<div class="refbox">${icon('clone outline')}<div><b>Shared snippet.</b> It looks the same everywhere it's used (${s.usedIn || 1} places). Edit it once and every place updates.
      <div class="refacts"><button class="btn sm" data-act="open-entry" data-id="${s.uuid}">${icon('external alternate')}Edit “${esc(s.name)}”</button><button class="btn sm" data-act="detach" data-key="${owner.key}">${icon('unlink')}Detach to edit here</button></div></div></div>` : ''}`;
}

function conditionsHTML(b) {
  const cs = b.conditions || [];
  const rows = cs.map((c, i) => {
    const opts = c.kind === 'channel' ? CHANNELS.map(x => [x.code, x.name]) : LOCALES.map(l => [l.code, l.name]);
    return `<div class="cond"><div class="cond-h"><b>${c.kind === 'channel' ? 'Channel is one of' : 'Locale is one of'}</b><a class="x" data-act="cond-del" data-key="${b.key}" data-i="${i}" title="Remove">${icon('times')}</a></div>
      <div>${opts.map(([v, l]) => `<label class="tgl"><input type="checkbox" data-change="cond-toggle" data-key="${b.key}" data-i="${i}" value="${v}" ${c.values.includes(v) ? 'checked' : ''}> ${esc(l)}</label>`).join('')}</div></div>`;
  }).join('');
  const hid = hiddenBy(b);
  return `<div class="sec"><div class="sec-h">${icon('eye slash')}Visibility</div>${rows || '<div class="muted small">Shown wherever this entry shows.</div>'}
    ${hid ? `<div class="hint">${icon('eye slash')}${esc(hid)}, so the preview leaves it out.</div>` : ''}
    <div class="addcond">${!cs.some(c => c.kind === 'channel') ? `<button class="btn sm" data-act="cond-add" data-key="${b.key}" data-kind="channel">+ Channel</button>` : ''}${!cs.some(c => c.kind === 'locale') ? `<button class="btn sm" data-act="cond-add" data-key="${b.key}" data-kind="locale">+ Locale</button>` : ''}<button class="btn sm" disabled title="Decided in the Targeting rules ticket">+ Customer group</button><button class="btn sm" disabled title="Decided in the Targeting rules ticket">+ Date window</button></div></div>`;
}

function blockActions(b, cls = 'btn sm icon-only') {
  const a = (act, ic, title) => `<button class="${cls}" data-act="${act}" data-key="${b.key}" title="${title}">${icon(ic)}</button>`;
  return a('up', 'arrow up', 'Move up') + a('down', 'arrow down', 'Move down') + a('dup', 'copy outline', 'Duplicate') + a('preset-open', 'star outline', 'Save as preset') + a('del', 'trash alternate outline', 'Delete');
}

function crumbsHTML() {
  const e = entry();
  const path = S.selected ? pathTo(S.selected) : [];
  return `<span class="crumbs"><a data-act="select-entry">${esc(CT_LABEL[e.contentType])}</a>${path.map(b => ` › <a data-act="select" data-key="${b.key}">${esc(BT[b.type].label)}</a>`).join('')}</span>`;
}

/* Inspector: one block's fields, or the entry's settings. Used by A (right pane) and B (floating drawer). */
function inspectorHTML(opts = {}) {
  const close = opts.close ? `<a class="x insp-x" data-act="drawer-close" title="Close">${icon('times')}</a>` : '';
  if (S.multi.length) {
    const ks = selectionKeys();
    return `<div class="insp"><div class="insp-h">${close}<div class="insp-t">${icon('object group outline')}<b>${ks.length} blocks selected</b></div></div><div class="insp-b">
      <ul class="inc">${ks.map(k => { const b = find(k).block; return `<li>${icon(BT[b.type].icon)}${esc(BT[b.type].label)} <span class="muted">${esc(summary(b))}</span></li>`; }).join('')}</ul>
      <div class="refacts"><button class="btn primary sm" data-act="preset-open">${icon('star outline')}Save selection as preset</button><button class="btn sm" data-act="clear-multi">Clear selection</button></div>
      <div class="fnote">⌘ or Ctrl-click blocks to add them to the selection.</div></div></div>`;
  }
  const f = S.selected && find(S.selected);
  if (!f) return `<div class="insp"><div class="insp-h">${close}<div class="insp-t">${icon('cog')}<b>${CT_LABEL[entry().contentType]} settings</b></div><div class="muted small">Select a block to edit it.</div></div><div class="insp-b">${entrySettingsHTML(entry())}</div></div>`;
  const b = f.block, d = BT[b.type];
  const kids = d.children ? `<div class="sec"><div class="sec-h">${icon('sitemap')}Contains</div>${b.children.map(c => `<a class="kid" data-act="select" data-key="${c.key}">${icon(BT[c.type].icon)}${esc(BT[c.type].label)} <span class="muted" data-summary="${c.key}">${esc(summary(c))}</span>${hiddenBy(c) ? ` <span class="muted">${icon('eye slash')}</span>` : ''}</a>`).join('')}
    ${canAddTo(b) ? `<button class="btn sm" data-act="insert" data-parent="${b.key}" data-index="${b.children.length}">${icon('plus')}Add ${esc(addLabel(b))}</button>` : `<div class="fnote">${esc(d.label)} holds at most ${d.max}.</div>`}</div>` : '';
  return `<div class="insp"><div class="insp-h">${close}${crumbsHTML()}
      <div class="insp-t">${icon(d.icon)}<b>${esc(d.label)}</b>${b.type === 'snippetRef' ? '<span class="bdg ref">Shared</span>' : ''}</div>
      <div class="insp-acts">${blockActions(b)}</div></div>
    <div class="insp-b">${fieldsHTML({ f: 'block', key: b.key }, d.fields, b.fields)}${kids}${conditionsHTML(b)}</div></div>`;
}

function translationsHTML(e) {
  return `<div class="sec"><div class="sec-h">${icon('language')}Translations</div>${LOCALES.map(l => {
    const has = e.translations.includes(l.code);
    const st = fieldStates(e, l.code);
    let s;
    if (!has) s = `<span class="muted">No translation</span> <a data-act="add-translation" data-v="${l.code}">Add</a>`;
    else if (l.code === BASE_LOCALE) s = '<span class="muted">Source</span>';
    else if (st.missing || st.outdated) s = (st.missing ? `<span class="bdg miss">${st.missing} untranslated</span>` : '') + (st.outdated ? `<span class="bdg out">${st.outdated} outdated</span>` : '');
    else s = '<span class="bdg ok">Complete</span>';
    return `<div class="trow2"><span class="lc">${l.short}</span>${esc(l.name)} <span class="sp"></span>${s}</div>`;
  }).join('')}</div>`;
}
function channelsHTML(e) {
  return `<div class="sec"><div class="sec-h">${icon('shopping cart')}Channels</div>${CHANNELS.map(c => `<label class="tgl"><input type="checkbox" data-change="entry-channel" value="${c.code}" ${e.channels.includes(c.code) ? 'checked' : ''}> ${esc(c.name)}</label>`).join('')}
    <div style="margin-top:6px"><label class="tgl"><input type="checkbox" data-change="entry-enabled" ${e.enabled ? 'checked' : ''}> Enabled</label><span class="fnote">How this relates to drafts is for the Publishing workflow ticket.</span></div></div>`;
}
function snippetInfoHTML(e) {
  const cur = currentPlacement(e);
  return `<div class="sec"><div class="sec-h">${icon('plug')}Context</div><div>${e.context ? `<b>Product pages.</b> It needs <code>product</code>, so it can only go where a product is available.` : '<b>Any page.</b> It needs no host-page context.'}</div></div>
    <div class="sec"><div class="sec-h">${icon('map marker alternate')}Placements</div>${(e.placements || []).map(p => `<a class="kid ${cur?.id === p.id ? 'on' : ''}" data-act="placement" data-id="${p.id}">${icon(cur?.id === p.id ? 'eye' : 'map marker alternate')}<span><b>${esc(SLOTS[p.slot].label)}</b><br><span class="muted">${esc(scopeLabel(p.scope))}</span></span></a>`).join('') || '<div class="muted small">Not placed anywhere. It previews on a plain page.</div>'}
      <div class="fnote">Placements take effect on save, without drafts. Where editors manage them is for the Admin information architecture ticket.</div></div>`;
}
function entrySettingsHTML(e) {
  return fieldsHTML({ f: 'entry', key: '' }, ENTRY_FIELDS[e.contentType] || [], e.fields) + translationsHTML(e) + (e.contentType === 'snippet' ? snippetInfoHTML(e) : '') + channelsHTML(e);
}

/* Tree outline: A's left pane and B's Layers panel. */
function treeHTML() {
  const e = entry();
  const node = (b, depth) => {
    const d = BT[b.type], kids = b.children, col = S.ui.collapsed[b.key], hid = hiddenBy(b), fl = blockFlags(b), lw = linkWarn(b);
    const sel = S.selected === b.key || S.multi.includes(b.key);
    let h = `<div class="trow ${sel ? 'sel' : ''} ${S.selected === b.key ? 'primary' : ''} ${hid ? 'hid' : ''}" style="--d:${depth}" data-act="select" data-key="${b.key}" draggable="true" data-drag="${b.key}" data-drop="${b.key}">
      <span class="grip">⠿</span><span class="tw">${kids && kids.length ? `<a data-act="collapse" data-key="${b.key}">${icon(col ? 'caret right' : 'caret down')}</a>` : ''}</span>${icon(d.icon)}<span class="tl">${esc(d.label)}</span><span class="ts" data-summary="${b.key}">${esc(summary(b))}</span>
      <span class="tb">${hid ? `<span title="${esc(hid)}">${icon('eye slash')}</span>` : ''}${lw ? `<span class="t-out" title="${esc(lw)}">${icon('exclamation triangle')}</span>` : ''}${fl.o ? '<span class="dot out" title="Outdated translation"></span>' : fl.m ? '<span class="dot miss" title="Untranslated"></span>' : ''}${b.type === 'snippetRef' ? `<span title="Shared snippet">${icon('clone outline')}</span>` : ''}</span>
      <a class="tadd" data-act="insert-after" data-key="${b.key}" title="Add a block after this one">${icon('plus')}</a></div>`;
    if (kids && !col) {
      h += kids.map(c => node(c, depth + 1)).join('');
      if (canAddTo(b)) h += `<div class="trow add" style="--d:${depth + 1}" data-act="insert" data-parent="${b.key}" data-index="${kids.length}" data-drop-end="${b.key}">${icon('plus')}Add ${esc(addLabel(b))}</div>`;
    }
    return h;
  };
  return `<div class="tree"><div class="trow entry ${!S.selected && !S.multi.length ? 'sel primary' : ''}" data-act="select-entry"><span class="grip"></span>${icon('cog')}<span class="tl">${esc(CT_LABEL[e.contentType])} settings</span></div>
    ${e.tree.map(b => node(b, 0)).join('')}
    <div class="trow add" style="--d:0" data-act="insert" data-parent="" data-index="${e.tree.length}" data-drop-end="">${icon('plus')}Add ${e.contentType === 'menu' ? 'menu item' : 'block'}</div></div>`;
}

/* Toolbar pieces */
function localeSwitcher() {
  const e = entry();
  const cur = LOCALES.find(l => l.code === S.locale);
  const dot = (code) => {
    const has = e.translations.includes(code);
    if (!has) return '<span class="dot none" title="No translation"></span>';
    if (code === BASE_LOCALE) return '';
    const st = fieldStates(e, code);
    return st.outdated ? '<span class="dot out"></span>' : st.missing ? '<span class="dot miss"></span>' : '<span class="dot ok"></span>';
  };
  const items = LOCALES.map(l => {
    const has = e.translations.includes(l.code), st = fieldStates(e, l.code);
    const desc = !has ? 'No translation' : l.code === BASE_LOCALE ? 'Source language' : (st.missing || st.outdated) ? [st.missing ? `${st.missing} untranslated` : '', st.outdated ? `${st.outdated} outdated` : ''].filter(Boolean).join(' · ') : 'Complete';
    const cls = !has ? 'muted' : st.outdated && l.code !== BASE_LOCALE ? 't-out' : st.missing && l.code !== BASE_LOCALE ? 't-miss' : 't-ok';
    return `<a class="pop-i ${l.code === S.locale ? 'on' : ''}" data-act="locale" data-v="${l.code}"><span class="lc">${l.short}</span>${esc(l.name)}<small class="${cls}">${desc}</small></a>`;
  }).join('');
  return `<span class="popw"><button class="btn tbtn" data-toggle="pop-locale" title="Editing language">${icon('language')}<span class="lc">${cur.short}</span>${esc(cur.name)} ${dot(S.locale)} ${icon('caret down')}</button><div class="pop" id="pop-locale"><div class="pop-h">Edit and preview in</div>${items}</div></span>`;
}
function channelSwitcher() {
  return `<label class="tsel" title="The channel the preview renders for">${icon('shopping cart')}<select data-change="channel">${CHANNELS.map(c => opt(c.code, c.name, c.code === S.channel)).join('')}</select></label>`;
}
function contextControls() {
  const e = entry();
  if (e.contentType !== 'snippet') return '';
  const pl = currentPlacement(e);
  const sp = sampleProduct(e);
  const plSel = (e.placements || []).length ? `<select data-change="placement" title="Placement to preview">${e.placements.map(p => opt(p.id, `${SLOTS[p.slot].label} · ${scopeLabel(p.scope)}`, p.id === pl?.id)).join('')}</select>` : '<span class="muted">No placements</span>';
  const sample = e.context === 'product' ? `<select data-change="sample" title="Sample product">${PRODUCTS.map(p => opt(p.code, (inScope(p, pl?.scope) ? '✓ ' : ' ') + pName(p), p.code === sp.code)).join('')}</select>` : '';
  return `<span class="ctxbar"><span class="ctx-chip" title="The context this snippet declares">${icon('plug')}${e.context ? 'Product pages' : 'Any page'}</span>${plSel}${sample}</span>`;
}
function deviceToggle() {
  return `<span class="seg-btns"><button class="btn icon-only ${S.device === 'desktop' ? 'on' : ''}" data-act="device" data-v="desktop" title="Desktop">${icon('desktop')}</button><button class="btn icon-only ${S.device === 'mobile' ? 'on' : ''}" data-act="device" data-v="mobile" title="Mobile">${icon('mobile alternate')}</button></span>`;
}
const undoBtn = () => `<button class="btn tbtn icon-only" data-act="undo" title="Undo (⌘Z)" ${S.undo.length ? '' : 'disabled'}>${icon('undo')}</button>`;
const saveBtn = (label = 'Save') => `<button class="btn tbtn primary" data-act="save">${icon('save')}${label}</button>`;
function entryTitle(e) { return e.contentType === 'page' ? (trResolve(e.fields.title, S.locale).value || e.name) : e.name; }
function banners() {
  const e = entry();
  let h = '';
  if (S.ui.back && S.entries[S.ui.back] && S.ui.back !== e.uuid) h += `<div class="banner info">${icon('clone outline')}You're editing the shared snippet “${esc(e.name)}”. Changes show in all ${e.usedIn || 1} places it's used. <a data-act="back">${icon('arrow left')}Back to ${esc(S.entries[S.ui.back].name)}</a></div>`;
  if (!e.translations.includes(S.locale)) {
    const what = e.contentType === 'page' ? `Shoppers browsing in ${localeName(S.locale)} get a 404 for this page.` : `It renders nothing for shoppers browsing in ${localeName(S.locale)}.`;
    h += `<div class="banner warn">${icon('exclamation triangle')}<b>No ${localeName(S.locale)} translation.</b> ${what} The fields show fallback text. <button class="btn sm" data-act="add-translation" data-v="${S.locale}">Add the ${localeName(S.locale)} translation</button></div>`;
  }
  return h;
}
function browserBar() {
  const e = entry();
  let url = `/${S.locale}/`;
  if (e.contentType === 'page') url = `/${S.locale}/${trResolve(e.fields.slug, S.locale).value}`;
  if (e.contentType === 'snippet' && e.context === 'product') url = `/${S.locale}/products/${sampleProduct().code.toLowerCase().replace(/_/g, '-')}`;
  const host = S.channel === 'NORDIC' ? 'nordic.example.com' : 'shop.example.com';
  return `<div class="bbar"><span class="d"></span><span class="d"></span><span class="d"></span><span class="url">${host}${esc(url)}</span><span class="muted">draft preview</span></div>`;
}
