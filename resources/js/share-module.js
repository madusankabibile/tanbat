// Reusable share popup + post 3-dot dropdown wiring.
// Loaded on every page that includes partials/share-modal.blade.php.
//
// Public API (window.Tanbat.openShare):
//   openShare({ kind: 'post' | 'profile', url, title, image })
//
// Public API (window.Tanbat.bindPostMenu):
//   bindPostMenu(rootEl, handlers)
//     handlers: { onChange?: (postId, patch) => void, onRemove?: (postId) => void }
//   Attaches a delegated listener to rootEl that handles 3-dot dropdowns
//   inside any [data-post-id] descendant: not_interested / hide / save /
//   edit / delete. The onChange/onRemove callbacks let the host page keep
//   its in-memory post list and DOM in sync.

(function () {
  const APP   = window.__APP__ || {};
  const toast = window.Tanbat?.toast || ((m) => console.log(m));
  const api   = window.Tanbat?.api;
  const openModal  = window.Tanbat?.openModal;
  const closeModal = window.Tanbat?.closeModal;

  // ─────────────── Share modal ───────────────
  let shareCtx = null; // { kind, url, title, image }

  function setText(sel, txt) {
    const el = document.querySelector(sel);
    if (el) el.textContent = txt;
  }
  function setHTML(sel, html) {
    const el = document.querySelector(sel);
    if (el) el.innerHTML = html;
  }

  function renderShareCtx(ctx) {
    const modal = document.getElementById('modalShare');
    if (!modal) return;

    const titleEl   = modal.querySelector('[data-share-preview-title]');
    const urlEl     = modal.querySelector('[data-share-preview-url]');
    const subEl     = modal.querySelector('[data-share-sub]');
    const iconWrap  = modal.querySelector('[data-share-icon]');
    const linkInput = modal.querySelector('[data-share-link-input]');

    if (titleEl)   titleEl.textContent = ctx.title || (ctx.kind === 'profile' ? 'A Tanbat profile' : 'A Tanbat post');
    if (urlEl)     urlEl.textContent   = ctx.url;
    if (linkInput) linkInput.value     = ctx.url;
    if (subEl)     subEl.textContent   = ctx.kind === 'profile'
      ? 'Send this profile to friends or post it elsewhere.'
      : 'Send this post to friends or post it elsewhere.';

    if (iconWrap) {
      if (ctx.image) {
        iconWrap.innerHTML = `<img src="${ctx.image}" alt="">`;
      } else if (ctx.kind === 'profile') {
        iconWrap.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>`;
      } else {
        iconWrap.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>`;
      }
    }
  }

  function openShare(ctx) {
    if (!ctx || !ctx.url) return;
    shareCtx = {
      kind:  ctx.kind || 'post',
      url:   ctx.url,
      title: ctx.title || '',
      image: ctx.image || '',
      postId: ctx.postId || null,
    };
    renderShareCtx(shareCtx);
    openModal?.('modalShare');
  }

  function shareUrlFor(target, ctx) {
    const u = encodeURIComponent(ctx.url);
    const t = encodeURIComponent(ctx.title || 'Tanbat');
    switch (target) {
      case 'whatsapp': return `https://wa.me/?text=${t}%20${u}`;
      case 'facebook': return `https://www.facebook.com/sharer/sharer.php?u=${u}`;
      case 'twitter':  return `https://twitter.com/intent/tweet?url=${u}&text=${t}`;
      case 'linkedin': return `https://www.linkedin.com/sharing/share-offsite/?url=${u}`;
      case 'telegram': return `https://t.me/share/url?url=${u}&text=${t}`;
      case 'reddit':   return `https://www.reddit.com/submit?url=${u}&title=${t}`;
      case 'email':    return `mailto:?subject=${t}&body=${u}`;
      default: return null;
    }
  }

  // Share to the signed-in user's own profile by creating a status post that
  // carries the shared title + URL. Reuses the existing /api/posts/status
  // endpoint so the new post flows through the normal feed/notification path.
  async function shareToOwnProfile(ctx) {
    if (!APP.user) { toast('Sign in to share to your profile', 'bad'); return; }
    const lines = [];
    if (ctx.title) lines.push(ctx.title);
    lines.push(ctx.url);
    const fd = new FormData();
    fd.append('status_text', lines.join('\n\n'));
    try {
      await api(APP.urls.api.statusPost, { method: 'POST', body: fd });
      toast('Shared to your profile', 'ok');
      closeModal?.('modalShare');
      recordShareIfPost(ctx);
      // If the host page is listening, ask it to refresh its feed so the new
      // post appears immediately.
      document.dispatchEvent(new CustomEvent('tanbat:post-shared-to-profile'));
    } catch (err) {
      toast(err.message || 'Could not share to your profile', 'bad');
    }
  }

  function copyToClipboard(text) {
    if (navigator.clipboard?.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
      try {
        const ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        resolve();
      } catch (e) { reject(e); }
    });
  }

  function recordShareIfPost(ctx) {
    if (!APP.user || ctx.kind !== 'post' || !ctx.postId) return;
    api?.(`${APP.urls.api.posts}/${ctx.postId}/share`, { method: 'POST' }).catch(() => {});
  }

  function bindShareModal() {
    const modal = document.getElementById('modalShare');
    if (!modal) return;
    modal.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-share-target]');
      if (!btn || !shareCtx) return;
      const target = btn.dataset.shareTarget;
      if (target === 'copy') {
        copyToClipboard(shareCtx.url).then(
          () => { toast('Link copied to clipboard', 'ok'); recordShareIfPost(shareCtx); },
          () => toast('Could not copy link', 'bad'),
        );
        return;
      }
      if (target === 'my_profile') {
        shareToOwnProfile(shareCtx);
        return;
      }
      const url = shareUrlFor(target, shareCtx);
      if (!url) return;
      window.open(url, '_blank', 'noopener,noreferrer,width=720,height=620');
      recordShareIfPost(shareCtx);
    });
  }

  // ─────────────── Confirm dialog ───────────────
  // Promise-returning confirm modal. Falls back to window.confirm if the
  // host page didn't include the partial (defensive — every page should).
  function confirmDialog(opts = {}) {
    const modal = document.getElementById('modalConfirm');
    if (!modal) {
      return Promise.resolve(window.confirm(opts.message || 'Are you sure?'));
    }
    return new Promise((resolve) => {
      const titleEl = modal.querySelector('[data-confirm-title]');
      const msgEl   = modal.querySelector('[data-confirm-message]');
      const okBtn   = modal.querySelector('[data-confirm-ok]');
      const cancel  = modal.querySelector('[data-confirm-cancel]');
      const iconWrap = modal.querySelector('[data-confirm-icon]');

      if (titleEl) titleEl.textContent = opts.title   || 'Are you sure?';
      if (msgEl)   msgEl.textContent   = opts.message || 'This action cannot be undone.';
      if (okBtn)   okBtn.textContent   = opts.okLabel || 'Confirm';
      if (cancel)  cancel.textContent  = opts.cancelLabel || 'Cancel';

      okBtn?.classList.toggle('is-danger', !!opts.danger);
      iconWrap?.classList.toggle('is-info', !opts.danger);

      const cleanup = (value) => {
        okBtn?.removeEventListener('click', onOk);
        cancel?.removeEventListener('click', onCancel);
        modal.removeEventListener('click', onBackdrop);
        document.removeEventListener('keydown', onKey);
        closeModal?.('modalConfirm');
        resolve(value);
      };
      const onOk     = () => cleanup(true);
      const onCancel = () => cleanup(false);
      const onBackdrop = (e) => { if (e.target === modal) cleanup(false); };
      const onKey = (e) => {
        if (e.key === 'Escape') cleanup(false);
        if (e.key === 'Enter')  cleanup(true);
      };

      okBtn?.addEventListener('click', onOk);
      cancel?.addEventListener('click', onCancel);
      modal.addEventListener('click', onBackdrop);
      document.addEventListener('keydown', onKey);
      openModal?.('modalConfirm');
      setTimeout(() => okBtn?.focus(), 30);
    });
  }

  // ─────────────── Post 3-dot dropdown ───────────────
  function closeAllPostMenus(except) {
    document.querySelectorAll('[data-post-menu].is-open').forEach((m) => {
      if (m !== except) m.classList.remove('is-open');
    });
  }

  // Close menus on outside click / escape — bound once for the whole page.
  let menuBound = false;
  function bindGlobalMenuClose() {
    if (menuBound) return;
    menuBound = true;
    document.addEventListener('click', (e) => {
      if (!e.target.closest('[data-post-menu]')) {
        closeAllPostMenus();
        closeAllSaveSubmenus();
      }
    });
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        closeAllPostMenus();
        closeAllSaveSubmenus();
      }
    });
  }

  // ─────────────── Edit-post modal ───────────────
  let editingPost = null;
  let editChangeCallback = null;

  function openEditModal(post, onChange) {
    editingPost = post;
    editChangeCallback = onChange;
    const modal = document.getElementById('modalEditPost');
    if (!modal) return toast('Edit modal not available', 'bad');

    modal.querySelectorAll('[data-edit-field]').forEach((f) => f.classList.add('hidden'));

    const fields = {
      status_text:       post.type === 'status',
      description:       post.type === 'image' || post.type === 'video',
      title:             post.type === 'article',
      short_description: post.type === 'article',
    };
    Object.entries(fields).forEach(([name, show]) => {
      if (!show) return;
      const wrap = modal.querySelector(`[data-edit-field="${name}"]`);
      if (!wrap) return;
      wrap.classList.remove('hidden');
      const input = wrap.querySelector(`[name="${name}"]`);
      if (input) input.value = post[name] || '';
    });

    openModal?.('modalEditPost');
  }

  function bindEditForm() {
    const form = document.getElementById('formEditPost');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!editingPost) return;
      const fd = new FormData(form);
      const body = {};
      ['title', 'short_description', 'status_text', 'description'].forEach((k) => {
        const wrap = form.querySelector(`[data-edit-field="${k}"]`);
        if (wrap && !wrap.classList.contains('hidden')) {
          body[k] = (fd.get(k) || '').toString();
        }
      });
      const url = APP.urls.api.postUpdate.replace(':id', editingPost.id);
      try {
        const { post } = await api(url, { method: 'PATCH', body });
        toast('Post updated', 'ok');
        closeModal?.('modalEditPost');
        editChangeCallback?.(post.id, post);
        editingPost = null;
      } catch (err) {
        toast(err.message || 'Could not update post', 'bad');
      }
    });
  }

  // ─────────────── Action handlers ───────────────
  async function handleNotInterested(postId, card, handlers) {
    if (!APP.user) return toast('Sign in to personalise your feed', 'bad');
    try {
      const url = APP.urls.api.postHide.replace(':id', postId);
      await api(url, { method: 'POST', body: { reason: 'not_interested' } });
      toast('Got it — we\'ll show fewer posts like this', 'ok');
      animateRemove(card, () => handlers?.onRemove?.(postId));
    } catch (err) { toast(err.message || 'Could not update preference', 'bad'); }
  }

  async function handleHide(postId, card, handlers) {
    if (!APP.user) return toast('Sign in to hide posts', 'bad');
    try {
      const url = APP.urls.api.postHide.replace(':id', postId);
      await api(url, { method: 'POST', body: { reason: 'hide' } });
      toast('Post hidden', 'ok');
      animateRemove(card, () => handlers?.onRemove?.(postId));
    } catch (err) { toast(err.message || 'Could not hide post', 'bad'); }
  }

  async function handleSave(postId, card, handlers, body = null) {
    if (!APP.user) return toast('Sign in to save posts', 'bad');
    try {
      const url = APP.urls.api.postSave.replace(':id', postId);
      const res = await api(url, { method: 'POST', body: body || undefined });

      // Saved-changed event carries the folder id so the modal sidebar can
      // recompute its counts without a full reload.
      const evtDetail = {
        postId,
        saved: res.saved,
        save_category_id: res.save_category_id ?? null,
        moved: !!res.moved,
        new_category: res.new_category || null,
      };
      if (res.moved) toast('Moved', 'ok');
      else toast(res.saved ? 'Saved' : 'Removed from saved', 'ok');

      if (res.new_category) {
        // Eagerly invalidate the in-memory folder cache so the next dropdown
        // open reflects the newly-created folder.
        folderCache = null;
      }

      handlers?.onChange?.(postId, {
        saved: res.saved,
        save_category_id: res.save_category_id ?? null,
      });
      document.dispatchEvent(new CustomEvent('tanbat:saved-changed', { detail: evtDetail }));
      refreshSaveButtonChrome(card, res.saved);
    } catch (err) { toast(err.message || 'Could not save post', 'bad'); }
  }

  function refreshSaveButtonChrome(card, saved) {
    const wrap = card?.querySelector('[data-post-menu]');
    const btn  = wrap?.querySelector('[data-post-act="save"]');
    if (!btn) return;
    btn.classList.toggle('is-active', saved);
    const title = btn.querySelector('.pmi-title');
    const sub   = btn.querySelector('.pmi-sub');
    const ic    = btn.querySelector('.pmi-ic svg');
    if (title) title.textContent = saved ? 'Unsave post' : 'Save post';
    if (sub)   sub.textContent   = saved
      ? 'Removed from your saved items'
      : 'Open it later from your bookmarks';
    if (ic)    ic.setAttribute('fill', saved ? 'currentColor' : 'none');
    const saveWrap = card?.querySelector('[data-save-wrap]');
    saveWrap?.classList.toggle('is-saved', saved);
  }

  // ─────────────── Save-to-folder submenu ───────────────
  // Folder list is cached for the page session and invalidated whenever the
  // user creates a new folder. The same cache backs every post-menu submenu.
  let folderCache = null;        // Promise<Folder[]> | null
  function loadFolders(force = false) {
    if (folderCache && !force) return folderCache;
    if (!APP.urls?.api?.saveCategories) return Promise.resolve([]);
    folderCache = api(APP.urls.api.saveCategories)
      .then((res) => Array.isArray(res.data) ? res.data : [])
      .catch(() => []);
    return folderCache;
  }

  function escapeHtml(s) {
    return String(s ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function renderFolderSubmenu(submenu, folders, currentFolderId) {
    const items = folders.map((f) => `
      <button type="button" class="psm-item ${String(f.id) === String(currentFolderId) ? 'is-active' : ''}"
              data-save-folder="${f.id}">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        <span class="psm-name">${escapeHtml(f.name)}</span>
        <span class="psm-count">${f.count || 0}</span>
      </button>
    `).join('');
    submenu.innerHTML = `
      <div class="psm-head">Save to folder</div>
      <button type="button" class="psm-item ${currentFolderId == null ? 'is-active' : ''}" data-save-folder="">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        <span class="psm-name">Uncategorized</span>
      </button>
      ${items}
      <div class="psm-sep"></div>
      <form class="psm-new" data-save-new>
        <input type="text" maxlength="60" placeholder="New folder name…" data-save-new-input>
        <button type="submit" aria-label="Create folder">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
      </form>
    `;
  }

  async function openSaveSubmenu(card, submenu, post) {
    submenu.classList.add('is-open');
    submenu.innerHTML = '<div class="psm-loading">Loading folders…</div>';
    const folders = await loadFolders();
    const currentFolderId = post?.save_category_id ?? null;
    renderFolderSubmenu(submenu, folders, currentFolderId);
    submenu.querySelector('[data-save-new-input]')?.focus();
  }

  function closeAllSaveSubmenus() {
    document.querySelectorAll('[data-save-submenu].is-open').forEach((s) => s.classList.remove('is-open'));
  }

  async function handleDelete(postId, card, handlers) {
    if (!APP.user) return;
    const ok = await confirmDialog({
      title:   'Delete this post?',
      message: 'This post will be permanently removed and can\'t be recovered.',
      okLabel: 'Delete post',
      danger:  true,
    });
    if (!ok) return;
    try {
      const url = APP.urls.api.postDelete.replace(':id', postId);
      await api(url, { method: 'DELETE' });
      toast('Post deleted', 'ok');
      animateRemove(card, () => handlers?.onRemove?.(postId));
    } catch (err) { toast(err.message || 'Could not delete post', 'bad'); }
  }

  function animateRemove(card, after) {
    if (!card) { after?.(); return; }
    card.style.transition = 'opacity .2s ease, transform .2s ease, max-height .25s ease';
    card.style.opacity = '0';
    card.style.transform = 'translateY(-6px)';
    card.style.maxHeight = card.offsetHeight + 'px';
    setTimeout(() => {
      card.style.maxHeight = '0px';
      card.style.marginTop = '0';
      card.style.marginBottom = '0';
      card.style.paddingTop = '0';
      card.style.paddingBottom = '0';
      setTimeout(() => { card.remove(); after?.(); }, 220);
    }, 180);
  }

  function bindPostMenu(root, handlers = {}) {
    if (!root) return;
    bindGlobalMenuClose();

    root.addEventListener('click', (e) => {
      // Open / toggle a menu
      const trigger = e.target.closest('[data-post-menu-trigger]');
      if (trigger) {
        e.preventDefault();
        e.stopPropagation();
        const wrap = trigger.closest('[data-post-menu]');
        const willOpen = !wrap.classList.contains('is-open');
        closeAllPostMenus();
        closeAllSaveSubmenus();
        wrap.classList.toggle('is-open', willOpen);
        return;
      }

      // Click inside the Save-to submenu — handle folder pick or stay open
      // for the new-folder form.
      const folderBtn = e.target.closest('[data-save-folder]');
      if (folderBtn) {
        e.preventDefault();
        e.stopPropagation();
        const card = folderBtn.closest('[data-post-id]');
        const postId = card?.dataset.postId;
        if (!postId) return;
        const raw = folderBtn.dataset.saveFolder;
        const body = raw === ''
          ? { save_category_id: null }
          : { save_category_id: parseInt(raw, 10) };
        closeAllPostMenus();
        closeAllSaveSubmenus();
        return handleSave(postId, card, handlers, body);
      }

      // Click inside new-folder form: don't let it bubble up and close menus.
      if (e.target.closest('[data-save-submenu]')) {
        e.stopPropagation();
        return;
      }

      // Menu item activation
      const item = e.target.closest('[data-post-act]');
      if (!item) return;
      const card = item.closest('[data-post-id]');
      const postId = card?.dataset.postId;
      if (!postId) return;
      e.preventDefault();
      e.stopPropagation();

      const act = item.dataset.postAct;

      // "Save to folder" chevron — open submenu instead of closing the menu.
      if (act === 'save-to') {
        const submenu = item.parentElement?.querySelector('[data-save-submenu]');
        if (!submenu) return;
        const isOpen = submenu.classList.contains('is-open');
        closeAllSaveSubmenus();
        if (!isOpen) {
          const post = handlers?.getPost?.(postId) || { save_category_id: null };
          openSaveSubmenu(card, submenu, post);
        }
        return;
      }

      closeAllPostMenus();
      closeAllSaveSubmenus();

      if (act === 'not_interested') return handleNotInterested(postId, card, handlers);
      if (act === 'hide')           return handleHide(postId, card, handlers);
      if (act === 'save')           return handleSave(postId, card, handlers);
      if (act === 'delete')         return handleDelete(postId, card, handlers);
      if (act === 'edit') {
        const post = handlers?.getPost?.(postId);
        if (!post) return toast('Cannot edit this post right now', 'bad');
        openEditModal(post, (id, updated) => handlers?.onChange?.(id, updated));
        return;
      }
    });

    // New-folder form submit (delegated, scoped to this root).
    root.addEventListener('submit', (e) => {
      const form = e.target.closest('[data-save-new]');
      if (!form) return;
      e.preventDefault();
      e.stopPropagation();
      const card = form.closest('[data-post-id]');
      const postId = card?.dataset.postId;
      const input = form.querySelector('[data-save-new-input]');
      const name = (input?.value || '').trim();
      if (!postId || !name) return;
      closeAllPostMenus();
      closeAllSaveSubmenus();
      handleSave(postId, card, handlers, { new_category: name });
    });
  }

  // ─────────────── Boot ───────────────
  function boot() {
    bindShareModal();
    bindEditForm();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  window.Tanbat = window.Tanbat || {};
  window.Tanbat.openShare      = openShare;
  window.Tanbat.bindPostMenu   = bindPostMenu;
  window.Tanbat.confirmDialog  = confirmDialog;
  window.Tanbat.loadSaveFolders = (force) => loadFolders(!!force);
  window.Tanbat.invalidateSaveFolders = () => { folderCache = null; };
})();
