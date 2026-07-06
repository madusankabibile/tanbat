// People Discovery page — Following / Followers / Visitors / Discover
// with filters (search, country, gender, age range, sort) and follow toggle.

import { applyBannerTo } from './banners.js';

const APP    = window.__APP__ || {};
const PEOPLE = window.__PEOPLE__ || { urls: {} };

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

const fmt = (n) => Number(n || 0).toLocaleString();

const toast = window.Tanbat?.toast || (() => {});
const api   = () => window.Tanbat?.api;

const SECTION_LABELS = {
  following: { title: 'Following',      empty: { title: "You're not following anyone yet", desc: 'Try the Discover tab to find new people.' } },
  followers: { title: 'Your followers', empty: { title: 'No followers yet',                 desc: 'Share your profile to grow your audience.' } },
  visitors:  { title: 'Profile visits', empty: { title: 'No one has visited your profile',  desc: 'Once visitors arrive, they will show up here.' } },
  discover:  { title: 'Discover',       empty: { title: 'No matches',                       desc: 'Try clearing some filters.' } },
};

let state = {
  section: ['following', 'followers', 'visitors', 'discover'].includes(PEOPLE.initial)
    ? PEOPLE.initial
    : 'following',
  page: 1,
  hasMore: false,
  filters: {
    q:       '',
    country: 'all',
    gender:  'all',
    age_min: '',
    age_max: '',
    sort:    'name',
  },
  pendingFollow: new Set(),
};

// ─────────── Tabs ───────────
function setActiveTab() {
  $$('.people-tab').forEach((b) => {
    b.classList.toggle('active', b.dataset.section === state.section);
  });
  const labels = SECTION_LABELS[state.section] || SECTION_LABELS.discover;
  const title = $('#sectionTitle');
  if (title) title.textContent = labels.title;
  $('#peopleEmptyTitle').textContent = labels.empty.title;
  $('#peopleEmptyDesc').textContent  = labels.empty.desc;

  // Default sort depends on section.
  if (state.filters.sort === 'name' || state.filters.sort === 'recent') {
    state.filters.sort = state.section === 'visitors' ? 'recent' : 'name';
    const sel = $('#filterSort');
    if (sel) sel.value = state.filters.sort;
  }
}

function bindTabs() {
  $$('.people-tab').forEach((b) => {
    b.addEventListener('click', () => {
      if (b.dataset.section === state.section) return;
      state.section = b.dataset.section;
      state.page = 1;
      setActiveTab();
      syncUrl();
      loadList({ append: false });
    });
  });
}

// ─────────── Filters ───────────
function bindFilters() {
  const searchInput = $('#peopleSearch');
  const clearBtn = $('#peopleSearchClear');
  let timer = null;
  searchInput?.addEventListener('input', () => {
    clearBtn.classList.toggle('hidden', !searchInput.value);
    clearTimeout(timer);
    timer = setTimeout(() => {
      state.filters.q = searchInput.value.trim();
      state.page = 1;
      loadList({ append: false });
    }, 220);
  });
  clearBtn?.addEventListener('click', () => {
    searchInput.value = '';
    clearBtn.classList.add('hidden');
    state.filters.q = '';
    state.page = 1;
    loadList({ append: false });
    searchInput.focus();
  });

  $('#filterCountry')?.addEventListener('change', (e) => {
    state.filters.country = e.target.value;
    state.page = 1; loadList({ append: false });
  });
  $('#filterGender')?.addEventListener('change', (e) => {
    state.filters.gender = e.target.value;
    state.page = 1; loadList({ append: false });
  });
  $('#filterSort')?.addEventListener('change', (e) => {
    state.filters.sort = e.target.value;
    state.page = 1; loadList({ append: false });
  });

  let ageTimer = null;
  const onAge = () => {
    state.filters.age_min = $('#ageMin').value.trim();
    state.filters.age_max = $('#ageMax').value.trim();
    clearTimeout(ageTimer);
    ageTimer = setTimeout(() => {
      state.page = 1; loadList({ append: false });
    }, 280);
  };
  $('#ageMin')?.addEventListener('input', onAge);
  $('#ageMax')?.addEventListener('input', onAge);

  $('#filterReset')?.addEventListener('click', () => {
    state.filters = { q: '', country: 'all', gender: 'all', age_min: '', age_max: '', sort: state.section === 'visitors' ? 'recent' : 'name' };
    $('#peopleSearch').value = '';
    $('#peopleSearchClear').classList.add('hidden');
    $('#filterCountry').value = 'all';
    $('#filterGender').value = 'all';
    $('#filterSort').value = state.filters.sort;
    $('#ageMin').value = '';
    $('#ageMax').value = '';
    state.page = 1;
    loadList({ append: false });
  });
}

// ─────────── Cards ───────────
function userCardHTML(u) {
  const avatar = u.profile_picture
    ? `<img src="${esc(u.profile_picture)}" alt="">`
    : esc((u.username || u.name || 'U').charAt(0).toUpperCase());

  const chips = [];
  if (u.country) chips.push(`<span class="uc-chip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 1 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
    ${esc(u.country)}
  </span>`);
  if (u.age) chips.push(`<span class="uc-chip">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    ${esc(u.age)} yrs
  </span>`);
  if (u.gender) {
    const g = String(u.gender).charAt(0).toUpperCase() + String(u.gender).slice(1);
    chips.push(`<span class="uc-chip">${esc(g)}</span>`);
  }
  if (u.joined_at) chips.push(`<span class="uc-chip">Joined ${esc(u.joined_at)}</span>`);

  let visitNote = '';
  if (u.last_visit) {
    const times = u.visit_count > 1 ? ` · ${fmt(u.visit_count)} visits` : '';
    visitNote = `<div class="uc-visit">Last visit: ${esc(u.last_visit)}${times}</div>`;
  }

  let followBtn;
  if (u.is_self) {
    followBtn = `<button type="button" class="uc-btn uc-btn-follow is-self" disabled>You</button>`;
  } else {
    const isFollowing = !!u.is_following;
    followBtn = `
      <button type="button"
              class="uc-btn uc-btn-follow ${isFollowing ? 'is-following' : ''}"
              data-action="toggle-follow"
              data-user-id="${u.id}"
              aria-pressed="${isFollowing ? 'true' : 'false'}">
        ${isFollowing
          ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
             <span class="uc-follow-label"><span class="uc-follow-label-text">Following</span></span>`
          : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
             <span>Follow</span>`}
      </button>`;
  }

  return `
    <article class="user-card" data-user-id="${u.id}" data-username="${esc(u.username || '')}">
      <div class="uc-cover"></div>
      <div class="uc-body">
        <div class="uc-avatar">${avatar}</div>
        <div class="uc-name">${esc(u.name || u.username || 'User')}</div>
        <div class="uc-handle">&#64;${esc(u.username || '')}</div>
        ${chips.length ? `<div class="uc-meta">${chips.join('')}</div>` : ''}
        ${visitNote}
        <div class="uc-actions">
          <a class="uc-btn uc-btn-view" href="${esc(u.url)}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            View
          </a>
          ${followBtn}
        </div>
      </div>
    </article>`;
}

function skeletonGridHTML(count = 6) {
  let html = '';
  for (let i = 0; i < count; i++) html += `<div class="uc-skeleton"></div>`;
  return html;
}

// ─────────── Follow toggle ───────────
async function toggleFollow(userId, btn) {
  if (!userId || state.pendingFollow.has(userId)) return;
  if (!APP.user) {
    toast('Please sign in to follow people.', 'bad');
    return;
  }
  const fetchFn = api();
  if (!fetchFn) return;

  state.pendingFollow.add(userId);
  btn.disabled = true;

  try {
    const url = (PEOPLE.urls.follow || '/api/users/:id/follow').replace(':id', userId);
    const res = await fetchFn(url, { method: 'POST' });

    // Reflect new state on every card with this user ID (multiple tabs / re-renders).
    $$(`.user-card[data-user-id="${userId}"] [data-action="toggle-follow"]`).forEach((b) => {
      if (res.following) {
        b.classList.add('is-following');
        b.setAttribute('aria-pressed', 'true');
        b.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                       <span class="uc-follow-label"><span class="uc-follow-label-text">Following</span></span>`;
      } else {
        b.classList.remove('is-following');
        b.setAttribute('aria-pressed', 'false');
        b.innerHTML = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                       <span>Follow</span>`;
      }
    });

    // Update counts in the section tabs.
    await loadSummary();

    // If we're on the Following tab and the user unfollowed, remove that card.
    if (state.section === 'following' && !res.following) {
      $$(`.user-card[data-user-id="${userId}"]`).forEach((c) => c.remove());
      checkEmpty();
    }
  } catch (e) {
    toast(e.message || 'Could not update follow.', 'bad');
  } finally {
    state.pendingFollow.delete(userId);
    btn.disabled = false;
  }
}

function bindGridActions() {
  $('#peopleGrid')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="toggle-follow"]');
    if (!btn) return;
    const userId = Number(btn.dataset.userId);
    toggleFollow(userId, btn);
  });
}

// ─────────── Loading ───────────
function checkEmpty() {
  const grid = $('#peopleGrid');
  const empty = $('#peopleEmpty');
  if (!grid || !empty) return;
  if (!grid.children.length) {
    empty.classList.remove('hidden');
  } else {
    empty.classList.add('hidden');
  }
}

async function loadList({ append = false } = {}) {
  const grid = $('#peopleGrid');
  const loading = $('#peopleLoading');
  const loadMore = $('#peopleLoadMore');
  const fetchFn = api();
  if (!grid || !fetchFn) return;

  if (!append) {
    grid.innerHTML = skeletonGridHTML(6);
    $('#peopleEmpty').classList.add('hidden');
  } else {
    loading.classList.remove('hidden');
  }

  try {
    const url = new URL(`${PEOPLE.urls.list}/${state.section}`, window.location.origin);
    url.searchParams.set('page', String(state.page));
    Object.entries(state.filters).forEach(([k, v]) => {
      if (v !== '' && v !== 'all' && v != null) url.searchParams.set(k, v);
    });

    const data = await fetchFn(url.toString());
    state.hasMore = !!data.has_more;
    loadMore.classList.toggle('hidden', !state.hasMore);

    const items = data.items || [];
    const html = items.map(userCardHTML).join('');

    if (append) {
      grid.insertAdjacentHTML('beforeend', html);
    } else {
      grid.innerHTML = html;
    }

    // Paint banner (uploaded image or seeded texture art) onto each newly
    // rendered card. We index by position so duplicates / re-appends don't
    // re-paint earlier cards.
    const cards = grid.querySelectorAll('.user-card');
    const startIdx = cards.length - items.length;
    items.forEach((u, i) => {
      const card = cards[startIdx + i];
      if (card) applyBannerTo(card.querySelector('.uc-cover'), u);
    });

    $('#sectionTotal').textContent =
      `${fmt(data.total || 0)} ${data.total === 1 ? 'person' : 'people'}`;

    checkEmpty();
  } catch (e) {
    grid.innerHTML = '';
    toast(e.message || 'Could not load people.', 'bad');
    checkEmpty();
  } finally {
    loading.classList.add('hidden');
  }
}

async function loadSummary() {
  const fetchFn = api();
  if (!fetchFn) return;
  try {
    const data = await fetchFn(PEOPLE.urls.summary);
    const counts = data.counts || {};
    $$('.people-count').forEach((el) => {
      el.textContent = fmt(counts[el.dataset.count] || 0);
    });
  } catch (e) {
    console.warn('Summary fetch failed', e);
  }
}

async function loadFilters() {
  const fetchFn = api();
  if (!fetchFn) return;
  try {
    const data = await fetchFn(PEOPLE.urls.filters);
    const sel = $('#filterCountry');
    if (sel && Array.isArray(data.countries)) {
      const existing = new Set(['all']);
      data.countries.forEach((c) => {
        if (!existing.has(c)) {
          const opt = document.createElement('option');
          opt.value = c;
          opt.textContent = c;
          sel.appendChild(opt);
          existing.add(c);
        }
      });
    }
  } catch (e) {
    console.warn('Filters fetch failed', e);
  }
}

function bindLoadMore() {
  $('#peopleLoadMore')?.addEventListener('click', () => {
    state.page += 1;
    loadList({ append: true });
  });
}

function syncUrl() {
  const u = new URL(window.location.href);
  u.searchParams.set('section', state.section);
  window.history.replaceState(null, '', u.toString());
}

document.addEventListener('DOMContentLoaded', () => {
  // Honor ?section=… in the URL.
  const initial = new URL(window.location.href).searchParams.get('section');
  if (initial && ['following', 'followers', 'visitors', 'discover'].includes(initial)) {
    state.section = initial;
  }
  setActiveTab();
  bindTabs();
  bindFilters();
  bindLoadMore();
  bindGridActions();
  loadFilters();
  loadSummary();
  loadList({ append: false });
});
