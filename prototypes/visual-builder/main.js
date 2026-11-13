/* PROTOTYPE wiring (throwaway): render loop, events, actions, modals and the floating switcher bar. */

/* ---------- render ---------- */
function render() {
  const sc = {};
  document.querySelectorAll('[data-scroll]').forEach(el => { sc[el.dataset.scroll] = el.scrollTop; });
  const focus = focusSig();
  document.getElementById('app').innerHTML = VARIANTS.find(v => v.k === S.variant).fn();
  renderOverlay();
  document.querySelectorAll('[data-scroll]').forEach(el => { if (sc[el.dataset.scroll] != null) el.scrollTop = sc[el.dataset.scroll]; });
  if (S.ui.scrollTo) {
    const k = S.ui.scrollTo; S.ui.scrollTo = null;
    requestAnimationFrame(() => document.querySelectorAll(`[data-pk="${k}"], .trow[data-key="${k}"], [data-card="${k}"]`).forEach(el => el.scrollIntoView({ block: 'nearest' })));
  }
  restoreFocus(focus);
  S.dirty = false;
  syncURL();
}
function renderOverlay() {
  document.getElementById('overlay').innerHTML = modalHTML() + (S.ui.toast ? `<div class="toast">${esc(S.ui.toast)}</div>` : '') + (S.ui.json ? jsonHTML() : '');
  document.getElementById('protobar').innerHTML = protobarHTML();
}
/* After typing: refresh what depends on the value without touching the field being typed in. */
function soft() {
  const a = document.activeElement;
  document.querySelectorAll('[data-region="preview"]').forEach(el => { if (!el.contains(a)) el.innerHTML = previewHTML(S.variant); });
  document.querySelectorAll('[data-summary]').forEach(el => { const f = find(el.dataset.summary); if (f) el.textContent = summary(f.block); });
  document.querySelectorAll('.tb-name').forEach(el => { el.textContent = entryTitle(entry()); });
  const pre = document.querySelector('.jsonp pre');
  if (pre) pre.textContent = entryJSON();
}
function focusSig() {
  const a = document.activeElement;
  if (!a || a === document.body) return null;
  if (a.id) return { sel: '#' + a.id };
  if (!a.dataset?.f) return null;
  let sel = `[data-f="${a.dataset.f}"][data-key="${a.dataset.key}"][data-field="${a.dataset.field}"]`;
  sel += a.dataset.path ? `[data-path="${a.dataset.path}"]` : ':not([data-path])';
  sel += a.dataset.inline ? '[data-inline]' : ':not([data-inline])';
  return { sel, start: a.selectionStart, end: a.selectionEnd };
}
function restoreFocus(f) {
  if (!f) return;
  const el = document.querySelector(f.sel);
  if (!el) return;
  el.focus({ preventScroll: true });
  try { if (f.start != null && el.setSelectionRange) el.setSelectionRange(f.start, f.end); } catch (e) { /* not a text field */ }
}
function syncURL() {
  try { const u = new URL(location.href); u.searchParams.set('variant', S.variant); u.searchParams.set('entry', S.entryId); history.replaceState(null, '', u); } catch (e) { /* file:// may refuse */ }
}

/* ---------- field writes ---------- */
function ownerOf(el) {
  if (el.dataset.f === 'entry') return entry().fields;
  const f = find(el.dataset.key);
  return f && f.block.fields;
}
function setFieldFromEl(el) {
  const owner = ownerOf(el);
  if (!owner) return;
  const name = el.dataset.field, path = el.dataset.path;
  let raw;
  if (el.type === 'checkbox') raw = el.checked;
  else if (el.type === 'number') raw = Math.max(1, parseInt(el.value || '1', 10) || 1);
  else if (el.isContentEditable) raw = el.dataset.inline === 'text' ? el.textContent : cleanRich(el.innerHTML);
  else raw = el.value;
  if (path) return setPath(owner, name, path, raw);
  const cur = owner[name];
  if (isTr(cur)) trSet(cur, S.locale, raw); else owner[name] = raw;
}
const defaultRef = t => ({ taxon: 'women', product: PRODUCTS[0].code, page: 'e-summer', url: '' }[t]);
function setPath(owner, name, path, raw) {
  const v = owner[name];
  if (path === 'fallback') { v.fallback = raw; return; }
  if (path === 'source') { owner[name] = { source: raw, config: sourceDefaults(raw) }; return; }
  if (path === 'type') { owner[name] = raw ? { type: raw, ref: defaultRef(raw) } : null; return; }
  if (path === 'ref') { (owner[name] ??= { type: 'url', ref: '' }).ref = raw; return; }
  if (path.startsWith('config.')) v.config[path.slice(7)] = raw;
}
function syncSiblings(el) {
  if (el.dataset.path) return;
  const owner = ownerOf(el);
  const v = owner?.[el.dataset.field];
  const val = isTr(v) ? (v.translations[S.locale] || '') : v;
  if (typeof val !== 'string') return;
  document.querySelectorAll(`[data-f="${el.dataset.f}"][data-key="${el.dataset.key}"][data-field="${el.dataset.field}"]:not([data-path])`).forEach(o => {
    if (o === el || o.closest('.pv')) return;
    if (o.isContentEditable) o.innerHTML = editorRich(val);
    else if (o.tagName === 'INPUT' && o.type !== 'checkbox') o.value = val;
  });
}
function handleFieldInput(el) { snapshotOnce(); setFieldFromEl(el); S.dirty = true; syncSiblings(el); soft(); }

function insertToken(path, target) {
  const [key, field] = target.split('.');
  let el = document.activeElement;
  if (!(el && el.isContentEditable && el.dataset.key === key && el.dataset.field === field)) {
    el = document.querySelector(`.rte-body[data-key="${key}"][data-field="${field}"]`);
    if (!el) return;
    el.focus();
    const r = document.createRange(); r.selectNodeContents(el); r.collapse(false);
    const s = getSelection(); s.removeAllRanges(); s.addRange(r);
  }
  const label = el.closest('.pv') ? (bindValue(path, ctxFor('B')) || bindLabel(path)) : bindLabel(path);
  document.execCommand('insertHTML', false, `<span contenteditable="false" data-bind="${path}">${esc(label)}</span>&nbsp;`);
  handleFieldInput(el);
}

/* ---------- selection ---------- */
function selectBlock(key, additive) {
  if (!find(key)) return;
  if (additive && S.selected && key !== S.selected) {
    const i = S.multi.indexOf(key);
    if (i >= 0) S.multi.splice(i, 1); else S.multi.push(key);
  } else { S.selected = key; S.multi = []; }
  S.ui.entrySettings = false;
  pathTo(key).forEach(b => { if (b.key !== key) delete S.ui.collapsed[b.key]; S.ui.expanded[b.key] = true; });
  if (entry().contentType === 'menu') { const top = pathTo(key)[0]; if (top?.type === 'menuGroup') S.ui.openGroup = top.key; }
  S.ui.scrollTo = key;
  render();
}
function goEntry(id, back = null) {
  S.entryId = id; S.ui.back = back; S.selected = null; S.multi = []; S.ui.openGroup = null; S.ui.entrySettings = false;
  render();
}
function openPicker(parentKey, index) {
  const parent = parentKey ? find(parentKey).block : null;
  if (!canAddTo(parent)) return toast(`${BT[parent.type].label} holds at most ${BT[parent.type].max}.`);
  const types = allowedTypes(parent);
  if (parent && types.length === 1) { // only one thing fits: skip the picker
    snapshot();
    const b = newBlock(types[0]);
    insertAt(parentKey, index, [b]);
    return selectBlock(b.key);
  }
  S.ui.modal = { kind: 'picker', parent: parentKey || null, index, tab: 'blocks' };
  render();
  setTimeout(() => document.querySelector('[data-picker-search]')?.focus(), 0);
}

/* ---------- actions ---------- */
const ACTIONS = {
  noop() {},
  select: (d, e) => selectBlock(d.key, e.metaKey || e.ctrlKey || e.shiftKey),
  'select-entry'() { S.selected = null; S.multi = []; S.ui.entrySettings = true; render(); },
  'entry-settings'() { S.selected = null; S.multi = []; S.ui.entrySettings = true; S.ui.drawer = true; render(); },
  'clear-multi'() { S.multi = []; render(); },
  collapse(d) { S.ui.collapsed[d.key] = !S.ui.collapsed[d.key]; render(); },
  'card-toggle'(d, e) {
    if (e.metaKey || e.ctrlKey || e.shiftKey) return selectBlock(d.key, true);
    if (S.selected === d.key) { S.ui.expanded[d.key] = !S.ui.expanded[d.key]; render(); } else selectBlock(d.key, false);
  },
  'expand-all'() { walk(entry().tree, b => { S.ui.expanded[b.key] = true; }); render(); },
  'collapse-all'() { S.ui.expanded = {}; render(); },
  insert: d => openPicker(d.parent || null, +d.index),
  'insert-after'(d) { const f = find(d.key); openPicker(f.parent?.key || null, f.index + 1); },
  'picker-tab'(d) { S.ui.modal.tab = d.v; render(); },
  pick(d) {
    const m = S.ui.modal;
    snapshot();
    const b = newBlock(d.type);
    insertAt(m.parent, m.index, [b]);
    S.ui.modal = null;
    selectBlock(b.key);
  },
  'pick-preset'(d) {
    const m = S.ui.modal, p = S.presets.find(x => x.id === d.id);
    snapshot();
    const copies = p.blocks.map(cloneNewKeys);
    insertAt(m.parent, m.index, copies);
    S.ui.modal = null;
    toast(`Inserted a copy of the preset “${p.name}”. Changes to the preset won't reach it.`);
    selectBlock(copies[0].key);
  },
  up(d) { const f = find(d.key); if (f.index === 0) return; snapshot(); f.list.splice(f.index, 1); f.list.splice(f.index - 1, 0, f.block); S.ui.scrollTo = d.key; render(); },
  down(d) { const f = find(d.key); if (f.index === f.list.length - 1) return; snapshot(); f.list.splice(f.index, 1); f.list.splice(f.index + 1, 0, f.block); S.ui.scrollTo = d.key; render(); },
  dup(d) {
    const f = find(d.key);
    if (!canAddTo(f.parent)) return toast(`${BT[f.parent.type].label} holds at most ${BT[f.parent.type].max}.`);
    snapshot();
    const c = cloneNewKeys(f.block);
    f.list.splice(f.index + 1, 0, c);
    selectBlock(c.key);
  },
  del(d) {
    const f = find(d.key), p = f.parent, pd = p && BT[p.type];
    if (pd?.min && p.children.length <= pd.min) return toast(`${pd.label} needs at least ${pd.min} ${addLabel(p)}${pd.min > 1 ? 's' : ''}.`);
    snapshot();
    f.list.splice(f.index, 1);
    S.selected = p ? p.key : null; S.multi = [];
    render();
    toast(`Deleted ${BT[f.block.type].label}. Undo with ⌘Z.`);
  },
  'preset-open'(d) {
    const keys = d.key ? [d.key] : selectionKeys();
    if (!keys.length) return;
    const one = keys.length === 1 && find(keys[0]).block;
    S.ui.modal = { kind: 'savePreset', keys, name: one ? trunc(summary(one) || BT[one.type].label, 30) : '' };
    render();
    setTimeout(() => document.getElementById('preset-name')?.select(), 0);
  },
  'preset-save'() {
    const name = document.getElementById('preset-name').value.trim() || 'Untitled preset';
    const blocks = S.ui.modal.keys.map(k => cloneNewKeys(find(k).block));
    snapshot();
    S.presets.push({ id: 'pr-' + Date.now(), name, origin: 'admin', desc: `Saved from ${entry().name}.`, blocks });
    S.ui.modal = null;
    S.multi = [];
    render();
    toast(`Saved the preset “${name}”. It's under Presets when you add a block.`);
  },
  detach(d) {
    const f = find(d.key), s = S.entries[f.block.fields.snippet];
    snapshot();
    const copies = s.tree.map(cloneNewKeys);
    f.list.splice(f.index, 1, ...copies);
    S.selected = copies[0]?.key || null;
    render();
    toast(`Detached. The blocks of “${s.name}” are now a copy in this ${CT_LABEL[entry().contentType].toLowerCase()}, and edits to the snippet no longer reach them.`);
  },
  'open-entry'(d) { const from = S.entryId; goEntry(d.id, from); },
  back() { goEntry(S.ui.back); },
  placement(d) { S.placement[S.entryId] = d.id; delete S.sample[S.entryId]; render(); },
  locale(d) { S.locale = d.v; render(); },
  'add-translation'(d) { snapshot(); entry().translations.push(d.v); render(); toast(`Added the ${localeName(d.v)} translation. Empty fields fall back until they're translated.`); },
  device(d) { S.device = d.v; render(); },
  undo: () => undo(),
  save() { toast('Saved. (Prototype: nothing is stored. Drafts and publishing belong to the Publishing workflow ticket.)'); },
  'close-modal'() { S.ui.modal = null; render(); },
  'media-open'(d) { S.ui.modal = { kind: 'media', f: d.f, key: d.key, field: d.field }; render(); },
  'media-pick'(d) {
    const m = S.ui.modal, owner = ownerOf({ dataset: m });
    snapshot();
    if (isTr(owner[m.field])) trSet(owner[m.field], S.locale, d.id); else owner[m.field] = d.id;
    S.ui.modal = null;
    render();
  },
  'media-clear'(d) {
    const owner = ownerOf({ dataset: d });
    snapshot();
    if (isTr(owner[d.field])) delete owner[d.field].translations[S.locale]; else owner[d.field] = null;
    render();
  },
  'pick-remove'(d) { snapshot(); ownerOf({ dataset: d })[d.field].config.items.splice(+d.i, 1); render(); },
  bind(d) {
    const owner = ownerOf({ dataset: d });
    snapshot();
    S.ui.unbound[d.key + '.' + d.field] = owner[d.field];
    owner[d.field] = { binding: d.prop, fallback: '' };
    render();
  },
  unbind(d) {
    const owner = ownerOf({ dataset: d });
    snapshot();
    owner[d.field] = S.ui.unbound[d.key + '.' + d.field] || T();
    render();
  },
  'mark-current'(d) { const v = ownerOf({ dataset: d })[d.field]; snapshot(); v.translatedFrom[S.locale] = { locale: BASE_LOCALE, fingerprint: fp(v.translations[BASE_LOCALE]) }; render(); },
  'copy-src'(d) { const v = ownerOf({ dataset: d })[d.field]; snapshot(); trSet(v, S.locale, v.translations[BASE_LOCALE]); render(); },
  'cond-add'(d) { const b = find(d.key).block; snapshot(); (b.conditions ??= []).push({ kind: d.kind, values: [d.kind === 'channel' ? S.channel : S.locale] }); render(); },
  'cond-del'(d) { const b = find(d.key).block; snapshot(); b.conditions.splice(+d.i, 1); render(); },
  'toggle-layers'() { S.ui.layers = !S.ui.layers; render(); },
  'drawer-open'() { S.ui.drawer = true; render(); },
  'drawer-close'() { S.ui.drawer = false; S.ui.entrySettings = false; render(); },
  'toggle-preview'() { S.ui.preview = !S.ui.preview; render(); },
  'new-page'(d) {
    snapshot();
    const n = Object.keys(S.entries).filter(k => k.startsWith('e-new')).length + 1;
    const p = d.id === 'blank' ? null : S.presets.find(x => x.id === d.id);
    const id = 'e-new-' + n;
    const name = p ? `${p.name} (new page)` : 'Untitled page';
    S.entries[id] = { uuid: id, contentType: 'page', code: 'new-page-' + n, name, channels: CHANNELS.map(c => c.code), enabled: false, translations: ['en_US'], fields: { title: T(p ? p.name : 'Untitled page'), slug: T('new-page-' + n), layout: 'Default' }, tree: p ? p.blocks.map(cloneNewKeys) : [] };
    S.ui.modal = null;
    goEntry(id);
    toast(p ? `New page started from the preset “${p.name}”. The blocks are a copy; the preset stays as it was.` : 'New blank page.');
  },
  'proto-prev'() { cycle(-1); },
  'proto-next'() { cycle(1); },
  'proto-json'() { S.ui.json = !S.ui.json; renderOverlay(); },
  'proto-reset'() { const v = S.variant; S = freshState(); S.variant = v; render(); toast('Reset all data.'); },
};
const CHANGES = {
  scenario(t) { if (t.value === 'new') { S.ui.modal = { kind: 'newPage' }; render(); return; } goEntry(t.value); },
  channel(t) { S.channel = t.value; render(); },
  placement(t) { S.placement[S.entryId] = t.value; delete S.sample[S.entryId]; render(); },
  sample(t) { S.sample[S.entryId] = t.value; render(); },
  'pick-add'(t) { if (!t.value) return; snapshot(); ownerOf(t)[t.dataset.field].config.items.push(t.value); render(); },
  'cond-toggle'(t) { snapshot(); const c = find(t.dataset.key).block.conditions[+t.dataset.i]; c.values = t.checked ? [...c.values, t.value] : c.values.filter(v => v !== t.value); render(); },
  'entry-channel'(t) { snapshot(); const e = entry(); e.channels = t.checked ? [...e.channels, t.value] : e.channels.filter(c => c !== t.value); render(); },
  'entry-enabled'(t) { snapshot(); entry().enabled = t.checked; render(); },
};
function cycle(dir) {
  const i = VARIANTS.findIndex(v => v.k === S.variant);
  S.variant = VARIANTS[(i + dir + VARIANTS.length) % VARIANTS.length].k;
  render();
}

/* ---------- modals ---------- */
function thumbSVG(t) {
  const r = (x, y, w, h, f = '#c9d3dd') => `<rect x="${x}" y="${y}" width="${w}" height="${h}" rx="2" fill="${f}"/>`;
  const s = {
    hero: r(0, 0, 80, 48, '#9fb6cc') + r(8, 14, 36, 6, '#fff') + r(8, 24, 28, 4, '#e8eef4') + r(8, 32, 18, 7, '#2185d0'),
    heading: r(8, 19, 56, 9, '#7d8b99'),
    richText: [10, 18, 26, 34].map(y => r(8, y, y === 34 ? 40 : 64, 4)).join(''),
    image: r(10, 6, 60, 36, '#b7c9d9') + '<circle cx="26" cy="18" r="5" fill="#fff"/>',
    columns: r(6, 8, 32, 32) + r(42, 8, 32, 32),
    productCarousel: [6, 25, 44, 63].map(x => r(x, 10, 14, 20, '#b7c9d9') + r(x, 33, 14, 3)).join(''),
    bannerCarousel: r(4, 6, 72, 30, '#9fb6cc') + [34, 40, 46].map((x, i) => `<circle cx="${x}" cy="42" r="2" fill="${i ? '#c9d3dd' : '#2185d0'}"/>`).join(''),
    blockCarousel: r(4, 10, 22, 28) + r(29, 10, 22, 28) + r(54, 10, 22, 28),
    uspBar: [6, 32, 58].map(x => `<circle cx="${x + 3}" cy="24" r="3" fill="#21ba45"/>` + r(x + 8, 22, 12, 4)).join(''),
    snippetRef: r(10, 10, 60, 28, '#e3f0fb') + '<rect x="10" y="10" width="60" height="28" rx="2" fill="none" stroke="#2185d0" stroke-dasharray="3 2"/>',
    productFacts: [12, 20, 28, 36].map(y => r(8, y, 24, 4) + r(36, y, 36, 4, '#e1e6eb')).join(''),
    menuLink: r(10, 20, 40, 6),
    menuGroup: r(8, 8, 30, 5, '#7d8b99') + [18, 26, 34].map(y => r(14, y, 30, 4)).join(''),
    menuPromo: r(14, 6, 52, 26, '#9fb6cc') + r(14, 36, 36, 4),
  }[t] || r(10, 10, 60, 28);
  return `<svg viewBox="0 0 80 48" width="100%" height="100%">${s}</svg>`;
}
function presetThumb(p) {
  return `<svg viewBox="0 0 40 48" width="100%" height="100%">${p.blocks.slice(0, 4).map((b, i) => `<rect x="2" y="${2 + i * 11.5}" width="36" height="9.5" rx="1.5" fill="${['#9fb6cc', '#c9d3dd', '#b7c9d9', '#dde4ea'][i]}"/>`).join('')}</svg>`;
}
function pickerHTML(m) {
  const parent = m.parent ? find(m.parent).block : null;
  const types = allowedTypes(parent);
  const excl = excludedByContext(parent);
  const where = parent ? BT[parent.type].label : CT_LABEL[entry().contentType];
  const presets = S.presets.filter(p => p.blocks.every(b => canContain(parent, b.type)));
  const tab = m.tab || 'blocks';
  const blocks = `<div class="pgrid">${types.map(t => `<a class="pcard" data-act="pick" data-type="${t}" data-search="${esc((BT[t].label + ' ' + BT[t].desc).toLowerCase())}"><div class="thumb">${thumbSVG(t)}</div><b>${esc(BT[t].label)}</b><small>${esc(BT[t].desc)}</small></a>`).join('')}</div>
    ${excl.length ? `<div class="fnote">${icon('plug')}Not offered here: ${excl.map(t => esc(BT[t].label)).join(', ')}. ${excl.length === 1 ? 'It needs' : 'They need'} a product page, so ${excl.length === 1 ? 'it works' : 'they work'} in snippets declared for product pages.</div>` : ''}`;
  const pres = `<div class="plist">${presets.map(p => `<a class="prow" data-act="pick-preset" data-id="${p.id}" data-search="${esc(p.name.toLowerCase())}"><div class="thumb">${presetThumb(p)}</div><div><b>${esc(p.name)}</b> <span class="bdg ${p.origin}">${p.origin === 'code' ? 'Shipped in code' : 'Saved by an editor'}</span><small>${esc(p.desc)} ${p.blocks.length} block${p.blocks.length > 1 ? 's' : ''}.</small></div></a>`).join('') || '<div class="muted">No presets fit here.</div>'}
    <div class="fnote">Inserting a preset copies its blocks. Later changes to the preset don't reach this ${CT_LABEL[entry().contentType].toLowerCase()}.</div></div>`;
  return `<div class="modal-h"><b>Add to ${esc(where)}</b><a class="x" data-act="close-modal">${icon('times')}</a></div>
    <div class="tabs"><a class="${tab === 'blocks' ? 'on' : ''}" data-act="picker-tab" data-v="blocks">Blocks <span class="cnt">${types.length}</span></a><a class="${tab === 'presets' ? 'on' : ''}" data-act="picker-tab" data-v="presets">Presets <span class="cnt">${presets.length}</span></a><input class="inp sm search" placeholder="Search…" data-picker-search></div>
    <div class="modal-b">${tab === 'blocks' ? blocks : pres}</div>`;
}
function savePresetHTML(m) {
  const blocks = m.keys.map(k => find(k)?.block).filter(Boolean);
  return `<div class="modal-h"><b>Save as preset</b><a class="x" data-act="close-modal">${icon('times')}</a></div><div class="modal-b">
    <label class="sublabel">Name</label><input class="inp" id="preset-name" value="${esc(m.name || '')}" placeholder="e.g. Summer hero">
    <div class="sublabel" style="margin-top:12px">Includes</div><ul class="inc">${blocks.map(b => `<li>${icon(BT[b.type].icon)}${esc(BT[b.type].label)} <span class="muted">${esc(summary(b))}</span>${b.children ? ` <span class="muted">· ${countBlocks(b.children)} inside</span>` : ''}</li>`).join('')}</ul>
    <div class="fnote">Saves a copy, translations included. Editing the preset later won't change places it was inserted, and editing this ${CT_LABEL[entry().contentType].toLowerCase()} won't change the preset.</div>
    <div class="modal-f"><button class="btn" data-act="close-modal">Cancel</button><button class="btn primary" data-act="preset-save">${icon('star outline')}Save preset</button></div></div>`;
}
function mediaModalHTML() {
  return `<div class="modal-h"><b>Choose an image</b><a class="x" data-act="close-modal">${icon('times')}</a></div><div class="modal-b">
    <div class="mgrid">${MEDIA.map(m => `<a data-act="media-pick" data-id="${m.uuid}"><img src="${ph(m.label, m.hue, 200, 120)}" alt=""><span>${esc(m.name)}</span></a>`).join('')}</div>
    <div class="fnote">A stand-in. Folders, focal points and usage belong to the Media library ticket.</div></div>`;
}
function newPageHTML() {
  const presets = S.presets.filter(p => p.blocks.every(b => ROOT_TYPES.page.includes(b.type)));
  return `<div class="modal-h"><b>New page: start from…</b><a class="x" data-act="close-modal">${icon('times')}</a></div><div class="modal-b">
    <div class="pgrid"><a class="pcard" data-act="new-page" data-id="blank"><div class="thumb"><svg viewBox="0 0 80 48" width="100%" height="100%"><rect x="10" y="8" width="60" height="32" rx="2" fill="none" stroke="#c9d3dd" stroke-dasharray="3 2"/></svg></div><b>Blank page</b><small>Start with no blocks.</small></a>
    ${presets.map(p => `<a class="pcard" data-act="new-page" data-id="${p.id}"><div class="thumb">${presetThumb(p)}</div><b>${esc(p.name)}</b><small><span class="bdg ${p.origin}">${p.origin === 'code' ? 'Shipped in code' : 'Saved by an editor'}</span> ${esc(p.desc)}</small></a>`).join('')}</div>
    <div class="fnote">The page gets a copy of the preset's blocks. It never syncs back.</div></div>`;
}
function modalHTML() {
  const m = S.ui.modal;
  if (!m) return '';
  const inner = { picker: pickerHTML, savePreset: savePresetHTML, media: mediaModalHTML, newPage: newPageHTML }[m.kind](m);
  return `<div class="modal-bg" data-act="close-modal"><div class="modal ${m.kind}" data-act="noop">${inner}</div></div>`;
}
function filterPicker(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('.modal [data-search]').forEach(el => { el.style.display = !q || el.dataset.search.includes(q) ? '' : 'none'; });
}

/* ---------- prototype chrome ---------- */
const SCENARIOS = { 'e-summer': 'Page · Summer sale', 'e-sizefit': 'Snippet for product pages · Size & fit', 'e-usp': 'Snippet on every page · USP bar', 'e-menu': 'Menu · Main menu' };
function protobarHTML() {
  const v = VARIANTS.find(x => x.k === S.variant);
  const ids = [...Object.keys(SCENARIOS), ...Object.keys(S.entries).filter(k => k.startsWith('e-new'))];
  if (!ids.includes(S.entryId)) ids.push(S.entryId);
  return `<div class="pbar"><span class="pbar-tag">PROTOTYPE</span>
    <button data-act="proto-prev" title="Previous variant (←)">◀</button><div class="pbar-label"><b>${v.k}</b> · ${esc(v.name)}</div><button data-act="proto-next" title="Next variant (→)">▶</button>
    <span class="sep"></span><select data-change="scenario" title="What to edit">${ids.map(id => opt(id, SCENARIOS[id] || `Page · ${S.entries[id].name}`, id === S.entryId)).join('')}<option value="new">+ New page from a preset…</option></select>
    <button data-act="proto-json" class="${S.ui.json ? 'on' : ''}" title="Show the stored JSON of this entry">{ } JSON</button><button data-act="proto-reset" title="Reset all data">↺</button></div>`;
}
function entryJSON() {
  const e = entry();
  const o = { uuid: e.uuid, contentType: e.contentType, code: e.code, enabled: e.enabled, channels: e.channels, translations: e.translations };
  if (e.contentType === 'snippet') { o.context = e.context ? [e.context] : []; o.placements = e.placements; }
  o.fields = e.fields;
  o.blockTree = e.tree;
  return JSON.stringify(o, null, 2);
}
function jsonHTML() {
  return `<div class="jsonp"><div class="jsonp-h"><span>Stored entry: what the server would save</span><a data-act="proto-json">${icon('times')}</a></div><pre>${esc(entryJSON())}</pre></div>`;
}

/* ---------- events ---------- */
function togglePop(id) { const p = document.getElementById(id); const was = p?.classList.contains('open'); closePops(); if (p && !was) p.classList.add('open'); }
function closePops() { document.querySelectorAll('.pop.open').forEach(p => p.classList.remove('open')); }

document.addEventListener('mousedown', e => {
  // keep the text selection in a rich-text field while using its toolbar or the binding menu
  if (e.target.closest('.rte-bar, .pop')) e.preventDefault();
});

document.addEventListener('click', e => {
  const tg = e.target.closest('[data-toggle]');
  if (tg) { e.preventDefault(); togglePop(tg.dataset.toggle); return; }
  const tok = e.target.closest('[data-token]');
  if (tok) { e.preventDefault(); insertToken(tok.dataset.token, tok.dataset.target); closePops(); return; }
  const cmd = e.target.closest('[data-cmd]');
  if (cmd) {
    e.preventDefault();
    const c = cmd.dataset.cmd;
    document.execCommand(c === 'link' ? 'createLink' : c, false, c === 'link' ? '#' : null);
    const el = document.activeElement;
    if (el?.isContentEditable && el.dataset.f) handleFieldInput(el);
    return;
  }
  if (!e.target.closest('.pop')) closePops();

  const act = e.target.closest('[data-act]');
  const field = e.target.closest('input, select, textarea, label, [contenteditable="true"]');
  if (act && !(field && act.contains(field) && act !== field)) {
    e.preventDefault();
    ACTIONS[act.dataset.act]?.(act.dataset, e, act);
    return;
  }
  // click-to-select in the preview
  const pk = e.target.closest('.pv [data-pk]');
  if (pk && !act) {
    const key = pk.dataset.pk;
    const inline = e.target.closest('[data-inline]');
    if (inline && S.selected === key) return; // already selected: let the caret land
    const x = e.clientX, y = e.clientY;
    selectBlock(key, e.metaKey || e.ctrlKey || e.shiftKey);
    if (inline) {
      const el = document.querySelector(`.pv [data-inline][data-key="${inline.dataset.key}"][data-field="${inline.dataset.field}"]`);
      if (el) {
        el.focus();
        if (!el.dataset.fallback && document.caretRangeFromPoint) {
          const r = document.caretRangeFromPoint(x, y);
          if (r && el.contains(r.startContainer)) { const s = getSelection(); s.removeAllRanges(); s.addRange(r); }
        }
      }
    }
  }
});

document.addEventListener('focusin', e => {
  const t = e.target;
  if (t.dataset?.inline && t.dataset.fallback) { // typing replaces the fallback text
    const r = document.createRange(); r.selectNodeContents(t);
    const s = getSelection(); s.removeAllRanges(); s.addRange(r);
  }
});
document.addEventListener('focusout', () => { S.typingSnap = false; });
// After typing, refresh badges and counts once the next click has done its work.
document.addEventListener('pointerup', e => {
  if (!S.dirty || e.target.closest('input, select, textarea, [contenteditable="true"], .pop')) return;
  setTimeout(() => { if (S.dirty) render(); }, 0);
});

document.addEventListener('input', e => {
  const t = e.target;
  if (t.matches?.('[data-picker-search]')) return filterPicker(t.value);
  if (t.dataset?.f && t.tagName !== 'SELECT' && t.type !== 'checkbox') handleFieldInput(t);
});
document.addEventListener('change', e => {
  const t = e.target;
  if (t.dataset.change) { CHANGES[t.dataset.change]?.(t); return; }
  if (t.dataset.f && (t.tagName === 'SELECT' || t.type === 'checkbox')) { snapshot(); setFieldFromEl(t); render(); }
});

document.addEventListener('keydown', e => {
  const t = e.target;
  if (e.key === 'Escape') { if (S.ui.modal) { S.ui.modal = null; render(); return; } closePops(); }
  if (t.id === 'preset-name' && e.key === 'Enter') { e.preventDefault(); ACTIONS['preset-save'](); return; }
  if (t.dataset?.inline === 'text' && e.key === 'Enter') { e.preventDefault(); t.blur(); return; }
  const typing = t.closest?.('input, textarea, select, [contenteditable="true"]');
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z' && !e.shiftKey && !typing) { e.preventDefault(); undo(); return; }
  if (typing || S.ui.modal) return;
  if (e.key === 'ArrowLeft') cycle(-1);
  if (e.key === 'ArrowRight') cycle(1);
});

let hoverEl = null;
document.addEventListener('mouseover', e => {
  const el = e.target.closest?.('.pv [data-pk]') || null;
  if (el === hoverEl) return;
  hoverEl?.classList.remove('pv-hover');
  hoverEl = el;
  el?.classList.add('pv-hover');
});

/* Drag and drop on tree rows (A, B) and card headers (C). */
let dragging = null;
function clearDrop() { document.querySelectorAll('.drop-before, .drop-after, .drop-inside, .drop-no').forEach(el => el.classList.remove('drop-before', 'drop-after', 'drop-inside', 'drop-no')); }
function dropZone(e) {
  const end = e.target.closest('[data-drop-end]');
  const dragged = find(dragging).block;
  if (end) {
    const pk = end.dataset.dropEnd || null, parent = pk ? find(pk).block : null;
    const same = find(dragging).parent?.key === (pk || undefined) || (!pk && !find(dragging).parent);
    if (pk && (pk === dragging || isDescendant(pk, dragging))) return { el: end, ok: false };
    const ok = canContain(parent, dragged.type) && (same || canAddTo(parent));
    return { el: end, ok, mode: 'inside', parent: pk, index: listOf(pk).length };
  }
  const row = e.target.closest('[data-drop]');
  if (!row) return null;
  const tk = row.dataset.drop;
  if (tk === dragging || isDescendant(tk, dragging)) return { el: row, ok: false };
  const target = find(tk);
  const r = row.getBoundingClientRect();
  const y = (e.clientY - r.top) / r.height;
  const tb = target.block;
  if (y > 0.3 && y < 0.7 && BT[tb.type].children && canContain(tb, dragged.type)) {
    const ok = find(dragging).parent === tb || canAddTo(tb);
    return { el: row, ok, mode: 'inside', parent: tb.key, index: tb.children.length };
  }
  const after = y >= 0.5;
  const sameParent = find(dragging).parent === target.parent;
  const ok = canContain(target.parent, dragged.type) && (sameParent || canAddTo(target.parent));
  return { el: row, ok, mode: after ? 'after' : 'before', parent: target.parent?.key || null, index: target.index + (after ? 1 : 0) };
}
document.addEventListener('dragstart', e => {
  const el = e.target.closest?.('[data-drag]');
  if (!el) return;
  dragging = el.dataset.drag;
  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/plain', dragging);
});
document.addEventListener('dragover', e => {
  if (!dragging) return;
  clearDrop();
  const z = dropZone(e);
  if (!z) return;
  if (!z.ok) { z.el.classList.add('drop-no'); return; }
  e.preventDefault();
  z.el.classList.add('drop-' + z.mode);
});
document.addEventListener('drop', e => {
  if (!dragging) return;
  const z = dropZone(e);
  clearDrop();
  if (z?.ok) {
    e.preventDefault();
    snapshot();
    moveBlock(dragging, z.parent, z.index);
    const k = dragging; dragging = null;
    selectBlock(k);
  }
});
document.addEventListener('dragend', () => { dragging = null; clearDrop(); });

/* ---------- boot ---------- */
S = freshState();
(() => {
  const q = new URLSearchParams(location.search);
  if (VARIANTS.some(v => v.k === q.get('variant'))) S.variant = q.get('variant');
  if (S.entries[q.get('entry')]) S.entryId = q.get('entry');
  render();
})();
