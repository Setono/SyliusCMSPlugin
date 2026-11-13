/* PROTOTYPE (throwaway): state, derived facts and the screens every variant shares.
   The variants disagree about navigation, where an entry's admin detail lives, where placements are managed
   and where problems surface. Everything in this file is the same in all three. */

const S = {
  variant: 'A',
  route: null,            // variant-specific, e.g. { s: 'list', type: 'page' }
  builder: null,          // { uuid, back: { label, route }, tab, sel, locale, channel }
  modal: null,            // { k: 'create' | 'placement' | 'share' | ... }
  toast: null,
  tasks: false,
  filters: {},            // per list key: { q, status, channel }
  media: { folder: 'all', sel: null, kind: '', missingAlt: false, unused: false },
  transfer: { tab: 'export', picked: new Set(['bf', 'pr-bfstart']), step: 0, discard: false, progress: 0 },
  presets: { tab: 'start' },
  mapChannel: 'FASHION_WEB', // variant C
  open: {},                 // expanded tree nodes (variant C)
};

const h = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const chName = c => CHANNELS.find(x => x.code === c).name;
const ord = n => n + (['th', 'st', 'nd', 'rd'][(n % 100 - 20) % 10] || ['th', 'st', 'nd', 'rd'][n % 100] || 'th');
const plural = (n, w, p = w + 's') => `${n} ${n === 1 ? w : p}`;
const lab = (cls, text, title = '') => `<span class="ui mini ${cls} label"${title ? ` title="${h(title)}"` : ''}>${text}</span>`;
const icon = n => `<i class="${n} icon"></i>`;

function entryLocales(e) {
  const ls = new Set(e.channels.flatMap(c => CHANNELS.find(x => x.code === c).locales));
  return Object.keys(LOCALES).filter(l => ls.has(l));
}
function entryUrl(e, loc = 'en_US') {
  if (!CT[e.type].url) return null;
  if (e.homepageOf.length) return `/${loc}/`;
  return `/${loc}/${e.slug}`;
}
const typeLabel = e => CT[e.type].label;
const ofType = t => ENTRIES.filter(e => e.type === t);
const snippetPlacements = uuid => PLACEMENTS.filter(p => p.snippet === uuid);
const placementsIn = slotId => PLACEMENTS.filter(p => p.slot === slotId);
const slotById = id => SLOTS.find(s => s.id === id);

/* ---------- Status and translations ---------- */
function statusLabels(e, { compact = false } = {}) {
  const L = [];
  switch (e.pub) {
    case 'live': L.push(lab('green', 'Published')); break;
    case 'changes': L.push(lab('green', 'Published'), lab('yellow', 'Unpublished changes')); break;
    case 'never': L.push(lab('basic', 'Never published')); break;
    case 'scheduled': L.push(lab('blue', `${icon('clock outline')}Publishes ${e.when}`)); break;
    case 'overdue': L.push(lab('red', `${icon('exclamation triangle')}Scheduled publish overdue`, `Was due ${e.when}`)); break;
    case 'failed': L.push(lab('red', `${icon('ban')}Scheduled publish failed`, e.reason)); break;
  }
  if (!e.enabled) L.push(lab('grey', 'Disabled'));
  if (e.window) L.push(e.window.state === 'ended' ? lab('basic', `${icon('calendar times outline')}Ended ${e.window.end}`)
    : lab('blue basic', `${icon('calendar outline')}Shows ${e.window.start}–${e.window.end}`));
  Object.entries(e.tr).filter(([l, s]) => s === 'held' && entryLocales(e).includes(l)).forEach(([l]) => L.push(lab('basic', `${LOCALES[l]} held back`)));
  if (!compact && e.audience !== 'Everyone') L.push(lab('purple basic', `${icon('users')}${e.audience}`));
  return L.join(' ');
}
function trChip(l, s) {
  if (s === 'ok') return `<span class="tr ok" title="${LOCALE_NAMES[l]}: complete">${LOCALES[l]}</span>`;
  if (s === '-') return `<span class="tr none" title="${LOCALE_NAMES[l]}: no translation, so it doesn't show in this locale">${LOCALES[l]}</span>`;
  if (s === 'held') return `<span class="tr held" title="${LOCALE_NAMES[l]}: held back">${LOCALES[l]} ⏸</span>`;
  const n = +s.slice(1);
  return s[0] === 'u' ? `<span class="tr untr" title="${LOCALE_NAMES[l]}: ${n} untranslated">${LOCALES[l]} ${n}</span>`
    : `<span class="tr outd" title="${LOCALE_NAMES[l]}: ${n} outdated">${LOCALES[l]} ↻${n}</span>`;
}
const trChips = e => `<span class="trs">${entryLocales(e).map(l => trChip(l, e.tr[l])).join('')}</span>`;

/* ---------- Slots, placements and who wins ---------- */
const audienceGroups = a => a === 'Everyone' ? GROUPS : a.replace(/^Only /, '').split(', ').map(g => g.trim());
const isCatchAll = p => { const s = byId[p.snippet]; return p.scope === 'All pages' && s.audience === 'Everyone' && !s.window; };
const homepageHidden = slot => slot.homepage && CHANNELS.every(c => ENTRIES.some(e => e.homepageOf.includes(c.code)));

function placementNotes(slotId) {
  const s = slotById(slotId), list = placementsIn(slotId), out = {};
  let covered = new Set();
  list.forEach((p, i) => {
    const sn = byId[p.snippet];
    if (homepageHidden(s)) { out[p.id] = { cls: 'muted', text: 'Never shows: every channel has a CMS homepage' }; return; }
    if (!s.cap) { out[p.id] = { cls: '', text: 'Shows wherever it matches' }; return; }
    if (s.cap === 1 && list.every(x => x.scope === 'All pages')) {
      const g = audienceGroups(sn.audience).filter(x => !covered.has(x));
      audienceGroups(sn.audience).forEach(x => covered.add(x));
      out[p.id] = g.length === 0 ? { cls: 'muted', text: 'Never wins: placements above always match first' }
        : { cls: 'win', text: g.length === GROUPS.length ? 'Wins for everyone' : `Wins for ${g.join(', ')}` };
      return;
    }
    out[p.id] = { cls: i < s.cap ? 'win' : '', text: i < s.cap ? `Wins where it matches` : `Wins only where fewer than ${s.cap} above match` };
  });
  return out;
}
function slotWarnings(s) {
  const W = [], list = placementsIn(s.id);
  if (homepageHidden(s) && list.length) W.push({ sev: 'warning', kind: 'hidden', text: `Every channel shows a CMS homepage, so this Sylius homepage slot is never rendered. Its ${plural(list.length, 'placement')} show nowhere.` });
  else if (s.cap && list.length && !list.some(isCatchAll)) W.push({ sev: 'warning', kind: 'catchall', text: `Capped at ${s.cap}, and no placement shows on every page to everyone. Some shoppers see nothing here.` });
  return W;
}

/* ---------- Problems ---------- */
function translationProblem(e) {
  if (e.pub === 'never') return null;
  const missing = [], partial = [];
  e.channels.forEach(c => CHANNELS.find(x => x.code === c).locales.forEach(l => { if (e.tr[l] === '-') missing.push(`${chName(c)} · ${LOCALES[l]}`); }));
  entryLocales(e).forEach(l => { const s = e.tr[l]; if (/^[uo]\d/.test(s)) partial.push(`${LOCALES[l]} ${s[0] === 'u' ? s.slice(1) + ' untranslated' : s.slice(1) + ' outdated'}`); });
  if (!missing.length && !partial.length) return null;
  return { sev: 'info', kind: 'translation', text: [missing.length ? `Not shown in ${missing.join(', ')} (no translation)` : '', partial.length ? partial.join(', ') : ''].filter(Boolean).join('. ') };
}
function entryProblems(e) {
  const P = [...e.problems];
  const t = translationProblem(e); if (t) P.push(t);
  if (e.type === 'snippet') snippetPlacements(e.uuid).forEach(p => {
    const s = slotById(p.slot);
    if (homepageHidden(s)) P.push({ sev: 'warning', kind: 'hidden', text: `Its placement in ${s.page} › ${s.label} never shows: every channel has a CMS homepage` });
  });
  return P;
}
function allProblems() {
  const P = [];
  ENTRIES.forEach(e => entryProblems(e).forEach(p => P.push({ ...p, ref: { t: 'entry', id: e.uuid } })));
  SLOTS.forEach(s => slotWarnings(s).filter(w => w.kind !== 'hidden').forEach(w => P.push({ ...w, ref: { t: 'slot', id: s.id } })));
  PRESETS.filter(p => p.invalid).forEach(p => P.push({ sev: 'warning', kind: 'preset', text: p.invalid, ref: { t: 'preset', id: p.id } }));
  MEDIA.filter(m => m.kind === 'image' && m.usedIn.length && Object.values(m.alt).includes('-')).forEach(m => P.push({ sev: 'info', kind: 'alt', text: `Missing alt text in ${Object.entries(m.alt).filter(([, v]) => v === '-').map(([l]) => LOCALES[l]).join(', ')}`, ref: { t: 'media', id: m.uuid } }));
  return P;
}
const SEV = { error: { cls: 'red', icon: 'times circle', n: 0 }, warning: { cls: 'orange', icon: 'exclamation triangle', n: 1 }, info: { cls: 'blue', icon: 'info circle', n: 2 } };
const KIND = { overdue: 'Scheduled publishes', failed: 'Scheduled publishes', reference: 'Broken references', layout: 'Removed layouts', route: 'URLs taken by other routes', archive: 'Category archives', catchall: 'Capped slots', hidden: 'Hidden placements', preset: 'Presets', translation: 'Translations', alt: 'Alt text' };
function problemIcon(P) {
  P = P.filter(p => p.kind !== 'translation'); // the translation chips already show these
  if (!P.length) return '';
  const worst = P.reduce((a, p) => SEV[p.sev].n < SEV[a].n ? p.sev : a, 'info');
  return `<span class="prob ${SEV[worst].cls}" title="${h(P.map(p => p.text).join('\n'))}">${icon(SEV[worst].icon)}${P.length}</span>`;
}
function problemRow(p, refHtml) {
  return `<div class="probrow"><span class="prob ${SEV[p.sev].cls}">${icon(SEV[p.sev].icon)}</span><div><div>${refHtml}</div><div class="muted small">${h(p.text)}</div></div></div>`;
}
function refName(ref) {
  if (ref.t === 'entry') return `${icon(CT[byId[ref.id].type].icon)}${h(byId[ref.id].title)} <span class="muted small">${typeLabel(byId[ref.id])}</span>`;
  if (ref.t === 'slot') { const s = slotById(ref.id); return `${icon('square outline')}Slot: ${h(s.page)} › ${h(s.label)}`; }
  if (ref.t === 'preset') return `${icon('star outline')}Preset: ${h(PRESETS.find(p => p.id === ref.id).name)}`;
  if (ref.t === 'media') return `${icon('image outline')}${h(mediaById[ref.id].name)}`;
}

/* ---------- Sylius admin chrome ---------- */
function syliusChrome(cmsMenu, content, { wide = false } = {}) {
  const section = (title, items) => `<div class="item"><div class="header">${title}</div><div class="menu">${items}</div></div>`;
  const it = (ic, t) => `<a class="item dead">${icon(ic)}${t}</a>`;
  return `<div class="sy">
    <div class="sy-side"><div class="sy-logo" data-act="logo" title="Dashboard">Sylius</div>
      <div class="ui inverted vertical menu sy-menu">
        ${section('Catalog', it('sitemap', 'Taxons') + it('cube', 'Products') + it('history', 'Inventory') + it('cubes', 'Attributes'))}
        ${section('Sales', it('cart', 'Orders') + it('payment', 'Payments') + it('truck', 'Shipments'))}
        ${section('Customers', it('users', 'Customers') + it('archive', 'Groups'))}
        ${section('Marketing', it('in cart', 'Promotions') + it('star', 'Product reviews'))}
        ${cmsMenu}
        ${section('Configuration', it('share alternate', 'Channels') + it('translate', 'Locales') + it('lock', 'Admin users'))}
      </div></div>
    <div class="sy-main"><div class="sy-top"><i class="sidebar icon"></i><div class="ui transparent icon input"><input placeholder="Search…"><i class="search icon"></i></div><span class="sy-user">${icon('user circle')}${ME}</span></div>
      <div class="sy-content${wide ? ' wide' : ''}">${content}</div></div></div>`;
}
function syHeader(ic, title, sub, crumbs, actions = '') {
  return `<div class="sy-head"><div><h1 class="ui header"><i class="circular ${ic} icon"></i><div class="content">${title}<div class="sub header">${sub}</div></div></h1>
    <div class="ui breadcrumb"><a class="section dead">Administration</a>${crumbs.map(c => `<i class="right chevron icon divider"></i>${c.act ? `<a class="section" ${c.act}>${c.t}</a>` : `<div class="active section">${c.t}</div>`}`).join('')}</div></div>
    <div class="sy-actions">${actions}</div></div>`;
}

/* ---------- Shared screen: media library ---------- */
function folderPath(id) { const f = FOLDERS.find(x => x.id === id); return f ? (f.parent ? folderPath(f.parent) + ' / ' : '') + f.name : 'Unsorted'; }
function mediaFiltered() {
  const m = S.media;
  return MEDIA.filter(x => (m.folder === 'all' || (m.folder === 'unsorted' ? !x.folder : (x.folder === m.folder || FOLDERS.find(f => f.id === x.folder)?.parent === m.folder)))
    && (!m.kind || x.kind === m.kind) && (!m.missingAlt || (x.kind === 'image' && Object.values(x.alt).includes('-'))) && (!m.unused || !x.usedIn.length));
}
function mediaThumb(x, big = false) {
  const glyph = x.kind === 'video' ? 'play circle outline' : x.kind === 'document' ? 'file pdf outline' : '';
  return `<div class="thumb${big ? ' big' : ''}" style="background:linear-gradient(135deg,hsl(${x.hue} 55% 72%),hsl(${(x.hue + 40) % 360} 50% 52%))">${glyph ? icon(glyph + ' huge') : ''}${big && x.kind === 'image' ? '<span class="focal" title="Focal point (drag)"></span>' : ''}</div>`;
}
function mediaGrid(pick = false) {
  const items = mediaFiltered(), m = S.media;
  const folderTree = (parent, depth) => FOLDERS.filter(f => f.parent === parent).map(f => `<a class="item${m.folder === f.id ? ' active' : ''}" style="padding-left:${12 + depth * 14}px" data-act="mfolder" data-id="${f.id}">${icon('folder' + (m.folder === f.id ? ' open' : '') + ' outline')}${f.name}</a>${folderTree(f.id, depth + 1)}`).join('');
  return `<div class="mlib${pick ? ' pick' : ''}">
    <div class="ui vertical menu mfolders"><a class="item${m.folder === 'all' ? ' active' : ''}" data-act="mfolder" data-id="all">${icon('images outline')}All media</a>${folderTree(null, 0)}<a class="item${m.folder === 'unsorted' ? ' active' : ''}" data-act="mfolder" data-id="unsorted">${icon('inbox')}Unsorted</a><a class="item muted" data-act="toast" data-msg="New folder (not built)">${icon('plus')}New folder</a></div>
    <div class="mmain"><div class="mbar"><div class="ui small icon input"><input placeholder="Search media…"><i class="search icon"></i></div>
      <div class="ui mini basic buttons">${[['', 'All'], ['image', 'Images'], ['video', 'Videos'], ['document', 'Documents']].map(([k, t]) => `<button class="ui button${m.kind === k ? ' active' : ''}" data-act="mkind" data-k="${k}">${t}</button>`).join('')}</div>
      <button class="ui mini basic button${m.missingAlt ? ' active' : ''}" data-act="mtoggle" data-k="missingAlt">${icon('exclamation triangle')}Missing alt text</button>
      <button class="ui mini basic button${m.unused ? ' active' : ''}" data-act="mtoggle" data-k="unused">Not used</button>
      <span class="grow"></span><button class="ui mini primary button" data-act="upload">${icon('upload')}Upload</button></div>
      <div class="mgrid">${items.map(x => `<div class="mtile${m.sel === x.uuid ? ' sel' : ''}" data-act="msel" data-id="${x.uuid}">${mediaThumb(x)}<div class="mname">${h(x.name)}</div><div class="small muted">${x.usedIn.length ? `Used in ${x.usedIn.length}` : 'Not used'}${x.kind === 'image' && Object.values(x.alt).includes('-') ? ` · <span class="orange">${icon('exclamation triangle')}alt</span>` : ''}</div></div>`).join('') || '<p class="muted">Nothing here.</p>'}</div></div>
    ${m.sel ? mediaDetail(mediaById[m.sel], pick) : ''}</div>`;
}
function mediaDetail(x, pick) {
  const alt = x.kind === 'image' ? `<div class="field"><label>Alt text <span class="muted small">per locale, on the media item; a use can override it</span></label>${Object.keys(LOCALES).map(l => `<div class="ui mini labeled input alt"><div class="ui label">${LOCALES[l]}</div><input value="${x.alt[l] === 'ok' ? h(x.name.replace(/\..*/, '').replace(/-/g, ' ')) : ''}" placeholder="${x.alt[l] === '-' ? 'Missing' : ''}" class="${x.alt[l] === '-' ? 'warnin' : ''}"></div>`).join('')}</div>` : '';
  return `<div class="mdetail">${mediaThumb(x, true)}
    <div class="ui small form"><div class="field"><label>Name</label><input value="${h(x.name)}"></div>
      <div class="small muted">${x.kind} · ${x.dims ? x.dims + ' · ' : ''}${x.size} · ${folderPath(x.folder)}</div>
      ${x.kind === 'image' ? '<div class="small muted">Drag the dot to set the focal point. Every crop keeps it in view.</div>' : ''}${alt}</div>
    <h5>Used in</h5>${x.usedIn.length ? `<div class="ui list">${x.usedIn.map(u => `<a class="item" data-act="openRef" data-id="${u}">${icon(CT[byId[u].type].icon)}${h(byId[u].title)} <span class="muted small">${typeLabel(byId[u])}</span></a>`).join('')}</div>` : '<p class="muted small">Not used anywhere.</p>'}
    ${pick ? `<button class="ui fluid primary button" data-act="pickMedia" data-id="${x.uuid}">Use this</button>` : `<div class="mbtns"><button class="ui mini button" data-act="replaceMedia" data-id="${x.uuid}">${icon('exchange')}Replace file</button><button class="ui mini button" data-act="toast" data-msg="Moved (not built)">${icon('folder')}Move</button>
      <button class="ui mini red basic button${x.usedIn.length ? ' disabled' : ''}" title="${x.usedIn.length ? `In use by ${plural(x.usedIn.length, 'entry', 'entries')}; remove it there first` : ''}">${icon('trash')}Delete</button></div>
      ${x.usedIn.length ? `<div class="small muted">Can't be deleted while it's used in ${plural(x.usedIn.length, 'entry', 'entries')}.</div>` : ''}`}</div>`;
}
function mediaScreen(header) { return `${header}<div class="ui segment flush">${mediaGrid(false)}</div>`; }

/* ---------- Shared screen: presets ---------- */
function presetsScreen(header) {
  const t = S.presets.tab, list = PRESETS.filter(p => p.kind === t);
  return `${header}<div class="ui top attached tabular menu"><a class="item${t === 'start' ? ' active' : ''}" data-act="ptab" data-t="start">Starting points <span class="ui mini label">${PRESETS.filter(p => p.kind === 'start').length}</span></a><a class="item${t === 'block' ? ' active' : ''}" data-act="ptab" data-t="block">Block presets <span class="ui mini label">${PRESETS.filter(p => p.kind === 'block').length}</span></a></div>
  <div class="ui bottom attached segment"><p class="muted small">${t === 'start' ? 'A starting point is offered when an entry of its content type is created. It can set a layout.' : 'Block presets are offered in the builder\'s block picker, under Presets.'} Editors save presets with ★ in the builder and change one by saving over it there. Presets shipped in code can't be changed here.</p>
  <table class="ui very basic table"><thead><tr><th>Name</th><th>From</th>${t === 'start' ? '<th>Content type · layout</th>' : ''}<th>Blocks</th><th></th><th></th></tr></thead><tbody>
  ${list.map(p => `<tr class="${p.invalid ? 'warnrow' : ''}"><td><b>${h(p.name)}</b></td><td>${p.src === 'code' ? lab('basic', `${icon('code')}Shipped in code`) : `<span class="small">Saved by ${p.by}, ${p.at}</span>`}</td>${t === 'start' ? `<td>${CT[p.ct].label} · ${p.layout}</td>` : ''}<td class="small">${h(p.blocks)}</td>
    <td>${p.invalid ? `<span class="prob orange" title="${h(p.invalid)}">${icon('exclamation triangle')}</span> <span class="small orange">${h(p.invalid)}</span>` : ''}</td>
    <td class="right aligned">${p.src === 'editor' ? `<button class="ui mini basic button" data-act="toast" data-msg="Rename (not built)">Rename</button><button class="ui mini red basic button" data-act="toast" data-msg="Deleted (not built)">Delete</button>` : ''}</td></tr>`).join('')}</tbody></table></div>`;
}

/* ---------- Shared screen: transfer ---------- */
function exportDeps() {
  const D = [];
  S.transfer.picked.forEach(id => {
    const e = byId[id]; if (!e) return;
    e.blocks.filter(b => b.startsWith('Snippet: ')).forEach(b => { const sn = ENTRIES.find(x => x.title === b.slice(9)); if (sn && !S.transfer.picked.has(sn.uuid)) D.push({ what: `${icon('puzzle piece')}${sn.title}`, why: `used by ${e.title}` }); });
    MEDIA.filter(m => m.usedIn.includes(id)).forEach(m => D.push({ what: `${icon('image outline')}${m.name}`, why: `used by ${e.title}` }));
  });
  return D;
}
function transferScreen(header) {
  const T = S.transfer;
  const tabs = `<div class="ui top attached tabular menu"><a class="item${T.tab === 'export' ? ' active' : ''}" data-act="ttab" data-t="export">${icon('download')}Export</a><a class="item${T.tab === 'import' ? ' active' : ''}" data-act="ttab" data-t="import">${icon('upload')}Import</a></div>`;
  if (T.tab === 'export') {
    const groups = Object.keys(CT).map(t => `<div class="xgroup"><div class="xhead">${icon(CT[t].icon)}${CT[t].label}s</div>${ofType(t).map(e => `<label class="xrow"><input type="checkbox" data-act="xpick" data-id="${e.uuid}" ${T.picked.has(e.uuid) ? 'checked' : ''}> ${h(e.title)} ${e.pub === 'never' ? '<span class="muted small">never published, nothing to export</span>' : ''}</label>`).join('')}</div>`).join('');
    const pres = `<div class="xgroup"><div class="xhead">${icon('star outline')}Editor presets</div>${PRESETS.filter(p => p.src === 'editor').map(p => `<label class="xrow"><input type="checkbox" data-act="xpick" data-id="${p.id}" ${T.picked.has(p.id) ? 'checked' : ''}> ${h(p.name)}</label>`).join('')}</div>`;
    const deps = exportDeps();
    return `${header}${tabs}<div class="ui bottom attached segment"><div class="xcols"><div class="xpick"><p class="small muted">Pick what to export. An export carries published content, plus the snippets and media items it uses. Drafts are never exported.</p>${groups}${pres}</div>
      <div class="xsum"><h4>This export</h4><div class="ui list">${[...T.picked].map(id => `<div class="item">${icon('check')}${h(byId[id]?.title ?? PRESETS.find(p => p.id === id)?.name)}</div>`).join('')}</div>
      ${deps.length ? `<h5>Added because they're used</h5><div class="ui list">${deps.map(d => `<div class="item">${d.what} <span class="muted small">${h(d.why)}</span></div>`).join('')}</div>` : ''}
      <button class="ui primary button" data-act="toast" data-msg="Downloaded export-2026-11-13.zip">${icon('download')}Download export (.zip)</button>
      <p class="small muted">Same format as <code>bin/console setono:cms:export</code> and the Sylius fixture.</p></div></div></div>`;
  }
  const plan = [
    { e: 'Black Friday', a: 'Update', st: 'ok' }, { e: 'Black Friday teaser', a: 'Create', st: 'ok', note: 'added because Black Friday uses it' },
    { e: 'Shipping & returns', a: 'Update', st: 'draft', note: 'Has a draft in this store' }, { e: 'About us', a: 'Update', st: 'changed', note: 'Changed here since the export was made (12 Nov, Mads)' },
    { e: 'Christmas gift guide', a: 'Create', st: 'ok' }, { e: 'black-friday-hero.jpg', a: 'Replace file', st: 'ok', note: 'takes effect at once' }, { e: 'Black Friday landing (preset)', a: 'Create', st: 'ok' },
  ];
  const blocked = plan.filter(p => p.st === 'changed' || (p.st === 'draft' && !T.discard));
  const steps = ['Upload', 'Check the plan', 'Import', 'Result'];
  const stepBar = `<div class="ui mini steps">${steps.map((s, i) => `<div class="step${i === T.step ? ' active' : ''}${i < T.step ? ' completed' : ''}"><div class="content"><div class="title">${s}</div></div></div>`).join('')}</div>`;
  let body = '';
  if (T.step === 0) body = `<div class="drop" data-act="tupload">${icon('cloud upload huge')}<p>Drop an export (.zip or folder) here, or click to choose.</p><p class="small muted">(Click to load a sample export from staging.)</p></div>`;
  if (T.step === 1) body = `<p>Dry run of <b>export-staging-2026-11-13.zip</b>. Nothing has changed yet. An import never deletes, matches records by UUID and lands in drafts.</p>
    <table class="ui very basic compact table"><thead><tr><th>Record</th><th>Change</th><th>Check</th></tr></thead><tbody>${plan.map(p => `<tr class="${p.st === 'changed' || (p.st === 'draft' && !T.discard) ? 'errrow' : ''}"><td>${h(p.e)}</td><td>${p.a}${p.a !== 'Replace file' ? ' <span class="muted small">into the draft</span>' : ''}</td><td>${p.st === 'ok' ? `<span class="green">${icon('check')}</span> <span class="muted small">${h(p.note ?? '')}</span>` : p.st === 'draft' ? (T.discard ? `<span class="orange">${icon('trash alternate outline')}Draft will be discarded</span>` : `<span class="red">${icon('times')}${p.note}</span>`) : `<span class="red">${icon('times')}${p.note}</span>`}</td></tr>`).join('')}</tbody></table>
    <h5>Unresolved references</h5><div class="probrow"><span class="prob orange">${icon('exclamation triangle')}</span><div>Christmas gift guide links to <b>Gift cards</b> (page), which isn't in the export or this store. The link will be empty.</div></div>
    <div class="ui form"><div class="field"><div class="ui checkbox"><input type="checkbox" id="disc" data-act="tdiscard" ${T.discard ? 'checked' : ''}><label for="disc">Discard drafts in this store so the import can update them (1 entry: Shipping & returns)</label></div></div></div>
    ${blocked.length ? `<p class="red small">${icon('ban')}Can't import: ${plural(blocked.length, 'record')} ${blocked.length === 1 ? 'is' : 'are'} blocked. ${blocked.some(b => b.st === 'changed') ? 'Make a fresh export, or leave out records changed here.' : ''}</p>` : ''}
    <button class="ui primary button${blocked.length ? ' disabled' : ''}" data-act="trun">Import</button> <button class="ui button" data-act="treset">Cancel</button>`;
  if (T.step === 2) body = `<p>Importing… applying everything in one transaction.</p><div class="ui indicating progress active" data-percent="${T.progress}"><div class="bar" style="width:${T.progress}%"><div class="progress">${T.progress}%</div></div></div>`;
  if (T.step === 3) body = `<div class="ui positive message"><div class="header">Imported 6 records</div><p>Entries went into drafts and show to shoppers only once they're published. A replaced media file and 2 placements took effect at once.</p></div>
    <div class="ui list">${['Black Friday', 'Black Friday teaser', 'Shipping & returns', 'Christmas gift guide'].map(t => `<div class="item">${icon('file alternate outline')}${t} — draft updated <a data-act="toast" data-msg="Opens the entry (not built)">Review and publish</a></div>`).join('')}</div><button class="ui button" data-act="treset">Done</button>`;
  return `${header}${tabs}<div class="ui bottom attached segment">${stepBar}<div class="tbody">${body}</div></div>`;
}

/* ---------- Create dialog ---------- */
function createModal(m) {
  if (!m.type) return modalWrap('Create', `<p>What do you want to create?</p><div class="cards3">${Object.keys(CT).map(t => `<a class="ccard" data-act="createType" data-t="${t}">${icon(CT[t].icon + ' big')}<b>${CT[t].label}</b><span class="small muted">${CT[t].url ? 'Has its own URL' : 'No URL of its own'}${CT[t].builtIn ? ' · built in' : ''}</span></a>`).join('')}</div>`, '');
  const ct = CT[m.type], starts = PRESETS.filter(p => p.kind === 'start' && p.ct === m.type);
  const wire = l => `<div class="wire ${l.toLowerCase()}"><i></i><i></i><i></i></div>`;
  return modalWrap(`New ${ct.label.toLowerCase()}`, `<div class="ui form"><div class="two fields"><div class="field"><label>Title</label><input value="${h(m.title ?? '')}" placeholder="e.g. Winter sale" data-act="createTitle"></div>
      ${ct.url ? `<div class="field"><label>Slug</label><div class="ui labeled input"><div class="ui label">/en_US/${ct.prefix ? ct.prefix + '/' : ''}</div><input value="${h((m.title ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '-'))}" placeholder="winter-sale"></div></div>` : ''}</div>
      ${ct.layouts && ct.layouts.length > 1 ? `<div class="field"><label>Layout</label><div class="cards3">${ct.layouts.map(l => `<a class="ccard${m.layout === l ? ' sel' : ''}" data-act="createLayout" data-l="${l}">${wire(l)}<b>${l}</b></a>`).join('')}</div></div>` : ''}
      ${ct.tree ? `<div class="field"><label>Start from</label><div class="cards3"><a class="ccard${!m.start ? ' sel' : ''}" data-act="createStart" data-id="">${icon('file outline big')}<b>Blank</b></a>${starts.map(p => `<a class="ccard${m.start === p.id ? ' sel' : ''}${p.invalid ? ' dis' : ''}" ${p.invalid ? `title="${h(p.invalid)}"` : `data-act="createStart" data-id="${p.id}"`}>${icon('star outline big')}<b>${h(p.name)}</b><span class="small muted">${p.src === 'code' ? 'Shipped' : 'Saved by ' + p.by} · sets layout ${p.layout}</span>${p.invalid ? `<span class="small orange">${icon('exclamation triangle')}${h(p.invalid)}</span>` : ''}</a>`).join('')}</div></div>` : ''}
      <div class="field"><label>Channels</label>${CHANNELS.map(c => `<div class="ui checkbox"><input type="checkbox" checked><label>${c.name}</label></div> `).join('')}</div></div>`,
    `<button class="ui button" data-act="closeModal">Cancel</button><button class="ui primary button" data-act="createGo">Create and open the builder</button>`);
}
function modalWrap(title, body, actions, cls = '') {
  return `<div class="ui dimmer modals page visible active" data-act="dimmer"><div class="ui modal visible active ${cls}" data-stop="1"><i class="close icon" data-act="closeModal"></i><div class="header">${title}</div><div class="scrolling content">${body}</div>${actions ? `<div class="actions">${actions}</div>` : ''}</div></div>`;
}

/* ---------- Placement editor, share links, revisions (used wherever a variant puts them) ---------- */
function placementTable(list, { showSnippet = true, showSlot = true, reorder = false, hl = null } = {}) {
  if (!list.length) return '<p class="muted small">No placements.</p>';
  return `<table class="ui very basic compact table ptable"><tbody>${list.map(p => {
    const s = slotById(p.slot), notes = placementNotes(p.slot), n = notes[p.id], sn = byId[p.snippet], idx = placementsIn(p.slot).indexOf(p);
    return `<tr class="${n.cls === 'muted' ? 'mutedrow' : ''}${p.id === hl ? ' hlrow' : ''}">${reorder ? `<td class="collapsing"><span class="handle" title="Drag to reorder">⠿</span><button class="ui mini icon basic button" data-act="pmove" data-id="${p.id}" data-d="-1">${icon('arrow up')}</button><button class="ui mini icon basic button" data-act="pmove" data-id="${p.id}" data-d="1">${icon('arrow down')}</button></td>` : ''}
      ${showSnippet ? `<td><a data-act="openRef" data-id="${sn.uuid}"><b>${h(sn.title)}</b></a> ${statusLabels(sn, { compact: true })}${sn.audience !== 'Everyone' ? `<div class="small purple">${icon('users')}${sn.audience}</div>` : ''}</td>` : ''}
      ${showSlot ? `<td>${h(s.page)} › <b>${h(s.label)}</b><div class="small muted">${ord(idx + 1)} of ${placementsIn(p.slot).length}${s.cap ? ` · capped at ${s.cap}` : ''}</div></td>` : ''}
      <td><span class="small">${icon('filter')}${h(p.scope)}</span></td><td class="small ${n.cls === 'win' ? 'green' : n.cls === 'muted' ? 'orange' : 'muted'}">${n.text}</td>
      <td class="right aligned collapsing"><button class="ui mini basic button" data-act="editPlacement" data-id="${p.id}">Edit</button><button class="ui mini icon basic button" data-act="removePlacement" data-id="${p.id}" title="Remove">${icon('trash alternate outline')}</button></td></tr>`;
  }).join('')}</tbody></table>`;
}
function placementModal(m) {
  const p = m.id ? PLACEMENTS.find(x => x.id === m.id) : { snippet: m.snippet ?? '', slot: m.slot ?? '', scope: 'All pages' };
  const sn = p.snippet ? byId[p.snippet] : null;
  const slotOpts = SLOTS.filter(s => !sn?.context || s.ctx.includes(sn.context)).map(s => `<option value="${s.id}" ${s.id === p.slot ? 'selected' : ''}>${s.page} › ${s.label}${s.cap ? ` (capped at ${s.cap})` : ''}</option>`).join('');
  const snOpts = ofType('snippet').filter(s => { const sl = slotById(p.slot); return !sl || !s.context || sl.ctx.includes(s.context); }).map(s => `<option value="${s.uuid}" ${s.uuid === p.snippet ? 'selected' : ''}>${s.title}${s.context ? ` (needs ${s.context})` : ''}</option>`).join('');
  return modalWrap(m.id ? 'Edit placement' : 'Add placement', `<div class="ui form">
    <div class="field"><label>Snippet</label><select ${m.lock === 'snippet' ? 'disabled' : ''}>${snOpts}</select></div>
    <div class="field"><label>Slot</label><select ${m.lock === 'slot' ? 'disabled' : ''}>${slotOpts}</select>${sn?.context ? `<div class="small muted">Only slots that provide <b>${sn.context}</b> are offered.</div>` : ''}</div>
    <div class="field"><label>Page scope</label><div class="scopebox">${p.scope === 'All pages' ? '<span class="muted">All pages the slot is on</span>' : `<span class="ui label">${h(p.scope)}</span>`} <a class="small" data-act="toast" data-msg="Condition builder (not built)">+ Add condition</a></div></div>
    <p class="small muted">${icon('bolt')}A placement takes effect when it's saved. It doesn't wait for a publish.</p></div>`,
    `<button class="ui button" data-act="closeModal">Cancel</button><button class="ui primary button" data-act="savePlacement">Save</button>`);
}
function revisionsList(e) {
  if (e.pub === 'never' || e.pub === 'scheduled') return '<p class="muted small">Never published, so there are no revisions yet.</p>';
  return `<table class="ui very basic compact table"><tbody>${REVISIONS.map((r, i) => `<tr><td><b>#${r.n}</b>${i === 0 ? ' ' + lab('green', 'Live') : ''}</td><td class="small">${r.at}</td><td class="small">${r.by}</td><td class="small muted">${r.note}</td><td class="right aligned"><button class="ui mini basic button" data-act="toast" data-msg="Opens revision #${r.n} in the shop (preview mode)">Preview</button>${i ? `<button class="ui mini basic button" data-act="toast" data-msg="Revision #${r.n} restored into the draft">Restore into draft</button>` : ''}</td></tr>`).join('')}</tbody></table>`;
}
function shareLinksList(e) {
  const L = SHARE_LINKS.filter(s => s.entry === e.uuid);
  return `${L.length ? `<table class="ui very basic compact table"><thead><tr><th>Channel · locale</th><th>Shown to</th><th>Date</th><th>Made by</th><th></th></tr></thead><tbody>${L.map(s => `<tr><td>${chName(s.channel)} · ${LOCALES[s.locale]}</td><td>${s.shownTo}</td><td>${s.date}</td><td class="small">${s.by}, ${s.created}</td><td class="right aligned"><button class="ui mini basic button" data-act="toast" data-msg="Link copied">${icon('copy')}Copy</button><button class="ui mini red basic button" data-act="revokeShare" data-id="${s.id}">Revoke</button></td></tr>`).join('')}</tbody></table>` : '<p class="muted small">No share links.</p>'}
    <button class="ui mini button" data-act="newShare" data-id="${e.uuid}">${icon('linkify')}New share link</button>`;
}
function shareModal(m) {
  const e = byId[m.entry];
  return modalWrap(`Share the draft of ${h(e.title)}`, `<div class="ui form"><div class="two fields"><div class="field"><label>Channel</label><select>${e.channels.map(c => `<option>${chName(c)}</option>`)}</select></div><div class="field"><label>Locale</label><select>${entryLocales(e).map(l => `<option>${LOCALE_NAMES[l]}</option>`)}</select></div></div>
    <div class="two fields"><div class="field"><label>Shown to</label><select>${GROUPS.map(g => `<option>${g}</option>`)}</select><div class="small muted">Who the visitor counts as, for audiences and visibility conditions.</div></div><div class="field"><label>Date</label><input value="Now"><div class="small muted">Shows the page as it would look then, for date windows.</div></div></div>
    <p class="small muted">Anyone with the link sees this draft until it's revoked.</p></div>`, `<button class="ui button" data-act="closeModal">Cancel</button><button class="ui primary button" data-act="saveShare">Create link</button>`);
}
function homepageField(e) {
  return `<div class="field"><label>Homepage of</label>${CHANNELS.map(c => {
    const cur = ENTRIES.find(x => x.homepageOf.includes(c.code));
    return `<div class="ui checkbox"><input type="checkbox" ${e.homepageOf.includes(c.code) ? 'checked' : ''} data-act="toggleHome" data-id="${e.uuid}" data-c="${c.code}"><label>${c.name} ${cur && cur !== e ? `<span class="muted small">now: ${h(cur.title)}</span>` : ''}</label></div><br>`;
  }).join('')}<div class="small muted">A channel's homepage replaces the Sylius homepage at its root URL. Its stock homepage slots aren't rendered.</div></div>`;
}

/* ---------- The builder (stub of the shell settled in "Visual builder look and behaviour") ---------- */
function builderOverlay() {
  const B = S.builder, e = byId[B.uuid], ct = CT[e.type];
  const tabs = VARIANTS[S.variant].builderTabs(e);
  if (!tabs.find(t => t.k === B.tab)) B.tab = tabs[0].k;
  const tree = `<div class="b-row settings${B.sel === null ? ' sel' : ''}" data-act="bsel" data-i="">${icon('cog')}Entry settings</div>
    ${ct.tree ? e.blocks.map((b, i) => `<div class="b-row${B.sel === i ? ' sel' : ''}" data-act="bsel" data-i="${i}"><span class="handle">⠿</span>${icon(b.startsWith('Snippet') ? 'puzzle piece' : 'square outline')}${h(b)}</div>`).join('') + '<div class="b-add">+ Add block</div>' : '<p class="muted small pad">This content type has no block tree. Its fields are in the entry settings.</p>'}`;
  const right = B.sel === null ? `<div class="ui secondary pointing small menu btabs">${tabs.map(t => `<a class="item${B.tab === t.k ? ' active' : ''}" data-act="btab" data-t="${t.k}">${t.label}</a>`).join('')}</div><div class="bpanel">${tabs.find(t => t.k === B.tab).html(e)}</div>`
    : `<div class="bpanel"><div class="small muted">Entry › ${h(e.blocks[B.sel])}</div><h4>${h(e.blocks[B.sel])}</h4><div class="ui small form"><div class="field"><label>Heading <span class="ui mini basic label">${LOCALES[B.locale]}</span></label><input value="Make it count"></div>
      <div class="field"><label>Image</label><div class="imgfield">${mediaThumb(MEDIA[0])}<div><div>${MEDIA[0].name}</div><button class="ui mini button" data-act="pickerOpen">Choose from library</button></div></div><div class="small muted">Alt text comes from the media item. <a>Override for this use</a></div></div></div></div>`;
  const locs = entryLocales(e);
  return `<div class="builder"><div class="b-top">
      <button class="ui mini inverted basic button" data-act="bback">${icon('arrow left')}${h(B.back.label)}</button>
      <div class="b-title">${icon(ct.icon)}<b>${h(e.title)}</b> <span class="muted">${ct.label}</span> ${statusLabels(e, { compact: true })}</div>
      <div class="b-locs">${locs.map(l => `<a class="b-loc${B.locale === l ? ' on' : ''}" data-act="bloc" data-l="${l}">${trChip(l, e.tr[l])}</a>`).join('')}</div>
      <select class="b-sel">${e.channels.map(c => `<option>${chName(c)}</option>`)}</select>
      <span class="grow"></span><span class="muted small">Draft saved</span>
      <div class="ui mini buttons"><button class="ui green button" data-act="toast" data-msg="Published">Publish</button><button class="ui green icon button" data-act="toast" data-msg="Publish menu: Publish now, Schedule…, Discard draft">${icon('dropdown')}</button></div></div>
    <div class="b-body"><div class="b-tree">${tree}</div><div class="b-prev">${previewMock(e)}</div><div class="b-right">${right}</div></div></div>`;
}
function previewMock(e) {
  if (e.type === 'snippet') {
    const p = snippetPlacements(e.uuid)[0], s = p ? slotById(p.slot) : null;
    return `<div class="shop"><div class="shop-head">Fashion Web Store</div><div class="shop-host">${s ? `<span class="muted small">${h(s.page)}${e.context ? ' · sample product: Linen Summer Dress' : ''}</span>` : '<span class="muted small">Not placed anywhere. Showing a blank page.</span>'}
      <div class="slotbox"><span class="slotlab">${s ? h(s.label) : 'Preview'}</span>${e.blocks.map(b => `<div class="pblock">${h(b)}</div>`).join('')}</div></div></div>`;
  }
  if (!CT[e.type].tree) return `<div class="shop"><div class="shop-head">Fashion Web Store</div><div class="shop-host"><span class="muted small">Shown as its card in listings</span><div class="pblock acc">▸ ${h(e.title)}</div></div></div>`;
  return `<div class="shop"><div class="shop-head">Fashion Web Store <span class="muted small">${entryUrl(e) ?? ''}</span></div><div class="shop-host">${e.blocks.map(b => `<div class="pblock">${h(b)}</div>`).join('')}</div></div>`;
}
/* Builder tabs every variant has: Settings, URL & SEO. Variants add publishing and placements, or link out. */
function bSettings(e) {
  const ct = CT[e.type];
  const cats = ct.collection && !ct.categoryOf && ENTRIES.some(x => x.type !== e.type && CT[x.type].categoryOf === e.type) ? `<div class="field"><label>Categories</label><div class="catpick">${e.categories.map(c => c.startsWith('deleted:') ? `<span class="ui red basic label" title="This category was deleted">${icon('unlink')}${c.slice(8)} (deleted)<i class="delete icon"></i></span>` : `<span class="ui label">${h(byId[c].title)}<i class="delete icon"></i></span>`).join('')}<a class="small" data-act="toast" data-msg="Category picker: entries of ${ENTRIES.find(x => CT[x.type].categoryOf === e.type) ? CT[ENTRIES.find(x => CT[x.type].categoryOf === e.type).type].label : ''}">+ Add</a></div></div>` : '';
  return `<div class="ui small form">
    <div class="field"><label>Title <span class="ui mini basic label">${LOCALES[S.builder.locale]}</span></label><input value="${h(e.title)}"></div>
    ${ct.layouts ? `<div class="field"><label>Layout</label><select>${[...new Set([...ct.layouts, e.layout])].map(l => `<option ${l === e.layout ? 'selected' : ''}>${l}${ct.layouts.includes(l) ? '' : ' (removed from code)'}</option>`).join('')}</select>${ct.layouts.includes(e.layout) ? '' : `<div class="small orange">${icon('exclamation triangle')}This layout was removed from code. The entry renders with ${ct.layouts[0]}.</div>`}</div>` : ''}
    ${cats}
    <div class="field"><label>Channels</label>${CHANNELS.map(c => `<div class="ui checkbox"><input type="checkbox" ${e.channels.includes(c.code) ? 'checked' : ''}><label>${c.name}</label></div> `).join('')}</div>
    <div class="field"><div class="ui toggle checkbox"><input type="checkbox" ${e.enabled ? 'checked' : ''}><label>Enabled</label></div></div>
    <div class="two fields"><div class="field"><label>Show from</label><input value="${e.window?.start ?? ''}" placeholder="Any time"></div><div class="field"><label>Show until</label><input value="${e.window?.end ?? ''}" placeholder="Any time"></div></div>
    <div class="field"><label>Audience</label><select><option ${e.audience === 'Everyone' ? 'selected' : ''}>Everyone</option><option ${e.audience.startsWith('Only') ? 'selected' : ''}>${e.audience.startsWith('Only') ? e.audience : 'Only some customer groups…'}</option><option>Everyone except…</option></select></div>
    <p class="small muted">${icon('bolt')}Channels, enabled, the date window and the audience take effect when saved.</p>
    ${e.type === 'page' ? homepageField(e) : ''}
    ${e.context ? `<div class="field"><label>Host-page context</label><span class="ui label">${icon('cube')}${e.context}</span></div>` : ''}</div>`;
}
function bSeo(e) {
  if (!CT[e.type].url) return '<p class="muted small">This content type has no URL of its own, so it has no URL or SEO settings.</p>';
  const L = S.builder.locale;
  return `<div class="ui small form"><div class="field"><label>Slug <span class="ui mini basic label">${LOCALES[L]}</span></label><div class="ui labeled input"><div class="ui label">/${L}/${CT[e.type].prefix ? CT[e.type].prefix + '/' : ''}</div><input value="${h((e.slug ?? '').replace(/^.*\//, ''))}" data-act="slugInput"></div>
      ${S.builder.slugConflict ? `<div class="ui pointing red basic label">Another page, About us, already has /${L}/about-us in Fashion Web Store.</div>` : '<div class="small muted">Type "about-us" to see a conflict.</div>'}</div>
    ${e.homepageOf.length ? `<p class="small">${icon('home')}Homepage of ${e.homepageOf.map(chName).join(', ')}, so it shows at /${L}/. Its slug is still reserved.</p>` : ''}
    ${e.routeOverride ? `<div class="ui small warning message visible">${icon('exclamation triangle')}This URL is taken by the Sylius route <code>${e.routeOverride}</code>, so shoppers never see this page.</div>` : ''}
    <div class="field"><label>Old slugs <span class="muted small">redirect with a 301</span></label>${e.oldSlugs.length ? e.oldSlugs.map(s => `<div class="oldslug"><code>/${L}/${s}</code> → current URL <a class="small" data-act="toast" data-msg="Old slug removed. It now returns 404.">Remove</a></div>`).join('') : '<span class="muted small">None</span>'}</div>
    <h5>Search engines</h5>
    <div class="field"><label>Meta title</label><input placeholder="${h(e.title)} | Fashion Web Store"></div>
    <div class="field"><label>Meta description</label><textarea rows="2"></textarea></div>
    <div class="field"><div class="ui checkbox"><input type="checkbox"><label>Hide from search engines (noindex)</label></div></div>
    <p class="small muted">Canonical and hreflang are printed automatically.</p></div>`;
}

/* ---------- Protobar and tasks ---------- */
const TASKS = [
  'Find everything that needs attention right now, and fix the overdue scheduled publish.',
  'See what\'s coming up this month: scheduled publishes and date windows.',
  'Open "About us" in the builder, then get back to where you were.',
  'Create a new campaign page from a starting point.',
  'Change which snippet a shopper sees above the cart items (a capped slot), and see who wins.',
  'Show "Size & fit" on product pages for shoes too.',
  'Make "Christmas gift guide" shareable with a colleague as a Wholesale customer on 1 Dec.',
  'Restore the previous revision of "Home".',
  'Find where black-friday-hero.jpg is used, then replace it.',
  'Reorder the FAQ, and fix the blog post with a deleted category.',
  'Make "Hjem" the Nordic homepage. Where do you see which page is each channel\'s homepage?',
  'Export the Black Friday campaign from staging and import it here.',
];
function protobar() {
  const V = Object.keys(VARIANTS), i = V.indexOf(S.variant);
  return `<div class="pbar"><button data-act="vprev">◀</button><span class="plabel">${S.variant} · ${VARIANTS[S.variant].name}</span><button data-act="vnext">▶</button><span class="psep"></span><button data-act="tasks">${S.tasks ? 'Hide tasks' : 'Tasks to try'}</button></div>
    ${S.tasks ? `<div class="ptasks"><b>Try these in each variant</b><ol>${TASKS.map(t => `<li>${h(t)}</li>`).join('')}</ol><div class="small">Prototype only: nothing is saved, a reload resets. Buttons marked "not built" just show a message.</div></div>` : ''}
    ${S.toast ? `<div class="ptoast">${h(S.toast)}</div>` : ''}`;
}
