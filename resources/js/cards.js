// Shared card renderers for the feed: status, image (with swipe gallery), video, article.
// Used by both home.js and profile.js.

const APP = window.__APP__;

// Facebook-style reactions. `key` must match Post::REACTIONS on the backend.
export const REACTIONS = [
  { key: 'like',  emoji: '👍', label: 'Like',  color: '#2563EB' },
  { key: 'love',  emoji: '❤️', label: 'Love',  color: '#F43F5E' },
  { key: 'haha',  emoji: '😆', label: 'Haha',  color: '#F59E0B' },
  { key: 'wow',   emoji: '😮', label: 'Wow',   color: '#F59E0B' },
  { key: 'sad',   emoji: '😢', label: 'Sad',   color: '#F59E0B' },
  { key: 'angry', emoji: '😡', label: 'Angry', color: '#EF4444' },
];
export const REACTION_MAP = Object.fromEntries(REACTIONS.map((r) => [r.key, r]));

const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

function profileUrl(username) {
  return username ? `${APP.urls.profileBase}/${encodeURIComponent(username)}` : '#';
}

// Posts older than 5 days show no timestamp; everything newer renders as a
// relative duration so viewers in any timezone see a consistent "X ago" label.
export function formatPostTime(iso) {
  if (!iso) return '';
  const t = Date.parse(iso);
  if (Number.isNaN(t)) return '';
  const diff = Math.max(0, Date.now() - t);
  const min = 60_000, hr = 60 * min, day = 24 * hr;
  if (diff >= 5 * day) return '';
  if (diff < min)      return 'just now';
  if (diff < hr)       { const n = Math.floor(diff / min); return `${n} min ago`; }
  if (diff < day)      { const n = Math.floor(diff / hr);  return `${n} hr ago`; }
  const n = Math.floor(diff / day);
  return `${n} day${n === 1 ? '' : 's'} ago`;
}

function isLight(hex) {
  if (!hex || hex[0] !== '#') return true;
  const h = hex.length === 4
    ? hex.slice(1).split('').map((c) => c + c).join('')
    : hex.slice(1, 7);
  const r = parseInt(h.slice(0, 2), 16);
  const g = parseInt(h.slice(2, 4), 16);
  const b = parseInt(h.slice(4, 6), 16);
  return (r * 299 + g * 587 + b * 114) / 1000 >= 160;
}

function avatarHTML(u) {
  const href = profileUrl(u?.username);
  const uid  = u?.id ? `data-user-id="${u.id}"` : '';
  if (u?.profile_picture) {
    return `<a href="${href}" ${uid} data-user-link><img class="avatar" src="${esc(u.profile_picture)}" alt=""></a>`;
  }
  const letter = esc((u?.username || u?.name || 'U').charAt(0).toUpperCase());
  return `<a href="${href}" ${uid} data-user-link><span class="avatar">${letter}</span></a>`;
}

function headerHTML(p, badgeKind, badgeLabel) {
  const u = p.user || {};
  const displayName = esc(u.name || u.username || 'User');
  const when = esc(formatPostTime(p.created_at_iso));
  const views = Number(p.views_count || 0).toLocaleString();
  const href = profileUrl(u.username);
  const uid  = u.id ? `data-user-id="${u.id}"` : '';
  return `
    <div class="post-head">
      ${avatarHTML(u)}
      <div class="who">
        <a href="${href}" ${uid} data-user-link class="name hover:underline">${displayName}</a>
        <span class="meta">
          ${when ? `<span>${when}</span><span class="dot"></span>` : ''}
          <span class="post-badge ${badgeKind}">${badgeLabel}</span>
          <span class="dot"></span>
          <span class="views" title="${views} views" data-card-views>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
            <strong data-views-count>${views}</strong>
            <span class="views-label">views</span>
          </span>
        </span>
      </div>
      ${postMenuHTML(p)}
    </div>
  `;
}

function postMenuHTML(p) {
  const isOwner = !!p.is_owner;
  const saved = !!p.saved;
  const items = [];
  if (!isOwner) {
    items.push(menuItem('not_interested', 'Not interested', 'Show fewer posts like this', iconBan()));
    items.push(menuItem('hide',           'Hide post',      'Remove from your feed',      iconEyeOff()));
  }
  // "Save post" is a split control: clicking the row toggles save into
  // Uncategorized; clicking the chevron opens a folder picker submenu that
  // is populated lazily by share-module.js the first time it's expanded.
  items.push(`
    <div class="post-menu-save-wrap ${saved ? 'is-saved' : ''}" data-save-wrap>
      <button type="button" class="post-menu-item ${saved ? 'is-active' : ''}"
              data-post-act="save" role="menuitem">
        <span class="pmi-ic">${iconBookmark(saved)}</span>
        <span class="pmi-text">
          <span class="pmi-title">${saved ? 'Unsave post' : 'Save post'}</span>
          <span class="pmi-sub">${saved ? 'Removed from your saved items' : 'Open it later from your bookmarks'}</span>
        </span>
      </button>
      <button type="button" class="post-menu-save-toggle" data-post-act="save-to" aria-label="Save to folder">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
      <div class="post-save-submenu" data-save-submenu role="menu">
        <div class="psm-loading">Loading folders…</div>
      </div>
    </div>
  `);
  if (isOwner) {
    items.push('<div class="post-menu-sep"></div>');
    items.push(menuItem('edit',   'Edit post',   'Update your post content', iconEdit()));
    items.push(menuItem('delete', 'Delete post', 'Permanently remove this post', iconTrash(), 'is-danger'));
  }
  return `
    <div class="post-menu-wrap" data-post-menu>
      <button type="button" class="post-menu" aria-label="Post options" data-post-menu-trigger>
        <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </button>
      <div class="post-menu-panel" role="menu">
        ${items.join('')}
      </div>
    </div>
  `;
}

function menuItem(action, title, sub, icon, extra = '') {
  return `
    <button type="button" class="post-menu-item ${extra}" data-post-act="${action}" role="menuitem">
      <span class="pmi-ic">${icon}</span>
      <span class="pmi-text">
        <span class="pmi-title">${title}</span>
        <span class="pmi-sub">${sub}</span>
      </span>
    </button>
  `;
}

function iconBan() {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>`;
}
function iconEyeOff() {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a19.77 19.77 0 0 1 4.22-5.06"/><path d="M1 1l22 22"/><path d="M9.9 4.24A10.94 10.94 0 0 1 12 4c7 0 11 8 11 8a19.5 19.5 0 0 1-3.13 4.18"/><path d="M14.12 14.12A3 3 0 1 1 9.88 9.88"/></svg>`;
}
function iconBookmark(filled) {
  const fill = filled ? 'currentColor' : 'none';
  return `<svg viewBox="0 0 24 24" fill="${fill}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>`;
}
function iconEdit() {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>`;
}
function iconTrash() {
  return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>`;
}

// The hover/long-press popover of reaction choices.
function reactionPopHTML() {
  return `
    <div class="reaction-pop" role="menu" aria-label="Pick a reaction">
      ${REACTIONS.map((r) => `
        <button type="button" class="reaction-pop-btn" data-react="${r.key}" title="${r.label}" aria-label="${r.label}">
          <span class="re">${r.emoji}</span>
        </button>`).join('')}
    </div>`;
}

// The little stacked emoji bubbles + total shown above the action bar.
export function reactionStackHTML(topReactions) {
  const top = (topReactions || []).slice(0, 3);
  return top.map((k) => `<span class="re-chip">${REACTION_MAP[k]?.emoji || '👍'}</span>`).join('');
}

function defaultLikeIcon() {
  return `<svg class="re-default" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>`;
}

function actionsHTML(p) {
  const likes = Number(p.likes_count || 0);
  const comments = Number(p.comments_count || 0);
  const r = p.my_reaction ? REACTION_MAP[p.my_reaction] : null;
  return `
    <div class="post-counts" ${(likes > 0 || comments > 0) ? '' : 'hidden'}>
      <span class="reaction-stack" data-reaction-stack>${reactionStackHTML(p.top_reactions)}</span>
      <span data-likes-count>${likes > 0 ? likes.toLocaleString() : ''}</span>
      <span class="comment-count" data-comment-count style="margin-left:auto">${comments > 0 ? `${comments.toLocaleString()} comment${comments === 1 ? '' : 's'}` : ''}</span>
    </div>
    <div class="post-actions">
      <div class="reaction-wrap">
        ${reactionPopHTML()}
        <button type="button" class="btn-like${r ? ' is-reacted' : ''}" data-act="like"
                data-reaction="${r ? r.key : ''}" aria-pressed="${r ? 'true' : 'false'}">
          <span class="re-emoji">${r ? r.emoji : ''}</span>
          ${defaultLikeIcon()}
          <span data-like-label class="lbl" ${r ? `style="color:${r.color}"` : ''}>${r ? r.label : 'Like'}</span>
        </button>
      </div>
      <button type="button" class="btn-comment" data-act="comment">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        <span class="lbl">Comment</span>
      </button>
      <button type="button" class="btn-share" data-act="share">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        <span class="lbl">Share</span>
      </button>
    </div>
  `;
}

/**
 * Update a card's Like button to reflect the viewer's current reaction
 * (or none). Shared by home.js and profile.js.
 */
export function applyReactionUI(card, btn, reactionKey) {
  if (!btn) return;
  const r = reactionKey ? REACTION_MAP[reactionKey] : null;
  btn.classList.toggle('is-reacted', !!r);
  btn.setAttribute('aria-pressed', r ? 'true' : 'false');
  btn.dataset.reaction = r ? r.key : '';
  const em = btn.querySelector('.re-emoji');
  if (em) em.textContent = r ? r.emoji : '';
  const label = btn.querySelector('[data-like-label]');
  if (label) {
    label.textContent = r ? r.label : 'Like';
    label.style.color = r ? r.color : '';
  }
}

export function setReactionCount(card, n) {
  const el = card?.querySelector('[data-likes-count]');
  if (el) el.textContent = n > 0 ? Number(n).toLocaleString() : '';
  syncCountsVisibility(card);
}

export function renderReactionSummary(card, topReactions) {
  const stack = card?.querySelector('[data-reaction-stack]');
  if (stack) stack.innerHTML = reactionStackHTML(topReactions);
}

// Show the counts strip only when there's something in it.
function syncCountsVisibility(card) {
  const row = card?.querySelector('.post-counts');
  if (!row) return;
  const likes = (row.querySelector('[data-likes-count]')?.textContent || '').trim();
  const comments = (row.querySelector('[data-comment-count]')?.textContent || '').trim();
  row.toggleAttribute('hidden', !likes && !comments);
}

/**
 * Wire reaction interactions for a feed container (event-delegated):
 *  • tapping an emoji in the popover sets that reaction
 *  • long-pressing the Like button opens the popover on touch devices
 * Desktop hover-to-open is pure CSS. `onReact(card, reactionKey)` performs
 * the network call + UI sync.
 */
// Force a picker shut after a choice and keep it shut while the cursor lingers
// over it (otherwise :hover would immediately reopen it). It re-arms once the
// pointer leaves the wrap.
export function dismissReactionPanel(wrap) {
  if (!wrap) return;
  wrap.classList.remove('is-open');
  if (wrap.contains(document.activeElement)) document.activeElement.blur();
  wrap.classList.add('is-dismissed');
  wrap.addEventListener('pointerleave', () => wrap.classList.remove('is-dismissed'), { once: true });
}

export function bindReactions(container, onReact) {
  if (!container) return;
  let pressTimer = null;
  const clearPress = () => { if (pressTimer) { clearTimeout(pressTimer); pressTimer = null; } };

  container.addEventListener('click', (e) => {
    const emoji = e.target.closest('[data-react]');
    if (!emoji || !container.contains(emoji)) return;
    e.preventDefault();
    e.stopPropagation();
    const card = emoji.closest('[data-post-id]');
    dismissReactionPanel(emoji.closest('.reaction-wrap'));
    onReact(card, emoji.dataset.react);
  });

  container.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'mouse') return; // desktop uses hover
    const main = e.target.closest('.btn-like');
    if (!main) return;
    const wrap = main.closest('.reaction-wrap');
    pressTimer = setTimeout(() => wrap?.classList.add('is-open'), 350);
  });
  ['pointerup', 'pointerleave', 'pointercancel'].forEach((ev) =>
    container.addEventListener(ev, clearPress));

  // Tapping elsewhere closes any open picker on touch.
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.reaction-wrap')) {
      container.querySelectorAll('.reaction-wrap.is-open').forEach((w) => w.classList.remove('is-open'));
    }
  });
}

function statusCard(p) {
  const bg = p.bg_color || '#EEF2FF';
  const fg = p.font_color || (isLight(bg) ? '#1E1B4B' : '#FFFFFF');
  const img = p.media?.[0]?.url
    ? `<div class="post-media"><img src="${esc(p.media[0].url)}" loading="lazy" alt=""></div>` : '';
  const cta = p.user?.username === 'robert_sheffield' ? newsbotCtaHTML() : '';
  return `
    <article class="post-card status-card" data-post-id="${p.id}">
      ${headerHTML(p, 'status', 'Status')}
      <div class="status-canvas" data-open style="background:${esc(bg)};color:${esc(fg)};">
        ${esc(p.status_text || '')}
      </div>
      ${img}
      ${cta}
      ${actionsHTML(p)}
    </article>
  `;
}

// Sponsor URL — same one the newsbot CTA uses. Re-used as the click target
// behind book covers across the wizard, the feed card, the books grid and
// the /books/{slug} hero.
export const AD_LINK_URL = 'https://www.effectivecpmnetwork.com/gc1v4hw8?key=b0e0c39593829879ba649d8cb2ef71ad';
const NEWSBOT_CONTINUE_URL = AD_LINK_URL;

export function newsbotCtaHTML() {
  return `<a class="newsbot-continue" href="${NEWSBOT_CONTINUE_URL}" target="_blank" rel="noopener sponsored">Continue reading…</a>`;
}

/**
 * Wire a 10-second countdown on every `[data-countdown]` download button
 * inside `root`. The button is inert during the count; when the timer hits
 * zero it flips to an active state and clicking it opens `data-dl` in a new
 * tab. Idempotent — already-bound buttons are skipped.
 */
export function bindDownloadCountdown(root = document) {
  const buttons = root.querySelectorAll('[data-countdown]:not([data-cd-bound])');
  buttons.forEach((btn) => {
    btn.dataset.cdBound = '1';
    const total = parseInt(btn.dataset.countdown, 10) || 10;
    const url = btn.dataset.dl;
    if (!url) return;
    const counter = btn.querySelector('[data-cd-counter]');
    const label   = btn.querySelector('[data-cd-label]');

    let remaining = total;
    btn.classList.add('is-counting');
    btn.classList.remove('is-ready');
    btn.disabled = true;
    if (counter) counter.textContent = `${remaining}s`;
    if (label) label.textContent = 'Preparing your download…';

    const tick = () => {
      remaining -= 1;
      if (remaining > 0) {
        if (counter) counter.textContent = `${remaining}s`;
        setTimeout(tick, 1000);
        return;
      }
      btn.classList.remove('is-counting');
      btn.classList.add('is-ready');
      btn.disabled = false;
      if (label) label.textContent = 'Get this book';
      if (counter) counter.textContent = '';
    };

    btn.addEventListener('click', (e) => {
      if (btn.classList.contains('is-counting')) {
        e.preventDefault();
        return;
      }
      window.open(url, '_blank', 'noopener');
    });

    setTimeout(tick, 1000);
  });
}

// Native ad slot used for the ad-bot account's image-type posts. The script tag injects the
// creative into the matching #container-… element when the card mounts.
const ADBOT_SLOT_KEY = '36ce0149ae6c36811ff6c54b088c483c';
const ADBOT_SLOT_SRC = `https://pl23865704.effectivecpmnetwork.com/${ADBOT_SLOT_KEY}/invoke.js`;

function adbotSlotHTML(postId) {
  // The ad network's invoke.js targets a fixed container id, so we render
  // the verbatim div here and hydrate the script tag separately (innerHTML
  // does not execute injected <script> elements).
  return `
    <div class="post-media adbot-slot" data-adbot-slot data-post-id="${postId}">
      <div id="container-${ADBOT_SLOT_KEY}"></div>
    </div>
  `;
}

// Walk a root element for any adbot slots and inject a real <script> so the
// browser actually fetches invoke.js. The ad network only fills one container
// per page load, so we add the script once globally even if multiple slots
// render — duplicate slots just stay empty, matching how every ad network
// degrades when over-served.
export function hydrateAdSlots(root) {
  if (!root) return;
  const slots = root.querySelectorAll?.('[data-adbot-slot]');
  if (!slots || !slots.length) return;
  if (document.getElementById('adbot-invoke')) return;
  const s = document.createElement('script');
  s.id = 'adbot-invoke';
  s.async = true;
  s.dataset.cfasync = 'false';
  s.src = ADBOT_SLOT_SRC;
  document.body.appendChild(s);
}

function imageCard(p) {
  const media = (p.media || []).filter((m) => m?.url);
  const isAdbot = p.user?.username === 'daniel_whitmore';
  // Adbot posts carry no real media; the ad-network script renders into a
  // per-post container in place of the image gallery.
  if (isAdbot) {
    return `
      <article class="post-card image-card adbot-card" data-post-id="${p.id}">
        ${headerHTML(p, 'image', 'Sponsored')}
        ${adbotSlotHTML(p.id)}
        ${actionsHTML(p)}
      </article>
    `;
  }
  // Defensive fallback: an image post without media still renders as a text card
  // so it never silently disappears from the feed.
  if (!media.length) {
    const desc = p.description ? `<div class="post-body">${esc(p.description)}</div>` : '';
    return `
      <article class="post-card image-card" data-post-id="${p.id}">
        ${headerHTML(p, 'image', 'Photo')}
        ${desc || '<div class="post-body" style="color:#94a3b8">[image unavailable]</div>'}
        ${actionsHTML(p)}
      </article>
    `;
  }
  const adult = p.is_adult ? `<span class="adult-pill">18+</span>` : '';
  const desc = p.description ? `<div class="post-body">${esc(p.description)}</div>` : '';
  const multi = media.length > 1;
  const slides = media.map((m) =>
    `<img class="gallery-slide" src="${esc(m.url)}" loading="lazy" alt="">`
  ).join('');
  const controls = multi ? `
      <button type="button" class="gallery-btn gallery-prev" data-gallery-nav="-1" aria-label="Previous image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      <button type="button" class="gallery-btn gallery-next" data-gallery-nav="1" aria-label="Next image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
      <span class="gallery-counter" data-gallery-counter>1 / ${media.length}</span>
  ` : '';
  const dots = multi ? `
    <div class="gallery-dots" data-gallery-dots>
      ${media.map((_, i) => `<span class="dot${i === 0 ? ' active' : ''}"></span>`).join('')}
    </div>` : '';
  return `
    <article class="post-card image-card" data-post-id="${p.id}">
      ${headerHTML(p, 'image', 'Photo')}
      ${desc}
      <div class="post-media ${p.is_adult ? 'is-adult' : ''}">
        <div class="gallery-wrap">
          <div class="gallery-track${multi ? ' is-multi' : ''}" data-gallery data-open>${slides}</div>
          ${controls}${adult}
        </div>
      </div>
      ${dots}
      ${actionsHTML(p)}
    </article>
  `;
}

function videoCard(p) {
  const adult = p.is_adult ? `<span class="adult-pill">18+</span>` : '';
  const thumb = p.thumbnail || p.media?.[0]?.url || '';
  const desc = p.description ? `<div class="post-body">${esc(p.description)}</div>` : '';
  // No thumbnail or media: render as text card rather than a broken-image stub.
  if (!thumb) {
    return `
      <article class="post-card video-card" data-post-id="${p.id}">
        ${headerHTML(p, 'video', 'Video')}
        ${desc || '<div class="post-body" style="color:#94a3b8">[video unavailable]</div>'}
        ${actionsHTML(p)}
      </article>
    `;
  }
  return `
    <article class="post-card video-card" data-post-id="${p.id}">
      ${headerHTML(p, 'video', 'Video')}
      ${desc}
      <div class="video-thumb-wrap ${p.is_adult ? 'is-adult' : ''}" data-open>
        <img src="${esc(thumb)}" loading="lazy" alt="">
        <div class="play-btn"><span class="play-disc"><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></div>
        ${adult}
      </div>
      ${actionsHTML(p)}
    </article>
  `;
}

function articleCard(p) {
  const cat = p.category?.name ? `<span class="article-cat">${esc(p.category.name)}</span>` : '';
  const figure = p.featured_image ? `
    <a class="article-figure block" href="${esc(p.view_url || '#')}">
      <img src="${esc(p.featured_image)}" loading="lazy" alt="">
      ${cat}
    </a>` : '';
  return `
    <article class="post-card article-card" data-post-id="${p.id}">
      ${headerHTML(p, 'article', 'Article')}
      ${figure}
      <div class="article-meta">
        <a href="${esc(p.view_url || '#')}" class="article-title block hover:text-brand-600">${esc(p.title || 'Untitled')}</a>
        ${p.short_description ? `<p class="article-desc">${esc(p.short_description)}</p>` : ''}
      </div>
      <a class="article-read" href="${esc(p.view_url || '#')}">
        Read article
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
      </a>
      ${actionsHTML(p)}
    </article>
  `;
}

function bookCard(p) {
  const b = p.book || {};
  // The title navigates to the dedicated /books/{slug} page; the cover image
  // is monetized as an ad image-link instead.
  const titleHref = p.view_url || '#';

  const cover = b.cover_url
    ? `<img src="${esc(b.cover_url)}" alt="" referrerpolicy="no-referrer"
           onerror="this.replaceWith(Object.assign(document.createElement('span'), { className:'book-noimg', textContent:'No cover' }))">`
    : `<span class="book-noimg">No cover</span>`;

  const tags = [
    b.extension ? `<span class="book-tag ext">${esc(b.extension)}</span>` : '',
    b.size      ? `<span class="book-tag size">${esc(b.size)}</span>`      : '',
    b.year      ? `<span class="book-tag year">📅 ${esc(b.year)}</span>`   : '',
    b.language  ? `<span class="book-tag lang">🌐 ${esc(b.language)}</span>` : '',
  ].filter(Boolean).join('');

  const dl = b.download_url
    ? `<button type="button" class="book-dl is-counting"
              data-countdown="10" data-dl="${esc(b.download_url)}">
         <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
         <span data-cd-label>Preparing your download…</span>
         <span class="book-dl-counter" data-cd-counter>10s</span>
       </button>`
    : '';

  const blurb = b.description
    ? `<p class="book-desc">${esc(String(b.description).slice(0, 220))}${String(b.description).length > 220 ? '…' : ''}</p>`
    : '';

  return `
    <article class="post-card book-card" data-post-id="${p.id}" data-book-slug="${esc(b.slug || '')}">
      ${headerHTML(p, 'book', 'Book')}
      <div class="book-body">
        <a class="book-cover" href="${AD_LINK_URL}" target="_blank" rel="sponsored noopener" data-ad-cover>${cover}</a>
        <div class="book-info">
          <a class="book-title" href="${esc(titleHref)}">${esc(b.title || p.title || 'Untitled')}</a>
          ${b.author    ? `<div class="book-author">by ${esc(b.author)}</div>`    : ''}
          ${b.publisher ? `<div class="book-pub">${esc(b.publisher)}</div>` : ''}
          <div class="book-tags">${tags}</div>
          ${blurb}
          ${dl}
        </div>
      </div>
      ${actionsHTML(p)}
    </article>
  `;
}

export function cardHTML(p) {
  if (p.type === 'status')  return statusCard(p);
  if (p.type === 'image')   return imageCard(p);
  if (p.type === 'video')   return videoCard(p);
  if (p.type === 'article') return articleCard(p);
  if (p.type === 'book')    return bookCard(p);
  return '';
}

export function bindGallery(wrap) {
  const track = wrap.querySelector('[data-gallery]');
  const dots  = wrap.parentElement?.parentElement?.querySelector('[data-gallery-dots]');
  const counter = wrap.querySelector('[data-gallery-counter]');
  if (!track) return;
  const update = () => {
    if (!track.clientWidth) return;
    const i = Math.round(track.scrollLeft / track.clientWidth);
    if (dots) dots.querySelectorAll('.dot').forEach((d, idx) => d.classList.toggle('active', idx === i));
    const total = track.children.length;
    if (counter) counter.textContent = `${i + 1} / ${total}`;
  };
  track.addEventListener('scroll', update, { passive: true });
}

/**
 * Render a video post into a stage element and mount Plyr on it.
 * Handles both uploaded files (HTML5 video) and YouTube/Vimeo embeds.
 * Returns the Plyr instance (caller is responsible for .destroy()).
 */
export function mountVideoStage(stage, post) {
  if (!stage || !post) return null;
  const hasEmbed = post.embed_provider && post.embed_id;
  const hasFile  = post.media?.[0]?.url;

  if (hasEmbed) {
    stage.innerHTML = `<div class="plyr__video-embed plyr-stage"
        data-plyr-provider="${esc(post.embed_provider)}"
        data-plyr-embed-id="${esc(post.embed_id)}"></div>`;
  } else if (hasFile) {
    stage.innerHTML = `<video class="plyr-stage" controls playsinline
        poster="${esc(post.thumbnail || '')}">
        <source src="${esc(post.media[0].url)}">
      </video>`;
  } else {
    stage.innerHTML = '';
    return null;
  }

  const el = stage.querySelector('.plyr-stage');
  if (!el || typeof window.Plyr === 'undefined') return null;

  // Plyr autoplay is gated by browser policy; muted=false means it may not
  // start until the user clicks play. That matches our previous behavior.
  return new window.Plyr(el, {
    autoplay: true,
    youtube: { noCookie: true, rel: 0, modestbranding: 1 },
  });
}

export { esc, isLight, profileUrl };
