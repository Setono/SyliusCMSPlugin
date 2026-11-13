/* PROTOTYPE (throwaway): three structurally different admin information architectures.
   A · Sylius menu: one admin menu item per thing, Sylius grids, rows open the builder, everything about an entry lives in the builder.
   B · Content hub: one menu item opens a hub with a home of what needs attention, one list of all entries, and an overview page per entry.
   C · Store map: content organised by where it shows in a channel: homepage, URLs, collections, shop pages with their slots and placements. */

const R = r => `data-act="go" data-r="${h(JSON.stringify(r))}"`;
const sySection = (title, items) => `<div class="item"><div class="header">${title}</div><div class="menu">${items}</div></div>`;
const syItem = (active, ic, t, r, badge = '') => `<a class="item${active ? ' active' : ''}" ${r}>${icon(ic)}${t}${badge}</a>`;
const countBadge = P => { const n = P.filter(p => p.sev !== 'info').length; return n ? `<span class="mbadge" title="${n} need attention">${n}</span>` : ''; };

const STATUS_FILTERS = { '': 'Any status', attention: 'Needs attention', changes: 'Unpublished changes', never: 'Never published', scheduled: 'Scheduled', off: 'Disabled or outside its date window' };
function matchStatus(e, f) {
  switch (f) {
    case 'attention': return entryProblems(e).some(p => p.sev !== 'info');
    case 'changes': return e.pub === 'changes';
    case 'never': return e.pub === 'never';
    case 'scheduled': return ['scheduled', 'overdue', 'failed'].includes(e.pub);
    case 'off': return !e.enabled || e.window?.state === 'ended';
    default: return true;
  }
}
const F = key => (S.filters[key] ??= { q: '', status: '', channel: '' });
function applyFilters(list, key) {
  const f = F(key);
  return list.filter(e => (!f.q || e.title.toLowerCase().includes(f.q.toLowerCase())) && matchStatus(e, f.status) && (!f.channel || e.channels.includes(f.channel)));
}
function filterBar(key, extra = '') {
  const f = F(key);
  return `<div class="ui form filters"><div class="fields">
    <div class="field"><input placeholder="Search" value="${h(f.q)}" data-filter="${key}" data-f="q"></div>
    <div class="field"><select data-filter="${key}" data-f="status">${Object.entries(STATUS_FILTERS).map(([k, v]) => `<option value="${k}" ${f.status === k ? 'selected' : ''}>${v}</option>`).join('')}</select></div>
    <div class="field"><select data-filter="${key}" data-f="channel"><option value="">All channels</option>${CHANNELS.map(c => `<option value="${c.code}" ${f.channel === c.code ? 'selected' : ''}>${c.name}</option>`).join('')}</select></div>${extra}</div></div>`;
}
const catNames = e => e.categories.map(c => c.startsWith('deleted:') ? `<span class="red" title="Deleted category">${icon('unlink')}${c.slice(8)}</span>` : h(byId[c].title)).join(', ');

/* Where an entry shows, per channel and locale. Used by B's overview page and C's panel. */
function availability(e, only = null) {
  const rows = CHANNELS.filter(c => !only || c.code === only).map(c => `<tr><td>${c.name}</td>${c.locales.map(l => {
    let st, cls = 'muted';
    if (!e.channels.includes(c.code)) st = 'Not in this channel';
    else if (e.tr[l] === '-') { st = 'No translation'; cls = 'orange'; }
    else if (e.tr[l] === 'held') st = 'Held back';
    else if (!e.enabled) st = 'Disabled';
    else if (e.pub === 'never') st = 'Not published yet';
    else if (e.pub === 'scheduled') { st = `Publishes ${e.when}`; cls = 'blue'; }
    else if (e.window?.state === 'ended') st = `Ended ${e.window.end}`;
    else if (e.window?.state === 'upcoming') { st = `From ${e.window.start}`; cls = 'blue'; }
    else if (e.routeOverride) { st = 'Hidden by a Sylius route'; cls = 'orange'; }
    else { st = 'Live'; cls = 'green'; }
    return `<td class="${cls}"><b>${LOCALES[l]}</b> ${st}</td>`;
  }).join('')}</tr>`).join('');
  return `<table class="ui very basic compact table avail"><tbody>${rows}</tbody></table>${e.audience !== 'Everyone' ? `<div class="small purple">${icon('users')}Audience: ${e.audience}. Everyone else gets a 404.</div>` : ''}`;
}
function scheduleBox(e) {
  if (e.pub === 'overdue') return `<div class="ui small error message visible"><b>${icon('exclamation triangle')}Scheduled publish overdue.</b> It was due ${e.when} and hasn't run. Check that the Messenger worker is running. <div class="mt"><button class="ui mini button" data-act="toast" data-msg="Published now">Publish now</button><button class="ui mini basic button" data-act="toast" data-msg="Schedule cancelled; the draft is editable again">Cancel the schedule</button></div></div>`;
  if (e.pub === 'failed') return `<div class="ui small error message visible"><b>${icon('ban')}Scheduled publish failed</b> at ${e.when}. ${h(e.reason)} The draft is unlocked again. <div class="mt"><button class="ui mini button" data-act="toast" data-msg="Rescheduled">Schedule again</button></div></div>`;
  if (e.pub === 'scheduled') return `<div class="ui small info message visible">${icon('clock outline')}Publishes ${e.when}. The draft is locked until then. <button class="ui mini basic button" data-act="toast" data-msg="Schedule cancelled; the draft is editable again">Cancel the schedule</button></div>`;
  if (e.pub === 'changes') return `<div class="ui small warning message visible">${icon('pencil')}Unpublished changes in the draft, last saved ${e.edited}. <button class="ui mini green button" data-act="toast" data-msg="Published">Publish</button><button class="ui mini basic button" data-act="toast" data-msg="Schedule…">Schedule…</button></div>`;
  if (e.pub === 'never') return `<div class="ui small message visible">Never published. Shoppers can't see it yet. <button class="ui mini green button" data-act="toast" data-msg="Published">Publish</button></div>`;
  return `<div class="small green">${icon('check')}Published. The draft has no unpublished changes.</div>`;
}
function usedInList(e) {
  const users = ENTRIES.filter(x => x.blocks.includes('Snippet: ' + e.title));
  return users.length ? `<div class="ui list">${users.map(u => `<a class="item" data-act="openRef" data-id="${u.uuid}">${icon(CT[u.type].icon)}${h(u.title)}</a>`).join('')}</div>` : '<p class="muted small">Not referenced from other entries.</p>';
}
function problemsList(P) { return P.length ? P.map(p => problemRow(p, `<span class="${SEV[p.sev].cls}">${KIND[p.kind] ?? ''}</span>`)).join('') : `<p class="small green">${icon('check')}No problems.</p>`; }
function upcomingList() { return `<div class="ui list">${UPCOMING.map(u => `<a class="item" data-act="openRef" data-id="${u.entry}"><span class="muted small upc">${u.at}</span> ${u.what}: <b>${h(byId[u.entry].title)}</b></a>`).join('')}</div>`; }
function problemsGrouped(onlyRefs = null) {
  const P = allProblems().filter(p => !onlyRefs || onlyRefs(p));
  const groups = {};
  P.sort((a, b) => SEV[a.sev].n - SEV[b.sev].n).forEach(p => (groups[KIND[p.kind]] ??= []).push(p));
  return Object.entries(groups).map(([k, L]) => `<div class="pgroup"><div class="pghead">${k} <span class="ui mini ${SEV[L[0].sev].cls} label">${L.length}</span></div>${L.map(p => problemRow(p, `<a data-act="openProblem" data-r="${h(JSON.stringify(p.ref))}">${refName(p.ref)}</a>`)).join('')}</div>`).join('');
}

/* =========================================================== A · Sylius menu */
const LISTS_A = {
  page: { title: 'Pages', icon: 'file alternate outline', sub: 'Standalone pages with their own URL', types: ['page'] },
  snippet: { title: 'Snippets', icon: 'puzzle piece', sub: 'Reusable content shown in slots or inside other entries', types: ['snippet'] },
  blog: { title: 'Blog', icon: 'newspaper outline', sub: 'Posts and their categories', types: ['blog_post', 'blog_category'] },
  faq: { title: 'FAQ', icon: 'question circle outline', sub: 'Questions and their categories', types: ['faq_item', 'faq_category'] },
  stores: { title: 'Store locations', icon: 'map marker alternate', sub: 'Added by your agency', types: ['store_location'] },
};
function renderA() {
  const r = S.route;
  const probsOf = types => ENTRIES.filter(e => types.includes(e.type)).flatMap(entryProblems);
  const menu = sySection('Content',
    Object.entries(LISTS_A).map(([k, L]) => syItem(r.s === 'list' && r.key === k, L.icon, L.title, R({ s: 'list', key: k }), countBadge(probsOf(L.types)))).join('')
    + syItem(r.s === 'slots' || r.s === 'slot', 'th large', 'Slots', R({ s: 'slots' }), countBadge(SLOTS.flatMap(slotWarnings)))
    + syItem(r.s === 'media', 'images outline', 'Media library', R({ s: 'media' }))
    + syItem(r.s === 'presets', 'star outline', 'Presets', R({ s: 'presets' }), countBadge(PRESETS.filter(p => p.invalid).map(() => ({ sev: 'warning' }))))
    + syItem(r.s === 'transfer', 'exchange', 'Transfer', R({ s: 'transfer' })));
  let c = '';
  if (r.s === 'dash') c = dashA();
  if (r.s === 'list') c = listA(r);
  if (r.s === 'slots') c = slotsA();
  if (r.s === 'slot') c = slotA(r.id);
  if (r.s === 'media') c = mediaScreen(syHeader('images outline', 'Media library', 'Images, videos and documents for every channel', [{ t: 'Media library' }]));
  if (r.s === 'presets') c = presetsScreen(syHeader('star outline', 'Presets', 'Starting points and block presets', [{ t: 'Presets' }]));
  if (r.s === 'transfer') c = transferScreen(syHeader('exchange', 'Transfer', 'Move content between environments, such as staging and production', [{ t: 'Transfer' }]));
  return syliusChrome(menu, c);
}
function dashA() {
  return `${syHeader('dashboard', 'Dashboard', 'Overview of your store', [{ t: 'Dashboard' }])}
  <div class="ui three statistics small"><div class="statistic"><div class="value">$12,480</div><div class="label">Sales this week</div></div><div class="statistic"><div class="value">134</div><div class="label">New orders</div></div><div class="statistic"><div class="value">58</div><div class="label">New customers</div></div></div>
  <div class="ui two column stackable grid mt"><div class="column"><div class="ui segment"><h4 class="ui header">${icon('exclamation triangle orange')}Content that needs attention</h4><p class="small muted">Added to the Sylius dashboard by the CMS. Each list also flags its own rows.</p>${problemsGrouped(p => p.sev !== 'info')}</div></div>
  <div class="column"><div class="ui segment"><h4 class="ui header">${icon('clock outline')}Coming up</h4>${upcomingList()}</div><div class="ui segment"><h4 class="ui header">${icon('chart line')}Latest orders</h4><p class="muted small">(Sylius's own widget)</p></div></div></div>`;
}
function listA(r) {
  const L = LISTS_A[r.key], tab = r.tab ?? L.types[0], fkey = r.key + ':' + tab;
  const tabs = L.types.length > 1 ? `<div class="ui secondary pointing menu">${L.types.map(t => `<a class="item${t === tab ? ' active' : ''}" ${R({ s: 'list', key: r.key, tab: t })}>${CT[t].plural} <span class="ui mini label">${ofType(t).length}</span></a>`).join('')}</div>` : '';
  let list = applyFilters(ofType(tab), fkey);
  const ct = CT[tab];
  if (ct.ordered) list = list.sort((a, b) => a.order - b.order);
  const create = `<button class="ui labeled icon primary button" data-act="create" data-t="${tab}">${icon('plus')}Create ${ct.label.toLowerCase()}</button>`;
  const cols = tab === 'page' ? ['Title', 'Status', 'Translations', 'Layout', 'Last edited']
    : tab === 'snippet' ? ['Title', 'Placements', 'Status', 'Translations']
      : ct.categoryOf ? ['Title', CT[ct.categoryOf].plural, 'Status', 'Translations'] : ct.collection ? ['Title', 'Categories', 'Status', 'Translations'] : ['Title', 'Status', 'Translations'];
  const row = e => {
    const P = entryProblems(e);
    const cells = cols.map(c => {
      if (c === 'Title') return `<td><a class="ttl" data-act="openRef" data-id="${e.uuid}">${h(e.title)}</a>${e.homepageOf.length ? ' ' + lab('teal', `${icon('home')}Homepage of ${e.homepageOf.map(chName).join(', ')}`) : ''}<div class="small muted">${entryUrl(e) ?? (e.context ? `Needs ${e.context}` : '')}</div></td>`;
      if (c === 'Status') return `<td>${statusLabels(e)}</td>`;
      if (c === 'Translations') return `<td>${trChips(e)}</td>`;
      if (c === 'Layout') return `<td class="small">${e.layout}${CT.page.layouts.includes(e.layout) ? '' : ` <span class="orange">${icon('exclamation triangle')}</span>`}</td>`;
      if (c === 'Last edited') return `<td class="small muted">${e.edited}</td>`;
      if (c === 'Placements') { const ps = snippetPlacements(e.uuid); return `<td class="small">${ps.length ? ps.map(p => `<a ${R({ s: 'slot', id: p.slot })}>${h(slotById(p.slot).page)} › ${h(slotById(p.slot).label)}</a>`).join('<br>') : '<span class="muted">Not placed</span>'}</td>`; }
      if (c === 'Categories') return `<td class="small">${catNames(e) || '<span class="muted">None</span>'}</td>`;
      return `<td class="small">${ENTRIES.filter(x => x.categories.includes(e.uuid)).length}</td>`;
    }).join('');
    return `<tr class="click" data-act="openRef" data-id="${e.uuid}">${ct.ordered ? `<td class="collapsing"><span class="handle">⠿</span>${e.order}</td>` : ''}${cells}<td class="collapsing">${problemIcon(P)}</td>
      <td class="right aligned collapsing"><button class="ui mini labeled icon button" data-act="openRef" data-id="${e.uuid}">${icon('pencil')}Edit</button><button class="ui mini icon basic button" data-act="toast" data-msg="Duplicate, Delete (not built)">${icon('ellipsis horizontal')}</button></td></tr>`;
  };
  return `${syHeader(L.icon, L.title, L.sub, [{ t: L.title }], create)}${tabs}
    <div class="ui segment">${filterBar(fkey)}</div>
    ${ct.ordered ? `<p class="small muted">${icon('sort')}Drag questions to set their order. Shoppers see them in this order.</p>` : ''}
    <table class="ui very basic celled table sy-grid"><thead><tr>${ct.ordered ? '<th>#</th>' : ''}${cols.map(c => `<th>${c}</th>`).join('')}<th></th><th></th></tr></thead><tbody>${list.map(row).join('') || `<tr><td colspan="9" class="muted">Nothing matches.</td></tr>`}</tbody></table>
    <div class="small muted">${list.length} of ${ofType(tab).length}. Click a row to open it in the builder.</div>`;
}
function slotsA() {
  return `${syHeader('th large', 'Slots', 'Places in shop pages where snippets show. Slots are declared in code.', [{ t: 'Slots' }])}
  ${SHOP_PAGES.map(pg => `<h4 class="ui dividing header">${h(pg)}</h4><table class="ui very basic table sy-grid"><tbody>${SLOTS.filter(s => s.page === pg).map(s => {
    const ps = placementsIn(s.id), W = slotWarnings(s);
    return `<tr class="click" ${R({ s: 'slot', id: s.id })}><td class="six wide"><b>${h(s.label)}</b><div class="small muted"><code>${s.id}</code>${s.ctx.length ? ` · provides ${s.ctx.join(', ')}` : ''}${s.cap ? ` · capped at ${s.cap}` : ''}</div></td>
      <td>${ps.map(p => `<span class="ui mini label">${h(byId[p.snippet].title)}</span>`).join(' ') || '<span class="muted small">Empty</span>'}</td><td class="collapsing">${problemIcon(W)}</td></tr>`;
  }).join('')}</tbody></table>`).join('')}`;
}
function slotA(id) {
  const s = slotById(id), W = slotWarnings(s);
  return `${syHeader('th large', `${h(s.page)} › ${h(s.label)}`, `<code>${s.id}</code>${s.ctx.length ? ` · provides ${s.ctx.join(', ')}` : ''}`, [{ t: 'Slots', act: R({ s: 'slots' }) }, { t: h(s.label) }], `<button class="ui labeled icon primary button" data-act="addPlacement" data-slot="${s.id}">${icon('plus')}Add placement</button>`)}
  ${W.map(w => `<div class="ui warning message visible">${icon('exclamation triangle')}${h(w.text)}</div>`).join('')}
  <div class="ui segment"><p class="small muted">${s.cap ? `Capped at ${s.cap}: only the first ${s.cap === 1 ? 'placement' : s.cap + ' placements'} that match show, in this order.` : 'Every placement that matches shows, in this order.'} Drag to reorder. Changes take effect on save.</p>
  ${placementTable(placementsIn(s.id), { showSlot: false, reorder: true })}</div>`;
}
function pubPanelA(e) {
  return `${scheduleBox(e)}<h5>Revisions</h5>${revisionsList(e)}<h5>Share links</h5>${shareLinksList(e)}`;
}

/* =========================================================== B · Content hub */
const HUB_TYPES = [['all', 'All entries', 'list'], ['page', 'Pages', 'file alternate outline'], ['snippet', 'Snippets', 'puzzle piece'], ['blog', 'Blog', 'newspaper outline'], ['faq', 'FAQ', 'question circle outline'], ['stores', 'Store locations', 'map marker alternate']];
const hubTypes = k => k === 'all' ? Object.keys(CT) : k in COLLECTIONS ? COLLECTIONS[k].types : [k];
function renderB() {
  const r = S.route;
  const menu = sySection('Content', syItem(true, 'pencil alternate', 'Content', R({ s: 'home' }), countBadge(allProblems())));
  const rail = `<div class="ui vertical menu hubrail">
    <a class="item${r.s === 'home' ? ' active' : ''}" ${R({ s: 'home' })}>${icon('home')}Overview <span class="mbadge">${allProblems().filter(p => p.sev !== 'info').length}</span></a>
    <div class="item"><div class="header">Entries</div><div class="menu">${HUB_TYPES.map(([k, t, ic]) => `<a class="item${r.s === 'entries' && r.type === k ? ' active' : ''}" ${R({ s: 'entries', type: k })}>${icon(ic)}${t}<span class="muted small cnt">${ENTRIES.filter(e => hubTypes(k).includes(e.type)).length}</span></a>`).join('')}</div></div>
    <div class="item"><div class="header">Library</div><div class="menu">
      <a class="item${r.s === 'media' ? ' active' : ''}" ${R({ s: 'media' })}>${icon('images outline')}Media</a>
      <a class="item${r.s === 'presets' ? ' active' : ''}" ${R({ s: 'presets' })}>${icon('star outline')}Presets</a>
      <a class="item${r.s === 'transfer' ? ' active' : ''}" ${R({ s: 'transfer' })}>${icon('exchange')}Transfer</a></div></div></div>`;
  let c = '';
  if (r.s === 'home') c = homeB();
  if (r.s === 'entries') c = entriesB(r);
  if (r.s === 'entry') c = entryB(r.id);
  if (r.s === 'media') c = mediaScreen(syHeader('images outline', 'Media', 'Shared by every channel', [{ t: 'Content', act: R({ s: 'home' }) }, { t: 'Media' }]));
  if (r.s === 'presets') c = presetsScreen(syHeader('star outline', 'Presets', 'Starting points and block presets', [{ t: 'Content', act: R({ s: 'home' }) }, { t: 'Presets' }]));
  if (r.s === 'transfer') c = transferScreen(syHeader('exchange', 'Transfer', 'Move content between environments', [{ t: 'Content', act: R({ s: 'home' }) }, { t: 'Transfer' }]));
  return syliusChrome(menu, `<div class="hub">${rail}<div class="hubmain">${c}</div></div>`, { wide: true });
}
function homeB() {
  const mine = ENTRIES.filter(e => ['changes', 'never'].includes(e.pub)).slice(0, 6);
  const comp = CHANNELS.flatMap(c => c.locales.map(l => {
    const es = ENTRIES.filter(e => e.channels.includes(c.code) && e.pub !== 'never');
    const ok = es.filter(e => e.tr[l] === 'ok').length;
    return `<tr><td>${c.name} · ${LOCALES[l]}</td><td><div class="ui tiny ${ok / es.length > .9 ? 'green' : 'yellow'} progress"><div class="bar" style="width:${Math.round(ok / es.length * 100)}%"></div></div></td><td class="small">${ok}/${es.length} complete</td><td><a class="small" ${R({ s: 'entries', type: 'all', missing: l })}>Show the rest</a></td></tr>`;
  })).join('');
  return `${syHeader('pencil alternate', 'Content', 'Everything shoppers read that isn\'t a product', [{ t: 'Content' }], `<button class="ui labeled icon primary button" data-act="create">${icon('plus')}Create</button>`)}
  <div class="ui two column stackable grid"><div class="ten wide column"><div class="ui segment"><h4 class="ui header">${icon('exclamation triangle orange')}Needs attention</h4>${problemsGrouped(p => p.sev !== 'info')}</div>
    <div class="ui segment"><h4 class="ui header">${icon('language')}Translations</h4><table class="ui very basic compact table">${comp}</table></div></div>
  <div class="six wide column"><div class="ui segment"><h4 class="ui header">${icon('clock outline')}Coming up</h4>${upcomingList()}</div>
    <div class="ui segment"><h4 class="ui header">${icon('pencil')}Drafts in progress</h4><div class="ui list">${mine.map(e => `<a class="item" data-act="openRef" data-id="${e.uuid}">${icon(CT[e.type].icon)}${h(e.title)} <span class="muted small">${e.edited}</span></a>`).join('')}</div></div></div></div>`;
}
function entriesB(r) {
  const fkey = 'hub:' + r.type, types = hubTypes(r.type);
  let list = applyFilters(ENTRIES.filter(e => types.includes(e.type)), fkey);
  const sub = r.type in COLLECTIONS ? COLLECTIONS[r.type].types : null;
  if (sub && r.sub) list = list.filter(e => e.type === r.sub);
  if (r.missing) list = list.filter(e => e.pub !== 'never' && entryLocales(e).includes(r.missing) && e.tr[r.missing] !== 'ok');
  const ordered = list.length && list.every(e => CT[e.type].ordered);
  if (ordered) list.sort((a, b) => a.order - b.order);
  const title = HUB_TYPES.find(x => x[0] === r.type)[1];
  const chips = sub ? `<div class="ui mini basic buttons">${[[null, 'All'], ...sub.map(t => [t, CT[t].plural])].map(([t, l]) => `<a class="ui button${(r.sub ?? null) === t ? ' active' : ''}" ${R({ ...r, sub: t })}>${l}</a>`).join('')}</div>` : '';
  return `${syHeader(HUB_TYPES.find(x => x[0] === r.type)[2], title, r.type === 'all' ? 'Every entry of every content type' : '', [{ t: 'Content', act: R({ s: 'home' }) }, { t: title }], `<button class="ui labeled icon primary button" data-act="create" ${r.type !== 'all' && !(r.type in COLLECTIONS) ? `data-t="${r.type}"` : ''}>${icon('plus')}Create</button>`)}
  ${chips}${r.missing ? `<div class="ui small info message visible">Showing entries that aren't complete in ${LOCALE_NAMES[r.missing]}. <a ${R({ s: 'entries', type: r.type })}>Clear</a></div>` : ''}<div class="ui segment">${filterBar(fkey)}</div>
  ${ordered ? `<p class="small muted">${icon('sort')}Drag to set the order shoppers see.</p>` : ''}
  <table class="ui very basic selectable table sy-grid"><thead><tr>${ordered ? '<th></th>' : ''}<th>Title</th>${r.type === 'all' ? '<th>Type</th>' : ''}<th>Status</th><th>Translations</th><th>Last edited</th><th></th></tr></thead><tbody>
  ${list.map(e => `<tr class="click" ${R({ s: 'entry', id: e.uuid })}>${ordered ? `<td class="collapsing"><span class="handle">⠿</span></td>` : ''}<td><b>${h(e.title)}</b>${e.homepageOf.length ? ' ' + lab('teal', `${icon('home')}Homepage`) : ''}<div class="small muted">${entryUrl(e) ?? ''}${e.categories.length ? ' · ' + catNames(e) : ''}</div></td>${r.type === 'all' ? `<td class="small">${icon(CT[e.type].icon)}${typeLabel(e)}</td>` : ''}<td>${statusLabels(e)}</td><td>${trChips(e)}</td><td class="small muted">${e.edited}</td><td class="collapsing">${problemIcon(entryProblems(e))}</td></tr>`).join('') || '<tr><td class="muted">Nothing matches.</td></tr>'}</tbody></table>
  <div class="small muted">Click a row to see the entry's overview. The builder opens from there.</div>`;
}
function entryB(id) {
  const e = byId[id], ct = CT[e.type], P = entryProblems(e);
  const backTo = ct.collection ? { t: COLLECTIONS[ct.collection].label, r: { s: 'entries', type: ct.collection } } : { t: ct.plural, r: { s: 'entries', type: e.type } };
  const placements = e.type === 'snippet' ? `<div class="ui segment"><h4 class="ui header">${icon('th large')}Placements <button class="ui mini right floated button" data-act="addPlacement" data-snippet="${e.uuid}">${icon('plus')}Add placement</button></h4>
    ${e.context ? `<p class="small muted">Needs <b>${e.context}</b>, so only slots that provide it are offered.</p>` : ''}${placementTable(snippetPlacements(e.uuid), { showSnippet: false })}
    ${snippetPlacements(e.uuid).filter(p => slotById(p.slot).cap).map(p => `<button class="ui mini basic button" data-act="slotOrder" data-id="${p.slot}">${icon('sort')}Order ${h(slotById(p.slot).label)} (${slotById(p.slot).page})…</button>`).join(' ')}</div>` : '';
  return `${syHeader(ct.icon, h(e.title), `${ct.label}${entryUrl(e) ? ' · ' + entryUrl(e) : ''}`, [{ t: 'Content', act: R({ s: 'home' }) }, { t: backTo.t, act: R(backTo.r) }, { t: h(e.title) }],
    `<button class="ui labeled icon primary button" data-act="openBuilder" data-id="${e.uuid}">${icon('paint brush')}Open in builder</button><button class="ui button" data-act="newShare" data-id="${e.uuid}">${icon('linkify')}Share draft</button>`)}
  <div class="tags">${statusLabels(e)}</div>
  <div class="ui two column stackable grid"><div class="nine wide column">
    <div class="ui segment"><h4 class="ui header">${icon('send')}Publishing</h4>${scheduleBox(e)}</div>
    ${P.length ? `<div class="ui segment"><h4 class="ui header">${icon('exclamation triangle orange')}Problems</h4>${problemsList(P)}</div>` : ''}
    ${placements}
    <div class="ui segment"><h4 class="ui header">${icon('eye')}Where it shows</h4>${availability(e)}</div>
    <div class="ui segment"><h4 class="ui header">${icon('history')}Revisions</h4>${revisionsList(e)}</div></div>
  <div class="seven wide column">
    <div class="ui segment"><h4 class="ui header">${icon('linkify')}Share links</h4>${shareLinksList(e)}</div>
    ${ct.url ? `<div class="ui segment"><h4 class="ui header">${icon('linkify')}URLs</h4>${entryLocales(e).map(l => `<div class="small"><code>${entryUrl(e, l)}</code></div>`).join('')}${e.oldSlugs.length ? `<div class="small muted mt">Old slugs redirect: ${e.oldSlugs.map(s => `<code>${s}</code>`).join(', ')}</div>` : ''}${e.type === 'page' ? `<div class="ui form mt">${homepageField(e)}</div>` : ''}</div>` : ''}
    ${e.type === 'snippet' ? `<div class="ui segment"><h4 class="ui header">${icon('linkify')}Used in</h4>${usedInList(e)}</div>` : ''}
    <div class="ui segment"><h4 class="ui header">${icon('language')}Translations</h4>${trChips(e)}<p class="small muted mt">Open the builder to translate. Its locale switcher shows the same counts.</p></div></div></div>`;
}

/* =========================================================== C · Store map */
function mapNodes() {
  const ch = S.mapChannel, inCh = e => e.channels.includes(ch);
  const en = (e, extra = {}) => ({ id: 'e:' + e.uuid, label: e.title, icon: CT[e.type].icon, sel: { t: 'entry', id: e.uuid }, sub: entryUrl(e)?.replace(/^\/[a-z_A-Z]+/, '') ?? '', P: entryProblems(e), muted: e.pub === 'never' || !e.enabled || e.window?.state === 'ended' || !!e.routeOverride, e, ...extra });
  const grp = (id, label, ic, kids, extra = {}) => ({ id, label, icon: ic, kids, sel: { t: 'group', id }, ...extra });
  const pages = ENTRIES.filter(e => e.type === 'page' && inCh(e) && !e.homepageOf.includes(ch)).sort((a, b) => a.slug.localeCompare(b.slug));
  const coll = k => {
    const C = COLLECTIONS[k], items = ENTRIES.filter(e => C.types.includes(e.type) && inCh(e));
    const catT = C.types.find(t => CT[t].categoryOf), itemT = C.types.find(t => !CT[t].categoryOf);
    let kids;
    if (catT) {
      const cats = items.filter(e => e.type === catT);
      kids = cats.map(c => en(c, { kids: items.filter(e => e.categories.includes(c.uuid)).sort((a, b) => (a.order ?? 0) - (b.order ?? 0)).map(x => en(x, { id: `e:${x.uuid}@${c.uuid}` })) }));
      const none = items.filter(e => e.type === itemT && !e.categories.some(c => byId[c]));
      if (none.length) kids.push(grp(`${k}:none`, 'Not in a category', 'folder outline', none.map(en), { muted: true }));
    } else kids = items.map(e => en(e));
    return grp('coll:' + k, C.label, C.icon, kids, { sub: C.agency ? 'added by your agency' : '' });
  };
  const shop = SHOP_PAGES.map(pg => grp('shop:' + pg, pg, 'window maximize outline', SLOTS.filter(s => s.page === pg).map(s => {
    const notes = placementNotes(s.id);
    return { id: 'slot:' + s.id, label: s.label, icon: 'square outline', sel: { t: 'slot', id: s.id }, P: slotWarnings(s), sub: s.cap ? `capped at ${s.cap}` : '', muted: homepageHidden(s),
      kids: placementsIn(s.id).map(p => ({ id: 'pl:' + p.id, label: byId[p.snippet].title, icon: 'puzzle piece', sel: { t: 'slot', id: s.id, p: p.id }, sub: p.scope === 'All pages' ? '' : p.scope, muted: notes[p.id].cls === 'muted' || !byId[p.snippet].channels.includes(ch), P: entryProblems(byId[p.snippet]).filter(x => x.kind !== 'hidden') })) };
  }), { muted: SLOTS.filter(s => s.page === pg).every(homepageHidden), sub: SLOTS.filter(s => s.page === pg).every(homepageHidden) ? 'not shown: CMS homepage' : '' }));
  return [
    grp('home', 'Homepage', 'home', ENTRIES.filter(e => e.homepageOf.includes(ch)).map(e => en(e, { sub: '/' }))),
    grp('pages', 'Pages', 'file alternate outline', pages.map(e => en(e))),
    ...Object.keys(COLLECTIONS).map(coll),
    grp('shop', 'Shop pages', 'shopping bag', shop, { sub: 'slots and placements' }),
  ];
}
const nodeProblems = n => [...(n.P ?? []), ...(n.kids ?? []).flatMap(nodeProblems)];
function renderTree(nodes, depth = 0) {
  const only = S.onlyProblems;
  return nodes.map(n => {
    const P = nodeProblems(n), big = P.filter(p => p.sev !== 'info');
    if (only && !big.length) return '';
    const open = S.open[n.id] ?? (depth < 1 || only);
    const sel = S.route.sel && JSON.stringify(S.route.sel) === JSON.stringify(n.sel);
    return `<div class="tnode${sel ? ' sel' : ''}${n.muted ? ' muted' : ''}" style="padding-left:${6 + depth * 14}px" data-act="msel2" data-sel="${h(JSON.stringify(n.sel))}">
      ${n.kids?.length ? `<i class="caret ${open ? 'down' : 'right'} icon" data-act="ttoggle" data-id="${h(n.id)}"></i>` : '<i class="icon"></i>'}${icon(n.icon)}<span class="tl">${h(n.label)}</span>${n.sub ? `<span class="tsub">${h(n.sub)}</span>` : ''}
      ${n.e ? `<span class="tdots">${n.e.pub === 'changes' ? '<i class="pencil icon yellow" title="Unpublished changes"></i>' : ''}${['scheduled'].includes(n.e.pub) ? '<i class="clock outline icon blue" title="Scheduled"></i>' : ''}</span>` : ''}${big.length ? problemIcon(big) : ''}</div>
      ${n.kids?.length && open ? renderTree(n.kids, depth + 1) : ''}`;
  }).join('');
}
function renderC() {
  const r = S.route, ch = S.mapChannel;
  const menu = sySection('Content', syItem(true, 'sitemap', 'Store content', R({ s: 'map', sel: { t: 'group', id: 'home' } }), countBadge(allProblems())));
  const nodes = mapNodes();
  const big = allProblems().filter(p => p.sev !== 'info').length;
  const left = `<div class="maptree"><div class="mapchan"><select data-act="mapch">${CHANNELS.map(c => `<option value="${c.code}" ${c.code === ch ? 'selected' : ''}>${c.name}</option>`).join('')}</select>
      <button class="ui mini primary icon button" data-act="create" title="Create">${icon('plus')}</button></div>
    <div class="mappills"><a class="mpill${r.sel?.t === 'attention' ? ' on' : ''}" ${R({ s: 'map', sel: { t: 'attention' } })}>${icon('exclamation triangle')}${big} need attention</a><a class="mpill${r.sel?.t === 'upcoming' ? ' on' : ''}" ${R({ s: 'map', sel: { t: 'upcoming' } })}>${icon('clock outline')}${UPCOMING.length} coming up</a>
      <label class="small"><input type="checkbox" data-act="onlyProblems" ${S.onlyProblems ? 'checked' : ''}> Only problems</label></div>
    <div class="tree">${renderTree(nodes)}</div>
    <div class="tlib"><div class="tlibh">Library</div>${[['snippets', 'puzzle piece', 'All snippets'], ['media', 'images outline', 'Media library'], ['presets', 'star outline', 'Presets'], ['transfer', 'exchange', 'Transfer']].map(([k, ic, t]) => `<div class="tnode${r.sel?.t === 'lib' && r.sel.id === k ? ' sel' : ''}" ${R({ s: 'map', sel: { t: 'lib', id: k } })}><i class="icon"></i>${icon(ic)}${t}</div>`).join('')}</div></div>`;
  return syliusChrome(menu, `<div class="map">${left}<div class="mappanel">${panelC(r.sel ?? { t: 'group', id: 'home' }, nodes)}</div></div>`, { wide: true });
}
function findNode(nodes, id) { for (const n of nodes) { if (n.id === id) return n; const k = n.kids && findNode(n.kids, id); if (k) return k; } }
function panelC(sel, nodes) {
  const ch = S.mapChannel;
  if (sel.t === 'attention') return `<h3 class="ui header">${icon('exclamation triangle orange')}Needs attention</h3><p class="small muted">The same problems are flagged in place in the tree. Tick "Only problems" to see just those branches.</p>${problemsGrouped(p => p.sev !== 'info')}`;
  if (sel.t === 'upcoming') return `<h3 class="ui header">${icon('clock outline')}Coming up</h3>${upcomingList()}`;
  if (sel.t === 'lib') {
    if (sel.id === 'media') return mediaScreen(`<h3 class="ui header">${icon('images outline')}Media library</h3>`);
    if (sel.id === 'presets') return presetsScreen(`<h3 class="ui header">${icon('star outline')}Presets</h3>`);
    if (sel.id === 'transfer') return transferScreen(`<h3 class="ui header">${icon('exchange')}Transfer</h3>`);
    return `<h3 class="ui header">${icon('puzzle piece')}All snippets <button class="ui mini right floated primary button" data-act="create" data-t="snippet">${icon('plus')}Create snippet</button></h3><p class="small muted">Placed snippets also appear under the slots they're in. Snippets only referenced from other entries, or not used at all, are found here.</p>
      <table class="ui very basic selectable table sy-grid"><tbody>${ofType('snippet').map(e => `<tr class="click" data-act="msel2" data-sel="${h(JSON.stringify({ t: 'entry', id: e.uuid }))}"><td><b>${h(e.title)}</b><div class="small muted">${snippetPlacements(e.uuid).length ? plural(snippetPlacements(e.uuid).length, 'placement') : usedInList(e).includes('item') ? 'Referenced from other entries' : 'Not used'}</div></td><td>${statusLabels(e, { compact: true })}</td><td>${problemIcon(entryProblems(e).filter(p => p.sev !== 'info'))}</td></tr>`).join('')}</tbody></table>`;
  }
  if (sel.t === 'slot') {
    const s = slotById(sel.id), W = slotWarnings(s);
    return `<div class="crumbs small muted">Shop pages › ${h(s.page)}</div><h3 class="ui header">${icon('square outline')}${h(s.label)}<div class="sub header"><code>${s.id}</code>${s.ctx.length ? ` · provides ${s.ctx.join(', ')}` : ''}${s.cap ? ` · capped at ${s.cap}` : ''}</div></h3>
      ${W.map(w => `<div class="ui warning message visible">${icon('exclamation triangle')}${h(w.text)}</div>`).join('')}
      <p class="small muted">${s.cap ? `Only the first ${s.cap === 1 ? 'placement' : s.cap} that match show, in this order.` : 'Every placement that matches shows, in this order.'} Placements take effect on save.</p>
      ${placementTable(placementsIn(s.id), { showSlot: false, reorder: true, hl: sel.p })}
      <button class="ui mini primary button" data-act="addPlacement" data-slot="${s.id}">${icon('plus')}Add placement</button>
      <div class="mt"><a class="small" data-act="toast" data-msg="Opens the shop page with this slot outlined (not built)">${icon('external')}See it in the shop</a></div>`;
  }
  if (sel.t === 'group') {
    const n = findNode(nodes, sel.id);
    if (!n) return '<p class="muted">Pick something in the map.</p>';
    if (sel.id.startsWith('shop:') || sel.id === 'shop') return `<h3 class="ui header">${icon(n.icon)}${h(n.label)}</h3>${n.sub ? `<p class="orange small">${icon('info circle')}${h(n.sub)}</p>` : ''}<p class="small muted">Slots are declared in code. Pick one to see and order its placements.</p>
      <table class="ui very basic selectable table sy-grid"><tbody>${(n.kids ?? []).map(k => `<tr class="click" data-act="msel2" data-sel="${h(JSON.stringify(k.sel))}"><td>${icon(k.icon)}<b>${h(k.label)}</b> <span class="small muted">${h(k.sub ?? '')}</span></td><td class="small">${(k.kids ?? []).map(x => h(x.label)).join(', ') || '<span class="muted">Empty</span>'}</td><td>${problemIcon(nodeProblems(k).filter(p => p.sev !== 'info'))}</td></tr>`).join('')}</tbody></table>`;
    const flat = []; const walk = L => L.forEach(k => { if (k.e && !flat.includes(k.e)) flat.push(k.e); if (k.kids) walk(k.kids); }); walk(n.kids ?? []);
    const t = sel.id === 'pages' || sel.id === 'home' ? 'page' : sel.id.startsWith('coll:') ? COLLECTIONS[sel.id.slice(5)].types.find(x => !CT[x].categoryOf) : null;
    const ordered = t && CT[t].ordered;
    return `<h3 class="ui header">${icon(n.icon)}${h(n.label)} <span class="small muted">in ${chName(ch)}</span>${t ? `<button class="ui mini right floated primary button" data-act="create" data-t="${t}">${icon('plus')}Create ${CT[t].label.toLowerCase()}</button>` : ''}</h3>
      ${sel.id === 'home' ? `<p class="small muted">${icon('home')}Each channel has at most one homepage. It replaces the Sylius homepage. Set it in a page's panel.</p>` : ''}
      ${ordered ? `<p class="small muted">${icon('sort')}Drag to set the order shoppers see.</p>` : ''}
      <table class="ui very basic selectable table sy-grid"><tbody>${flat.map(e => `<tr class="click" data-act="msel2" data-sel="${h(JSON.stringify({ t: 'entry', id: e.uuid }))}">${ordered ? '<td class="collapsing"><span class="handle">⠿</span></td>' : ''}<td><b>${h(e.title)}</b><div class="small muted">${entryUrl(e) ?? ''}${e.categories.length ? ' · ' + catNames(e) : ''}</div></td><td>${statusLabels(e)}</td><td>${trChips(e)}</td><td>${problemIcon(entryProblems(e))}</td></tr>`).join('')}</tbody></table>`;
  }
  const e = byId[sel.id], ct = CT[e.type], P = entryProblems(e);
  return `<div class="crumbs small muted">${ct.label}${ct.collection ? ' · ' + COLLECTIONS[ct.collection].label : ''}</div>
    <h3 class="ui header">${icon(ct.icon)}${h(e.title)}<div class="sub header">${entryUrl(e) ?? ''}</div></h3>
    <div class="tags">${statusLabels(e)}</div>
    <div class="mt"><button class="ui labeled icon primary button" data-act="openBuilder" data-id="${e.uuid}">${icon('paint brush')}Open in builder</button><button class="ui button" data-act="newShare" data-id="${e.uuid}">${icon('linkify')}Share draft</button><button class="ui icon basic button" data-act="toast" data-msg="Duplicate, Delete (not built)">${icon('ellipsis horizontal')}</button></div>
    <div class="mt">${scheduleBox(e)}</div>
    ${P.length ? `<h5>Problems</h5>${problemsList(P)}` : ''}
    <h5>Where it shows</h5>${availability(e)}
    ${e.type === 'snippet' ? `<h5>Placements</h5>${placementTable(snippetPlacements(e.uuid), { showSnippet: false })}<button class="ui mini button" data-act="addPlacement" data-snippet="${e.uuid}">${icon('plus')}Add placement</button><h5>Used in</h5>${usedInList(e)}` : ''}
    ${e.type === 'page' ? `<div class="ui form mt">${homepageField(e)}</div>` : ''}
    ${e.oldSlugs.length ? `<h5>Old slugs</h5><div class="small">${e.oldSlugs.map(s => `<code>${s}</code>`).join(', ')} redirect here with a 301</div>` : ''}
    <h5>Revisions</h5>${revisionsList(e)}<h5>Share links</h5>${shareLinksList(e)}`;
}

/* =========================================================== Variant registry */
const linkOutTab = where => ({ k: 'more', label: 'Publishing & more', html: e => `<p class="small">Revisions, share links${e.type === 'snippet' ? ', placements' : ''} and where it shows are ${where}.</p><button class="ui mini button" data-act="bback">${icon('arrow left')}Go there</button><div class="mt">${scheduleBox(e)}</div>` });
const VARIANTS = {
  A: {
    name: 'Sylius menu', init() { S.route = { s: 'list', key: 'page' }; }, render: renderA,
    builderTabs: e => [{ k: 'settings', label: 'Settings', html: bSettings }, ...(CT[e.type].url ? [{ k: 'seo', label: 'URL & SEO', html: bSeo }] : []), { k: 'pub', label: 'Publishing', html: pubPanelA },
      ...(e.type === 'snippet' ? [{ k: 'place', label: `Placements (${snippetPlacements(e.uuid).length})`, html: e => `${placementTable(snippetPlacements(e.uuid), { showSnippet: false })}<button class="ui mini button" data-act="addPlacement" data-snippet="${e.uuid}">${icon('plus')}Add placement</button><p class="small muted mt">To order a slot's placements, open the slot from Content › Slots.</p><h5>Used in</h5>${usedInList(e)}` }] : []),
      { k: 'probs', label: `Problems${entryProblems(e).length ? ` (${entryProblems(e).length})` : ''}`, html: e => problemsList(entryProblems(e)) }],
    backLabel() { const r = S.route; return r.s === 'list' ? LISTS_A[r.key].title : r.s === 'slot' ? 'Slot' : r.s === 'dash' ? 'Dashboard' : r.s === 'media' ? 'Media library' : r.s[0].toUpperCase() + r.s.slice(1); },
    openEntry(id) { openBuilder(id); },
    openProblem(ref) { if (ref.t === 'entry') { openBuilder(ref.id); S.builder.tab = 'probs'; } else if (ref.t === 'slot') go({ s: 'slot', id: ref.id }); else if (ref.t === 'preset') go({ s: 'presets' }); else { S.media.sel = ref.id; go({ s: 'media' }); } },
    openSlot(id) { go({ s: 'slot', id }); },
  },
  B: {
    name: 'Content hub', init() { S.route = { s: 'home' }; }, render: renderB,
    builderTabs: e => [{ k: 'settings', label: 'Settings', html: bSettings }, ...(CT[e.type].url ? [{ k: 'seo', label: 'URL & SEO', html: bSeo }] : []), linkOutTab('on the entry\'s overview page')],
    backLabel() { return S.route.s === 'entry' ? byId[S.route.id].title : 'Content'; },
    openEntry(id) { if (S.builder) { S.builder = null; } go({ s: 'entry', id }); },
    openProblem(ref) { if (ref.t === 'entry') go({ s: 'entry', id: ref.id }); else if (ref.t === 'slot') S.modal = { k: 'slotOrder', id: ref.id }; else if (ref.t === 'preset') go({ s: 'presets' }); else { S.media.sel = ref.id; go({ s: 'media' }); } },
    openSlot(id) { S.modal = { k: 'slotOrder', id }; },
  },
  C: {
    name: 'Store map', init() { S.route = { s: 'map', sel: { t: 'group', id: 'home' } }; }, render: renderC,
    builderTabs: e => [{ k: 'settings', label: 'Settings', html: bSettings }, ...(CT[e.type].url ? [{ k: 'seo', label: 'URL & SEO', html: bSeo }] : []), linkOutTab('in its panel in the store map')],
    backLabel() { return 'Store map'; },
    openEntry(id) { S.builder = null; const e = byId[id]; if (!e.channels.includes(S.mapChannel)) S.mapChannel = e.channels[0]; go({ s: 'map', sel: { t: 'entry', id } }); },
    openProblem(ref) { if (ref.t === 'entry') this.openEntry(ref.id); else if (ref.t === 'slot') go({ s: 'map', sel: { t: 'slot', id: ref.id } }); else if (ref.t === 'preset') go({ s: 'map', sel: { t: 'lib', id: 'presets' } }); else { S.media.sel = ref.id; go({ s: 'map', sel: { t: 'lib', id: 'media' } }); } },
    openSlot(id) { go({ s: 'map', sel: { t: 'slot', id } }); },
  },
};
