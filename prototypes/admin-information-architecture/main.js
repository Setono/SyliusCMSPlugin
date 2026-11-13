/* PROTOTYPE (throwaway): rendering, events and actions. */

function go(r) { S.route = r; S.builder = null; S.modal = null; }
function openBuilder(id) {
  const e = byId[id];
  S.builder = { uuid: id, back: { label: VARIANTS[S.variant].backLabel(), route: JSON.parse(JSON.stringify(S.route)) }, tab: 'settings', sel: null, locale: entryLocales(e)[0] };
  S.modal = null;
}
function toast(msg) { S.toast = msg; clearTimeout(toast.t); toast.t = setTimeout(() => { S.toast = null; render(); }, 2600); }

function modalHtml() {
  const m = S.modal; if (!m) return '';
  if (m.k === 'create') return createModal(m);
  if (m.k === 'placement') return placementModal(m);
  if (m.k === 'share') return shareModal(m);
  if (m.k === 'slotOrder') {
    const s = slotById(m.id);
    return modalWrap(`Order: ${h(s.page)} › ${h(s.label)}`, `${slotWarnings(s).map(w => `<div class="ui warning message visible">${h(w.text)}</div>`).join('')}<p class="small muted">${s.cap ? `Capped at ${s.cap}: only the first ${s.cap === 1 ? 'placement' : s.cap} that match show.` : 'Every placement that matches shows.'} Drag to reorder. Reached from a snippet's placements; there's no Slots screen in this variant.</p>${placementTable(placementsIn(s.id), { showSlot: false, reorder: true })}`, `<button class="ui button" data-act="closeModal">Done</button>`, 'large');
  }
  if (m.k === 'upload') return modalWrap('This file is already in the library', `<div class="dup">${mediaThumb(mediaById['m-ship'])}<div><b>free-shipping.png</b><div class="small muted">Unsorted · 1200×200 · used in 1 entry</div><p>The file you uploaded is identical to this media item. Use the existing one, or upload it as a new media item?</p></div></div>`, `<button class="ui button" data-act="toast" data-msg="Uploaded as a new media item">Upload as new</button><button class="ui primary button" data-act="closeModal">Use the existing one</button>`, 'small');
  if (m.k === 'replace') { const x = mediaById[m.id]; return modalWrap(`Replace the file of ${h(x.name)}`, `<p>It's used in ${plural(x.usedIn.length, 'entry', 'entries')}: ${x.usedIn.map(u => '<b>' + h(byId[u].title) + '</b>').join(', ')}.</p><p>${icon('bolt')}The new file shows everywhere at once, without a publish. Alt text and the focal point are kept.</p>`, `<button class="ui button" data-act="closeModal">Cancel</button><button class="ui primary button" data-act="toast" data-msg="File replaced everywhere">Choose the new file…</button>`, 'small'); }
  if (m.k === 'picker') return modalWrap('Choose from the media library', mediaGrid(true), '', 'fullscreen');
}

function render() {
  const app = document.getElementById('app'), ov = document.getElementById('overlay'), md = document.getElementById('modal');
  // keep focus in inputs across re-renders
  const a = document.activeElement, key = a && a.matches?.('input,textarea') ? (a.dataset.filter ? `[data-filter="${a.dataset.filter}"][data-f="${a.dataset.f}"]` : a.dataset.act ? `[data-act="${a.dataset.act}"]` : null) : null;
  const pos = key ? a.selectionStart : 0;
  app.innerHTML = VARIANTS[S.variant].render();
  ov.innerHTML = S.builder ? builderOverlay() : '';
  md.innerHTML = modalHtml();
  document.getElementById('protobar').innerHTML = protobar();
  if (key) { const n = document.querySelector((S.modal ? '#modal ' : S.builder ? '#overlay ' : '#app ') + key) || document.querySelector(key); if (n) { n.focus(); try { n.setSelectionRange(pos, pos); } catch (_) { } } }
}

function setVariant(v) {
  S.variant = v; S.builder = null; S.modal = null; VARIANTS[v].init();
  const u = new URL(location.href); u.searchParams.set('variant', v); history.replaceState(null, '', u);
}

const ACT = {
  go: el => { go(JSON.parse(el.dataset.r)); },
  logo: () => { if (S.variant === 'A') go({ s: 'dash' }); else VARIANTS[S.variant].init(); S.builder = null; S.modal = null; },
  openRef: el => { S.modal = null; VARIANTS[S.variant].openEntry(el.dataset.id); },
  openProblem: el => { S.modal = null; VARIANTS[S.variant].openProblem(JSON.parse(el.dataset.r)); },
  openBuilder: el => openBuilder(el.dataset.id),
  bback: () => { S.route = S.builder.back.route; S.builder = null; },
  btab: el => { S.builder.tab = el.dataset.t; },
  bsel: el => { S.builder.sel = el.dataset.i === '' ? null : +el.dataset.i; },
  bloc: el => { S.builder.locale = el.dataset.l; },
  create: el => { S.modal = { k: 'create', type: el.dataset.t || null }; },
  createType: el => { S.modal.type = el.dataset.t; S.modal.layout = CT[el.dataset.t].layouts?.[0]; },
  createLayout: el => { S.modal.layout = el.dataset.l; },
  createStart: el => { S.modal.start = el.dataset.id || null; const p = PRESETS.find(x => x.id === el.dataset.id); if (p?.layout) S.modal.layout = p.layout; },
  createGo: () => {
    const m = S.modal, uuid = 'new' + Date.now(), title = m.title?.trim() || 'Untitled';
    const p = PRESETS.find(x => x.id === m.start);
    const e = E(uuid, m.type, title, { pub: 'never', slug: CT[m.type].url ? (CT[m.type].prefix ? CT[m.type].prefix + '/' : '') + title.toLowerCase().replace(/[^a-z0-9]+/g, '-') : null, layout: m.layout, tr: TR('u3', '-', '-'), edited: 'just now, ' + ME, blocks: p ? p.blocks.split(', ') : [] });
    ENTRIES.push(e); byId[uuid] = e;
    S.modal = null; openBuilder(uuid); toast(`${CT[m.type].label} created as a draft`);
  },
  closeModal: () => { S.modal = null; },
  dimmer: (el, ev) => { if (ev.target === el) S.modal = null; else return 'skip'; },
  addPlacement: el => { S.modal = { k: 'placement', slot: el.dataset.slot, snippet: el.dataset.snippet, lock: el.dataset.slot ? 'slot' : 'snippet' }; },
  editPlacement: el => { S.modal = { k: 'placement', id: el.dataset.id }; },
  savePlacement: () => {
    const [sn, sl] = [...document.querySelectorAll('#modal select')].map(s => s.value);
    if (S.modal.id) Object.assign(PLACEMENTS.find(p => p.id === S.modal.id), { snippet: sn, slot: sl });
    else PLACEMENTS.push({ id: 'p' + Date.now(), snippet: sn, slot: sl, scope: 'All pages' });
    S.modal = null; toast('Placement saved. It takes effect now.');
  },
  removePlacement: el => { PLACEMENTS = PLACEMENTS.filter(p => p.id !== el.dataset.id); toast('Placement removed. It takes effect now.'); },
  pmove: el => {
    const p = PLACEMENTS.find(x => x.id === el.dataset.id), same = placementsIn(p.slot), i = same.indexOf(p), j = i + +el.dataset.d;
    if (j < 0 || j >= same.length) return;
    const a = PLACEMENTS.indexOf(p), b = PLACEMENTS.indexOf(same[j]);[PLACEMENTS[a], PLACEMENTS[b]] = [PLACEMENTS[b], PLACEMENTS[a]];
    toast('Order saved. It takes effect now.');
  },
  slotOrder: el => { S.modal = { k: 'slotOrder', id: el.dataset.id }; },
  newShare: el => { S.modal = { k: 'share', entry: el.dataset.id }; },
  saveShare: () => {
    const [c, l, g] = [...document.querySelectorAll('#modal select')].map(s => s.value), d = document.querySelector('#modal input').value;
    const e = byId[S.modal.entry];
    SHARE_LINKS.push({ id: 'sl' + Date.now(), entry: e.uuid, channel: CHANNELS.find(x => x.name === c).code, locale: Object.keys(LOCALE_NAMES).find(k => LOCALE_NAMES[k] === l), shownTo: g, date: d, by: ME, created: '13 Nov' });
    S.modal = null; toast('Share link created and copied');
  },
  revokeShare: el => { SHARE_LINKS = SHARE_LINKS.filter(s => s.id !== el.dataset.id); toast('Share link revoked'); },
  toggleHome: el => {
    const e = byId[el.dataset.id], c = el.dataset.c;
    if (e.homepageOf.includes(c)) { e.homepageOf = e.homepageOf.filter(x => x !== c); toast(`${chName(c)} shows the Sylius homepage again`); }
    else { const prev = ENTRIES.find(x => x.homepageOf.includes(c)); if (prev) prev.homepageOf = prev.homepageOf.filter(x => x !== c); e.homepageOf.push(c); if (!e.channels.includes(c)) e.channels.push(c); toast(`${e.title} is now the homepage of ${chName(c)}${prev ? `, instead of ${prev.title}` : ''}`); }
  },
  mfolder: el => { S.media.folder = el.dataset.id; S.media.sel = null; },
  mkind: el => { S.media.kind = el.dataset.k; },
  mtoggle: el => { S.media[el.dataset.k] = !S.media[el.dataset.k]; },
  msel: el => { S.media.sel = S.media.sel === el.dataset.id ? null : el.dataset.id; },
  upload: () => { S.modal = { k: 'upload' }; },
  replaceMedia: el => { S.modal = { k: 'replace', id: el.dataset.id }; },
  pickerOpen: () => { S.media.sel = null; S.modal = { k: 'picker' }; },
  pickMedia: el => { S.modal = null; toast(`${mediaById[el.dataset.id].name} picked`); },
  ptab: el => { S.presets.tab = el.dataset.t; },
  ttab: el => { S.transfer.tab = el.dataset.t; },
  xpick: el => { const P = S.transfer.picked; P.has(el.dataset.id) ? P.delete(el.dataset.id) : P.add(el.dataset.id); },
  tdiscard: () => { S.transfer.discard = !S.transfer.discard; },
  tupload: () => { S.transfer.step = 1; },
  trun: () => { S.transfer.step = 2; S.transfer.progress = 0; const t = setInterval(() => { S.transfer.progress += 20; if (S.transfer.progress >= 100) { clearInterval(t); S.transfer.step = 3; } render(); }, 350); },
  treset: () => { Object.assign(S.transfer, { step: 0, discard: false, progress: 0 }); },
  msel2: el => { go({ s: 'map', sel: JSON.parse(el.dataset.sel) }); },
  ttoggle: (el, ev) => { const id = el.dataset.id, cur = document.querySelector(`[data-act="ttoggle"][data-id="${CSS.escape(id)}"]`).classList.contains('down'); S.open[id] = !cur; },
  onlyProblems: () => { S.onlyProblems = !S.onlyProblems; },
  vprev: () => { const V = Object.keys(VARIANTS); setVariant(V[(V.indexOf(S.variant) + V.length - 1) % V.length]); },
  vnext: () => { const V = Object.keys(VARIANTS); setVariant(V[(V.indexOf(S.variant) + 1) % V.length]); },
  tasks: () => { S.tasks = !S.tasks; },
  toast: el => { toast(el.dataset.msg); },
};

document.addEventListener('click', ev => {
  const el = ev.target.closest('[data-act]');
  if (!el || el.classList.contains('dead')) return;
  const f = ACT[el.dataset.act];
  if (!f) return;
  if (el.tagName === 'A' || el.tagName === 'BUTTON') ev.preventDefault();
  if (f(el, ev) === 'skip') return;
  render();
});
document.addEventListener('input', ev => {
  const t = ev.target;
  if (t.dataset.filter) { F(t.dataset.filter)[t.dataset.f] = t.value; render(); }
  else if (t.dataset.act === 'createTitle') { S.modal.title = t.value; render(); }
  else if (t.dataset.act === 'slugInput') { const c = t.value === 'about-us' && S.builder.uuid !== 'about'; if (c !== !!S.builder.slugConflict) { S.builder.slugConflict = c; render(); } }
});
document.addEventListener('change', ev => {
  const t = ev.target;
  if (t.dataset.filter && t.tagName === 'SELECT') { F(t.dataset.filter)[t.dataset.f] = t.value; render(); }
  if (t.dataset.act === 'mapch') { S.mapChannel = t.value; render(); }
});
document.addEventListener('keydown', ev => {
  if (ev.target.closest('input,textarea,select,[contenteditable]')) return;
  if (ev.key === 'ArrowLeft') { ACT.vprev(); render(); }
  if (ev.key === 'ArrowRight') { ACT.vnext(); render(); }
  if (ev.key === 'Escape') { if (S.modal) S.modal = null; else if (S.builder) ACT.bback(); render(); }
});

const Q = new URLSearchParams(location.search);
setVariant(Q.get('variant')?.toUpperCase() in VARIANTS ? Q.get('variant').toUpperCase() : 'A');
if (Q.get('r')) S.route = JSON.parse(Q.get('r'));   // deep link for testing, e.g. ?variant=B&r={"s":"entry","id":"bf"}
if (Q.get('b')) openBuilder(Q.get('b'));            // ?b=<entry uuid> opens the builder
render();
