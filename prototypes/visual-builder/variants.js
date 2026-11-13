/* PROTOTYPE variants (throwaway). Three structurally different shells around the same data and widgets.

   A · Three panes        Full-screen builder. Outline tree left, live preview centre, form for the selected block right.
                          (Storyblok, Shopify theme editor.) The tree is the main way to see and change structure.
   B · Canvas first       Full-screen builder. The preview is the editor: click to select, type text in place,
                          hover between blocks to add. Fields float in a drawer; the tree is an optional Layers panel.
                          (WordPress Site Editor, Builder.io, Shopify's preview inspector.)
   C · Form in the admin  Stays inside the Sylius admin, like editing a product. The block tree is nested cards
                          with their fields inline; the preview is a side panel you can hide.
                          (Payload, Wagtail StreamField, Sylius's own forms.) */

const VARIANTS = [
  { k: 'A', name: 'Three panes', fn: variantA },
  { k: 'B', name: 'Canvas first', fn: variantB },
  { k: 'C', name: 'Form in the admin', fn: variantC },
];

function topbar(e, extraLeft = '') {
  return `<header class="topbar">
    <div class="tb-left"><a class="tb-back" data-act="noop" title="Back to the admin">${icon('arrow left')}</a>
      <div class="tb-title"><div class="tb-name">${esc(entryTitle(e))}</div><div class="tb-sub">${CT_LABEL[e.contentType]} · draft · ${e.enabled ? 'enabled' : 'disabled'}</div></div>${extraLeft}</div>
    <div class="tb-mid">${localeSwitcher()}${channelSwitcher()}${contextControls()}</div>
    <div class="tb-right">${deviceToggle()}${undoBtn()}${saveBtn()}</div></header>`;
}

/* ---------------- A · Three panes ---------------- */
function variantA() {
  const e = entry();
  return `<div class="vA">${topbar(e)}${banners()}
    <div class="vA-body">
      <aside class="vA-tree" data-scroll="treeA"><div class="pane-h">Structure <span class="muted">· drag to move, ⌘-click to multi-select</span></div>${treeHTML()}</aside>
      <main class="vA-preview" data-scroll="pvA"><div class="frame ${S.device}">${browserBar()}<div class="pv" data-region="preview">${previewHTML('A')}</div></div></main>
      <aside class="vA-insp" data-scroll="inspA">${inspectorHTML()}</aside>
    </div></div>`;
}

/* ---------------- B · Canvas first ---------------- */
function drawerOpen() { return S.ui.drawer && (S.selected || S.multi.length || S.ui.entrySettings); }
function variantB() {
  const e = entry();
  const extra = `<button class="btn tbtn ${S.ui.layers ? 'on' : ''}" data-act="toggle-layers" title="Show the block tree">${icon('bars')}Layers</button>
    <button class="btn tbtn ${!S.selected && S.ui.entrySettings && S.ui.drawer ? 'on' : ''}" data-act="entry-settings">${icon('cog')}Settings</button>`;
  return `<div class="vB">${topbar(e, extra)}${banners()}
    <div class="vB-sub">${crumbsHTML()}<span class="muted hint-line">Click a block to select it · click its text to type · hover between blocks for <b>+</b></span></div>
    <div class="vB-body">
      ${S.ui.layers ? `<aside class="vB-layers" data-scroll="layersB"><div class="pane-h">Layers</div>${treeHTML()}</aside>` : ''}
      <main class="vB-canvas ${drawerOpen() ? 'with-drawer' : ''}" data-scroll="pvB"><div class="frame ${S.device}"><div class="pv edit-B" data-region="preview">${previewHTML('B')}</div></div></main>
      ${drawerOpen() ? `<aside class="vB-drawer" data-scroll="drawerB">${inspectorHTML({ close: true })}</aside>` : ''}
    </div></div>`;
}

/* ---------------- C · Form in the admin ---------------- */
function admSidebar(e) {
  const it = (ic, l, on) => `<div class="adm-it ${on ? 'on' : ''}">${icon(ic)}${l}</div>`;
  return `<div class="adm-logo">Sylius</div>
    <div class="adm-sec">Catalog</div>${it('sitemap', 'Taxons')}${it('cube', 'Products')}${it('tags', 'Attributes')}
    <div class="adm-sec">Sales</div>${it('cart', 'Orders')}${it('credit card', 'Payments')}
    <div class="adm-sec">Customers</div>${it('users', 'Customers')}
    <div class="adm-sec">Marketing</div>${it('percent', 'Promotions')}
    <div class="adm-sec">Content</div>${it('file alternate outline', 'Pages', e.contentType === 'page')}${it('clone outline', 'Snippets', e.contentType === 'snippet')}${it('bars', 'Menus', e.contentType === 'menu')}${it('newspaper outline', 'Blog')}${it('images outline', 'Media')}
    <div class="adm-sec">Configuration</div>${it('cogs', 'Channels')}`;
}
function linkInline(b) {
  const v = b.fields.label;
  const own = v.translations[S.locale] || '';
  const fb = strip(trResolve(v, S.locale).value) || targetName(b.fields.target);
  return `<input class="inp sm inline-lbl" data-f="block" data-key="${b.key}" data-field="label" value="${esc(own)}" placeholder="${esc(isEmptyVal(own) ? fb : '')}" title="Label">
    <span class="muted small">→ ${esc(b.fields.target ? `${TARGET_TYPES[b.fields.target.type]}: ${targetName(b.fields.target)}` : 'no target')}</span><span class="sum"></span>`;
}
function cardHTML(b) {
  const d = BT[b.type], open = !!S.ui.expanded[b.key], sel = S.selected === b.key, multi = S.multi.includes(b.key);
  const hid = hiddenBy(b), fl = blockFlags(b), lw = linkWarn(b);
  const head = `<div class="card-h" data-act="card-toggle" data-key="${b.key}" draggable="true" data-drag="${b.key}" data-drop="${b.key}">
      <span class="grip">⠿</span>${icon(open ? 'caret down' : 'caret right')}${icon(d.icon)}<b>${esc(d.label)}</b>
      ${b.type === 'menuLink' && !open ? linkInline(b) : `<span class="sum" data-summary="${b.key}">${esc(summary(b))}</span>`}
      <span class="tb">${hid ? `<span class="bdg" title="${esc(hid)}">${icon('eye slash')}${esc(hid)}</span>` : ''}${fl.o ? '<span class="bdg out">Outdated</span>' : fl.m ? '<span class="bdg miss">Untranslated</span>' : ''}${lw ? `<span class="bdg out" title="${esc(lw)}">${icon('exclamation triangle')}Hidden link</span>` : ''}${b.type === 'snippetRef' ? '<span class="bdg ref">Shared</span>' : ''}</span>
      <span class="card-acts">${blockActions(b, 'cact')}</span></div>`;
  let body = '';
  if (open) {
    const kids = d.children ? `<div class="sec-h kids-h">${icon('sitemap')}Contains</div><div class="card-kids">${b.children.map(cardHTML).join('')}
      ${canAddTo(b) ? `<button class="btn sm c-add" data-act="insert" data-parent="${b.key}" data-index="${b.children.length}" data-drop-end="${b.key}">${icon('plus')}Add ${esc(addLabel(b))}</button>` : `<div class="fnote">${esc(d.label)} holds at most ${d.max}.</div>`}</div>` : '';
    body = `<div class="card-b">${fieldsHTML({ f: 'block', key: b.key }, d.fields, b.fields)}${kids}${conditionsHTML(b)}</div>`;
  } else if (d.children && b.children.length) {
    body = `<div class="card-collapsed muted small">${b.children.length} inside: ${esc(b.children.slice(0, 4).map(c => summary(c) || BT[c.type].label).join(', '))}${b.children.length > 4 ? '…' : ''}</div>`;
  }
  return `<div class="card ${sel ? 'sel' : ''} ${multi ? 'multi' : ''} ${hid ? 'hid' : ''}" data-card="${b.key}">${head}${body}</div>`;
}
function variantC() {
  const e = entry();
  const n = countBlocks(e.tree);
  const isMenu = e.contentType === 'menu';
  const details = `<div class="seg"><div class="seg-h">${icon('cog')}${CT_LABEL[e.contentType]} details</div><div class="seg-b"><div class="grid-entry"><div>${fieldsHTML({ f: 'entry', key: '' }, ENTRY_FIELDS[e.contentType] || [], e.fields)}${channelsHTML(e)}</div><div>${translationsHTML(e)}${e.contentType === 'snippet' ? snippetInfoHTML(e) : ''}</div></div></div></div>`;
  const content = `<div class="seg"><div class="seg-h">${icon(isMenu ? 'sitemap' : 'th list')}${isMenu ? 'Menu items' : 'Content'} <span class="muted" style="font-weight:400">${n} blocks</span>
      <span class="seg-links"><a data-act="expand-all">Expand all</a> · <a data-act="collapse-all">Collapse all</a></span></div>
    <div class="seg-b">${e.tree.map(cardHTML).join('')}<button class="btn c-add" data-act="insert" data-parent="" data-index="${e.tree.length}" data-drop-end="">${icon('plus')}Add ${isMenu ? 'menu item' : 'block'}</button></div></div>`;
  return `<div class="vC"><aside class="adm-side">${admSidebar(e)}</aside>
    <div class="adm-main">
      <div class="adm-top"><span class="muted">${icon('search')}Search</span><span class="muted" style="margin-left:auto">${icon('user')}Admin</span></div>
      <div class="adm-head"><div class="adm-crumb">Administration › ${isMenu ? 'Menus' : e.contentType === 'snippet' ? 'Snippets' : 'Pages'} › ${esc(e.name)} › Edit</div>
        <h1>${icon(CT_ICON[e.contentType])}<span>${esc(entryTitle(e))}<span class="sub">Edit ${CT_LABEL[e.contentType].toLowerCase()} · draft</span></span></h1>
        <div class="adm-actions">${localeSwitcher()}${channelSwitcher()}${contextControls()}<button class="btn tbtn ${S.ui.preview ? 'on' : ''}" data-act="toggle-preview">${icon('eye')}Preview</button>${undoBtn()}${saveBtn('Save changes')}</div></div>
      ${banners()}
      <div class="vC-split">
        <div class="vC-form" data-scroll="formC">${isMenu ? content + details : details + content}</div>
        ${S.ui.preview ? `<div class="vC-pv" data-scroll="pvC"><div class="pv-h">${deviceToggle()}<span class="muted small">Live preview · click a block to open it</span></div><div class="frame ${S.device}">${browserBar()}<div class="pv" data-region="preview">${previewHTML('C')}</div></div></div>` : ''}
      </div></div></div>`;
}
