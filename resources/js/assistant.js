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
  cursor: 0,
  confirming: false,
  nextTimer: null,     // setInterval id for the gate on "show next"
  prepTimer: null,     // setInterval id for the prep-pane clock
  pollTimer: null,     // setTimeout id for the next poll tick
  pendingMd5: null,
  pendingEta: 0,
};

const NEXT_WAIT_SECONDS = 10;

// Disable "No, show next" for NEXT_WAIT_SECONDS so the user actually looks at
// the result before flipping past it. Yes-confirm stays enabled the whole time.
function startNextButtonCountdown() {
  const btn = $('#btnNotMine');
  if (!btn) return;
  cancelNextButtonCountdown();
  let remaining = NEXT_WAIT_SECONDS;
  btn.disabled = true;
  btn.classList.add('is-waiting');
  btn.textContent = `Wait ${remaining}s…`;
  state.nextTimer = setInterval(() => {
    remaining -= 1;
    if (remaining > 0) {
      btn.textContent = `Wait ${remaining}s…`;
      return;
    }
    cancelNextButtonCountdown();
    btn.disabled = false;
    btn.classList.remove('is-waiting');
    btn.textContent = 'No, show next';
  }, 1000);
}

function cancelNextButtonCountdown() {
  if (state.nextTimer) {
    clearInterval(state.nextTimer);
    state.nextTimer = null;
  }
}

const STEPS = ['service', 'query', 'results', 'preparing', 'done'];
const PREPARATION_TOTAL_SECONDS = 120;

// ─── Navigation ───────────────────────────────────────────────────────
function goTo(step) {
  // Always stop the 10s gate when leaving the results pane so the timer
  // doesn't keep firing on a hidden button.
  if (state.step === 'results' && step !== 'results') cancelNextButtonCountdown();
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
    state.cursor = 0;
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
      state.cursor = 0;
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
    state.cursor = 0;
    hideResultsLoading();
    if (!state.results.length) {
      showEmpty();
    } else {
      renderCurrentResult();
    }
  } catch (err) {
    hideResultsLoading();
    toast(err.message || 'Could not run that search.', 'bad');
    showEmpty();
  }
}

// ─── Step 3: Results review ───────────────────────────────────────────
function showResultsLoading() {
  $('#resultsLoading')?.classList.remove('hidden');
  $('#resultCard')?.classList.add('hidden');
  $('#resultsEmpty')?.classList.add('hidden');
  $('.result-actions')?.classList.add('hidden');
}
function hideResultsLoading() {
  $('#resultsLoading')?.classList.add('hidden');
  $('#resultCard')?.classList.remove('hidden');
  $('.result-actions')?.classList.remove('hidden');
}
function showEmpty() {
  $('#resultsEmpty')?.classList.remove('hidden');
  $('#resultCard')?.classList.add('hidden');
  $('.result-actions')?.classList.add('hidden');
  $('#resultsCounter').textContent = '';
}

function renderCurrentResult() {
  const r = state.results[state.cursor];
  if (!r) return showEmpty();

  $('#resultsEmpty')?.classList.add('hidden');
  $('#resultCard')?.classList.remove('hidden');
  $('.result-actions')?.classList.remove('hidden');

  const tags = [
    r.extension ? `<span class="result-tag ext">${esc(r.extension)}</span>` : '',
    r.size      ? `<span class="result-tag size">${esc(r.size)}</span>`      : '',
    r.year      ? `<span class="result-tag year">📅 ${esc(r.year)}</span>`   : '',
    r.language  ? `<span class="result-tag lang">🌐 ${esc(r.language)}</span>` : '',
  ].filter(Boolean).join('');

  const cover = r.cover
    ? `<img src="${esc(r.cover)}" alt="" referrerpolicy="no-referrer"
           onerror="this.replaceWith(Object.assign(document.createElement('span'), { className:'noimg', textContent:'No cover' }))">`
    : `<span class="noimg">No cover</span>`;

  $('#resultCard').innerHTML = `
    <a class="result-cover" href="${AD_LINK_URL}" target="_blank" rel="sponsored noopener">${cover}</a>
    <div class="result-info">
      <div class="result-title">${esc(r.title)}</div>
      ${r.author    ? `<div class="result-author">by ${esc(r.author)}</div>`    : ''}
      ${r.publisher ? `<div class="result-pub">${esc(r.publisher)}</div>` : ''}
      <div class="result-tags">${tags}</div>
    </div>
  `;

  $('#resultsCounter').textContent = `Match ${state.cursor + 1} of ${state.results.length}`;
  startNextButtonCountdown();
}

function bindResultsActions() {
  $('#btnNotMine')?.addEventListener('click', () => {
    if (state.cursor < state.results.length - 1) {
      state.cursor++;
      renderCurrentResult();
    } else {
      // Walked past the end → show empty + restart hint.
      showEmpty();
    }
  });

  $('#btnYesMine')?.addEventListener('click', () => {
    confirmCurrent();
  });
}

async function confirmCurrent() {
  if (state.confirming) return;
  const r = state.results[state.cursor];
  if (!r?.md5) {
    toast('That result is missing an identifier — try the next one.', 'bad');
    return;
  }
  // Guests can request too — the backend attributes it to a shared anonymous
  // account and publishes it just like a signed-in request.
  state.confirming = true;
  const yesBtn = $('#btnYesMine');
  const noBtn  = $('#btnNotMine');
  yesBtn.disabled = true; noBtn.disabled = true;
  yesBtn.textContent = 'Adding…';
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
    yesBtn.disabled = false; noBtn.disabled = false;
    yesBtn.textContent = "Yes, that's it";
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

  $('#doneCard').innerHTML = `
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
  bindResultsActions();
  goTo('service');
});
