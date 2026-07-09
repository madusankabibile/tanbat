// "Account deleted" recovery page — loads suggested people (geo + activity
// ranked) into a filterable grid, with follow + load-more. Public/guest-safe.
import { esc } from './cards.js';

const APP  = window.__APP__ || {};
const LOST = window.__LOST__ || { urls: {} };

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const toast = window.Tanbat?.toast || (() => {});
const api   = window.Tanbat?.api;

const fmt = (n) => Number(n || 0).toLocaleString();

const state = {
  page: 1,
  lastPage: 1,
  loading: false,
  pendingFollow: new Set(),
  filters: { q: '', country: 'all', gender: 'all', sort: 'suggested', ageMin: '', ageMax: '' },
  geoShown: false,
};

// Turn an ISO country code into a display name when the browser supports it.
let regionNames = null;
try { regionNames = new Intl.DisplayNames(['en'], { type: 'region' }); } catch (_) {}
function countryName(code) {
  if (!code) return '';
  try { return regionNames?.of(String(code).toUpperCase()) || code; } catch (_) { return code; }
}

// ─────────── Card ───────────
function userCardHTML(u) {
  const avatar = u.profile_picture
    ? `<img src="${esc(u.profile_picture)}" alt="">`
    : esc((u.username || u.name || 'U').charAt(0).toUpperCase());
  const onlineDot = u.online ? '<span class="uc-dot" title="Active now"></span>' : '';

  const chips = [];
  if (u.online) chips.push(`<span class="uc-chip is-online">● Active now</span>`);
  if (u.country) chips.push(`<span class="uc-chip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
    ${esc(u.country)}</span>`);
  if (u.age) chips.push(`<span class="uc-chip">${esc(u.age)} yrs</span>`);
  if (u.gender) {
    const g = String(u.gender).charAt(0).toUpperCase() + String(u.gender).slice(1);
    chips.push(`<span class="uc-chip">${esc(g)}</span>`);
  }
  if (u.joined_at) chips.push(`<span class="uc-chip">Joined ${esc(u.joined_at)}</span>`);

  let followBtn;
  if (u.is_self) {
    followBtn = `<button type="button" class="uc-btn uc-btn-follow" disabled>You</button>`;
  } else {
    const f = !!u.is_following;
    followBtn = `<button type="button" class="uc-btn uc-btn-follow ${f ? 'is-following' : ''}"
        data-action="toggle-follow" data-user-id="${u.id}" aria-pressed="${f ? 'true' : 'false'}">
      ${f
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
           <span class="uc-follow-label-text"><span>Following</span></span>`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
           <span>Follow</span>`}
    </button>`;
  }

  return `<article class="user-card" data-user-id="${u.id}" data-username="${esc(u.username || '')}">
    <div class="uc-cover"></div>
    <div class="uc-body">
      <div class="uc-avatar">${avatar}${onlineDot}</div>
      <div class="uc-name">${esc(u.name || u.username || 'User')}</div>
      <div class="uc-handle">&#64;${esc(u.username || '')}</div>
      ${chips.length ? `<div class="uc-meta">${chips.join('')}</div>` : ''}
      <div class="uc-actions">
        <a class="uc-btn uc-btn-view" href="${esc(u.url || `${LOST.urls.profileBase}/${u.username}`)}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          View
        </a>
        ${followBtn}
      </div>
    </div>
  </article>`;
}

function skeletons(n = 6) {
  return Array.from({ length: n }, () => '<div class="uc-skeleton"></div>').join('');
}

// ─────────── Load list ───────────
async function loadList({ append = false } = {}) {
  if (state.loading || !api) return;
  state.loading = true;

  const grid   = $('#peopleGrid');
  const loading = $('#peopleLoading');
  const empty   = $('#peopleEmpty');
  const more    = $('#peopleLoadMore');

  empty.classList.add('hidden');
  if (append) { loading.classList.remove('hidden'); }
  else { grid.innerHTML = skeletons(6); more.classList.add('hidden'); }

  const p = new URLSearchParams();
  p.set('page', state.page);
  p.set('per_page', '18');
  const f = state.filters;
  if (f.q) p.set('q', f.q);
  if (f.country && f.country !== 'all') p.set('country', f.country);
  if (f.gender && f.gender !== 'all') p.set('gender', f.gender);
  if (f.sort) p.set('sort', f.sort);
  if (f.ageMin !== '') p.set('age_min', f.ageMin);
  if (f.ageMax !== '') p.set('age_max', f.ageMax);

  try {
    const res = await api(`${LOST.urls.suggested}?${p.toString()}`);
    state.lastPage = res.last_page || 1;

    const html = (res.items || []).map(userCardHTML).join('');
    if (append) grid.insertAdjacentHTML('beforeend', html);
    else grid.innerHTML = html;

    if (!grid.children.length) empty.classList.remove('hidden');
    more.classList.toggle('hidden', !res.has_more);

    maybeShowGeoNote(res.geo_country);
  } catch (e) {
    if (!append) $('#peopleGrid').innerHTML = '';
    toast(e.message || 'Could not load people.', 'bad');
  } finally {
    state.loading = false;
    loading.classList.add('hidden');
  }
}

function maybeShowGeoNote(code) {
  const note = $('#geoNote');
  if (!note) return;
  // Only meaningful on the default suggested view with no explicit country.
  if (code && state.filters.sort === 'suggested' && state.filters.country === 'all') {
    note.innerHTML = `<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg> People near you · ${esc(countryName(code))}`;
    note.classList.remove('hidden');
  } else {
    note.classList.add('hidden');
  }
}

// ─────────── Follow ───────────
async function toggleFollow(userId, btn) {
  if (!userId || state.pendingFollow.has(userId)) return;
  if (!APP.user) return toast('Please sign in to follow people.', 'bad');
  if (!api) return;

  state.pendingFollow.add(userId);
  btn.disabled = true;
  try {
    const url = (LOST.urls.follow || '/api/users/:id/follow').replace(':id', userId);
    const res = await api(url, { method: 'POST' });
    $$(`.user-card[data-user-id="${userId}"] [data-action="toggle-follow"]`).forEach((b) => {
      b.classList.toggle('is-following', res.following);
      b.setAttribute('aria-pressed', res.following ? 'true' : 'false');
      b.innerHTML = res.following
        ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
           <span class="uc-follow-label-text"><span>Following</span></span>`
        : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
           <span>Follow</span>`;
    });
  } catch (e) {
    toast(e.message || 'Could not update follow.', 'bad');
  } finally {
    state.pendingFollow.delete(userId);
    btn.disabled = false;
  }
}

// ─────────── Filters ───────────
let searchTimer = null;
function resetAndLoad() { state.page = 1; loadList({ append: false }); }

function bindFilters() {
  const search = $('#peopleSearch');
  const clear  = $('#peopleSearchClear');
  search?.addEventListener('input', () => {
    state.filters.q = search.value.trim();
    clear.classList.toggle('hidden', !search.value);
    clearTimeout(searchTimer);
    searchTimer = setTimeout(resetAndLoad, 300);
  });
  clear?.addEventListener('click', () => {
    search.value = ''; state.filters.q = ''; clear.classList.add('hidden'); resetAndLoad();
  });

  $('#filterCountry')?.addEventListener('change', (e) => { state.filters.country = e.target.value; resetAndLoad(); });
  $('#filterGender')?.addEventListener('change', (e) => { state.filters.gender = e.target.value; resetAndLoad(); });
  $('#filterSort')?.addEventListener('change', (e) => { state.filters.sort = e.target.value; resetAndLoad(); });

  let ageTimer = null;
  const onAge = () => {
    state.filters.ageMin = $('#ageMin').value.trim();
    state.filters.ageMax = $('#ageMax').value.trim();
    clearTimeout(ageTimer);
    ageTimer = setTimeout(resetAndLoad, 400);
  };
  $('#ageMin')?.addEventListener('input', onAge);
  $('#ageMax')?.addEventListener('input', onAge);

  $('#filterReset')?.addEventListener('click', () => {
    state.filters = { q: '', country: 'all', gender: 'all', sort: 'suggested', ageMin: '', ageMax: '' };
    if (search) search.value = '';
    clear?.classList.add('hidden');
    $('#filterCountry').value = 'all';
    $('#filterGender').value = 'all';
    $('#filterSort').value = 'suggested';
    $('#ageMin').value = '';
    $('#ageMax').value = '';
    resetAndLoad();
  });

  $('#peopleLoadMore')?.addEventListener('click', () => {
    if (state.page < state.lastPage) { state.page += 1; loadList({ append: true }); }
  });
}

function bindGridActions() {
  $('#peopleGrid')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="toggle-follow"]');
    if (btn) { e.preventDefault(); toggleFollow(btn.dataset.userId, btn); }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  bindFilters();
  bindGridActions();
  loadList({ append: false });
});
