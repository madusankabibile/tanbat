// Search results page — All / Posts / Images / Videos / People / Tags

const APP    = window.__APP__;
const SEARCH = window.__SEARCH__ || { q: '', tab: 'all', urls: {} };

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

const toast = window.Tanbat?.toast || (() => {});
const api   = window.Tanbat?.api;

const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

const fmt = (n) => Number(n || 0).toLocaleString();

let state = {
  q:    SEARCH.q || '',
  tab:  SEARCH.tab || 'all',
  page: 1,
  hasMore: false,
};

function setActiveTab() {
  $$('.search-tab').forEach((b) => {
    b.classList.toggle('active', b.dataset.tab === state.tab);
  });
}

function updateCounts(counts) {
  $$('.search-count').forEach((el) => {
    const key = el.dataset.count;
    el.textContent = fmt(counts?.[key] || 0);
  });
}

function postUrl(p) {
  // Articles + books both have their own dedicated detail page.
  if (p.view_url && (p.type === 'article' || p.type === 'book')) return p.view_url;
  // home page knows how to open the modal via hash for status/image/video.
  return `${APP.urls.home}#post-${p.id}`;
}

function thumbForPost(p) {
  if (p.type === 'book' && p.book?.cover_url) return p.book.cover_url;
  if (p.thumbnail) return p.thumbnail;
  if (p.featured_image) return p.featured_image;
  const first = (p.media || [])[0];
  return first?.url || null;
}

function postCardHTML(p) {
  if (p.type === 'book') return bookResultCardHTML(p);
  const u = p.user || {};
  const av = u.profile_picture
    ? `<img class="h-9 w-9 rounded-full object-cover" src="${esc(u.profile_picture)}" alt="">`
    : `<span class="grid h-9 w-9 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white">${esc((u.username || u.name || 'U').charAt(0).toUpperCase())}</span>`;

  const thumb = thumbForPost(p);
  const thumbHtml = thumb
    ? `<div class="relative h-32 w-32 shrink-0 overflow-hidden rounded-xl bg-slate-100 sm:h-36 sm:w-36">
         <img src="${esc(thumb)}" class="h-full w-full object-cover" alt="" loading="lazy">
         ${p.type === 'video' ? '<span class="absolute inset-0 grid place-items-center bg-black/30 text-white"><svg class="h-8 w-8" viewBox="0 0 24 24" fill="currentColor"><polygon points="6 4 20 12 6 20 6 4"/></svg></span>' : ''}
       </div>`
    : '';

  const title = p.title || (p.status_text || p.description || p.short_description || '').slice(0, 160);
  const body  = p.short_description || p.description || (p.title ? '' : (p.status_text || '')) || '';

  return `
    <a href="${esc(postUrl(p))}" class="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 transition hover:border-brand-200 hover:shadow-pop sm:flex-row">
      ${thumbHtml}
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2 text-xs text-slate-500">
          ${av}
          <span class="font-semibold text-slate-700">${esc(u.name || u.username || 'User')}</span>
          <span>·</span>
          <span class="capitalize">${esc(p.type)}</span>
          ${p.category ? `<span>·</span><span>${esc(p.category.name)}</span>` : ''}
          <span>·</span>
          <span>${esc(p.created_at || '')}</span>
        </div>
        <h3 class="mt-2 line-clamp-2 text-base font-extrabold text-slate-900">${esc(title || '(Untitled)')}</h3>
        ${body ? `<p class="mt-1 line-clamp-2 text-sm text-slate-600">${esc(body)}</p>` : ''}
        <div class="mt-2 flex items-center gap-4 text-xs text-slate-500">
          <span>${fmt(p.likes_count)} likes</span>
          <span>${fmt(p.comments_count)} comments</span>
          <span>${fmt(p.views_count)} views</span>
        </div>
      </div>
    </a>`;
}

// Book results get their own card so the cover sits proudly on the left and
// the title + author + format tags read naturally — matches the rest of the
// app's book-card aesthetic.
function bookResultCardHTML(p) {
  const b = p.book || {};
  const cover = b.cover_url
    ? `<img src="${esc(b.cover_url)}" class="h-full w-full object-cover" alt="" loading="lazy" referrerpolicy="no-referrer">`
    : `<span class="grid h-full w-full place-items-center text-xs text-slate-400 p-2 text-center">No cover</span>`;

  const tags = [
    b.extension ? `<span class="sb-tag ext">${esc(b.extension)}</span>` : '',
    b.size      ? `<span class="sb-tag size">${esc(b.size)}</span>`      : '',
    b.year      ? `<span class="sb-tag year">${esc(b.year)}</span>`      : '',
    b.language  ? `<span class="sb-tag lang">${esc(b.language)}</span>`  : '',
  ].filter(Boolean).join('');

  const blurb = b.description ? esc(String(b.description).slice(0, 180)) + (String(b.description).length > 180 ? '…' : '') : '';

  return `
    <a href="${esc(postUrl(p))}" class="search-book-card">
      <div class="sb-cover">${cover}</div>
      <div class="sb-body">
        <div class="sb-meta-row">
          <span class="sb-badge">Book</span>
          <span class="sb-when">${esc(p.created_at || '')}</span>
        </div>
        <h3 class="sb-title">${esc(b.title || p.title || 'Untitled')}</h3>
        ${b.author    ? `<div class="sb-author">by <strong>${esc(b.author)}</strong></div>` : ''}
        ${b.publisher ? `<div class="sb-pub">${esc(b.publisher)}</div>` : ''}
        ${tags ? `<div class="sb-tags">${tags}</div>` : ''}
        ${blurb ? `<p class="sb-blurb">${blurb}</p>` : ''}
      </div>
    </a>`;
}

function mediaTilesHTML(items, kind) {
  // For images: one tile per image in p.media.
  // For videos: one tile per video post (thumbnail).
  const tiles = [];
  items.forEach((p) => {
    if (kind === 'image') {
      (p.media || []).filter((m) => m.kind === 'image').forEach((m) => {
        tiles.push(`
          <a href="${esc(postUrl(p))}" class="media-tile" title="${esc(p.description || p.title || '')}">
            <img src="${esc(m.url)}" alt="" loading="lazy">
            <span class="badge">Image</span>
          </a>`);
      });
    } else {
      const thumb = p.thumbnail || (p.media || []).find((m) => m.kind === 'image')?.url || '';
      tiles.push(`
        <a href="${esc(postUrl(p))}" class="media-tile" title="${esc(p.description || p.title || '')}">
          ${thumb ? `<img src="${esc(thumb)}" alt="" loading="lazy">` : ''}
          <span class="badge">Video</span>
          <span class="play"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="6 4 20 12 6 20 6 4"/></svg></span>
        </a>`);
    }
  });
  return `<div class="media-grid">${tiles.join('') || '<div class="col-span-full py-8 text-center text-sm text-slate-500">No media found.</div>'}</div>`;
}

function peopleGridHTML(items) {
  if (!items.length) return '';
  const cards = items.map((u) => {
    const av = u.profile_picture
      ? `<img src="${esc(u.profile_picture)}" alt="">`
      : `<span>${esc((u.username || u.name || 'U').charAt(0).toUpperCase())}</span>`;
    return `
      <a href="${esc(u.url)}" class="person-card">
        <span class="pc-avatar">${av}</span>
        <div class="min-w-0 flex-1">
          <div class="pc-name truncate">${esc(u.name || u.username)}</div>
          <div class="pc-handle truncate">&#64;${esc(u.username)}</div>
          ${u.country ? `<div class="pc-handle truncate">${esc(u.country)}</div>` : ''}
        </div>
      </a>`;
  }).join('');
  return `<div class="people-grid">${cards}</div>`;
}

function tagsGridHTML(items) {
  if (!items.length) return '';
  const cards = items.map((t) => `
    <a href="${esc(t.url)}" class="tag-card">
      <span class="tg-hash">#</span>
      <div class="min-w-0 flex-1">
        <div class="tg-name truncate">${esc(t.name)}</div>
        <div class="tg-count">${fmt(t.count)} post${t.count === 1 ? '' : 's'}</div>
      </div>
    </a>`).join('');
  return `<div class="tag-grid">${cards}</div>`;
}

function postsListHTML(items) {
  if (!items.length) return '<div class="py-8 text-center text-sm text-slate-500">No posts match.</div>';
  return `<div class="space-y-3">${items.map(postCardHTML).join('')}</div>`;
}

function renderResults(payload, { append = false } = {}) {
  const wrap = $('#searchResults');
  const empty = $('#searchEmpty');
  const loadMore = $('#searchLoadMore');

  state.hasMore = !!payload.has_more;
  loadMore.classList.toggle('hidden', !state.hasMore);

  if (!append) {
    wrap.innerHTML = '';
  }

  const tab = payload.tab || state.tab;
  const items = payload.items || [];

  let block = '';
  if (tab === 'people') {
    block = peopleGridHTML(items);
  } else if (tab === 'tags') {
    block = tagsGridHTML(items);
  } else if (tab === 'images') {
    block = mediaTilesHTML(items, 'image');
  } else if (tab === 'videos') {
    block = mediaTilesHTML(items, 'video');
  } else {
    // posts / all
    block = postsListHTML(items);
  }

  if (append) {
    wrap.insertAdjacentHTML('beforeend', block);
  } else {
    wrap.innerHTML = block;
  }

  const totalShown = wrap.children.length || wrap.querySelectorAll('a').length;
  if (!totalShown) {
    empty.classList.remove('hidden');
  } else {
    empty.classList.add('hidden');
  }
}

async function fetchAndRender({ append = false } = {}) {
  if (!state.q) {
    $('#searchEmpty').classList.add('hidden');
    $('#searchResults').innerHTML = '';
    updateCounts({ posts: 0, images: 0, videos: 0, people: 0, tags: 0 });
    return;
  }

  const loading = $('#searchLoading');
  if (!append) loading.classList.remove('hidden');

  try {
    const url = new URL(SEARCH.urls.results, window.location.origin);
    url.searchParams.set('q', state.q);
    url.searchParams.set('tab', state.tab);
    url.searchParams.set('page', String(state.page));
    const data = await api(url.toString());

    updateCounts(data.counts || {});
    renderResults(data, { append });

    const summary = $('#searchSummary');
    if (summary) {
      const total = (data.counts?.posts || 0) + (data.counts?.people || 0) + (data.counts?.tags || 0);
      summary.textContent = total
        ? `Found ${fmt(total)} match${total === 1 ? '' : 'es'} across posts, people and tags.`
        : `No matches found for "${state.q}".`;
    }
  } catch (e) {
    toast(e.message || 'Search failed', 'bad');
  } finally {
    loading.classList.add('hidden');
  }
}

function syncUrl() {
  const u = new URL(window.location.href);
  if (state.q) u.searchParams.set('q', state.q);
  else u.searchParams.delete('q');
  u.searchParams.set('tab', state.tab);
  window.history.replaceState(null, '', u.toString());
}

function bindTabs() {
  $$('.search-tab').forEach((b) => {
    b.addEventListener('click', () => {
      if (b.dataset.tab === state.tab) return;
      state.tab = b.dataset.tab;
      state.page = 1;
      setActiveTab();
      syncUrl();
      fetchAndRender({ append: false });
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });
}

function bindLoadMore() {
  $('#searchLoadMore')?.addEventListener('click', () => {
    state.page += 1;
    fetchAndRender({ append: true });
  });
}

// Keep state.q in sync with the navbar input — submitting the form will reload
// the page, but if the user clears it and types here we want to react too.
function watchNavInput() {
  const input = document.getElementById('navSearchInput');
  if (!input) return;
  // When the navbar form submits, the page reloads with the new ?q=, so we
  // don't need to intercept Enter here. We only react to "clear" actions.
  input.addEventListener('input', () => {
    if (!input.value.trim()) {
      // Don't clear the page automatically — let the user submit empty to "reset"
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  setActiveTab();
  bindTabs();
  bindLoadMore();
  watchNavInput();
  fetchAndRender({ append: false });
});
