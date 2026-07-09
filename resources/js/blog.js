// /blog — public blog index. Renders geo/hot/new-ranked article cards, with a
// top search bar, sort tabs (For you / Hot / New) and topic filter. Talks to
// the public GET /api/blog/feed endpoint; works for guests and members alike.

const BLOG  = window.__BLOG__ || { urls: {} };
const api   = window.Tanbat.api;

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

const state = {
  sort: 'for-you',
  q: '',
  // Pre-filter to a category when the page was opened as /blog-category/{id}
  // (BLOG.initialCategory is that category's slug, else null).
  category: BLOG.initialCategory || 'all',
  page: 1,
  lastPage: 1,
  total: 0,
  loading: false,
};

const SORT_LABELS = {
  'for-you': 'Picked for you',
  hot: 'Hottest articles',
  new: 'Freshly published',
};

function initials(name) {
  return String(name || '?').trim().split(/\s+/).slice(0, 2).map((w) => w[0] || '').join('').toUpperCase() || '?';
}
function fmtNum(n) {
  n = Number(n) || 0;
  if (n >= 1e6) return (n / 1e6).toFixed(n >= 1e7 ? 0 : 1).replace(/\.0$/, '') + 'M';
  if (n >= 1e3) return (n / 1e3).toFixed(n >= 1e4 ? 0 : 1).replace(/\.0$/, '') + 'K';
  return String(n);
}

function badgesHTML(a) {
  const b = [];
  // "Popular near you" — readers from the visitor's own country have opened
  // this article. The count comes from the per-country view table.
  if (a.is_local && a.country) {
    const t = `${fmtNum(a.country_views)} reads in ${esc(a.country)}`;
    b.push(`<span class="bc-badge local" title="${t}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>Popular in ${esc(a.country)}</span>`);
  }
  if (a.is_hot) {
    b.push(`<span class="bc-badge hot"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/></svg>Hot</span>`);
  }
  if (a.is_new) {
    b.push(`<span class="bc-badge new">New</span>`);
  }
  return b.length ? `<div class="bc-badges">${b.join('')}</div>` : '';
}

function cardHTML(a) {
  const href = esc(a.view_url || '#');
  const figure = a.featured_image
    ? `<img src="${esc(a.featured_image)}" loading="lazy" alt="" referrerpolicy="no-referrer"
          onerror="this.replaceWith(Object.assign(document.createElement('span'),{className:'bc-noimg',innerHTML:'<svg viewBox=\\'0 0 24 24\\' fill=\\'none\\' stroke=\\'currentColor\\' stroke-width=\\'1.6\\'><path d=\\'M4 5h16v14H4z\\'/><path d=\\'m4 15 5-5 4 4 3-3 4 4\\'/></svg>'}))">`
    : `<span class="bc-noimg"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 5h16v14H4z"/><path d="m4 15 5-5 4 4 3-3 4 4"/></svg></span>`;
  const cat = a.category ? `<span class="bc-cat">${esc(a.category)}</span>` : '';

  const av = a.author?.avatar
    ? `<img src="${esc(a.author.avatar)}" alt="" referrerpolicy="no-referrer" onerror="this.replaceWith(document.createTextNode('${esc(initials(a.author?.name))}'))">`
    : esc(initials(a.author?.name));
  const authorName = a.author?.name ? esc(a.author.name) : 'Tanbat';
  const authorUrl = a.author?.url ? esc(a.author.url) : '#';

  return `
    <article class="blog-card" data-id="${a.id}">
      <a class="bc-figure" href="${href}">
        ${badgesHTML(a)}
        ${figure}
        ${cat}
      </a>
      <div class="bc-body">
        <a class="bc-title" href="${href}">${esc(a.title || 'Untitled')}</a>
        ${a.excerpt ? `<p class="bc-excerpt">${esc(a.excerpt)}</p>` : '<p class="bc-excerpt"></p>'}
        <div class="bc-foot">
          <a class="bc-av" href="${authorUrl}" aria-label="${authorName}">${av}</a>
          <a class="bc-who" href="${authorUrl}">
            <span class="bc-author">${authorName}</span>
            <span class="bc-sub">${esc(a.published_at || '')}</span>
          </a>
          <span class="bc-views" title="${fmtNum(a.views)} views">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            ${fmtNum(a.views)}
          </span>
        </div>
      </div>
    </article>
  `;
}

function skeletonHTML(n = 6) {
  return Array.from({ length: n }).map(() => `
    <div class="bc-skeleton">
      <span class="sk sk-img"></span>
      <div class="sk-pad">
        <span class="sk sk-line" style="width:90%"></span>
        <span class="sk sk-line" style="width:70%"></span>
        <span class="sk sk-line" style="width:40%;margin-top:14px"></span>
      </div>
    </div>`).join('');
}

async function load({ reset = false } = {}) {
  if (state.loading) return;
  state.loading = true;

  const grid    = $('#blogGrid');
  const loading = $('#blogLoading');
  const empty   = $('#blogEmpty');
  const pager   = $('#blogPager');
  const note    = $('#blogResultNote');

  if (reset) {
    state.page = 1;
    grid.innerHTML = skeletonHTML();
    empty.classList.add('hidden');
    pager.classList.add('hidden');
  }
  loading.classList.remove('hidden');

  try {
    const params = new URLSearchParams({ sort: state.sort, page: String(state.page) });
    if (state.q) params.set('q', state.q);
    if (state.category && state.category !== 'all') params.set('category', state.category);

    const res = await api(`${BLOG.urls.feed}?${params.toString()}`);
    const items = res.items || [];
    state.lastPage = res.last_page || 1;
    state.total = res.total || 0;

    if (reset) grid.innerHTML = '';

    if (state.page === 1 && !items.length) {
      empty.classList.remove('hidden');
      pager.classList.add('hidden');
      note.textContent = '';
    } else {
      grid.insertAdjacentHTML('beforeend', items.map(cardHTML).join(''));
      pager.classList.toggle('hidden', !res.has_more);
      const base = SORT_LABELS[state.sort] || 'Articles';
      note.textContent = state.q
        ? `${fmtNum(state.total)} result${state.total === 1 ? '' : 's'} for “${state.q}”`
        : `${base} · ${fmtNum(state.total)} articles`;
    }
  } catch (e) {
    if (reset) grid.innerHTML = '';
    $('#blogEmpty').classList.remove('hidden');
    $('#blogEmpty .be-title').textContent = 'Could not load articles';
    $('#blogEmpty .be-sub').textContent = 'Please check your connection and try again.';
  } finally {
    loading.classList.add('hidden');
    state.loading = false;
  }
}

function setSort(sort) {
  if (state.sort === sort) return;
  state.sort = sort;
  // Keep the top tabs and the left-rail quick-nav in sync.
  $$('.blog-tab').forEach((t) => t.classList.toggle('is-active', t.dataset.sort === sort));
  $$('.blog-navlink').forEach((t) => t.classList.toggle('is-active', t.dataset.sort === sort));
  load({ reset: true });
}

function debounce(fn, ms) {
  let t;
  return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

function init() {
  // Mark the default quick-nav link active.
  $$('.blog-navlink').forEach((t) => t.classList.toggle('is-active', t.dataset.sort === state.sort));

  $('#blogTabs')?.addEventListener('click', (e) => {
    const btn = e.target.closest('.blog-tab');
    if (btn) setSort(btn.dataset.sort);
  });

  $('.blog-left')?.addEventListener('click', (e) => {
    const nav = e.target.closest('.blog-navlink');
    if (nav) setSort(nav.dataset.sort);
  });

  const input = $('#blogSearchInput');
  const clear = $('#blogSearchClear');
  input?.addEventListener('input', debounce(() => {
    state.q = input.value.trim();
    clear.classList.toggle('hidden', !input.value);
    load({ reset: true });
  }, 350));
  clear?.addEventListener('click', () => {
    input.value = '';
    state.q = '';
    clear.classList.add('hidden');
    input.focus();
    load({ reset: true });
  });

  $('#blogLoadMore')?.addEventListener('click', () => {
    if (state.page >= state.lastPage) return;
    state.page += 1;
    load();
  });

  load({ reset: true });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
