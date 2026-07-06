// /books page — browse every Book post in the library.

const APP   = window.__APP__;
const toast = window.Tanbat.toast;
const api   = window.Tanbat.api;

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

const state = {
  q: '',
  page: 1,
  lastPage: 1,
  loading: false,
  posts: [],
};

function bookTileHTML(p) {
  const b = p.book || {};
  const cover = b.cover_url
    ? `<img src="${esc(b.cover_url)}" alt="" referrerpolicy="no-referrer"
           onerror="this.replaceWith(Object.assign(document.createElement('span'), { className:'noimg', textContent:'No cover' }))">`
    : `<span class="noimg">No cover</span>`;
  const ext = b.extension ? `<span class="bt-ext">${esc(b.extension)}</span>` : '';
  const author = b.author ? esc(b.author) : 'Unknown author';
  const meta = [b.year, b.language, b.size].filter(Boolean).map(esc);
  const metaHtml = meta.length
    ? meta.map((m, i) => `${i === 0 ? '' : '<span class="dot"></span>'}<span>${m}</span>`).join('')
    : '';

  const href = p.view_url || `${APP.urls.books || '/books'}/${b.slug || ''}`;

  return `
    <div class="book-tile" data-post-id="${p.id}">
      <a class="bt-cover" href="${esc(href)}">${cover}${ext}</a>
      <a class="bt-body" href="${esc(href)}">
        <div class="bt-title">${esc(b.title || p.title || 'Untitled')}</div>
        <div class="bt-author">${author}</div>
        ${metaHtml ? `<div class="bt-meta">${metaHtml}</div>` : ''}
      </a>
    </div>
  `;
}

async function loadBooks({ reset = false } = {}) {
  if (state.loading) return;
  state.loading = true;
  const empty = $('#booksEmpty');
  const loading = $('#booksLoading');
  const grid = $('#booksGrid');
  const pager = $('#booksPager');
  if (reset) {
    state.page = 1; state.posts = []; grid.innerHTML = '';
    pager.classList.add('hidden');
    empty.classList.add('hidden');
  }
  loading.classList.remove('hidden');

  try {
    const url = `${APP.urls.api.books}?page=${state.page}${state.q ? `&q=${encodeURIComponent(state.q)}` : ''}`;
    const res = await api(url);
    const items = res.data || [];
    state.lastPage = res.meta?.last_page || 1;

    if (state.page === 1 && !items.length) {
      empty.classList.remove('hidden');
      pager.classList.add('hidden');
    } else {
      state.posts = state.posts.concat(items);
      grid.insertAdjacentHTML('beforeend', items.map(bookTileHTML).join(''));
      pager.classList.toggle('hidden', state.page >= state.lastPage);
    }
  } catch (err) {
    toast(err.message || 'Could not load books.', 'bad');
  } finally {
    state.loading = false;
    loading.classList.add('hidden');
  }
}

function bindLoadMore() {
  $('#booksLoadMore')?.addEventListener('click', () => {
    if (state.page >= state.lastPage) return;
    state.page++;
    loadBooks();
  });
}

function bindSearch() {
  const form = $('#booksSearchForm');
  const input = $('#booksSearchInput');
  let debounce = null;
  input?.addEventListener('input', () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
      const next = input.value.trim();
      if (next === state.q) return;
      state.q = next;
      loadBooks({ reset: true });
    }, 250);
  });
  form?.addEventListener('submit', (e) => {
    e.preventDefault();
    state.q = input.value.trim();
    loadBooks({ reset: true });
  });
}

// ─── Boot ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  bindLoadMore();
  bindSearch();
  loadBooks({ reset: true });
});
