// Home-page logic: feed, create-modals, post-detail modal
import { cardHTML, bindGallery, isLight, mountVideoStage, newsbotCtaHTML, hydrateAdSlots, feedAdCardHTML, formatPostTime,
         REACTIONS, REACTION_MAP, reactionStackHTML, applyReactionUI, setReactionCount, renderReactionSummary, bindReactions, dismissReactionPanel,
         bindDownloadCountdown } from './cards.js';

const APP = window.__APP__;

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const esc = (s) => String(s ?? '')
  .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
  .replace(/"/g,'&quot;').replace(/'/g,'&#039;');

const toast = window.Tanbat.toast;
const api   = window.Tanbat.api;
const openModal  = window.Tanbat.openModal;
const closeModal = window.Tanbat.closeModal;

// ─────────── State ───────────
let allPosts = [];
let filter   = 'all';
let categories = [];
// Streaming feed: each load fetches the next single post given the IDs
// the client has already rendered in this session. Page refresh resets it.
let sessionLoadedIds = [];   // sticky for the lifetime of the page
let feedLoading = false;
let feedExhausted = false;   // true once /api/feed returns zero posts
let feedColdStart = false;
let feedRecycled = false;    // true once the API starts serving previously-seen posts
let openedAtMs = 0;          // timestamp when current post modal opened (for dwell)
let currentPlyr = null;      // Plyr instance for the post-detail video stage

// Sidebar (profile / site stats / active users) is rendered by sidebars.js, which
// is loaded by the partials/side-rails.blade.php include.
function fmt(n) { return Number(n || 0).toLocaleString(); }

// ─────────── Categories ───────────
async function loadCategories() {
  try {
    categories = await api(APP.urls.api.categories);
    $$('[data-categories]').forEach((sel) => {
      sel.innerHTML = '<option value="">Choose a category…</option>' +
        categories.map((c) => `<option value="${c.id}">${esc(c.name)}</option>`).join('');
    });
  } catch (e) { console.warn(e); }
}

// ─────────── Streaming feed (one batch per request) ───────────
// The first call loads the 3 most relevant posts as a strong first impression.
// Subsequent batches fetch 2 at a time as the user scrolls near the bottom.
// Every call re-runs the server-side ranker, so likes/comments/clicks/dwell on
// already-rendered cards immediately shape what comes next.
const FEED_FIRST_BATCH = 3;
const FEED_BATCH_SIZE  = 2;

async function loadFeed({ reset = true } = {}) {
  if (reset) {
    sessionLoadedIds = [];
    allPosts = [];
    feedExhausted = false;
    feedRecycled = false;
    $('#feedEnd')?.classList.add('hidden');
  }
  const loading = $('#feedLoading');
  if (loading && reset) loading.classList.remove('hidden');
  try {
    await loadNextBatch();
  } finally {
    if (loading) loading.classList.add('hidden');
  }
}

async function loadNextBatch() {
  if (feedLoading || feedExhausted) return;
  feedLoading = true;
  $('#feedNextLoading')?.classList.remove('hidden');
  try {
    const base = APP.urls.api.feed || APP.urls.api.posts;
    const batchSize = sessionLoadedIds.length === 0 ? FEED_FIRST_BATCH : FEED_BATCH_SIZE;
    const url = `${base}?limit=${batchSize}&exclude_ids=${sessionLoadedIds.join(',')}`;
    const res = await api(url);
    const page = res.data || [];
    feedColdStart = !!res.cold_start;
    if (res.recycled) feedRecycled = true;

    if (!page.length) {
      feedExhausted = !!res.exhausted;
      if (feedExhausted) showEndOfFeed();
      else if (!allPosts.length) showEmptyState();
      return;
    }

    page.forEach((p) => {
      if (!sessionLoadedIds.includes(p.id)) {
        sessionLoadedIds.push(p.id);
        allPosts.push(p);
      }
    });
    renderFeed({ append: true });
  } catch (e) {
    if (allPosts.length === 0) toast('Could not load feed.', 'bad');
    console.warn(e);
  } finally {
    feedLoading = false;
    $('#feedNextLoading')?.classList.add('hidden');
  }
}

function showEndOfFeed() {
  const end = $('#feedEnd');
  if (end) end.classList.remove('hidden');
  // Hide the bottom spinner since there's nothing more to load.
  $('#feedNextLoading')?.classList.add('hidden');
}

function renderFeed({ append = false } = {}) {
  const feed = $('#feed');
  const posts = filter === 'all' ? allPosts : allPosts.filter((p) => p.type === filter);
  if (!posts.length) {
    feed.innerHTML = '';
    showEmptyState();
    return;
  }
  $('#feedEmpty')?.classList.add('hidden');

  if (append) {
    // Render only the cards we haven't rendered yet to avoid losing scroll.
    const rendered = new Set(Array.from(feed.querySelectorAll('[data-post-id]')).map((el) => el.dataset.postId));
    const fresh = posts.filter((p) => !rendered.has(String(p.id)));
    if (fresh.length) {
      feed.insertAdjacentHTML('beforeend', fresh.map(cardHTML).join(''));
    }
  } else {
    feed.innerHTML = posts.map(cardHTML).join('');
  }

  ensureFeedAd();
  feed.querySelectorAll('.gallery-wrap').forEach(bindGallery);
  hydrateAdSlots(feed);
  bindDownloadCountdown(feed);
  observeImpressions();
}

// Pin a single sponsored card to the very top of the feed. It's inserted once
// (a full re-render on filter change wipes it, so we re-add), never duplicated
// on append, and carries no data-post-id so it's ignored by impression tracking.
function ensureFeedAd() {
  const feed = $('#feed');
  if (!feed || feed.querySelector('[data-ad-feed]')) return;
  feed.insertAdjacentHTML('afterbegin', feedAdCardHTML());
}

// Pick the right copy depending on whether the user has *ever* seen content.
// "Nothing here yet" should only show on a truly cold account / empty network.
function showEmptyState() {
  const empty = $('#feedEmpty');
  if (!empty) return;
  const title = $('#feedEmptyTitle');
  const desc  = $('#feedEmptyDesc');
  if (filter !== 'all') {
    if (title) title.textContent = 'No posts in this filter';
    if (desc)  desc.textContent  = 'Try switching back to All to see everything.';
  } else {
    if (title) title.textContent = 'Nothing here yet';
    if (desc)  desc.textContent  = 'Be the first to share something — hit Create above.';
  }
  empty.classList.remove('hidden');
}

// ─────────── Impression tracking (IntersectionObserver → batched POST) ──
const pendingImpressions = new Set();
const reportedImpressions = new Set();
let impressionFlushTimer = null;
let impressionObserver = null;

function observeImpressions() {
  if (!APP.user) return; // only logged-in users feed the algorithm
  if (!('IntersectionObserver' in window)) return;
  if (!impressionObserver) {
    impressionObserver = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const id = entry.target.dataset.postId;
        if (!id || reportedImpressions.has(id)) return;
        pendingImpressions.add(id);
        scheduleImpressionFlush();
      });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.5 });
  }
  document.querySelectorAll('#feed [data-post-id]').forEach((el) => {
    if (!el.dataset.impressionBound) {
      impressionObserver.observe(el);
      el.dataset.impressionBound = '1';
    }
  });
}

function scheduleImpressionFlush() {
  if (impressionFlushTimer) return;
  impressionFlushTimer = setTimeout(flushImpressions, 2500);
}

async function flushImpressions() {
  impressionFlushTimer = null;
  if (!pendingImpressions.size) return;
  const ids = Array.from(pendingImpressions).map((v) => Number(v)).filter(Boolean);
  pendingImpressions.clear();
  ids.forEach((id) => reportedImpressions.add(String(id)));
  if (!APP.urls.api.feedImpressions) return;
  try {
    await api(APP.urls.api.feedImpressions, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ post_ids: ids }),
    });
  } catch (e) {
    // Soft-fail — impressions are best-effort. Restore so we retry later.
    ids.forEach((id) => reportedImpressions.delete(String(id)));
  }
}

// ─────────── Infinite scroll: load next page near bottom ───────────
// Scroll-driven loading: each batch is requested only when the user actually
// scrolls near the bottom. No cascade on page load — the user sees the first
// batch and has to scroll to get more, by design.
function bindInfiniteScroll() {
  const LOAD_AHEAD_PX = 500; // start the next fetch when this close to bottom
  let ticking = false;

  const onScroll = () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      ticking = false;
      if (feedLoading || feedExhausted) return;
      const distanceFromBottom = (document.documentElement.scrollHeight
        - (window.scrollY + window.innerHeight));
      if (distanceFromBottom < LOAD_AHEAD_PX) {
        loadNextBatch();
      }
    });
  };
  // Passive listener — we never need to preventDefault, so the browser can
  // keep scroll buttery-smooth even while this handler runs.
  window.addEventListener('scroll', onScroll, { passive: true });
}

// ─────────── Click + dwell tracking ───────────
async function recordClick(postId) {
  if (!APP.user) return;
  openedAtMs = Date.now();
  try {
    await fetch(`${APP.urls.api.posts}/${postId}/click`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrf },
      credentials: 'same-origin',
      body: JSON.stringify({ event: 'click' }),
    });
  } catch {}
}

async function recordDwell(postId) {
  if (!APP.user || !openedAtMs) return;
  const dwell_ms = Date.now() - openedAtMs;
  openedAtMs = 0;
  if (dwell_ms < 1500) return; // ignore accidental opens
  try {
    await fetch(`${APP.urls.api.posts}/${postId}/click`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': APP.csrf },
      credentials: 'same-origin',
      body: JSON.stringify({ event: 'dwell', dwell_ms }),
    });
  } catch {}
}

// ─────────── Filter chips ───────────
function bindFilters() {
  $$('[data-filter]').forEach((btn) => {
    btn.addEventListener('click', () => {
      $$('[data-filter]').forEach((b) => b.classList.remove('active'));
      btn.classList.add('active');
      filter = btn.dataset.filter;
      renderFeed();
    });
  });
}

// ─────────── Create dropdown handlers ───────────
function bindCreate() {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-create]');
    if (!btn) return;
    const kind = btn.dataset.create;
    // close create dropdown
    document.querySelectorAll('[data-menu-panel]').forEach((p) => p.classList.add('hidden'));

    if (kind === 'status') openStatusModal();
    if (kind === 'image')  openModal('modalImage');
    if (kind === 'video')  openModal('modalVideo');
  });
}

// ─────────── Status modal ───────────
const BG_COLORS = ['#FFFFFF','#1E1B4B','#6C63FF','#FF6584','#10B981','#F59E0B','#0EA5E9','#111827'];
const FONT_COLORS = ['#1E1B4B','#FFFFFF','#FFD700','#FF6584','#0EA5E9','#10B981'];
function setupStatus() {
  const bgWrap = $('#bgSwatches');
  const fontWrap = $('#fontSwatches');
  bgWrap.innerHTML = BG_COLORS.map((c) =>
    `<button type="button" data-bg="${c}" class="h-6 w-6 rounded-full border border-slate-200" style="background:${c}"></button>`
  ).join('');
  fontWrap.innerHTML = FONT_COLORS.map((c) =>
    `<button type="button" data-fg="${c}" class="h-6 w-6 rounded-full border border-slate-200" style="background:${c}"></button>`
  ).join('');

  bgWrap.addEventListener('click', (e) => {
    const b = e.target.closest('[data-bg]'); if (!b) return;
    const c = b.dataset.bg;
    const canvas = $('#statusCanvas');
    canvas.style.background = c;
    // auto-flip text color if user hasn't picked one explicitly
    if (!canvas.dataset.userFg) {
      canvas.style.color = isLight(c) ? '#1E1B4B' : '#FFFFFF';
    }
  });
  fontWrap.addEventListener('click', (e) => {
    const b = e.target.closest('[data-fg]'); if (!b) return;
    const c = b.dataset.fg;
    const canvas = $('#statusCanvas');
    canvas.style.color = c;
    canvas.dataset.userFg = '1';
  });

  $('#statusImage').addEventListener('change', (e) => {
    const f = e.target.files[0]; if (!f) return;
    const r = new FileReader();
    r.onload = (ev) => { const img = $('#statusPreview'); img.src = ev.target.result; img.classList.remove('hidden'); };
    r.readAsDataURL(f);
  });

  $('#formStatus').addEventListener('submit', async (e) => {
    e.preventDefault();
    const body = $('#statusText').value.trim();
    if (!body) return toast('Write something first', 'bad');
    const fd = new FormData();
    fd.append('status_text', body);
    fd.append('bg_color',    $('#statusCanvas').style.background || '');
    fd.append('font_color',  $('#statusCanvas').style.color || '');
    const file = $('#statusImage').files[0]; if (file) fd.append('image', file);
    try {
      const { post } = await api(APP.urls.api.statusPost, { method: 'POST', body: fd });
      toast('Posted!', 'ok');
      closeModal('modalStatus');
      allPosts.unshift(post);
      if (!sessionLoadedIds.includes(post.id)) sessionLoadedIds.push(post.id);
      renderFeed();
      resetStatusModal();
    } catch (err) { toast(err.message, 'bad'); }
  });
}
function openStatusModal() { resetStatusModal(); openModal('modalStatus'); }
function resetStatusModal() {
  $('#formStatus').reset();
  const c = $('#statusCanvas');
  c.style.background = '#FFFFFF'; c.style.color = '#1E1B4B';
  delete c.dataset.userFg;
  $('#statusPreview').classList.add('hidden');
  $('#statusPreview').src = '';
}

// ─────────── Image modal ───────────
function setupImage() {
  const input = $('#imageFiles');
  input.addEventListener('change', () => {
    const wrap = $('#imagePreviews'); wrap.innerHTML = '';
    Array.from(input.files).slice(0, 10).forEach((f) => {
      const r = new FileReader();
      r.onload = (ev) => {
        wrap.insertAdjacentHTML('beforeend',
          `<div class="overflow-hidden rounded-lg border border-slate-200"><img src="${ev.target.result}" class="aspect-square w-full object-cover" alt=""></div>`);
      };
      r.readAsDataURL(f);
    });
  });
  $('#formImage').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cat = $('#imageCategory').value;
    if (!cat) return toast('Choose a category', 'bad');
    if (!input.files.length) return toast('Add at least one image', 'bad');
    const fd = new FormData();
    fd.append('description', $('#imageDesc').value.trim());
    fd.append('category_id', cat);
    fd.append('is_adult',    document.querySelector('input[name="imageAdult"]:checked').value);
    Array.from(input.files).slice(0, 10).forEach((f) => fd.append('images[]', f));
    try {
      const { post } = await api(APP.urls.api.imagePost, { method: 'POST', body: fd });
      toast('Posted!', 'ok');
      closeModal('modalImage');
      $('#formImage').reset(); $('#imagePreviews').innerHTML = '';
      allPosts.unshift(post);
      if (!sessionLoadedIds.includes(post.id)) sessionLoadedIds.push(post.id);
      renderFeed();
    } catch (err) { toast(err.message, 'bad'); }
  });
}

// ─────────── Video modal ───────────
// Parses a YouTube/Vimeo URL on the client so we can preview the thumbnail
// instantly. The server re-parses authoritatively at submit time.
function parseEmbedUrl(url) {
  const u = String(url || '').trim();
  if (!u) return null;
  let m;
  if ((m = u.match(/^https?:\/\/(?:www\.)?youtu\.be\/([A-Za-z0-9_-]{6,})/i))) {
    return { provider: 'youtube', id: m[1] };
  }
  if ((m = u.match(/^https?:\/\/(?:www\.|m\.)?youtube\.com\/(?:watch\?[^#]*\bv=|embed\/|shorts\/|v\/)([A-Za-z0-9_-]{6,})/i))) {
    return { provider: 'youtube', id: m[1] };
  }
  if ((m = u.match(/^https?:\/\/(?:www\.|player\.)?vimeo\.com\/(?:video\/)?(\d{5,})/i))) {
    return { provider: 'vimeo', id: m[1] };
  }
  return null;
}

function setupVideo() {
  const vf = $('#videoFile');
  const vt = $('#videoThumb');
  let videoSource = 'file'; // 'file' | 'embed'
  let embedPick = null;     // { provider, id } | null

  // Tab toggling
  $$('[data-video-tab]').forEach((btn) => {
    btn.addEventListener('click', () => {
      videoSource = btn.dataset.videoTab;
      $$('[data-video-tab]').forEach((b) => b.classList.toggle('is-active', b === btn));
      $$('[data-video-pane]').forEach((p) => p.classList.toggle('hidden', p.dataset.videoPane !== videoSource));
    });
  });

  vf.addEventListener('change', () => {
    const name = vf.files[0]?.name || 'MP4, WEBM, MOV';
    $('#videoFileLabel').innerHTML = `<span class="font-semibold text-brand-600">${esc(name)}</span>`;
  });
  vt.addEventListener('change', () => {
    const f = vt.files[0]; if (!f) return;
    const r = new FileReader();
    r.onload = (ev) => {
      $('#videoThumbPreview').src = ev.target.result;
      $('#videoThumbPreview').classList.remove('hidden');
      $('#videoThumbHint').classList.add('hidden');
    };
    r.readAsDataURL(f);
  });

  // Embed URL preview
  const embedInput = $('#videoEmbedUrl');
  const embedPreview = $('#videoEmbedPreview');
  const embedThumb = $('#videoEmbedThumb');
  const embedMeta = $('#videoEmbedMeta');
  const refreshEmbedPreview = () => {
    const parsed = parseEmbedUrl(embedInput.value);
    embedPick = parsed;
    if (!parsed) {
      embedPreview.classList.add('hidden');
      return;
    }
    if (parsed.provider === 'youtube') {
      embedThumb.src = `https://img.youtube.com/vi/${parsed.id}/hqdefault.jpg`;
      embedMeta.textContent = 'YouTube · ready to publish';
    } else {
      // Vimeo poster is fetched server-side. Show a neutral placeholder here.
      embedThumb.removeAttribute('src');
      embedMeta.textContent = 'Vimeo · thumbnail will be fetched on publish';
    }
    embedPreview.classList.remove('hidden');
  };
  embedInput.addEventListener('input', refreshEmbedPreview);
  embedInput.addEventListener('paste', () => setTimeout(refreshEmbedPreview, 0));
  $('#videoEmbedClear').addEventListener('click', () => {
    embedInput.value = '';
    embedPick = null;
    embedPreview.classList.add('hidden');
  });

  $('#formVideo').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cat = $('#videoCategory').value;
    if (!cat) return toast('Choose a category', 'bad');

    const fd = new FormData();
    fd.append('description', $('#videoDesc').value.trim());
    fd.append('category_id', cat);
    fd.append('is_adult',    document.querySelector('input[name="videoAdult"]:checked').value);
    fd.append('source',      videoSource);

    if (videoSource === 'embed') {
      const parsed = embedPick || parseEmbedUrl(embedInput.value);
      if (!parsed) return toast('Paste a valid YouTube or Vimeo URL', 'bad');
      fd.append('embed_url', embedInput.value.trim());
      // Thumbnail is optional for embeds — auto-fetched server-side.
      if (vt.files[0]) fd.append('thumbnail', vt.files[0]);
    } else {
      if (!vf.files[0]) return toast('Add a video file', 'bad');
      if (!vt.files[0]) return toast('Add a thumbnail', 'bad');
      fd.append('video',     vf.files[0]);
      fd.append('thumbnail', vt.files[0]);
    }

    try {
      const { post } = await api(APP.urls.api.videoPost, { method: 'POST', body: fd });
      toast('Posted!', 'ok');
      closeModal('modalVideo');
      $('#formVideo').reset();
      $('#videoThumbPreview').classList.add('hidden'); $('#videoThumbHint').classList.remove('hidden');
      embedPreview.classList.add('hidden');
      embedPick = null;
      // Reset to default tab
      $$('[data-video-tab]').forEach((b) => b.classList.toggle('is-active', b.dataset.videoTab === 'file'));
      $$('[data-video-pane]').forEach((p) => p.classList.toggle('hidden', p.dataset.videoPane !== 'file'));
      videoSource = 'file';
      allPosts.unshift(post);
      if (!sessionLoadedIds.includes(post.id)) sessionLoadedIds.push(post.id);
      renderFeed();
    } catch (err) { toast(err.message, 'bad'); }
  });
}

// ─────────── Post-detail modal ───────────
let currentPost = null;
let currentComments = [];
let currentMediaIndex = -1; // -1 → not an image-gallery post; comments/likes are post-level
let replyTarget = null; // { id, username } | null
let pendingImage = null; // File | null

function isGalleryPost(p) {
  return p && p.type === 'image' && Array.isArray(p.media) && p.media.length > 0;
}

function activeMediaId() {
  if (!isGalleryPost(currentPost) || currentMediaIndex < 0) return null;
  return currentPost.media[currentMediaIndex]?.id ?? null;
}

function commentsForActiveMedia() {
  const mid = activeMediaId();
  if (mid === null) {
    // Non-gallery posts: show every comment (post-level threads + any legacy ones).
    return currentComments;
  }
  // Gallery posts: only this image's thread. Legacy post-level comments (media_id === null)
  // are intentionally hidden so each image gets a clean, independent thread.
  return currentComments.filter((c) => Number(c.media_id) === Number(mid));
}

// ─────────── Saved posts modal (opens from left panel) ───────────
// Local UI state. The folder list itself is fetched via the shared cache in
// share-module.js (window.Tanbat.loadSaveFolders) so the post-card submenu
// and the modal sidebar always agree on what folders exist.
let savedPostsCache = null;       // [post, ...] for the currently selected folder
let savedFoldersCache = [];       // [{id,name,slug,count}, ...]
let savedUncategorizedCount = 0;
let savedTotalCount = 0;
let savedActiveFolder = 'all';    // 'all' | 'uncategorized' | numeric id (as string)
const savedHandlersForModal = {
  getPost: (id) => savedPostsCache?.find((p) => String(p.id) === String(id)),
  onRemove: (id) => {
    savedPostsCache = (savedPostsCache || []).filter((p) => String(p.id) !== String(id));
    renderSavedModal(savedPostsCache);
  },
  onChange: (id, patch) => {
    if (!savedPostsCache) return;
    const i = savedPostsCache.findIndex((p) => String(p.id) === String(id));
    if (i < 0) return;
    // Unsaving from inside the modal: drop the card too.
    if (patch && patch.saved === false) {
      savedPostsCache.splice(i, 1);
      renderSavedModal(savedPostsCache);
      return;
    }
    Object.assign(savedPostsCache[i], patch);
    if (patch && (patch.status_text != null || patch.description != null || patch.title != null)) {
      renderSavedModal(savedPostsCache);
    }
  },
};

function updateSavedCount(n) {
  const pill = document.getElementById('savedCount');
  if (!pill) return;
  if (!n) { pill.classList.add('hidden'); return; }
  pill.textContent = n > 99 ? '99+' : String(n);
  pill.classList.remove('hidden');
}

function renderSavedModal(posts) {
  const list = document.getElementById('savedList');
  if (!list) return;
  if (!posts || !posts.length) {
    list.innerHTML = `
      <div class="saved-empty">
        <div class="empty-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
        </div>
        <div class="empty-title">${savedActiveFolder === 'all' ? 'Nothing saved yet' : 'This folder is empty'}</div>
        <div>${savedActiveFolder === 'all'
          ? 'Hit the three-dot menu on any post and choose "Save post" to keep it here.'
          : 'Move a saved post into this folder using the bookmark menu on any post.'}</div>
      </div>`;
    return;
  }
  list.innerHTML = `<div class="feed-stack">${posts.map(cardHTML).join('')}</div>`;
  list.querySelectorAll('.gallery-wrap').forEach(bindGallery);
  hydrateAdSlots(list);
}

function renderSavedSidebar() {
  const ul = document.getElementById('savedFolders');
  if (!ul) return;
  const folders = savedFoldersCache || [];
  const fixed = `
    <li class="saved-folder ${savedActiveFolder === 'all' ? 'is-active' : ''}" data-folder="all">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
      <span class="sf-name">All saved</span>
      <span class="sf-count">${savedTotalCount}</span>
    </li>
    <li class="saved-folder ${savedActiveFolder === 'uncategorized' ? 'is-active' : ''}" data-folder="uncategorized">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      <span class="sf-name">Uncategorized</span>
      <span class="sf-count">${savedUncategorizedCount}</span>
    </li>
  `;
  const custom = folders.map((f) => `
    <li class="saved-folder ${String(savedActiveFolder) === String(f.id) ? 'is-active' : ''}" data-folder="${f.id}">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
      <span class="sf-name" title="${escHtml(f.name)}">${escHtml(f.name)}</span>
      <span class="sf-count">${f.count || 0}</span>
      <button type="button" class="sf-delete" data-folder-delete="${f.id}" aria-label="Delete folder">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
      </button>
    </li>
  `).join('');
  ul.innerHTML = fixed + custom;
}

function escHtml(s) {
  return String(s ?? '')
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

async function loadSavedFolders({ force = false } = {}) {
  if (!APP.user || !APP.urls?.api?.saveCategories) return;
  try {
    if (force) window.Tanbat?.invalidateSaveFolders?.();
    // Even though loadSaveFolders returns just the list, we still need the
    // totals — so make a direct call here. The shared helper's cache will be
    // populated as a side effect via window.Tanbat.loadSaveFolders next time.
    const res = await api(APP.urls.api.saveCategories);
    savedFoldersCache = res.data || [];
    savedTotalCount = res.total ?? 0;
    savedUncategorizedCount = res.uncategorized ?? 0;
    updateSavedCount(savedTotalCount);
    renderSavedSidebar();
  } catch (e) { /* leave previous state */ }
}

async function loadSavedPosts({ silent = false } = {}) {
  if (!APP.user || !APP.urls?.api?.postsSaved) return;
  const list = document.getElementById('savedList');
  if (!silent && list) list.innerHTML = '<div class="saved-loading">Loading saved posts…</div>';
  try {
    const url = `${APP.urls.api.postsSaved}?category=${encodeURIComponent(savedActiveFolder)}`;
    const res = await api(url);
    savedPostsCache = res.data || [];
    renderSavedModal(savedPostsCache);
  } catch (e) {
    if (list && !silent) {
      list.innerHTML = '<div class="saved-empty"><div class="empty-title">Could not load saved posts</div></div>';
    }
  }
}

async function reloadSavedView({ silent = false } = {}) {
  await Promise.all([
    loadSavedFolders(),
    loadSavedPosts({ silent }),
  ]);
}

async function createSavedFolderInline(name) {
  if (!name || !APP.urls?.api?.saveCategories) return;
  try {
    const res = await api(APP.urls.api.saveCategories, { method: 'POST', body: { name } });
    window.Tanbat?.invalidateSaveFolders?.();
    if (res?.category) {
      // Optimistically merge so the user sees their new folder right away.
      savedFoldersCache.push(res.category);
      savedFoldersCache.sort((a, b) => a.name.localeCompare(b.name));
      renderSavedSidebar();
    }
    toast('Folder created', 'ok');
  } catch (err) { toast(err.message || 'Could not create folder', 'bad'); }
}

async function deleteSavedFolder(folderId) {
  if (!APP.urls?.api?.saveCategory) return;
  const ok = await (window.Tanbat?.confirmDialog?.({
    title:   'Delete this folder?',
    message: 'Posts in this folder won\'t be deleted — they\'ll move to Uncategorized.',
    okLabel: 'Delete folder',
    danger:  true,
  }) ?? Promise.resolve(window.confirm('Delete folder? Saved posts will move to Uncategorized.')));
  if (!ok) return;
  try {
    const url = APP.urls.api.saveCategory.replace(':id', folderId);
    await api(url, { method: 'DELETE' });
    window.Tanbat?.invalidateSaveFolders?.();
    if (String(savedActiveFolder) === String(folderId)) savedActiveFolder = 'all';
    toast('Folder deleted', 'ok');
    await reloadSavedView();
  } catch (err) { toast(err.message || 'Could not delete folder', 'bad'); }
}

function bindSavedSidebar() {
  const ul = document.getElementById('savedFolders');
  if (!ul) return;
  ul.addEventListener('click', (e) => {
    const del = e.target.closest('[data-folder-delete]');
    if (del) {
      e.preventDefault();
      e.stopPropagation();
      return deleteSavedFolder(del.dataset.folderDelete);
    }
    const folder = e.target.closest('[data-folder]');
    if (!folder) return;
    const next = folder.dataset.folder;
    if (savedActiveFolder === next) return;
    savedActiveFolder = next;
    renderSavedSidebar();
    loadSavedPosts();
  });

  const form = document.getElementById('savedNewFolder');
  form?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const input = form.querySelector('input');
    const name = (input?.value || '').trim();
    if (!name) return;
    input.value = '';
    await createSavedFolderInline(name);
  });
}

function bindSavedPosts() {
  const modal = document.getElementById('modalSaved');
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-action="open-saved-posts"]');
    if (!btn) return;
    e.preventDefault();
    if (!APP.user) return toast('Sign in to view saved posts', 'bad');
    openModal('modalSaved');
    reloadSavedView();
  });
  if (modal && window.Tanbat?.bindPostMenu) {
    window.Tanbat.bindPostMenu(modal, savedHandlersForModal);
  }
  bindSavedSidebar();
  // Keep counts + the visible folder fresh when the user saves/unsaves/moves
  // from the main feed or from inside the modal.
  document.addEventListener('tanbat:saved-changed', () => {
    if (!APP.user) return;
    reloadSavedView({ silent: true });
  });
  if (APP.user) loadSavedFolders();
}

function bindFeedPostMenu() {
  const feed = $('#feed');
  if (!feed || !window.Tanbat?.bindPostMenu) return;
  window.Tanbat.bindPostMenu(feed, {
    getPost: (id) => allPosts.find((p) => String(p.id) === String(id)),
    onRemove: (id) => {
      allPosts = allPosts.filter((p) => String(p.id) !== String(id));
      // Don't strip from sessionLoadedIds — we don't want hidden posts to
      // immediately be eligible for re-fetch in this same session.
      if (!allPosts.length) showEmptyState();
    },
    onChange: (id, patch) => {
      const local = allPosts.find((p) => String(p.id) === String(id));
      if (local) Object.assign(local, patch);
      // If text fields changed (edit), re-render that single card in place.
      if (patch && (patch.status_text != null || patch.description != null || patch.title != null)) {
        const card = feed.querySelector(`[data-post-id="${id}"]`);
        if (card && local) {
          const next = document.createElement('div');
          next.innerHTML = cardHTML(local).trim();
          const newCard = next.firstChild;
          if (newCard) card.replaceWith(newCard);
        }
      }
    },
  });
}

function bindFeedClicks() {
  bindReactions($('#feed'), handleReact);
  $('#feed').addEventListener('click', async (e) => {
    // Gallery navigation must not bubble up to the media-open handler
    const nav = e.target.closest('[data-gallery-nav]');
    if (nav) {
      e.preventDefault(); e.stopPropagation();
      const track = nav.parentElement.querySelector('[data-gallery]');
      if (track) {
        const dir = parseInt(nav.dataset.galleryNav, 10);
        track.scrollBy({ left: dir * track.clientWidth, behavior: 'smooth' });
      }
      return;
    }
    const actBtn = e.target.closest('[data-act]');
    if (actBtn) {
      const card = actBtn.closest('[data-post-id]');
      const id = card?.dataset.postId;
      if (!id) return;
      const act = actBtn.dataset.act;
      // Main button: tapping toggles — re-sending the current reaction clears it,
      // sending 'like' when none is set adds the default reaction.
      if (act === 'like')    return handleReact(card, actBtn.dataset.reaction || 'like');
      if (act === 'comment') {
        // Book posts have their own page; the shared modal can't render them.
        if (card.classList.contains('book-card')) {
          const slug = card.dataset.bookSlug;
          if (slug) { location.href = `${APP.urls.books || '/books'}/${slug}`; return; }
        }
        return openPostModal(id);
      }
      if (act === 'share')   return handleShare(id, card);
      return;
    }
    // Click on media / status canvas → open detail modal
    const opener = e.target.closest('[data-open]');
    if (!opener) return;
    const card = opener.closest('[data-post-id]');
    const id = card?.dataset.postId;
    if (id) openPostModal(id);
  });
}

async function handleReact(card, reactionKey) {
  if (!card) return;
  if (!APP.user) { toast('Sign in to react to posts', 'bad'); return; }
  const id = card.dataset.postId;
  const btn = card.querySelector('.btn-like');
  if (!id || !btn) return;
  dismissReactionPanel(card.querySelector('.reaction-wrap'));
  try {
    const res = await api(`${APP.urls.api.posts}/${id}/like`, {
      method: 'POST',
      body: { reaction: reactionKey },
    });
    applyReactionUI(card, btn, res.reaction);
    setReactionCount(card, res.likes_count);
    renderReactionSummary(card, res.top_reactions);
    const local = allPosts.find((p) => String(p.id) === String(id));
    if (local) {
      local.liked = res.reacted;
      local.my_reaction = res.reaction;
      local.likes_count = res.likes_count;
      local.top_reactions = res.top_reactions;
    }
  } catch (err) {
    toast(err.message || 'Could not update reaction', 'bad');
  }
}

function syncCardViews(postId, viewsCount) {
  const n = Number(viewsCount || 0);
  const local = allPosts.find((p) => String(p.id) === String(postId));
  if (local) local.views_count = n;
  const card = document.querySelector(`[data-post-id="${postId}"]`);
  if (!card) return;
  const badge = card.querySelector('[data-card-views]');
  const countEl = card.querySelector('[data-views-count]');
  if (countEl) {
    const prev = parseInt((countEl.textContent || '0').replace(/[^\d]/g, ''), 10) || 0;
    countEl.textContent = n.toLocaleString();
    if (badge && n > prev) {
      badge.classList.remove('is-pulse');
      // Force reflow so the animation can replay on repeated opens
      void badge.offsetWidth;
      badge.classList.add('is-pulse');
    }
  }
  if (badge) badge.setAttribute('title', `${n.toLocaleString()} views`);
}

function handleShare(id, card) {
  const post = allPosts.find((p) => String(p.id) === String(id));
  // Every post type now has a canonical permalink (articles/books at their own
  // pages; status/image/video at /posts/{type}/{id}). Fall back to the feed
  // hash only if the shape somehow lacks a view_url.
  const url = post?.view_url || `${APP.urls.home}#post-${id}`;
  const title = post?.title
    || post?.status_text
    || post?.description
    || (post?.user?.name ? `${post.user.name} on Tanbat` : 'A Tanbat post');
  const image = post?.featured_image || post?.thumbnail || post?.media?.[0]?.url || '';
  window.Tanbat?.openShare?.({
    kind: 'post',
    postId: id,
    url,
    title,
    image,
  });
}

function openPostFromHash() {
  const m = location.hash.match(/^#post-(\d+)$/);
  if (!m) return;
  // Book posts open in their own page, not the shared modal.
  const local = allPosts.find((p) => String(p.id) === String(m[1]));
  if (local?.type === 'book' && local?.view_url) {
    location.replace(local.view_url);
    return;
  }
  openPostModal(m[1]);
}

async function openPostModal(id) {
  try {
    recordClick(id);
    const { post, comments } = await api(`${APP.urls.api.posts}/${id}`);
    currentPost = post;
    currentComments = Array.isArray(comments) ? comments : [];
    currentMediaIndex = isGalleryPost(post) ? 0 : -1;

    // LEFT — media
    const stage = $('#postLeftStage');
    // Tear down any prior Plyr instance before re-mounting (post switching).
    if (currentPlyr) { try { currentPlyr.destroy(); } catch (_) {} currentPlyr = null; }
    if (post.type === 'video') {
      currentPlyr = mountVideoStage(stage, post);
    } else if (post.type === 'image' && post.media?.length) {
      stage.innerHTML = buildGalleryHTML(post.media);
      bindGallerySlider(stage);
    } else if (post.type === 'status') {
      if (post.user?.username === 'robert_sheffield') {
        // News card on a white panel so it's legible over the black stage.
        const img = post.media?.[0]?.url ? `<img src="${esc(post.media[0].url)}" class="w-full rounded-xl" alt="">` : '';
        const desc = post.description ? `<p class="news-desc">${esc(post.description)}</p>` : '';
        stage.innerHTML = `
          <div class="h-full w-full overflow-y-auto bg-white">
            <div class="mx-auto max-w-xl p-5">
              ${img}
              <div class="news-topic">${esc(post.status_text || '')}</div>
              ${desc}
              ${newsbotCtaHTML()}
            </div>
          </div>`;
      } else {
        const bg = post.bg_color || '#1E1B4B';
        const fg = post.font_color || (isLight(bg) ? '#1E1B4B' : '#FFFFFF');
        const img = post.media?.[0]?.url ? `<img src="${esc(post.media[0].url)}" class="mt-4 max-h-72 rounded-xl" alt="">` : '';
        stage.innerHTML = `
          <div class="grid h-full w-full place-items-center p-10 text-center" style="background:${esc(bg)};color:${esc(fg)}">
            <div>
              <div class="text-2xl font-semibold leading-snug">${esc(post.status_text || '')}</div>
              ${img}
            </div>
          </div>`;
      }
    }

    // RIGHT — meta + comments
    const u = post.user || {};
    const uHref = u.username ? `${APP.urls.profileBase}/${encodeURIComponent(u.username)}` : null;
    const uidAttr = u.id ? ` data-user-id="${u.id}" data-user-link` : '';
    const avInner = u.profile_picture
      ? `<img src="${esc(u.profile_picture)}" class="h-10 w-10 rounded-full object-cover" alt="">`
      : `<span class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white">${esc((u.username||'U').charAt(0).toUpperCase())}</span>`;
    const av = uHref ? `<a href="${uHref}"${uidAttr} class="shrink-0">${avInner}</a>` : avInner;
    const nameInner = `<div class="text-sm font-semibold text-slate-900 hover:text-brand-600">${esc(u.name || u.username)}</div><div class="text-xs text-slate-500">&#64;${esc(u.username || '')}</div>`;
    const nameBlock = uHref ? `<a href="${uHref}"${uidAttr} class="min-w-0">${nameInner}</a>` : `<div class="min-w-0">${nameInner}</div>`;
    $('#postAuthor').innerHTML = `${av}${nameBlock}`;

    $('#postDescription').innerHTML = post.description
      ? esc(post.description)
      : (post.status_text ? esc(post.status_text) : '<span class="text-slate-400">No description.</span>');

    const viewsEl = document.querySelector('[data-modal-views]');
    if (viewsEl) viewsEl.textContent = Number(post.views_count || 0).toLocaleString();
    syncCardViews(post.id, post.views_count);
    $('#postCreatedAt').textContent = formatPostTime(post.created_at_iso);
    $('#postLeftMeta').innerHTML = post.category
      ? `<span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-3 py-1 text-xs">${esc(post.category.name)}</span>`
      : '';

    // Composer avatar reflects the signed-in user
    const meAv = $('#commentAvatar');
    if (meAv) {
      if (APP.user?.profile_picture) {
        meAv.innerHTML = `<img src="${esc(APP.user.profile_picture)}" alt="" class="h-8 w-8 rounded-full object-cover">`;
      } else {
        meAv.textContent = ((APP.user?.username || APP.user?.name || 'U').charAt(0).toUpperCase());
      }
    }

    resetComposer();
    // Engagement bar + thread reflect the active image (or the whole post for non-gallery).
    refreshActiveMediaUI();
    openModal('modalPost');
  } catch (err) {
    toast(err.message, 'bad');
  }
}

function buildGalleryHTML(media) {
  const slides = media.map((m, i) => `
    <div class="flex h-full w-full shrink-0 snap-center items-center justify-center" data-slide="${i}">
      <img src="${esc(m.url)}" class="max-h-full max-w-full object-contain" alt="" draggable="false">
    </div>
  `).join('');

  const arrows = media.length > 1 ? `
    <button type="button" data-gallery-arrow="-1"
            class="gallery-arrow absolute left-2 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white opacity-90 transition hover:bg-black/70 hover:opacity-100 disabled:cursor-not-allowed disabled:opacity-30"
            aria-label="Previous image">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </button>
    <button type="button" data-gallery-arrow="1"
            class="gallery-arrow absolute right-2 top-1/2 z-10 grid h-10 w-10 -translate-y-1/2 place-items-center rounded-full bg-black/45 text-white opacity-90 transition hover:bg-black/70 hover:opacity-100 disabled:cursor-not-allowed disabled:opacity-30"
            aria-label="Next image">
      <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
    </button>
    <div class="gallery-counter pointer-events-none absolute right-3 top-3 z-10 rounded-full bg-black/45 px-2.5 py-1 text-[11px] font-semibold text-white">
      <span data-gallery-pos>1</span> / <span>${media.length}</span>
    </div>
    <div class="gallery-dots absolute bottom-3 left-1/2 z-10 flex -translate-x-1/2 items-center gap-1.5">
      ${media.map((_, i) => `<button type="button" data-gallery-dot="${i}" class="h-1.5 w-1.5 rounded-full bg-white/40 transition hover:bg-white/70" aria-label="Image ${i + 1}"></button>`).join('')}
    </div>
  ` : '';

  return `
    <div class="gallery-stage relative h-full w-full" tabindex="0">
      <div data-gallery-track
           class="gallery-track flex h-full w-full snap-x snap-mandatory overflow-x-auto scrollbar-none cursor-grab select-none">
        ${slides}
      </div>
      ${arrows}
    </div>
  `;
}

function bindGallerySlider(stage) {
  const root  = stage.querySelector('.gallery-stage');
  const track = stage.querySelector('[data-gallery-track]');
  if (!root || !track) return;

  const slides = Array.from(track.children);
  const total  = slides.length;
  if (!total) return;

  const goTo = (idx, smooth = true) => {
    const clamped = Math.max(0, Math.min(total - 1, idx));
    track.scrollTo({ left: clamped * track.clientWidth, behavior: smooth ? 'smooth' : 'auto' });
  };

  // Arrows
  root.querySelectorAll('[data-gallery-arrow]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const dir = parseInt(btn.dataset.galleryArrow, 10);
      goTo(currentMediaIndex + dir);
    });
  });

  // Dots
  root.querySelectorAll('[data-gallery-dot]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      goTo(parseInt(btn.dataset.galleryDot, 10));
    });
  });

  // Keyboard
  root.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowLeft')  { e.preventDefault(); goTo(currentMediaIndex - 1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); goTo(currentMediaIndex + 1); }
  });

  // Mouse drag (touch and trackpad swipe already work via overflow-x-auto)
  let dragging = false, startX = 0, startLeft = 0;
  track.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'touch') return; // let native scrolling handle touch
    dragging = true;
    startX = e.clientX;
    startLeft = track.scrollLeft;
    track.classList.add('cursor-grabbing');
    track.setPointerCapture(e.pointerId);
  });
  track.addEventListener('pointermove', (e) => {
    if (!dragging) return;
    track.scrollLeft = startLeft - (e.clientX - startX);
  });
  const endDrag = (e) => {
    if (!dragging) return;
    dragging = false;
    track.classList.remove('cursor-grabbing');
    try { track.releasePointerCapture(e.pointerId); } catch (_) {}
    // Snap to nearest slide
    const idx = Math.round(track.scrollLeft / track.clientWidth);
    goTo(idx);
  };
  track.addEventListener('pointerup', endDrag);
  track.addEventListener('pointercancel', endDrag);
  // Prevent click-through from drag
  track.addEventListener('click', (e) => {
    if (Math.abs(track.scrollLeft - startLeft) > 6) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  // Track scroll → update active index + per-image UI
  let scrollTimer = null;
  track.addEventListener('scroll', () => {
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
      const idx = Math.round(track.scrollLeft / track.clientWidth);
      if (idx !== currentMediaIndex && idx >= 0 && idx < total) {
        currentMediaIndex = idx;
        refreshActiveMediaUI();
      }
    }, 80);
  });

  // Initialise position + UI
  goTo(currentMediaIndex < 0 ? 0 : currentMediaIndex, false);
  // Defer focus so it doesn't fight the modal opening animation.
  setTimeout(() => root.focus({ preventScroll: true }), 50);
}

function refreshActiveMediaUI() {
  if (!currentPost) return;
  const post = currentPost;
  const isGallery = isGalleryPost(post);

  // Gallery chrome — counter + dot active state
  if (isGallery) {
    const pos = document.querySelector('[data-gallery-pos]');
    if (pos) pos.textContent = String(currentMediaIndex + 1);
    document.querySelectorAll('[data-gallery-dot]').forEach((d) => {
      const active = Number(d.dataset.galleryDot) === currentMediaIndex;
      d.classList.toggle('bg-white', active);
      d.classList.toggle('w-4', active);
      d.classList.toggle('bg-white/40', !active);
    });
    const prev = document.querySelector('[data-gallery-arrow="-1"]');
    const next = document.querySelector('[data-gallery-arrow="1"]');
    if (prev) prev.disabled = currentMediaIndex <= 0;
    if (next) next.disabled = currentMediaIndex >= post.media.length - 1;
  }

  // Engagement bar — reflect the active scope (per-image or post-level)
  let likes = post.likes_count, reaction = post.my_reaction || null, commentsCount = post.comments_count;
  if (isGallery) {
    const m = post.media[currentMediaIndex];
    likes = m?.likes_count ?? 0;
    reaction = m?.my_reaction || null;
    commentsCount = m?.comments_count ?? 0;
  }
  $('#postLikes').textContent = Number(likes || 0).toLocaleString();
  const cc = $('#postCommentsCount');
  if (cc) cc.textContent = Number(commentsCount || 0).toLocaleString();
  syncModalReactionUI(reaction);

  // Comment thread + composer reset (cancel any in-flight reply that belonged to the previous image)
  clearReplyTarget();
  renderComments(commentsForActiveMedia());
}

function profileHref(user) {
  return user?.username ? `${APP.urls.profileBase}/${encodeURIComponent(user.username)}` : null;
}

function userLinkAttrs(user) {
  return user?.id ? ` data-user-id="${user.id}" data-user-link` : '';
}

function avatarChip(user, size = 'md') {
  const dims = size === 'sm' ? 'h-7 w-7 text-[10px]' : 'h-9 w-9 text-xs';
  const href = profileHref(user);
  const inner = user?.profile_picture
    ? `<img src="${esc(user.profile_picture)}" class="${dims} shrink-0 rounded-full object-cover" alt="">`
    : `<span class="${dims} shrink-0 grid place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 font-bold text-white">${esc((user?.username || user?.name || 'U').charAt(0).toUpperCase())}</span>`;
  return href ? `<a href="${href}"${userLinkAttrs(user)} class="shrink-0">${inner}</a>` : inner;
}

function commentBubbleHTML(c, isReply = false) {
  const av = avatarChip(c.user, isReply ? 'sm' : 'md');
  const name = esc(c.user?.name || c.user?.username || 'User');
  const handle = c.user?.username ? `@${esc(c.user.username)}` : '';
  const body = c.body ? `<div class="mt-0.5 whitespace-pre-wrap break-words text-sm text-slate-700">${esc(c.body)}</div>` : '';
  const img = c.image_url
    ? `<a href="${esc(c.image_url)}" target="_blank" rel="noopener" class="mt-2 block overflow-hidden rounded-xl border border-slate-200">
         <img src="${esc(c.image_url)}" alt="" class="max-h-72 w-auto object-cover">
       </a>` : '';
  const canDelete = APP.user && c.user?.id === APP.user.id;
  const deleteBtn = canDelete
    ? `<button type="button" data-comment-act="delete" data-comment-id="${c.id}" class="text-slate-400 transition hover:text-rose-600">Delete</button>`
    : '';
  const replyBtn = APP.user
    ? `<button type="button" data-comment-act="reply" data-comment-id="${c.id}" data-comment-user="${esc(c.user?.username || '')}" class="font-semibold text-slate-500 transition hover:text-brand-600">Reply</button>`
    : '';

  const href = profileHref(c.user);
  const uAttrs = userLinkAttrs(c.user);
  const nameEl = href
    ? `<a href="${href}"${uAttrs} class="text-xs font-semibold text-slate-900 hover:text-brand-600 hover:underline">${name}</a>`
    : `<span class="text-xs font-semibold text-slate-900">${name}</span>`;
  const handleEl = handle
    ? (href
        ? `<a href="${href}"${uAttrs} class="text-[10px] text-slate-400 hover:text-brand-600">${handle}</a>`
        : `<span class="text-[10px] text-slate-400">${handle}</span>`)
    : '';

  return `
    <div class="flex gap-2" data-comment-id="${c.id}">
      ${av}
      <div class="min-w-0 flex-1">
        <div class="rounded-2xl bg-white px-3 py-2 shadow-sm ring-1 ring-slate-100">
          <div class="flex items-baseline gap-1.5">
            ${nameEl}
            ${handleEl}
          </div>
          ${body}
          ${img}
        </div>
        <div class="mt-1 flex items-center gap-3 px-2 text-[11px]">
          <span class="text-slate-400">${esc(c.created_at || '')}</span>
          ${replyBtn}
          ${deleteBtn}
        </div>
      </div>
    </div>
  `;
}

function repliesBlockHTML(parentId, replies) {
  if (!replies?.length) {
    return `<div class="mt-2 ml-9" data-replies-of="${parentId}"></div>`;
  }
  const list = replies.map((r) => commentBubbleHTML(r, true)).join('');
  const toggle = replies.length > 2
    ? `<button type="button" data-replies-toggle="${parentId}" class="mb-2 inline-flex items-center gap-1 text-[11px] font-semibold text-slate-500 transition hover:text-brand-600">
         <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
         <span>View ${replies.length} replies</span>
       </button>`
    : '';
  const hiddenAttr = replies.length > 2 ? ' hidden' : '';
  return `
    <div class="ml-9 mt-2">
      ${toggle}
      <div class="space-y-3${hiddenAttr ? ' hidden' : ''}" data-replies-of="${parentId}"${hiddenAttr}>
        ${list}
      </div>
    </div>
  `;
}

function renderComments(comments) {
  const wrap = $('#postComments');
  if (!comments.length) {
    wrap.innerHTML = `
      <div data-comments-empty class="grid place-items-center py-10 text-center text-slate-400">
        <svg class="mb-2 h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <div class="text-sm font-semibold text-slate-500">No comments yet</div>
        <div class="text-xs">Be the first to share your thoughts.</div>
      </div>`;
    return;
  }
  wrap.innerHTML = comments.map((c) => `
    <div class="comment-thread" data-thread-id="${c.id}">
      ${commentBubbleHTML(c, false)}
      ${repliesBlockHTML(c.id, c.replies || [])}
    </div>
  `).join('');
}

function setCommentsCount(n) {
  const el = $('#postCommentsCount');
  if (el) el.textContent = Number(n || 0).toLocaleString();
}

function resetComposer() {
  const body = $('#commentBody');
  if (body) {
    body.value = '';
    body.style.height = 'auto';
  }
  clearReplyTarget();
  clearPendingImage();
  $('#commentEmojiPanel')?.classList.add('hidden');
  $('#commentEmojiBtn')?.setAttribute('aria-expanded', 'false');
}

function clearPendingImage() {
  pendingImage = null;
  const inp = $('#commentImage');
  if (inp) inp.value = '';
  $('#commentImagePreview')?.classList.add('hidden');
  const thumb = $('#commentImageThumb');
  if (thumb) thumb.src = '';
}

function setReplyTarget(id, username) {
  replyTarget = { id, username: username || 'user' };
  const bar = $('#replyBar');
  if (!bar) return;
  bar.classList.remove('hidden');
  bar.classList.add('flex');
  $('#replyTarget').textContent = '@' + replyTarget.username;
  const body = $('#commentBody');
  if (body) {
    body.focus();
    body.placeholder = `Reply to @${replyTarget.username}…`;
  }
}

function clearReplyTarget() {
  replyTarget = null;
  const bar = $('#replyBar');
  if (bar) { bar.classList.add('hidden'); bar.classList.remove('flex'); }
  const body = $('#commentBody');
  if (body) body.placeholder = 'Write a comment…';
}

function syncModalReactionUI(reactionKey) {
  const btn = document.getElementById('modalLikeBtn');
  if (!btn) return;
  const r = reactionKey ? REACTION_MAP[reactionKey] : null;
  btn.dataset.reaction = r ? r.key : '';
  btn.setAttribute('aria-pressed', r ? 'true' : 'false');
  btn.classList.toggle('is-reacted', !!r);
  const em = btn.querySelector('.re-emoji');
  if (em) em.textContent = r ? r.emoji : '';
  const label = document.getElementById('modalReactLabel');
  if (label) { label.textContent = r ? r.label : 'Like'; label.style.color = r ? r.color : ''; }
  btn.classList.toggle('text-slate-600', !r);
}

async function performModalReaction(reactionKey) {
  if (!currentPost) return;
  if (!APP.user) return toast('Sign in to react to posts', 'bad');
  const mediaId = activeMediaId();
  const likesEl = $('#postLikes');
  try {
    const res = await api(`${APP.urls.api.posts}/${currentPost.id}/like`, {
      method: 'POST',
      body: mediaId !== null ? { media_id: mediaId, reaction: reactionKey } : { reaction: reactionKey },
    });
    const scopedCount = mediaId !== null && res.media_likes_count != null ? res.media_likes_count : res.likes_count;
    syncModalReactionUI(res.reaction);
    if (likesEl) likesEl.textContent = Number(scopedCount).toLocaleString();

    if (mediaId !== null && isGalleryPost(currentPost)) {
      const m = currentPost.media[currentMediaIndex];
      if (m) {
        m.liked = res.reacted;
        m.my_reaction = res.reaction;
        m.likes_count = res.media_likes_count ?? m.likes_count;
        m.top_reactions = res.top_reactions;
      }
    } else {
      currentPost.liked = res.reacted;
      currentPost.my_reaction = res.reaction;
    }
    currentPost.likes_count = res.likes_count;

    // Reflect into the feed card if it's mounted. A media-scoped reaction only
    // touches the post total there; the card's button/stack stay post-level.
    const card = document.querySelector(`.post-card[data-post-id="${currentPost.id}"]`);
    if (card) {
      setReactionCount(card, res.likes_count);
      if (mediaId === null) {
        applyReactionUI(card, card.querySelector('.btn-like'), res.reaction);
        renderReactionSummary(card, res.top_reactions);
      }
    }
    const local = allPosts.find((p) => String(p.id) === String(currentPost.id));
    if (local) {
      local.likes_count = res.likes_count;
      if (mediaId === null) { local.liked = res.reacted; local.my_reaction = res.reaction; local.top_reactions = res.top_reactions; }
    }
  } catch (err) {
    toast(err.message || 'Could not update reaction', 'bad');
  }
}

function bindModalActions() {
  document.addEventListener('click', async (e) => {
    // Reaction picker emoji inside the modal engagement bar.
    const reactBtn = e.target.closest('[data-modal-react]');
    if (reactBtn && currentPost) {
      e.preventDefault();
      dismissReactionPanel(document.getElementById('modalReactionWrap'));
      return performModalReaction(reactBtn.dataset.modalReact);
    }
    const b = e.target.closest('[data-modal-act]');
    if (!b || !currentPost) return;
    const act = b.dataset.modalAct;
    if (act === 'share') {
      const url = `${APP.urls.home}#post-${currentPost.id}`;
      if (navigator.share) { navigator.share({ title: 'Tanbat post', url }).catch(() => {}); return; }
      if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => toast('Link copied', 'ok'), () => toast('Could not copy', 'bad'));
      }
      return;
    }
    if (act === 'like') {
      // Tap toggles: re-send current reaction to clear it, else apply 'like'.
      return performModalReaction(b.dataset.reaction || 'like');
    }
  });
}

function bindCommentForm() {
  const form = $('#commentForm');
  const body = $('#commentBody');
  const submit = $('#commentSubmit');

  // Auto-resize textarea
  body.addEventListener('input', () => {
    body.style.height = 'auto';
    body.style.height = Math.min(body.scrollHeight, 128) + 'px';
  });
  // Enter to send, Shift+Enter for newline
  body.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.requestSubmit();
    }
  });

  // Image attach
  $('#commentImage').addEventListener('change', (e) => {
    const f = e.target.files?.[0];
    if (!f) return;
    pendingImage = f;
    const r = new FileReader();
    r.onload = (ev) => {
      $('#commentImageThumb').src = ev.target.result;
      $('#commentImagePreview').classList.remove('hidden');
    };
    r.readAsDataURL(f);
  });
  $('#commentImageClear').addEventListener('click', clearPendingImage);

  // Emoji picker open/close
  const emojiBtn = $('#commentEmojiBtn');
  const emojiPanel = $('#commentEmojiPanel');
  emojiBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = emojiPanel.classList.toggle('hidden') === false;
    emojiBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
  emojiPanel.addEventListener('click', (e) => {
    const b = e.target.closest('[data-emoji]');
    if (!b) return;
    const emoji = b.dataset.emoji;
    const start = body.selectionStart ?? body.value.length;
    const end = body.selectionEnd ?? body.value.length;
    body.value = body.value.slice(0, start) + emoji + body.value.slice(end);
    const caret = start + emoji.length;
    body.focus();
    body.setSelectionRange(caret, caret);
    body.dispatchEvent(new Event('input'));
  });
  document.addEventListener('click', (e) => {
    if (emojiPanel.classList.contains('hidden')) return;
    if (!emojiPanel.contains(e.target) && !emojiBtn.contains(e.target)) {
      emojiPanel.classList.add('hidden');
      emojiBtn.setAttribute('aria-expanded', 'false');
    }
  });

  // Reply cancel
  $('#replyCancel').addEventListener('click', clearReplyTarget);

  // Click delegation inside the comments column
  $('#postComments').addEventListener('click', async (e) => {
    const toggleBtn = e.target.closest('[data-replies-toggle]');
    if (toggleBtn) {
      const id = toggleBtn.dataset.repliesToggle;
      const list = document.querySelector(`[data-replies-of="${id}"]`);
      if (list) {
        const hidden = list.hasAttribute('hidden');
        if (hidden) { list.removeAttribute('hidden'); list.classList.remove('hidden'); }
        else { list.setAttribute('hidden', ''); list.classList.add('hidden'); }
        const lbl = toggleBtn.querySelector('span');
        if (lbl) {
          const n = list.children.length;
          lbl.textContent = hidden ? `Hide ${n} repl${n === 1 ? 'y' : 'ies'}` : `View ${n} repl${n === 1 ? 'y' : 'ies'}`;
        }
      }
      return;
    }
    const actBtn = e.target.closest('[data-comment-act]');
    if (!actBtn) return;
    const act = actBtn.dataset.commentAct;
    const id = actBtn.dataset.commentId;
    if (act === 'reply') {
      if (!APP.user) { toast('Sign in to reply', 'bad'); return; }
      setReplyTarget(id, actBtn.dataset.commentUser);
    } else if (act === 'delete') {
      if (!confirm('Delete this comment?')) return;
      try {
        const { deleted, comments_count } = await api(`${APP.urls.api.commentDelete.replace(':id', id)}`, { method: 'DELETE' });

        // Pull the deleted comment (+ its replies) out of the local cache so per-image counts stay accurate.
        const numericId = Number(id);
        let deletedMediaId = null;
        let removedCount = 0;
        const rootIdx = currentComments.findIndex((c) => Number(c.id) === numericId);
        if (rootIdx !== -1) {
          deletedMediaId = currentComments[rootIdx].media_id;
          removedCount = 1 + (currentComments[rootIdx].replies?.length || 0);
          currentComments.splice(rootIdx, 1);
        } else {
          for (const root of currentComments) {
            const rIdx = root.replies?.findIndex((r) => Number(r.id) === numericId);
            if (rIdx != null && rIdx !== -1) {
              deletedMediaId = root.replies[rIdx].media_id;
              removedCount = 1;
              root.replies.splice(rIdx, 1);
              break;
            }
          }
        }

        // Remove from DOM
        const thread = document.querySelector(`[data-thread-id="${id}"]`);
        if (thread) {
          thread.remove();
        } else {
          actBtn.closest('[data-comment-id]')?.remove();
        }

        // Update per-image cached count and the visible engagement-bar count
        if (deletedMediaId != null && isGalleryPost(currentPost)) {
          const m = currentPost.media.find((x) => Number(x.id) === Number(deletedMediaId));
          if (m) m.comments_count = Math.max(0, (m.comments_count || 0) - removedCount);
          const activeM = currentPost.media[currentMediaIndex];
          setCommentsCount(activeM?.comments_count ?? 0);
        } else {
          setCommentsCount(comments_count);
        }

        currentPost.comments_count = comments_count;
        const found = allPosts.find((p) => String(p.id) === String(currentPost.id));
        if (found) found.comments_count = comments_count;
        if (!$('#postComments').children.length) renderComments([]);
        toast(deleted > 1 ? `Removed comment and ${deleted - 1} replies` : 'Comment deleted', 'ok');
      } catch (err) { toast(err.message || 'Could not delete', 'bad'); }
    }
  });

  // Submit
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!currentPost) return;
    if (!APP.user) { toast('Sign in to comment', 'bad'); return; }
    const text = body.value.trim();
    if (!text && !pendingImage) { toast('Write something or attach an image', 'bad'); return; }

    const fd = new FormData();
    if (text) fd.append('body', text);
    if (pendingImage) fd.append('image', pendingImage);
    if (replyTarget) fd.append('parent_id', String(replyTarget.id));
    const mediaId = activeMediaId();
    if (mediaId !== null) fd.append('media_id', String(mediaId));

    submit.disabled = true;
    try {
      const { comment, comments_count, media_id: respMediaId, media_comments_count } = await api(
        `${APP.urls.api.posts}/${currentPost.id}/comments`,
        { method: 'POST', body: fd }
      );

      // Cache the new comment so slide switches stay consistent.
      if (comment.parent_id) {
        const root = currentComments.find((c) => Number(c.id) === Number(comment.parent_id));
        if (root) {
          root.replies = root.replies || [];
          root.replies.push(comment);
        }
      } else {
        currentComments.unshift({ ...comment, replies: [] });
      }

      const wrap = $('#postComments');
      // Clear the empty-state placeholder if present
      wrap.querySelector('[data-comments-empty]')?.remove();

      let scrollTarget = null;
      if (comment.parent_id) {
        // Append to the right replies block
        const parentId = comment.parent_id;
        let block = document.querySelector(`[data-replies-of="${parentId}"]`);
        const thread = document.querySelector(`[data-thread-id="${parentId}"]`);
        if (!block && thread) {
          thread.insertAdjacentHTML('beforeend', repliesBlockHTML(parentId, []));
          block = document.querySelector(`[data-replies-of="${parentId}"]`);
        }
        if (block) {
          block.classList.remove('hidden');
          block.removeAttribute('hidden');
          block.insertAdjacentHTML('beforeend', commentBubbleHTML(comment, true));
          scrollTarget = block.lastElementChild;
        }
      } else {
        // New top-level comment goes to the top
        wrap.insertAdjacentHTML('afterbegin', `
          <div class="comment-thread" data-thread-id="${comment.id}">
            ${commentBubbleHTML(comment, false)}
            ${repliesBlockHTML(comment.id, [])}
          </div>`);
        scrollTarget = wrap.firstElementChild;
      }
      scrollTarget?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

      // Engagement-bar count reflects the active scope (per-image or post-level).
      const scoped = respMediaId != null && media_comments_count != null
        ? media_comments_count
        : comments_count;
      setCommentsCount(scoped);

      // Persist into local state so re-rendering on slide change shows the right counts.
      if (respMediaId != null && isGalleryPost(currentPost)) {
        const m = currentPost.media.find((x) => Number(x.id) === Number(respMediaId));
        if (m) m.comments_count = media_comments_count ?? (m.comments_count || 0) + 1;
      }
      currentPost.comments_count = comments_count;
      const found = allPosts.find((p) => String(p.id) === String(currentPost.id));
      if (found) found.comments_count = comments_count;
      resetComposer();
    } catch (err) {
      toast(err.message || 'Could not post comment', 'bad');
    } finally {
      submit.disabled = false;
    }
  });
}

// ─────────── User preview sheet ───────────
let userSheetCache = new Map();
let userSheetCurrent = null; // { id, username } | null

function bindUserSheet() {
  const sheet = $('#userSheet');
  if (!sheet) return;

  // Intercept any user-link click on the page → open the slide-up sheet instead of navigating.
  document.addEventListener('click', (e) => {
    // Let modifier-clicks open the profile in a new tab as expected.
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1) return;
    const link = e.target.closest('[data-user-link]');
    if (!link) return;
    const id = link.dataset.userId || link.closest('[data-user-id]')?.dataset.userId;
    if (!id) return;
    e.preventDefault();
    e.stopPropagation();
    openUserSheet(id, link.getAttribute('href'));
  });

  // Close interactions
  sheet.addEventListener('click', (e) => {
    if (e.target.matches('[data-user-sheet-scrim]') || e.target.closest('[data-user-sheet-close]')) {
      closeUserSheet();
    }
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sheet.classList.contains('is-open')) closeUserSheet();
  });
}

async function openUserSheet(id, href) {
  const sheet = $('#userSheet');
  if (!sheet) return;
  userSheetCurrent = { id };

  // Show shell immediately with a loading state; fill in once the API responds.
  paintUserSheet(null, href);
  sheet.classList.add('is-open');
  sheet.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';

  let profile = userSheetCache.get(String(id));
  if (!profile) {
    try {
      const res = await api(`${APP.urls.api.users}/${id}`);
      profile = res.profile;
      userSheetCache.set(String(id), profile);
    } catch (err) {
      toast(err.message || 'Could not load profile', 'bad');
      closeUserSheet();
      return;
    }
  }
  // Bail if the user already navigated to a different sheet.
  if (!userSheetCurrent || String(userSheetCurrent.id) !== String(id)) return;
  paintUserSheet(profile, href);
}

function closeUserSheet() {
  const sheet = $('#userSheet');
  if (!sheet) return;
  sheet.classList.remove('is-open');
  sheet.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
  userSheetCurrent = null;
}

function paintUserSheet(profile, fallbackHref) {
  const av  = $('#userSheetAvatar');
  const nm  = $('#userSheetName');
  const hd  = $('#userSheetHandle');
  const meta = $('#userSheetMeta');
  const view = $('#userSheetViewBtn');
  const followBtn = $('#userSheetFollow');

  if (!profile) {
    av.innerHTML = '';
    nm.textContent = 'Loading…';
    hd.textContent = '';
    meta.innerHTML = '';
    document.querySelectorAll('[data-user-stat]').forEach((el) => { el.textContent = '–'; });
    if (view) view.setAttribute('href', fallbackHref || '#');
    followBtn?.classList.add('hidden');
    return;
  }

  av.innerHTML = profile.profile_picture
    ? `<img src="${esc(profile.profile_picture)}" alt="" class="h-full w-full object-cover">`
    : esc((profile.username || profile.name || 'U').charAt(0).toUpperCase());
  nm.textContent = profile.name || profile.username || 'User';
  hd.textContent = profile.username ? '@' + profile.username : '';

  const bits = [];
  if (profile.role && profile.role !== 'user') {
    bits.push(`<span class="rounded-full bg-brand-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-brand-700">${esc(profile.role)}</span>`);
  }
  if (profile.country) bits.push(`<span>${esc(profile.country)}</span>`);
  if (profile.created_at) bits.push(`<span>Joined ${esc(profile.created_at)}</span>`);
  meta.innerHTML = bits.join('<span class="text-slate-300">·</span>');

  const c = profile.counts || {};
  const setStat = (k, v) => {
    const el = document.querySelector(`[data-user-stat="${k}"]`);
    if (el) el.textContent = Number(v || 0).toLocaleString();
  };
  setStat('posts', c.posts);
  setStat('followers', c.followers);
  setStat('following', c.following);
  setStat('likes', c.likes);

  if (view) {
    view.setAttribute('href', profile.username ? `${APP.urls.profileBase}/${encodeURIComponent(profile.username)}` : (fallbackHref || '#'));
  }

  // Follow button: hidden when viewing yourself or not logged in.
  if (followBtn) {
    if (!APP.user || profile.is_self) {
      followBtn.classList.add('hidden');
    } else {
      followBtn.classList.remove('hidden');
      setFollowUI(followBtn, !!profile.is_following);
      followBtn.onclick = async () => {
        if (followBtn.disabled) return;
        followBtn.disabled = true;
        const wasFollowing = followBtn.getAttribute('aria-pressed') === 'true';
        setFollowUI(followBtn, !wasFollowing); // optimistic
        try {
          const res = await api(`${APP.urls.api.users}/${profile.id}/follow`, { method: 'POST' });
          setFollowUI(followBtn, res.following);
          // Update cached counts so the next open shows fresh numbers.
          const cached = userSheetCache.get(String(profile.id));
          if (cached) {
            cached.is_following = res.following;
            if (cached.counts) cached.counts.followers = res.followers;
          }
          setStat('followers', res.followers);
        } catch (err) {
          setFollowUI(followBtn, wasFollowing);
          toast(err.message || 'Could not update follow', 'bad');
        } finally {
          followBtn.disabled = false;
        }
      };
    }
  }
}

function setFollowUI(btn, following) {
  btn.setAttribute('aria-pressed', following ? 'true' : 'false');
  btn.textContent = following ? 'Following' : 'Follow';
  btn.classList.toggle('bg-brand-600', !following);
  btn.classList.toggle('hover:bg-brand-700', !following);
  btn.classList.toggle('text-white', !following);
  btn.classList.toggle('bg-slate-100', following);
  btn.classList.toggle('text-slate-700', following);
  btn.classList.toggle('hover:bg-slate-200', following);
}

// ─────────── Search (placeholder) ───────────
window.Tanbat.search = function (q) {
  q = (q || '').trim().toLowerCase();
  if (!q) return renderFeed();
  const filtered = allPosts.filter((p) => {
    const hay = [p.title, p.status_text, p.description, p.short_description, p.user?.username, p.user?.name, p.category?.name]
      .filter(Boolean).join(' ').toLowerCase();
    return hay.includes(q);
  });
  const feed = $('#feed');
  if (!filtered.length) { feed.innerHTML = ''; $('#feedEmpty').classList.remove('hidden'); return; }
  $('#feedEmpty').classList.add('hidden');
  feed.innerHTML = filtered.map(cardHTML).join('');
  hydrateAdSlots(feed);
};

// ─────────── Boot ───────────
function bindDwellTracking() {
  const teardownPlyr = () => {
    if (currentPlyr) { try { currentPlyr.destroy(); } catch (_) {} currentPlyr = null; }
  };
  // Listen for any close action on #modalPost (X button, backdrop, Escape).
  // We use capture so we fire BEFORE Tanbat.closeModal hides the element.
  document.addEventListener('click', (e) => {
    const closer = e.target.closest('[data-close-modal]');
    if (!closer) return;
    const modal = closer.closest('#modalPost');
    if (modal && currentPost) { recordDwell(currentPost.id); teardownPlyr(); }
  }, true);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && currentPost) {
      const m = document.getElementById('modalPost');
      if (m && !m.classList.contains('hidden')) { recordDwell(currentPost.id); teardownPlyr(); }
    }
  });
  // Backdrop click — Tanbat.bindModalCloses fires closeModal when the user
  // clicks the modal element itself. Mirror that here.
  document.addEventListener('click', (e) => {
    const m = document.getElementById('modalPost');
    if (m && e.target === m && currentPost) { recordDwell(currentPost.id); teardownPlyr(); }
  }, true);
  // Beacon any pending dwell on tab close.
  window.addEventListener('pagehide', () => {
    if (currentPost) recordDwell(currentPost.id);
    flushImpressions();
  });
}

document.addEventListener('DOMContentLoaded', () => {
  loadCategories();
  loadFeed();
  bindFilters();
  bindCreate();
  setupStatus();
  setupImage();
  setupVideo();
  bindFeedClicks();
  bindCommentForm();
  bindModalActions();
  bindUserSheet();
  bindInfiniteScroll();
  bindDwellTracking();
  bindFeedPostMenu();
  bindSavedPosts();
  // If we arrived at /home#post-{id} (e.g. from a notification), open that post once the feed has loaded.
  window.addEventListener('hashchange', openPostFromHash);
  // Defer the initial check so feed/api calls settle first.
  setTimeout(openPostFromHash, 400);
});
