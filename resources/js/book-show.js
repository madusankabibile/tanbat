// /books/{slug} — wires the inline engagement row and comment composer.
// Markup + styles are server-rendered in book-show.blade.php; this script
// only handles user actions (like, share, post comment, reply).

import { bindDownloadCountdown } from './cards.js';

const APP   = window.__APP__;
const api   = window.Tanbat.api;
const toast = window.Tanbat.toast;

const REACTION_EMOJI = { like: '👍', love: '❤️', haha: '😆', wow: '😮', sad: '😢', angry: '😡' };
const REACTION_LABEL = { like: 'Like', love: 'Love', haha: 'Haha', wow: 'Wow', sad: 'Sad', angry: 'Angry' };

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
const esc = (s) => String(s ?? '')
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');

// ─── Read post id from the engagement section ────────────────────────
function postId() {
  return document.querySelector('[data-post-id]')?.dataset.postId;
}

// ─── Reactions ───────────────────────────────────────────────────────
function syncLikeButton(reactionKey, count) {
  const btn = $('#bkLikeBtn');
  if (!btn) return;
  btn.classList.toggle('is-active', !!reactionKey);
  btn.dataset.reaction = reactionKey || '';
  const em = btn.querySelector('.re-emoji');
  if (em) em.textContent = reactionKey ? REACTION_EMOJI[reactionKey] : '';
  const lbl = btn.querySelector('.re-label');
  if (lbl) lbl.textContent = reactionKey ? REACTION_LABEL[reactionKey] : 'Like';
  if (typeof count === 'number') {
    const c = $('#bkLikeCount'); if (c) c.textContent = count.toLocaleString();
  }
}

async function react(reactionKey) {
  if (!APP.user) { toast('Sign in to react.', 'bad'); return; }
  const id = postId(); if (!id) return;
  const wrap = $('.reaction-wrap');
  // Close the popover after picking — same behaviour as the feed cards.
  if (wrap) {
    wrap.classList.add('is-dismissed');
    wrap.addEventListener('pointerleave', () => wrap.classList.remove('is-dismissed'), { once: true });
  }
  try {
    const res = await api(`${APP.urls.api.posts}/${id}/like`, {
      method: 'POST', body: { reaction: reactionKey },
    });
    syncLikeButton(res.reaction, res.likes_count);
  } catch (err) {
    toast(err.message || 'Could not update reaction.', 'bad');
  }
}

function bindReactionPopover() {
  // Click on the main like button → toggle current reaction.
  $('#bkLikeBtn')?.addEventListener('click', (e) => {
    e.preventDefault(); e.stopPropagation();
    const current = $('#bkLikeBtn').dataset.reaction;
    react(current || 'like');
  });
  // Pick from the popover.
  $$('.reaction-pop-btn').forEach((b) => {
    b.addEventListener('click', (e) => {
      e.preventDefault(); e.stopPropagation();
      react(b.dataset.react);
    });
  });
  // Long-press on touch devices opens the popover.
  let press = null;
  const btn = $('#bkLikeBtn');
  btn?.addEventListener('pointerdown', (e) => {
    if (e.pointerType === 'mouse') return;
    press = setTimeout(() => $('.reaction-wrap')?.classList.add('is-open'), 350);
  });
  ['pointerup', 'pointerleave', 'pointercancel'].forEach((ev) =>
    btn?.addEventListener(ev, () => { if (press) { clearTimeout(press); press = null; } }));
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.reaction-wrap')) {
      $('.reaction-wrap')?.classList.remove('is-open');
    }
  });
}

// ─── Share ───────────────────────────────────────────────────────────
function bindShare() {
  $('#bkShareBtn')?.addEventListener('click', () => {
    const id = postId();
    const url = location.href;
    const title = document.querySelector('.bk-title')?.textContent?.trim() || 'A book on Tanbat';
    const image = document.querySelector('.bk-hero-cover img')?.src || '';
    window.Tanbat?.openShare?.({ kind: 'post', postId: id, url, title, image });
  });
}

// ─── Comments ────────────────────────────────────────────────────────
function bumpCommentCount(delta) {
  ['.bk-comments-count', '#bkCommentCount'].forEach((sel) => {
    const el = document.querySelector(sel);
    if (!el) return;
    const raw = (el.textContent || '').replace(/[^\d]/g, '');
    const n = Math.max(0, (parseInt(raw, 10) || 0) + delta);
    el.textContent = el.classList.contains('bk-comments-count') ? `(${n.toLocaleString()})` : n.toLocaleString();
  });
}

function avatarHTML(user, small = false) {
  const cls = 'bk-av' + (small ? ' bk-av-sm' : '');
  if (user?.profile_picture) {
    return `<img src="${esc(user.profile_picture)}" class="${cls}" alt="">`;
  }
  const letter = esc((user?.username || user?.name || 'U').charAt(0).toUpperCase());
  return `<span class="${cls} bk-av-fallback">${letter}</span>`;
}

function topCommentHTML(c) {
  return `
    <div class="bk-thread" data-comment-id="${c.id}">
      <div class="bk-thread-row">
        ${avatarHTML(c.user)}
        <div class="bk-thread-body">
          <div class="bk-bubble">
            <div class="bk-bubble-head">
              <a href="${esc((APP.urls.profileBase || '/u') + '/' + (c.user?.username || ''))}" class="bk-bubble-name">${esc(c.user?.username || 'User')}</a>
              <span class="bk-bubble-when">${esc(c.created_at || 'just now')}</span>
            </div>
            <p class="bk-bubble-text">${esc(c.body || '')}</p>
          </div>
          ${APP.user ? `
            <div class="bk-bubble-actions">
              <button type="button" class="bk-reply-trigger" data-parent="${c.id}">Reply</button>
            </div>
            <form class="bk-reply-form" data-parent="${c.id}" hidden>
              <input type="text" maxlength="1000" required placeholder="Reply to @${esc(c.user?.username || '')}…">
              <button type="submit">Reply</button>
              <button type="button" class="bk-reply-cancel">Cancel</button>
            </form>
          ` : ''}
          <div class="bk-replies" data-replies-of="${c.id}"></div>
        </div>
      </div>
    </div>`;
}

function replyHTML(r) {
  return `
    <div class="bk-reply" data-reply-id="${r.id}">
      ${avatarHTML(r.user, true)}
      <div class="bk-bubble bk-bubble-reply">
        <div class="bk-bubble-head">
          <span class="bk-bubble-name">${esc(r.user?.username || 'User')}</span>
          <span class="bk-bubble-when">${esc(r.created_at || 'just now')}</span>
        </div>
        <p class="bk-bubble-text">${esc(r.body || '')}</p>
      </div>
    </div>`;
}

async function postComment(body, parentId) {
  const id = postId(); if (!id) return null;
  const res = await api(`${APP.urls.api.posts}/${id}/comments`, {
    method: 'POST',
    body: { body, parent_id: parentId || null },
  });
  return res?.comment || null;
}

function bindCommentForm() {
  const form = $('#bkCommentForm');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const ta = $('#bkCommentBody');
    const text = (ta?.value || '').trim();
    if (!text) return;
    const submit = form.querySelector('.bk-comment-submit');
    if (submit) submit.disabled = true;
    try {
      const c = await postComment(text, null);
      if (c) {
        // Drop the empty-state, then prepend.
        $('.bk-comments-empty')?.remove();
        $('#bkCommentList')?.insertAdjacentHTML('afterbegin', topCommentHTML(c));
        ta.value = '';
        bumpCommentCount(1);
        toast('Comment posted!', 'ok');
      }
    } catch (err) {
      toast(err.message || 'Could not post comment.', 'bad');
    } finally {
      if (submit) submit.disabled = false;
    }
  });
}

function bindReplies() {
  document.addEventListener('click', (e) => {
    const trigger = e.target.closest('.bk-reply-trigger');
    if (trigger) {
      const parent = trigger.dataset.parent;
      const form = document.querySelector(`.bk-reply-form[data-parent="${parent}"]`);
      if (form) {
        form.hidden = false;
        form.querySelector('input')?.focus();
      }
      return;
    }
    const cancel = e.target.closest('.bk-reply-cancel');
    if (cancel) {
      const form = cancel.closest('.bk-reply-form');
      if (form) { form.hidden = true; form.reset(); }
    }
  });

  document.addEventListener('submit', async (e) => {
    const form = e.target.closest('.bk-reply-form');
    if (!form) return;
    e.preventDefault();
    const parent = form.dataset.parent;
    const input = form.querySelector('input');
    const text = (input?.value || '').trim();
    if (!text) return;
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;
    try {
      const c = await postComment(text, parent);
      if (c) {
        const replies = document.querySelector(`[data-replies-of="${parent}"]`);
        replies?.insertAdjacentHTML('beforeend', replyHTML(c));
        input.value = '';
        form.hidden = true;
        bumpCommentCount(1);
      }
    } catch (err) {
      toast(err.message || 'Could not reply.', 'bad');
    } finally {
      if (submitBtn) submitBtn.disabled = false;
    }
  });
}

// ─── Boot ────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  bindReactionPopover();
  bindShare();
  bindCommentForm();
  bindReplies();
  bindDownloadCountdown(document);
});
