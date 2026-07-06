// Tanbat Assistant — wizard state machine.
//
// Concurrency note: the entire wizard state lives in this page's memory.
// Different users / tabs each have their own state. The backend dedups by
// book md5 inside a transaction, so two users confirming the same book at
// the same instant collapse onto one Post.

import { AD_LINK_URL } from './cards.js';

const APP   = window.__APP__;
const toast = window.Tanbat.toast;
const api   = window.Tanbat.api;

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

// ─── State ────────────────────────────────────────────────────────────
const state = {
  step: 'service',     // service | query | results | preparing | done
  service: null,       // 'book' | 'software' | 'game' | 'movie'
  query: '',
  results: [],
  page: 0,             // current results page (0-indexed), PAGE_SIZE per page
  confirming: false,
  prepTimer: null,     // setInterval id for the prep-pane clock
  pollTimer: null,     // setTimeout id for the next poll tick
  pendingMd5: null,
  pendingEta: 0,
};

// Show the library results 10 at a time; the user pages through them and
// picks the one they want.
const PAGE_SIZE = 10;

const STEPS = ['service', 'query', 'results', 'preparing', 'done'];
const PREPARATION_TOTAL_SECONDS = 120;

function totalPages() {
  return Math.max(1, Math.ceil(state.results.length / PAGE_SIZE));
}

// ─── Navigation ───────────────────────────────────────────────────────
function goTo(step) {
  // Tear down the preparing-pane timers when we leave it (either to "done"
  // or back to "service" via restart). Polling keeps running while the pane
  // is visible; it's cheap enough not to need cancellation on success.
  if (state.step === 'preparing' && step !== 'preparing') cancelPreparing();

  state.step = step;
  $$('[data-pane]').forEach((p) => p.classList.toggle('hidden', p.dataset.pane !== step));

  // Stepper indicator
  const idx = STEPS.indexOf(step);
  $$('#assistSteps li').forEach((li, i) => {
    li.classList.toggle('is-active', i === idx);
    li.classList.toggle('is-done', i < idx);
  });

  // Focus the first sensible target so keyboard users land in the right place.
  if (step === 'query') setTimeout(() => $('#assistQuery')?.focus(), 60);
}

// ─── Step 1: Service ──────────────────────────────────────────────────
function bindServices() {
  $('#serviceGrid')?.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-service]');
    if (!btn) return;
    if (btn.dataset.locked === '1') {
      toast(`${btn.querySelector('.svc-title')?.textContent || 'This service'} is coming soon.`, 'def');
      return;
    }
    state.service = btn.dataset.service;
    state.query = '';
    state.results = [];
    state.page = 0;
    $('#assistQuery').value = '';
    $('[data-svc-name]').textContent = ({
      book: 'Book', software: 'Software', game: 'Game', movie: 'Movie',
    })[state.service] || 'Item';
    goTo('query');
  });
}

// ─── Back / restart buttons (shared) ──────────────────────────────────
function bindBack() {
  document.addEventListener('click', (e) => {
    if (e.target.closest('[data-back]')) {
      if (state.step === 'query')   return goTo('service');
      if (state.step === 'results') return goTo('query');
      if (state.step === 'done')    return goTo('service');
    }
    if (e.target.closest('[data-restart]')) {
      state.service = null;
      state.query = '';
      state.results = [];
      state.page = 0;
      goTo('service');
    }
  });
}

// ─── Step 2: Query ────────────────────────────────────────────────────
function bindQueryForm() {
  $('#assistQueryForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = $('#assistQuery').value.trim();
    if (q.length < 2) {
      toast('Type at least 2 characters.', 'bad');
      return;
    }
    state.query = q;
    await runSearch();
  });
}

async function runSearch() {
  goTo('results');
  showResultsLoading();
  try {
    const res = await api(`${APP.urls.api.assistantSearch}?service=${encodeURIComponent(state.service)}&query=${encodeURIComponent(state.query)}`);
    if (!res.ok) {
      hideResultsLoading();
      toast(res.message || 'Search failed.', 'bad');
      showEmpty();
      return;
    }
    state.results = res.results || [];
    state.page = 0;
    hideResultsLoading();
    if (!state.results.length) {
      showEmpty();
    } else {
      renderResultsPage();
    }
  } catch (err) {
    hideResultsLoading();
    toast(err.message || 'Could not run that search.', 'bad');
    showEmpty();
  }
}

// ─── Step 3: Results list (paginated, pick one) ───────────────────────
function showResultsLoading() {
  $('#resultsLoading')?.classList.remove('hidden');
  $('#resultsList')?.classList.add('hidden');
  $('#resultsEmpty')?.classList.add('hidden');
  $('#resultsNav')?.classList.add('hidden');
}
function hideResultsLoading() {
  $('#resultsLoading')?.classList.add('hidden');
  $('#resultsList')?.classList.remove('hidden');
  $('#resultsNav')?.classList.remove('hidden');
}
function showEmpty() {
  $('#resultsEmpty')?.classList.remove('hidden');
  $('#resultsList')?.classList.add('hidden');
  $('#resultsNav')?.classList.add('hidden');
  const counter = $('#resultsCounter');
  if (counter) counter.textContent = '';
}

function resultItemHTML(r, idx) {
  const cover = r.cover
    ? `<img src="${esc(r.cover)}" alt="" referrerpolicy="no-referrer"
           onerror="this.replaceWith(Object.assign(document.createElement('span'), { className:'noimg', textContent:'No cover' }))">`
    : `<span class="noimg">No cover</span>`;

  const tags = [
    r.extension ? `<span class="result-tag ext">${esc(r.extension)}</span>` : '',
    r.size      ? `<span class="result-tag size">${esc(r.size)}</span>`      : '',
    r.year      ? `<span class="result-tag year">📅 ${esc(r.year)}</span>`   : '',
    r.language  ? `<span class="result-tag lang">🌐 ${esc(r.language)}</span>` : '',
  ].filter(Boolean).join('');

  return `
    <div class="result-item" data-index="${idx}" role="button" tabindex="0">
      <a class="ri-cover" href="${AD_LINK_URL}" target="_blank" rel="sponsored noopener">${cover}</a>
      <div class="ri-info">
        <div class="ri-title">${esc(r.title)}</div>
        ${r.author ? `<div class="ri-author">by ${esc(r.author)}</div>` : ''}
        <div class="ri-tags">${tags}</div>
      </div>
      <div class="ri-action">
        <button type="button" class="btn-primary ri-select" data-index="${idx}">Select</button>
      </div>
    </div>`;
}

function renderResultsPage() {
  const list = $('#resultsList');
  if (!list) return;

  const total = state.results.length;
  if (!total) return showEmpty();

  $('#resultsEmpty')?.classList.add('hidden');
  list.classList.remove('hidden');
  $('#resultsNav')?.classList.remove('hidden');

  const pages = totalPages();
  state.page = Math.min(Math.max(0, state.page), pages - 1);

  const start = state.page * PAGE_SIZE;
  const slice = state.results.slice(start, start + PAGE_SIZE);
  list.innerHTML = slice.map((r, i) => resultItemHTML(r, start + i)).join('');

  // Counter + pager state
  const first = start + 1;
  const last  = Math.min(start + PAGE_SIZE, total);
  const counter = $('#resultsCounter');
  if (counter) counter.textContent = `Showing ${first}–${last} of ${total} match${total === 1 ? '' : 'es'}`;

  const ind = $('#pageIndicator');
  if (ind) ind.textContent = `Page ${state.page + 1} of ${pages}`;
  const prev = $('#btnPrevPage');
  const next = $('#btnNextPage');
  if (prev) prev.disabled = state.page <= 0;
  if (next) next.disabled = state.page >= pages - 1;
  // Only one page → no need for the prev/next controls.
  $('#resultsPager')?.classList.toggle('hidden', pages <= 1);
}

function scrollResultsTop() {
  $('[data-pane="results"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function bindResultsControls() {
  $('#btnPrevPage')?.addEventListener('click', () => {
    if (state.page > 0) { state.page -= 1; renderResultsPage(); scrollResultsTop(); }
  });
  $('#btnNextPage')?.addEventListener('click', () => {
    if (state.page < totalPages() - 1) { state.page += 1; renderResultsPage(); scrollResultsTop(); }
  });

  // Selecting a book: the whole row is clickable (except the cover, which is a
  // sponsored link), and so is the explicit "Select" button.
  $('#resultsList')?.addEventListener('click', (e) => {
    if (e.target.closest('.ri-cover')) return; // let the ad link open
    const item = e.target.closest('.result-item');
    if (!item) return;
    const idx = Number(item.dataset.index);
    if (Number.isInteger(idx)) confirmSelection(idx);
  });
  // Keyboard: Enter/Space on a focused row selects it.
  $('#resultsList')?.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const item = e.target.closest('.result-item');
    if (!item) return;
    e.preventDefault();
    const idx = Number(item.dataset.index);
    if (Number.isInteger(idx)) confirmSelection(idx);
  });
}

async function confirmSelection(index) {
  if (state.confirming) return;
  const r = state.results[index];
  if (!r?.md5) {
    toast('That result is missing an identifier — pick another.', 'bad');
    return;
  }
  // Guests can request too — the backend attributes it to a shared anonymous
  // account and publishes it just like a signed-in request.
  state.confirming = true;
  const selectBtns = $$('.ri-select');
  selectBtns.forEach((b) => { b.disabled = true; });
  const active = $(`.ri-select[data-index="${index}"]`);
  if (active) active.textContent = 'Adding…';

  try {
    const res = await api(APP.urls.api.assistantConfirm, {
      method: 'POST',
      body: { service: state.service, query: state.query, md5: r.md5 },
    });
    if (!res.ok) throw new Error(res.message || 'Could not save that book.');

    // Fast path — the book already existed; skip the wait.
    if (res.status === 'ready' && res.post) {
      renderDone(res.post, res.view_url);
      goTo('done');
      return;
    }

    // Queued — show the 2-minute preparing pane and start polling.
    state.pendingMd5 = res.md5 || r.md5;
    state.pendingEta = Number(res.eta_seconds || PREPARATION_TOTAL_SECONDS);
    goTo('preparing');
    startPreparing(state.pendingMd5, state.pendingEta);
  } catch (err) {
    toast(err.message || 'Could not save that book.', 'bad');
  } finally {
    state.confirming = false;
    selectBtns.forEach((b) => { b.disabled = false; });
    if (active) active.textContent = 'Select';
  }
}

// ─── Step 4: Done ─────────────────────────────────────────────────────
function renderDone(post, viewUrl) {
  const b = post?.book || {};
  const cover = b.cover_url
    ? `<img src="${esc(b.cover_url)}" alt="" referrerpolicy="no-referrer">`
    : `<span class="noimg">No cover</span>`;
  const tags = [
    b.extension ? `<span class="result-tag ext">${esc(b.extension)}</span>` : '',
    b.size      ? `<span class="result-tag size">${esc(b.size)}</span>`      : '',
    b.year      ? `<span class="result-tag year">${esc(b.year)}</span>`      : '',
    b.language  ? `<span class="result-tag lang">${esc(b.language)}</span>`  : '',
  ].filter(Boolean).join('');

  const doneCard = $('#doneCard');
  if (doneCard) doneCard.innerHTML = `
    <a class="dc-cover" href="${AD_LINK_URL}" target="_blank" rel="sponsored noopener">${cover}</a>
    <div class="dc-meta">
      <div class="dc-title">${esc(b.title || post.title || 'Untitled')}</div>
      ${b.author    ? `<div class="dc-author">by ${esc(b.author)}</div>`    : ''}
      ${b.publisher ? `<div class="dc-author">${esc(b.publisher)}</div>` : ''}
      <div class="dc-tags">${tags}</div>
    </div>
  `;
  const viewBtn = $('#doneViewBtn');
  if (viewBtn) viewBtn.href = viewUrl || (APP.urls.home + '#post-' + post.id);
}

// ─── Preparing pane (2-minute wait + polling) ────────────────────────
function pad2(n) { return String(Math.max(0, Math.floor(n))).padStart(2, '0'); }

function renderPrepClock(remaining) {
  remaining = Math.max(0, remaining);
  const minutes = Math.floor(remaining / 60);
  const seconds = remaining % 60;
  $('#prepMinutes').textContent = pad2(minutes);
  $('#prepSeconds').textContent = pad2(seconds);
  const progressPct = Math.min(100, ((PREPARATION_TOTAL_SECONDS - remaining) / PREPARATION_TOTAL_SECONDS) * 100);
  const fill = $('#prepBarFill');
  if (fill) fill.style.width = `${progressPct}%`;
}

function startPreparing(md5, etaSeconds) {
  cancelPreparing();
  let remaining = Math.max(0, etaSeconds);
  renderPrepClock(remaining);

  state.prepTimer = setInterval(() => {
    remaining -= 1;
    renderPrepClock(remaining);
    if (remaining <= 0) {
      clearInterval(state.prepTimer);
      state.prepTimer = null;
    }
  }, 1000);

  // First poll: shortly before the timer ends. After that, keep polling every
  // 5s until we see status=ready or the user navigates away.
  const firstPollDelay = Math.max(2000, (etaSeconds - 2) * 1000);
  state.pollTimer = setTimeout(() => pollStatus(md5), firstPollDelay);
}

function cancelPreparing() {
  if (state.prepTimer) { clearInterval(state.prepTimer); state.prepTimer = null; }
  if (state.pollTimer) { clearTimeout(state.pollTimer); state.pollTimer = null; }
}

async function pollStatus(md5) {
  // If the user navigated away from the preparing pane, stop polling.
  if (state.step !== 'preparing' || state.pendingMd5 !== md5) return;
  try {
    const res = await api(`${APP.urls.api.assistantStatus}?md5=${encodeURIComponent(md5)}`);
    if (res?.status === 'ready' && res.post) {
      // Snap the clock to 00:00 visually before transitioning.
      renderPrepClock(0);
      renderDone(res.post, res.view_url);
      goTo('done');
      return;
    }
  } catch (err) {
    // Soft-fail — try again on the next tick.
  }
  state.pollTimer = setTimeout(() => pollStatus(md5), 5000);
}

// ─── Boot ─────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  bindServices();
  bindBack();
  bindQueryForm();
  bindResultsControls();
  goTo('service');
});
