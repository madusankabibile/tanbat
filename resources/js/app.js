// Tanbat — global helpers (loaded on every page)
(function () {
const APP = window.__APP__ || {};

// ─────────── Toast ───────────
function toast(msg, kind = 'def') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  const bg = kind === 'ok' ? '#10B981' : kind === 'bad' ? '#EF4444' : '#1E1B4B';
  t.style.background = bg;
  t.style.opacity = '1';
  t.style.transform = 'translate(-50%, 0)';
  clearTimeout(t._timer);
  t._timer = setTimeout(() => {
    t.style.opacity = '0';
    t.style.transform = 'translate(-50%, 1rem)';
  }, 3000);
}
window.Tanbat = window.Tanbat || {};
window.Tanbat.toast = toast;

// ─────────── Dropdown menus (Create / Profile) ───────────
function bindMenus() {
  document.querySelectorAll('[data-menu]').forEach((root) => {
    const trigger = root.querySelector('[data-menu-trigger]');
    const panel   = root.querySelector('[data-menu-panel]');
    if (!trigger || !panel) return;
    trigger.addEventListener('click', (e) => {
      e.stopPropagation();
      document.querySelectorAll('[data-menu-panel]').forEach((p) => {
        if (p !== panel) p.classList.add('hidden');
      });
      panel.classList.toggle('hidden');
    });
  });
  document.addEventListener('click', (e) => {
    document.querySelectorAll('[data-menu]').forEach((root) => {
      if (!root.contains(e.target)) {
        root.querySelector('[data-menu-panel]')?.classList.add('hidden');
      }
    });
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      document.querySelectorAll('[data-menu-panel]').forEach((p) => p.classList.add('hidden'));
      document.querySelectorAll('[data-modal-open]').forEach((m) => closeModal(m));
    }
  });
}

// ─────────── Modal helpers ───────────
function openModal(id) {
  const m = document.getElementById(id);
  if (!m) return;
  m.classList.remove('hidden');
  m.classList.add('flex');
  m.setAttribute('data-modal-open', '');
  document.body.style.overflow = 'hidden';
}
function closeModal(elOrId) {
  const m = typeof elOrId === 'string' ? document.getElementById(elOrId) : elOrId;
  if (!m) return;
  m.classList.add('hidden');
  m.classList.remove('flex');
  m.removeAttribute('data-modal-open');
  if (!document.querySelector('[data-modal-open]')) {
    document.body.style.overflow = '';
  }
}
window.Tanbat.openModal  = openModal;
window.Tanbat.closeModal = closeModal;

function bindModalCloses() {
  document.addEventListener('click', (e) => {
    const closeBtn = e.target.closest('[data-close-modal]');
    if (closeBtn) {
      const modal = closeBtn.closest('[id^="modal"]');
      if (modal) closeModal(modal);
      return;
    }
    document.querySelectorAll('[data-modal-open]').forEach((m) => {
      if (m === e.target) closeModal(m);
    });
  });
}

// ─────────── Logout ───────────
async function logout() {
  try {
    await fetch(APP.urls.logout, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': APP.csrf,
      },
      credentials: 'same-origin',
    });
  } catch (e) {}
  location.href = APP.urls.landing;
}

function bindNavActions() {
  document.addEventListener('click', (e) => {
    const a = e.target.closest('[data-action]');
    if (!a) return;
    const action = a.dataset.action;
    if (action === 'logout') { logout(); return; }
    if (action === 'notifications') toast('Notifications coming soon!');
    // messages: handled by messenger.js (opens threads dropdown)
    // users: handled by the People link in the navbar (anchor, not a button)
    if (action === 'settings')      toast('Profile settings coming soon!');
    if (action === 'share-my-profile') {
      const url = a.dataset.shareUrl;
      if (!url) return;
      // close the profile dropdown that the button lives inside
      document.querySelectorAll('[data-menu-panel]').forEach((p) => p.classList.add('hidden'));
      window.Tanbat?.openShare?.({
        kind:  'profile',
        url,
        title: a.dataset.shareTitle || 'My profile on Tanbat',
        image: a.dataset.shareImage || '',
      });
    }
  });
}

// ─────────── API helpers ───────────
async function api(url, opts = {}) {
  const headers = {
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    'X-CSRF-TOKEN': APP.csrf,
    ...(opts.headers || {}),
  };
  if (opts.body && !(opts.body instanceof FormData) && typeof opts.body !== 'string') {
    headers['Content-Type'] = 'application/json';
    opts.body = JSON.stringify(opts.body);
  }
  const res = await fetch(url, { credentials: 'same-origin', ...opts, headers });
  let data = null;
  try { data = await res.json(); } catch (_) {}
  if (!res.ok) {
    const msg = data?.message || `Request failed (${res.status})`;
    throw Object.assign(new Error(msg), { status: res.status, data });
  }
  return data;
}
window.Tanbat.api = api;

// ─────────── Notifications ───────────
const escNotif = (s) => String(s ?? '')
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
  .replace(/"/g,'&quot;').replace(/'/g,'&#039;');

async function fetchNotifications() {
  if (!APP.user) return;
  const url = APP.urls?.api?.notifications;
  if (!url) return;
  try {
    const { unread, items } = await api(url);
    updateNotifBadge(unread);
    renderNotifList(items || []);
  } catch (e) { /* silent */ }
}

function updateNotifBadge(n) {
  const b = document.getElementById('notifBadge');
  if (!b) return;
  if (!n) {
    b.classList.add('hidden');
  } else {
    b.classList.remove('hidden');
    b.textContent = n > 99 ? '99+' : String(n);
  }
}

function renderNotifList(items) {
  const wrap = document.getElementById('notifList');
  if (!wrap) return;
  if (!items.length) {
    wrap.innerHTML = `<div class="py-8 text-center text-xs text-slate-400">No notifications yet.</div>`;
    return;
  }
  wrap.innerHTML = items.map((n) => {
    const actor = n.actor || {};
    const av = actor.profile_picture
      ? `<img src="${escNotif(actor.profile_picture)}" alt="" class="h-10 w-10 rounded-full object-cover">`
      : `<span class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white">${escNotif((actor.username||'U').charAt(0).toUpperCase())}</span>`;
    const dot = !n.read ? `<span class="ml-auto h-2.5 w-2.5 rounded-full bg-brand-500 shrink-0"></span>` : '';
    const href = n.link || '#';
    return `
      <a href="${escNotif(href)}" class="flex items-start gap-3 px-3 py-2.5 hover:bg-slate-50 ${n.read ? '' : 'bg-brand-50/40'}">
        ${av}
        <div class="min-w-0 flex-1">
          <div class="text-[13px] leading-snug text-slate-800">${escNotif(n.message)}</div>
          <div class="mt-0.5 text-[11px] text-slate-400">${escNotif(n.created_at || '')}</div>
        </div>
        ${dot}
      </a>
    `;
  }).join('');
}

async function markNotificationsRead() {
  if (!APP.user) return;
  const url = APP.urls?.api?.notifications;
  if (!url) return;
  try {
    await api(`${url}/read`, { method: 'POST' });
    updateNotifBadge(0);
    // re-render to clear the "unread" highlight
    document.querySelectorAll('#notifList a').forEach((el) => {
      el.classList.remove('bg-brand-50/40');
      el.querySelector('.bg-brand-500')?.remove();
    });
  } catch (_) {}
}

function bindNotifications() {
  if (!APP.user) return;
  // Mark-read button inside dropdown
  document.addEventListener('click', (e) => {
    if (e.target.closest('#notifMarkRead')) {
      e.preventDefault();
      markNotificationsRead();
    }
  });
  // Mark read automatically when the dropdown opens
  const root = document.querySelector('[data-menu="notifications"]');
  if (root) {
    const trigger = root.querySelector('[data-menu-trigger]');
    trigger?.addEventListener('click', () => {
      // bindMenus toggles .hidden — wait one tick then check
      setTimeout(() => {
        const panel = root.querySelector('[data-menu-panel]');
        if (panel && !panel.classList.contains('hidden')) {
          markNotificationsRead();
        }
      }, 0);
    });
  }
  // Initial load + poll every 45s
  fetchNotifications();
  setInterval(fetchNotifications, 45_000);
}

// ─────────── Nav search (live suggestions + Enter → /search) ───────────
const escSrch = (s) => String(s ?? '')
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
  .replace(/"/g,'&quot;').replace(/'/g,'&#039;');

function bindNavSearch() {
  const form    = document.getElementById('navSearch');
  const input   = document.getElementById('navSearchInput');
  const panel   = document.getElementById('navSearchPanel');
  const clearBt = document.getElementById('navSearchClear');
  if (!form || !input || !panel) return;

  const suggestUrl = (APP.urls?.base || '') + '/api/search/suggest';
  const resultsUrl = (APP.urls?.base || '') + '/search';

  let lastReq = 0;
  let debounceTimer = null;
  let activeIdx = -1;
  let lastItems = [];

  function showPanel() { panel.classList.remove('hidden'); }
  function hidePanel() { panel.classList.add('hidden'); activeIdx = -1; }
  function toggleClear() {
    if (!clearBt) return;
    clearBt.classList.toggle('hidden', !input.value);
  }

  function loadingMarkup() {
    return `<div class="py-6 text-center text-xs text-slate-400">Searching…</div>`;
  }
  function emptyMarkup(q) {
    return `
      <div class="py-6 text-center">
        <div class="text-xs text-slate-400">No quick matches for <b>${escSrch(q)}</b></div>
        <button type="button" data-search-submit class="mt-2 text-xs font-semibold text-brand-600 hover:underline">
          Search everywhere →
        </button>
      </div>`;
  }

  function renderPanel(data) {
    const q = data.q || input.value;
    const people = data.people || [];
    const posts  = data.posts  || [];
    const tags   = data.tags   || [];

    lastItems = []; // build a flat keyboard-nav list
    let html = '';

    if (people.length) {
      html += `<div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">People</div>`;
      people.forEach((p) => {
        lastItems.push(p.url);
        const av = p.profile_picture
          ? `<img class="h-9 w-9 rounded-full object-cover" src="${escSrch(p.profile_picture)}" alt="">`
          : `<span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-xs font-bold text-white">${escSrch((p.username||p.name||'U').charAt(0).toUpperCase())}</span>`;
        html += `
          <a href="${escSrch(p.url)}" class="sg-row flex items-center gap-3 px-4 py-2 hover:bg-slate-50">
            ${av}
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-semibold text-slate-900">${escSrch(p.name || p.username)}</div>
              <div class="truncate text-xs text-slate-500">&#64;${escSrch(p.username)}</div>
            </div>
            <span class="ml-auto text-[10px] font-semibold uppercase tracking-wide text-slate-400">Profile</span>
          </a>`;
      });
    }

    if (posts.length) {
      html += `<div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Posts</div>`;
      posts.forEach((p) => {
        lastItems.push(p.url);
        const thumb = p.thumb
          ? `<img class="h-9 w-9 rounded-lg object-cover" src="${escSrch(p.thumb)}" alt="">`
          : `<span class="grid h-9 w-9 place-items-center rounded-lg bg-slate-100 text-slate-400">
               <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h10"/></svg>
             </span>`;
        const author = p.user?.username ? `<span class="ml-1 text-slate-400">· @${escSrch(p.user.username)}</span>` : '';
        html += `
          <a href="${escSrch(p.url)}" class="sg-row flex items-center gap-3 px-4 py-2 hover:bg-slate-50">
            ${thumb}
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm text-slate-900">${escSrch(p.snippet || '(Untitled post)')}</div>
              <div class="truncate text-xs text-slate-500"><span class="capitalize">${escSrch(p.type)}</span>${author}</div>
            </div>
          </a>`;
      });
    }

    if (tags.length) {
      html += `<div class="px-4 pt-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Tags</div>`;
      tags.forEach((t) => {
        lastItems.push(t.url);
        html += `
          <a href="${escSrch(t.url)}" class="sg-row flex items-center gap-3 px-4 py-2 hover:bg-slate-50">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-50 text-brand-600 text-sm font-bold">#</span>
            <div class="min-w-0 flex-1">
              <div class="truncate text-sm font-semibold text-slate-900">#${escSrch(t.name)}</div>
              <div class="truncate text-xs text-slate-500">${t.count} post${t.count === 1 ? '' : 's'}</div>
            </div>
          </a>`;
      });
    }

    if (!html) {
      html = emptyMarkup(q);
    } else {
      const allUrl = `${resultsUrl}?q=${encodeURIComponent(q)}`;
      lastItems.push(allUrl);
      html += `
        <div class="border-t border-slate-100">
          <a href="${escSrch(allUrl)}" class="sg-row flex items-center justify-center gap-2 px-4 py-3 text-xs font-semibold text-brand-600 hover:bg-brand-50/60">
            See all results for "${escSrch(q)}"
            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
          </a>
        </div>`;
    }

    panel.innerHTML = html;
    showPanel();
  }

  function highlight(idx) {
    const rows = panel.querySelectorAll('.sg-row');
    rows.forEach((r, i) => r.classList.toggle('bg-slate-100', i === idx));
    if (idx >= 0 && rows[idx]) rows[idx].scrollIntoView({ block: 'nearest' });
  }

  async function fetchSuggest(q) {
    const myId = ++lastReq;
    try {
      const r = await fetch(`${suggestUrl}?q=${encodeURIComponent(q)}`, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (myId !== lastReq) return; // a newer request superseded us
      const data = await r.json();
      if (input.value.trim() === '') { hidePanel(); return; }
      renderPanel(data);
    } catch (_) { /* silent */ }
  }

  input.addEventListener('input', () => {
    toggleClear();
    const q = input.value.trim();
    if (!q) { hidePanel(); return; }
    panel.innerHTML = loadingMarkup();
    showPanel();
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => fetchSuggest(q), 180);
  });

  input.addEventListener('focus', () => {
    toggleClear();
    if (input.value.trim()) {
      // re-show last results when refocusing
      if (panel.innerHTML.trim()) showPanel();
      else fetchSuggest(input.value.trim());
    }
  });

  input.addEventListener('keydown', (e) => {
    const rows = panel.querySelectorAll('.sg-row');
    if (e.key === 'ArrowDown') {
      if (!rows.length) return;
      e.preventDefault();
      activeIdx = (activeIdx + 1) % rows.length;
      highlight(activeIdx);
    } else if (e.key === 'ArrowUp') {
      if (!rows.length) return;
      e.preventDefault();
      activeIdx = (activeIdx - 1 + rows.length) % rows.length;
      highlight(activeIdx);
    } else if (e.key === 'Escape') {
      hidePanel();
    } else if (e.key === 'Enter') {
      if (activeIdx >= 0 && lastItems[activeIdx]) {
        e.preventDefault();
        location.href = lastItems[activeIdx];
      }
      // otherwise let the form submit naturally to /search?q=...
    }
  });

  clearBt?.addEventListener('click', () => {
    input.value = '';
    toggleClear();
    hidePanel();
    input.focus();
  });

  // Panel-level click for the inline "Search everywhere" link inside empty-state
  panel.addEventListener('click', (e) => {
    const submit = e.target.closest('[data-search-submit]');
    if (submit) { e.preventDefault(); form.submit(); }
  });

  // Click outside closes panel
  document.addEventListener('click', (e) => {
    if (!form.contains(e.target)) hidePanel();
  });

  // Submission goes to /search?q=
  form.addEventListener('submit', (e) => {
    const q = input.value.trim();
    if (!q) { e.preventDefault(); return; }
    hidePanel();
  });

  toggleClear();
}

// ─────────── Boot ───────────
document.addEventListener('DOMContentLoaded', () => {
  bindMenus();
  bindModalCloses();
  bindNavActions();
  bindNotifications();
  bindNavSearch();
});

})();
