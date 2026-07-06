// Loads /api/sidebar (site stats + active users) into the shared left/right
// side rails. Auto-runs on any page that includes the partial (home, search,
// people discovery, etc.).

const APP = window.__APP__ || {};

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
const fmt = (n) => Number(n || 0).toLocaleString();

const api   = () => window.Tanbat?.api;

function renderSiteStats(stats) {
  if (!stats) return;
  $$('#siteStats [data-site]').forEach((el) => {
    el.textContent = fmt(stats[el.dataset.site]);
  });
}

function renderActiveUsers(users) {
  const ul = $('#activeUsers');
  if (!ul) return;
  if (!users || !users.length) {
    ul.innerHTML = `<li class="px-3 py-4 text-center text-xs text-slate-400">No active users yet.</li>`;
    return;
  }
  const profileBase = APP.urls?.profileBase || '/u';
  ul.innerHTML = users.map((u) => {
    const av = u.profile_picture
      ? `<img src="${esc(u.profile_picture)}" alt="">`
      : esc((u.username || u.name || 'U').charAt(0).toUpperCase());
    const href = u.username ? `${profileBase}/${encodeURIComponent(u.username)}` : '#';
    return `
      <li data-user-id="${u.id}" data-user-username="${esc(u.username || '')}" role="link" tabindex="0">
        <a href="${href}" class="contents" data-user-link>
          <span class="av ${u.online ? 'is-online' : ''}">${av}<span class="dot"></span></span>
          <div class="who">
            <span class="name">${esc(u.name || u.username || 'User')}</span>
            <span class="meta">${fmt(u.posts)} post${u.posts === 1 ? '' : 's'}${u.online ? ' · online' : ''}</span>
          </div>
        </a>
      </li>
    `;
  }).join('');
}

async function loadSidebar() {
  // Only fetch if the rails are actually on the page.
  if (!document.querySelector('#siteStats') && !document.querySelector('#activeUsers')) return;
  const fetchFn = api();
  if (!fetchFn) return;
  try {
    const url = APP.urls?.api?.sidebar;
    if (!url) return;
    const { stats, active_users } = await fetchFn(url);
    renderSiteStats(stats);
    renderActiveUsers(active_users || []);
  } catch (e) {
    console.warn('Sidebar fetch failed', e);
    // Don't leave the skeleton loaders spinning forever — drop them to a
    // graceful empty state if the request failed.
    const ul = $('#activeUsers');
    if (ul) ul.innerHTML = `<li class="px-3 py-4 text-center text-xs text-slate-400">Couldn't load active users.</li>`;
  }
}

// Expose for callers that need to re-fetch.
window.Tanbat = window.Tanbat || {};
window.Tanbat.loadSidebar = loadSidebar;

document.addEventListener('DOMContentLoaded', loadSidebar);
