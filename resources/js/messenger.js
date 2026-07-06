// Tanbat Messenger — loaded on every page (popup chat dock + full /messages page)
//
// Real-time-ish via polling (no broadcast driver is configured).
//
// Receipt model (server-driven):
//   sent      — message exists, peer not yet online since send → clock icon
//   delivered — peer was online (polled or opened any thread)   → single check
//   read      — peer opened this conversation                   → double blue check

(function () {
  const APP = window.__APP__ || {};
  if (!APP.user) return;

  // ─────────── URLs ───────────
  const URL_THREADS       = APP.urls.api.messages.threads;
  const URL_THREAD        = (id) => `${APP.urls.api.messages.thread}/${id}`;
  const URL_THREAD_SEND   = (id) => `${APP.urls.api.messages.thread}/${id}`;
  const URL_THREAD_POLL   = (id) => `${APP.urls.api.messages.thread}/${id}/poll`;
  const URL_THREAD_TYPING = (id) => `${APP.urls.api.messages.thread}/${id}/typing`;
  const URL_THREAD_STOP   = (id) => `${APP.urls.api.messages.thread}/${id}/stop-typing`;
  const URL_THREAD_READ   = (id) => `${APP.urls.api.messages.thread}/${id}/read`;
  const URL_UNREAD        = APP.urls.api.messages.unread;
  const URL_MESSAGES_PAGE = APP.urls.messagesPage;

  const api   = window.Tanbat?.api;
  const toast = window.Tanbat?.toast || ((m) => console.log(m));
  if (!api) return;

  // ─────────── Utils ───────────
  const $  = (sel, ctx = document) => ctx.querySelector(sel);
  const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const esc = (s) => String(s ?? '')
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#039;');

  // Escape text then auto-link plain http(s) URLs so they're clickable in
  // message bubbles. Trailing punctuation that isn't part of the URL is kept
  // outside the anchor.
  const escWithLinks = (s) => {
    const text = String(s ?? '');
    const re = /\bhttps?:\/\/[^\s<>"']+/gi;
    let out = '';
    let last = 0;
    let m;
    while ((m = re.exec(text)) !== null) {
      out += esc(text.slice(last, m.index));
      let url = m[0];
      let trail = '';
      const trailMatch = url.match(/[.,!?;:)\]}'"]+$/);
      if (trailMatch) {
        trail = trailMatch[0];
        url = url.slice(0, -trail.length);
      }
      out += `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer nofollow" class="msg-link">${esc(url)}</a>${esc(trail)}`;
      last = m.index + m[0].length;
    }
    out += esc(text.slice(last));
    return out;
  };

  const initialOf = (s) => String(s || 'U').trim().charAt(0).toUpperCase();

  function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return '';
    const now = new Date();
    if (d.toDateString() === now.toDateString()) {
      return d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
    }
    const yesterday = new Date(now); yesterday.setDate(now.getDate() - 1);
    if (d.toDateString() === yesterday.toDateString()) return 'Yesterday';
    if ((now - d) < 7 * 86400e3) return d.toLocaleDateString([], { weekday: 'short' });
    return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
  }

  function dateLabel(iso) {
    const d = new Date(iso);
    const now = new Date();
    if (d.toDateString() === now.toDateString()) return 'Today';
    const y = new Date(now); y.setDate(now.getDate() - 1);
    if (d.toDateString() === y.toDateString()) return 'Yesterday';
    return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
  }

  const avatarHtml = (peer) => peer?.profile_picture
    ? `<img src="${esc(peer.profile_picture)}" alt="">`
    : esc(initialOf(peer?.username || peer?.name));

  /* ─────────── Tick / receipt rendering ─────────── */

  function messageStatus(m, state) {
    if (!m.is_mine) return null;
    const id = Number(m.id);
    if (!Number.isFinite(id)) return 'pending';   // optimistic tempId
    if (id <= (state.peerLastReadId      || 0)) return 'read';
    if (id <= (state.peerLastDeliveredId || 0)) return 'delivered';
    if (m.read)      return 'read';
    if (m.delivered) return 'delivered';
    return 'sent';
  }

  function tickHtml(status) {
    if (status === 'read') {
      return `
        <svg class="msg-tick is-read" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="2 13 7 18 14 7"/>
          <polyline points="9 13 14 18 22 7"/>
        </svg>`;
    }
    if (status === 'delivered') {
      return `
        <svg class="msg-tick is-single" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="20 6 9 17 4 12"/>
        </svg>`;
    }
    // sent (or pending)
    return `
      <svg class="msg-tick is-clock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="9"/>
        <polyline points="12 7 12 12 15 14"/>
      </svg>`;
  }

  /* ═══════════════════════════════════════════════════════════════════
     Navbar badge — total unread count
     ═══════════════════════════════════════════════════════════════════ */

  function ensureBadge() {
    const btn = document.querySelector('[data-action="messages"]');
    if (!btn) return null;
    let badge = btn.querySelector('.msg-badge');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'msg-badge';
      badge.style.display = 'none';
      btn.style.position = 'relative';
      btn.appendChild(badge);
    }
    return badge;
  }

  async function refreshUnreadBadge() {
    const badge = ensureBadge();
    if (!badge) return;
    try {
      const { unread } = await api(URL_UNREAD);
      // Inline style beats both Tailwind's .hidden and the .msg-badge { display: grid }
      // rule that ships in this stylesheet — neither class-based hide reliably wins.
      if (!unread) {
        badge.style.display = 'none';
        badge.textContent = '';
      } else {
        badge.style.display = '';
        badge.textContent = unread > 99 ? '99+' : String(unread);
      }
    } catch (_) {}
  }

  /* ═══════════════════════════════════════════════════════════════════
     Emoji picker (one shared instance, retargets to whichever input
     opened it)
     ═══════════════════════════════════════════════════════════════════ */

  const EMOJIS = [
    '😀','😁','😂','🤣','😊','😍','😘','😉','😎','🤩',
    '🥳','🙂','😅','😇','🤗','🤔','😐','😶','🙄','😏',
    '😴','😋','😛','🤪','😜','🤑','😳','😬','😢','😭',
    '😤','😡','🤯','🥺','😱','🥶','👍','👎','👏','🙌',
    '🙏','💪','✌️','🤞','👌','🤘','💯','✨','⭐','🌟',
    '🔥','💥','💢','💔','❤️','🧡','💛','💚','💙','💜',
    '🖤','🤍','💖','💕','🎉','🎊','🎁','🎂','🎈','💐',
    '🌹','🌸','🌷','🌻','☕','🍕','🍔','🍩','🍰','🥤',
    '🚀','✈️','🌍','🌈','☀️','🌙','⚡','💧','❄️','🤝',
    '👋','🤷','🙇','💃','🕺','😻','🐶','🐱','🦄','🌟',
  ];

  let pickerEl = null;
  let pickerTarget = null;

  function buildPicker() {
    if (pickerEl) return pickerEl;
    pickerEl = document.createElement('div');
    pickerEl.className = 'emoji-picker';
    pickerEl.hidden = true;
    pickerEl.innerHTML = `<div class="emoji-grid">${
      EMOJIS.map((e) => `<button type="button" data-emoji="${e}">${e}</button>`).join('')
    }</div>`;
    pickerEl.addEventListener('click', (e) => {
      const btn = e.target.closest('[data-emoji]');
      if (!btn || !pickerTarget) return;
      insertAtCursor(pickerTarget, btn.dataset.emoji);
      // synthetic input event so typing/preview logic re-runs
      pickerTarget.dispatchEvent(new Event('input', { bubbles: true }));
    });
    document.addEventListener('click', (e) => {
      if (!pickerEl || pickerEl.hidden) return;
      if (pickerEl.contains(e.target)) return;
      if (e.target.closest('[data-emoji-toggle]')) return;
      hidePicker();
    });
    return pickerEl;
  }

  function showPickerFor(triggerBtn, inputEl, anchorEl) {
    buildPicker();
    pickerTarget = inputEl;
    anchorEl.appendChild(pickerEl);
    pickerEl.hidden = false;
  }
  function hidePicker() {
    if (pickerEl) pickerEl.hidden = true;
  }

  function insertAtCursor(input, str) {
    const s = input.selectionStart ?? input.value.length;
    const e = input.selectionEnd ?? input.value.length;
    input.value = input.value.slice(0, s) + str + input.value.slice(e);
    const pos = s + str.length;
    input.setSelectionRange(pos, pos);
    input.focus();
  }

  /* ═══════════════════════════════════════════════════════════════════
     Rendering — shared between popup + full page
     ═══════════════════════════════════════════════════════════════════ */

  function appendMessage(state, m, animate) {
    if (state.messageIds.has(m.id)) return;

    // Race-dedupe: if the server's poll returns one of our own just-sent
    // messages before the send response has had a chance to swap the
    // optimistic tempId, re-bind the optimistic row instead of appending a
    // duplicate bubble. Matched by body against the in-flight send registry.
    if (m.is_mine && typeof m.id === 'number' && Array.isArray(state.pendingSends)) {
      const now = Date.now();
      state.pendingSends = state.pendingSends.filter((p) => now - p.sentAt < 30000);
      const idx = state.pendingSends.findIndex((p) => p.body === (m.body || ''));
      if (idx >= 0) {
        const { tempId } = state.pendingSends[idx];
        state.pendingSends.splice(idx, 1);
        const row = state.body.querySelector(`[data-msg-id="${tempId}"]`);
        if (row) {
          row.dataset.msgId = m.id;
          state.messageIds.delete(tempId);
          state.messageIds.add(m.id);
          state.lastMessageId = Math.max(state.lastMessageId, m.id);
          paintPeerReceipts(state);
          return;
        }
      }
    }

    state.messageIds.add(m.id);
    if (typeof m.id === 'number') {
      state.lastMessageId = Math.max(state.lastMessageId, m.id);
    }

    const day = m.created_at ? new Date(m.created_at).toDateString() : null;
    if (day && day !== state.lastMessageDate) {
      const sep = document.createElement('div');
      sep.className = 'msg-date-sep';
      sep.textContent = dateLabel(m.created_at);
      state.body.appendChild(sep);
      state.lastMessageDate = day;
    }

    const row = document.createElement('div');
    row.className = 'msg-row';
    row.dataset.mine = m.is_mine ? '1' : '0';
    row.dataset.msgId = m.id;

    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble';

    const hasImage = !!m.attachment_url;
    if (hasImage) {
      bubble.classList.add('is-image');
      const img = document.createElement('img');
      img.src = m.attachment_url;
      img.alt = '';
      img.addEventListener('click', () => window.open(m.attachment_url, '_blank'));
      bubble.appendChild(img);
      if (m.body) {
        const cap = document.createElement('div');
        cap.className = 'img-caption';
        cap.innerHTML = escWithLinks(m.body);
        bubble.appendChild(cap);
      }
    } else {
      bubble.innerHTML = escWithLinks(m.body || '');
    }
    row.appendChild(bubble);
    state.body.appendChild(row);

    const meta = document.createElement('div');
    meta.className = 'msg-meta';
    meta.style.alignSelf = m.is_mine ? 'flex-end' : 'flex-start';
    const status = messageStatus(m, state);
    meta.innerHTML = `${esc(formatTime(m.created_at))}${m.is_mine ? `<span class="msg-tick-wrap">${tickHtml(status)}</span>` : ''}`;
    state.body.appendChild(meta);

    if (!animate) bubble.style.animation = 'none';
    scrollToBottom(state.body, animate);
  }

  function paintPeerReceipts(state) {
    // Re-evaluate every "mine" row against the latest peerLast*Id markers.
    $$('.msg-row[data-mine="1"]', state.body).forEach((row) => {
      const meta = row.nextElementSibling;
      if (!meta?.classList.contains('msg-meta')) return;
      const wrap = meta.querySelector('.msg-tick-wrap');
      if (!wrap) return;
      const idRaw = row.dataset.msgId;
      const id = Number(idRaw);
      const m = { id, is_mine: true };
      wrap.innerHTML = tickHtml(messageStatus(m, state));
    });
  }

  function setTypingUI(state, on) {
    if (!state.typing) return;
    // Inline display beats the .chat-popup__typing { display: flex } rule
    // that otherwise overrides Tailwind's .hidden in this stylesheet.
    state.typing.style.display = on ? '' : 'none';
  }

  function scrollToBottom(el, smooth) {
    el.scrollTo({ top: el.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
  }

  function autoGrow(el) {
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 80) + 'px';
  }

  /* ═══════════════════════════════════════════════════════════════════
     Composer helpers — emoji button + image attachment
     ═══════════════════════════════════════════════════════════════════ */

  function bindEmojiButton(btn, input, anchor) {
    if (!btn) return;
    btn.setAttribute('data-emoji-toggle', '');
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      if (pickerEl && !pickerEl.hidden && pickerTarget === input) hidePicker();
      else showPickerFor(btn, input, anchor);
    });
  }

  function bindAttachment(state, attachBtn, fileInput, preview, previewImg, previewX) {
    state.attachmentFile = null;
    if (!attachBtn || !fileInput) return;

    attachBtn.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', () => {
      const f = fileInput.files?.[0];
      if (!f) return;
      if (!f.type.startsWith('image/')) {
        toast('Only image files are supported', 'bad');
        fileInput.value = '';
        return;
      }
      if (f.size > 5 * 1024 * 1024) {
        toast('Image must be 5 MB or smaller', 'bad');
        fileInput.value = '';
        return;
      }
      state.attachmentFile = f;
      previewImg.src = URL.createObjectURL(f);
      preview.hidden = false;
    });

    previewX?.addEventListener('click', () => {
      clearAttachment(state, fileInput, preview, previewImg);
    });
  }

  function clearAttachment(state, fileInput, preview, previewImg) {
    state.attachmentFile = null;
    if (fileInput) fileInput.value = '';
    if (previewImg?.src && previewImg.src.startsWith('blob:')) {
      URL.revokeObjectURL(previewImg.src);
    }
    if (previewImg) previewImg.src = '';
    if (preview) preview.hidden = true;
  }

  /* ═══════════════════════════════════════════════════════════════════
     Send — shared
     ═══════════════════════════════════════════════════════════════════ */

  async function postMessage(peerId, body, file) {
    const fd = new FormData();
    if (body) fd.append('body', body);
    if (file) fd.append('attachment', file);
    return api(URL_THREAD_SEND(peerId), { method: 'POST', body: fd });
  }

  /* ═══════════════════════════════════════════════════════════════════
     Chat-popup manager (Facebook-style floating boxes)
     ═══════════════════════════════════════════════════════════════════ */

  const popups = new Map();
  const getDock = () => document.getElementById('chatDock');

  function openPopup(peer) {
    const dock = getDock();
    if (!dock) return;
    if (popups.has(peer.id)) {
      const existing = popups.get(peer.id);
      existing.root.classList.remove('is-minimized');
      existing.input?.focus();
      return existing;
    }

    if (popups.size >= 3) {
      const [oldestId] = popups.keys();
      closePopup(oldestId);
    }

    const tpl = document.getElementById('tplChatPopup');
    if (!tpl) return;
    const node = tpl.content.firstElementChild.cloneNode(true);
    dock.appendChild(node);

    const state = {
      peer,
      root:        node,
      head:        node.querySelector('[data-cp-head]'),
      avatar:      node.querySelector('[data-cp-avatar]'),
      name:        node.querySelector('[data-cp-name]'),
      status:      node.querySelector('[data-cp-status]'),
      peerLink:    node.querySelector('[data-cp-peerlink]'),
      body:        node.querySelector('[data-cp-body]'),
      typing:      node.querySelector('[data-cp-typing]'),
      form:        node.querySelector('[data-cp-form]'),
      input:       node.querySelector('[data-cp-input]'),
      emojiBtn:    node.querySelector('[data-cp-emoji]'),
      attachBtn:   node.querySelector('[data-cp-attach]'),
      fileInput:   node.querySelector('[data-cp-file]'),
      preview:     node.querySelector('[data-cp-preview]'),
      previewImg:  node.querySelector('[data-cp-preview-img]'),
      previewX:    node.querySelector('[data-cp-preview-x]'),
      conversationId: null,
      lastMessageId:  0,
      peerLastReadId: 0,
      peerLastDeliveredId: 0,
      pollHandle:  null,
      typingSentAt: 0,
      typingStopSent: true,
      minimized: false,
      messageIds: new Set(),
      lastMessageDate: null,
      attachmentFile: null,
      pendingSends: [],
    };
    popups.set(peer.id, state);

    // Header
    state.avatar.innerHTML = avatarHtml(peer);
    if (peer.online) state.avatar.classList.add('is-online');
    state.name.textContent = peer.name || ('@' + peer.username);
    state.status.textContent = peer.online ? 'Active now' : '@' + (peer.username || '');
    if (peer.username) state.peerLink.href = APP.urls.profileBase + '/' + encodeURIComponent(peer.username);

    state.head.addEventListener('click', (e) => {
      if (e.target.closest('[data-cp-close], [data-cp-min], [data-cp-expand], [data-cp-peerlink]')) return;
      toggleMinimize(state);
    });
    node.querySelector('[data-cp-close]').addEventListener('click', () => closePopup(peer.id));
    node.querySelector('[data-cp-min]').addEventListener('click', () => toggleMinimize(state));
    node.querySelector('[data-cp-expand]').addEventListener('click', () => {
      window.location.href = URL_MESSAGES_PAGE + '/' + peer.id;
    });

    // Composer
    state.form.addEventListener('submit', (e) => { e.preventDefault(); sendFromPopup(state); });
    state.input.addEventListener('input', () => {
      autoGrow(state.input);
      if (state.input.value.trim().length > 0) pingTyping(state);
      else stopTyping(state);
    });
    state.input.addEventListener('blur', () => stopTyping(state));
    state.input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendFromPopup(state); }
    });

    bindEmojiButton(state.emojiBtn, state.input, state.form);
    bindAttachment(state, state.attachBtn, state.fileInput, state.preview, state.previewImg, state.previewX);

    initThread(state).then(() => state.input?.focus());
    return state;
  }

  function closePopup(userId) {
    const state = popups.get(userId);
    if (!state) return;
    clearInterval(state.pollHandle);
    state.root.style.animation = 'popup-in .18s ease-in reverse';
    setTimeout(() => state.root.remove(), 160);
    popups.delete(userId);
  }

  function toggleMinimize(state) {
    state.minimized = !state.minimized;
    state.root.classList.toggle('is-minimized', state.minimized);
    if (!state.minimized) {
      scrollToBottom(state.body, false);
      state.input?.focus();
      if (state.conversationId) {
        api(URL_THREAD_READ(state.peer.id), { method: 'POST' }).catch(()=>{});
        refreshUnreadBadge();
      }
    }
  }

  async function initThread(state) {
    try {
      // cache:'no-store' — always hit the server. Without it, some browsers
      // happily serve a stale response on close→reopen, which can return
      // is_mine flipped (msgs rendered as if from peer: white bubbles on left).
      const data = await api(URL_THREAD(state.peer.id), { cache: 'no-store' });
      state.conversationId = data.conversation_id;
      state.peerLastReadId = data.peer_last_read_id || 0;
      state.peerLastDeliveredId = data.peer_last_delivered_id || 0;
      state.peer = { ...state.peer, ...data.peer, online: !!data.peer_online };
      applyPopupOnline(state);

      state.body.innerHTML = '';
      state.messageIds.clear();
      state.lastMessageDate = null;
      (data.messages || []).forEach((m) => appendMessage(state, m, false));
      paintPeerReceipts(state);
      setTypingUI(state, !!data.peer_typing);
      scrollToBottom(state.body, false);

      clearInterval(state.pollHandle);
      state.pollHandle = setInterval(() => pollPopup(state), 2200);
      refreshUnreadBadge();
    } catch (e) {
      console.warn('Thread init failed', e);
      state.body.innerHTML = `<div class="text-center text-xs text-rose-500 py-6">Couldn’t load chat.</div>`;
    }
  }

  function applyPopupOnline(state) {
    const on = !!state.peer.online;
    state.avatar.classList.toggle('is-online', on);
    state.status.textContent = on ? 'Active now' : '@' + (state.peer.username || '');
  }

  // Wipe the on-screen thread when the server reports the conversation has
  // been emptied out from under us (e.g. BabyBoss's post-success reset).
  function maybeApplyServerWipe(state, data) {
    if (!('thread_max_id' in data)) return false;
    const serverMax = data.thread_max_id;
    if ((serverMax == null || serverMax < state.lastMessageId) && state.lastMessageId > 0) {
      state.body.innerHTML = '';
      state.messageIds && state.messageIds.clear && state.messageIds.clear();
      state.lastMessageId = 0;
      state.lastMessageDate = null;
      state.peerLastReadId = 0;
      state.peerLastDeliveredId = 0;
      state.pendingSends = [];
      return true;
    }
    return false;
  }

  async function pollPopup(state) {
    if (!state.conversationId) return;
    try {
      const data = await api(`${URL_THREAD_POLL(state.peer.id)}?after_id=${state.lastMessageId}`);
      if (maybeApplyServerWipe(state, data)) return;
      (data.messages || []).forEach((m) => appendMessage(state, m, true));
      let receiptsChanged = false;
      if (typeof data.peer_last_read_id === 'number' && data.peer_last_read_id !== state.peerLastReadId) {
        state.peerLastReadId = data.peer_last_read_id; receiptsChanged = true;
      }
      if (typeof data.peer_last_delivered_id === 'number' && data.peer_last_delivered_id !== state.peerLastDeliveredId) {
        state.peerLastDeliveredId = data.peer_last_delivered_id; receiptsChanged = true;
      }
      if (receiptsChanged) paintPeerReceipts(state);
      setTypingUI(state, !!data.peer_typing);
      if (typeof data.peer_online === 'boolean' && data.peer_online !== state.peer.online) {
        state.peer.online = data.peer_online;
        applyPopupOnline(state);
      }
      if ((data.messages || []).some((m) => !m.is_mine)) refreshUnreadBadge();
    } catch (_) {}
  }

  async function sendFromPopup(state) {
    const body = state.input.value.trim();
    const file = state.attachmentFile;
    if (!body && !file) return;
    state.typingSentAt = 0; state.typingStopSent = true;

    const tempId = 'tmp-' + Date.now();
    const optimistic = {
      id: tempId,
      body,
      is_mine: true,
      created_at: new Date().toISOString(),
      delivered: false,
      read: false,
      attachment_url: file ? URL.createObjectURL(file) : null,
      attachment_type: file ? 'image' : null,
    };
    appendMessage(state, optimistic, true);
    state.pendingSends = state.pendingSends || [];
    state.pendingSends.push({ tempId, body, sentAt: Date.now() });
    state.input.value = '';
    autoGrow(state.input);
    const fileSent = file;
    clearAttachment(state, state.fileInput, state.preview, state.previewImg);

    try {
      const { message } = await postMessage(state.peer.id, body, fileSent);
      // Send response won — clear this entry so the poll doesn't try to re-bind.
      state.pendingSends = state.pendingSends.filter((p) => p.tempId !== tempId);
      const row = state.body.querySelector(`[data-msg-id="${tempId}"]`);
      if (row) {
        row.dataset.msgId = message.id;
        state.messageIds.delete(tempId);
        state.messageIds.add(message.id);
        state.lastMessageId = Math.max(state.lastMessageId, message.id);
        // Replace optimistic blob image with the persisted URL so the bubble
        // keeps working after the blob is revoked.
        if (message.attachment_url) {
          const img = row.querySelector('img');
          if (img) img.src = message.attachment_url;
        }
      }
      paintPeerReceipts(state);
    } catch (e) {
      const row = state.body.querySelector(`[data-msg-id="${tempId}"]`);
      if (row) {
        const meta = row.nextElementSibling;
        if (meta?.classList.contains('msg-meta')) {
          meta.innerHTML = '<span class="text-rose-500">Failed to send · tap to retry</span>';
        }
      }
      toast('Message failed to send', 'bad');
    }
  }

  /* ─── Typing ─── */

  function pingTyping(state) {
    const now = Date.now();
    if (now - state.typingSentAt < 1500) return;
    state.typingSentAt = now;
    state.typingStopSent = false;
    if (!state.conversationId) return;
    api(URL_THREAD_TYPING(state.peer.id), { method: 'POST' }).catch(()=>{});
  }

  function stopTyping(state) {
    if (state.typingStopSent) return;
    if (!state.typingSentAt) return;
    state.typingStopSent = true;
    state.typingSentAt = 0;
    if (!state.conversationId) return;
    api(URL_THREAD_STOP(state.peer.id), { method: 'POST' }).catch(()=>{});
  }

  /* ═══════════════════════════════════════════════════════════════════
     Threads-list dropdown (navbar Messages button)
     ═══════════════════════════════════════════════════════════════════ */

  let threadsPanel = null;
  function ensureThreadsPanel() {
    if (threadsPanel) return threadsPanel;
    const btn = document.querySelector('[data-action="messages"]');
    if (!btn) return null;
    btn.style.position = 'relative';

    const panel = document.createElement('div');
    panel.setAttribute('data-menu-panel', '');
    panel.className = 'nav-drop msg-panel hidden origin-top-right border-slate-200 bg-white shadow-pop animate-fup';
    panel.innerHTML = `
      <div class="nav-drop-head">
        <div class="nav-drop-title">Messages</div>
        <a href="${URL_MESSAGES_PAGE}">See all</a>
      </div>
      <div id="msgDropdownList" class="nav-drop-list">
        <div class="py-8 text-center text-xs text-slate-400">Loading…</div>
      </div>
    `;
    const wrap = document.createElement('span');
    wrap.style.position = 'relative';
    wrap.style.display  = 'inline-flex';
    btn.parentNode.insertBefore(wrap, btn);
    wrap.appendChild(btn);
    wrap.appendChild(panel);
    threadsPanel = panel;

    document.addEventListener('click', (e) => {
      if (!wrap.contains(e.target)) panel.classList.add('hidden');
    });
    return panel;
  }

  async function renderThreadsDropdown() {
    const panel = ensureThreadsPanel();
    if (!panel) return;
    const list = panel.querySelector('#msgDropdownList');
    try {
      const { threads } = await api(URL_THREADS);
      if (!threads.length) {
        list.innerHTML = `<div class="py-8 text-center text-xs text-slate-400">No conversations yet.<br>Open a profile and tap <span class="font-semibold">Message</span>.</div>`;
        return;
      }
      list.innerHTML = threads.map(threadRowHtml).join('');
      list.querySelectorAll('[data-open-popup]').forEach((row) => {
        row.addEventListener('click', () => {
          const data = JSON.parse(row.dataset.peer);
          panel.classList.add('hidden');
          openPopup(data);
        });
      });
    } catch (_) {
      list.innerHTML = `<div class="py-8 text-center text-xs text-rose-400">Couldn’t load.</div>`;
    }
  }

  function threadRowHtml(t) {
    const av = t.peer?.profile_picture
      ? `<img src="${esc(t.peer.profile_picture)}" alt="">`
      : esc(initialOf(t.peer?.username || t.peer?.name));
    let snippet;
    if (t.last_message) {
      const prefix = t.last_message.is_mine ? 'You: ' : '';
      if (t.last_message.has_image && !t.last_message.body) snippet = prefix + '📷 Photo';
      else if (t.last_message.has_image) snippet = prefix + '📷 ' + t.last_message.body;
      else snippet = prefix + (t.last_message.body || '');
    } else snippet = 'Start the conversation…';
    const time = t.last_message?.created_at ? formatTime(t.last_message.created_at) : '';
    const badge = t.unread ? `<span class="ti-badge">${t.unread > 99 ? '99+' : t.unread}</span>` : '';
    const unreadCls = t.unread ? 'is-unread' : '';
    const onlineCls = t.peer?.online ? 'is-online' : '';
    return `
      <div class="thread-item ${unreadCls}" data-open-popup data-peer='${esc(JSON.stringify(t.peer))}'>
        <span class="ti-av ${onlineCls}">${av}</span>
        <div class="ti-meta">
          <div class="ti-name"><span class="truncate">${esc(t.peer?.name || '@'+t.peer?.username || 'User')}</span><span class="ti-time">${esc(time)}</span></div>
          <div class="ti-snippet">${esc(snippet)}</div>
        </div>
        ${badge}
      </div>
    `;
  }

  /* ═══════════════════════════════════════════════════════════════════
     Full /messages page
     ═══════════════════════════════════════════════════════════════════ */

  function initFullPage() {
    const page = document.getElementById('messengerPage');
    if (!page) return;

    let activePeer = null;
    let state = null;
    let pollHandle = null;
    const typing = { sentAt: 0, stopSent: true };

    const els = {
      list:       $('#threadsList', page),
      search:     $('#threadsSearch', page),
      refresh:    $('#threadsRefresh', page),
      empty:      $('#threadEmpty', page),
      active:     $('#threadActive', page),
      peerLink:   $('#peerLink', page),
      peerAv:     $('#peerAvatar', page),
      peerName:   $('#peerName', page),
      peerStatus: $('#peerStatus', page),
      body:       $('#threadBody', page),
      typingRow:  $('#typingRow', page),
      typingWho:  $('#typingWho', page),
      form:       $('#threadComposer', page),
      input:      $('#threadInput', page),
      emojiBtn:   $('#threadEmojiBtn', page),
      attachBtn:  $('#threadAttachBtn', page),
      fileInput:  $('#threadFile', page),
      preview:    $('#threadPreview', page),
      previewImg: $('#threadPreviewImg', page),
      previewX:   $('#threadPreviewX', page),
    };

    // Per-pane state for attachment
    const paneAttach = { file: null };

    async function loadThreads() {
      try {
        const { threads } = await api(URL_THREADS);
        if (!threads.length) {
          els.list.innerHTML = `<li class="px-4 py-10 text-center text-xs text-slate-400">No conversations yet.</li>`;
          return;
        }
        els.list.innerHTML = threads.map((t) => `<li>${threadRowHtml(t)}</li>`).join('');
        els.list.querySelectorAll('[data-open-popup]').forEach((row) => {
          row.addEventListener('click', () => {
            const peer = JSON.parse(row.dataset.peer);
            openInPane(peer);
            els.list.querySelectorAll('.thread-item').forEach((x) => x.classList.remove('is-active'));
            row.classList.add('is-active');
          });
        });
        if (activePeer) {
          const row = els.list.querySelector(`[data-peer*='"id":${activePeer.id}']`);
          row?.classList.add('is-active');
        }
      } catch (_) {
        els.list.innerHTML = `<li class="px-4 py-10 text-center text-xs text-rose-400">Failed to load.</li>`;
      }
    }

    async function openInPane(peer) {
      activePeer = peer;
      els.empty.classList.add('hidden');
      els.active.classList.remove('hidden');
      els.active.classList.add('flex');
      els.peerAv.innerHTML = avatarHtml(peer);
      els.peerAv.classList.toggle('is-online', !!peer.online);
      els.peerName.textContent = peer.name || ('@' + peer.username);
      els.peerStatus.textContent = peer.online ? 'Active now' : '@' + (peer.username || '');
      els.peerLink.href = peer.username ? APP.urls.profileBase + '/' + encodeURIComponent(peer.username) : '#';
      els.typingWho.textContent = (peer.name || peer.username || 'They') + ' is typing…';

      const targetUrl = URL_MESSAGES_PAGE + '/' + peer.id;
      if (location.pathname !== targetUrl) history.replaceState({}, '', targetUrl);

      state = {
        peer,
        body: els.body,
        typing: els.typingRow,
        conversationId: null,
        lastMessageId: 0,
        peerLastReadId: 0,
        peerLastDeliveredId: 0,
        messageIds: new Set(),
        lastMessageDate: null,
        attachmentFile: null,
        pendingSends: [],
      };
      els.body.innerHTML = '';
      clearAttachment(state, els.fileInput, els.preview, els.previewImg);

      try {
        const data = await api(URL_THREAD(peer.id), { cache: 'no-store' });
        state.conversationId = data.conversation_id;
        state.peerLastReadId = data.peer_last_read_id || 0;
        state.peerLastDeliveredId = data.peer_last_delivered_id || 0;
        state.peer.online = !!data.peer_online;
        applyPaneOnline();
        (data.messages || []).forEach((m) => appendMessage(state, m, false));
        paintPeerReceipts(state);
        setTypingUI(state, !!data.peer_typing);
        scrollToBottom(els.body, false);
        clearInterval(pollHandle);
        pollHandle = setInterval(pollPane, 2200);
        refreshUnreadBadge();
        loadThreads();
      } catch (_) {
        els.body.innerHTML = `<div class="text-center text-xs text-rose-500 py-10">Couldn’t load chat.</div>`;
      }
    }

    function applyPaneOnline() {
      if (!state) return;
      const on = !!state.peer.online;
      els.peerAv.classList.toggle('is-online', on);
      els.peerStatus.textContent = on ? 'Active now' : '@' + (state.peer.username || '');
    }

    async function pollPane() {
      if (!state?.conversationId) return;
      try {
        const data = await api(`${URL_THREAD_POLL(state.peer.id)}?after_id=${state.lastMessageId}`);
        if (maybeApplyServerWipe(state, data)) {
          loadThreads();
          return;
        }
        (data.messages || []).forEach((m) => appendMessage(state, m, true));
        let changed = false;
        if (typeof data.peer_last_read_id === 'number' && data.peer_last_read_id !== state.peerLastReadId) {
          state.peerLastReadId = data.peer_last_read_id; changed = true;
        }
        if (typeof data.peer_last_delivered_id === 'number' && data.peer_last_delivered_id !== state.peerLastDeliveredId) {
          state.peerLastDeliveredId = data.peer_last_delivered_id; changed = true;
        }
        if (changed) paintPeerReceipts(state);
        setTypingUI(state, !!data.peer_typing);
        if (typeof data.peer_online === 'boolean' && data.peer_online !== state.peer.online) {
          state.peer.online = data.peer_online;
          applyPaneOnline();
        }
        if ((data.messages || []).some((m) => !m.is_mine)) {
          refreshUnreadBadge();
          loadThreads();
        }
      } catch (_) {}
    }

    async function sendFromPane(e) {
      e?.preventDefault();
      const body = els.input.value.trim();
      const file = state?.attachmentFile;
      if ((!body && !file) || !state) return;
      typing.sentAt = 0; typing.stopSent = true;

      const tempId = 'tmp-' + Date.now();
      appendMessage(state, {
        id: tempId, body, is_mine: true,
        created_at: new Date().toISOString(),
        delivered: false, read: false,
        attachment_url: file ? URL.createObjectURL(file) : null,
        attachment_type: file ? 'image' : null,
      }, true);
      state.pendingSends = state.pendingSends || [];
      state.pendingSends.push({ tempId, body, sentAt: Date.now() });
      els.input.value = ''; autoGrow(els.input);
      const fileSent = file;
      clearAttachment(state, els.fileInput, els.preview, els.previewImg);

      try {
        const { message } = await postMessage(state.peer.id, body, fileSent);
        state.pendingSends = state.pendingSends.filter((p) => p.tempId !== tempId);
        const row = els.body.querySelector(`[data-msg-id="${tempId}"]`);
        if (row) {
          row.dataset.msgId = message.id;
          state.messageIds.delete(tempId);
          state.messageIds.add(message.id);
          state.lastMessageId = Math.max(state.lastMessageId, message.id);
          if (message.attachment_url) {
            const img = row.querySelector('img');
            if (img) img.src = message.attachment_url;
          }
        }
        paintPeerReceipts(state);
        loadThreads();
      } catch (_) {
        toast('Message failed to send', 'bad');
      }
    }

    function pingTypingPane() {
      const now = Date.now();
      if (now - typing.sentAt < 1500) return;
      typing.sentAt = now;
      typing.stopSent = false;
      if (state?.conversationId) {
        api(URL_THREAD_TYPING(state.peer.id), { method: 'POST' }).catch(()=>{});
      }
    }
    function stopTypingPane() {
      if (typing.stopSent) return;
      if (!typing.sentAt) return;
      typing.stopSent = true;
      typing.sentAt = 0;
      if (state?.conversationId) {
        api(URL_THREAD_STOP(state.peer.id), { method: 'POST' }).catch(()=>{});
      }
    }

    // Search filter
    els.search?.addEventListener('input', () => {
      const q = els.search.value.toLowerCase().trim();
      els.list.querySelectorAll('li').forEach((li) => {
        const text = li.textContent.toLowerCase();
        li.style.display = !q || text.includes(q) ? '' : 'none';
      });
    });

    els.refresh?.addEventListener('click', loadThreads);
    els.form?.addEventListener('submit', sendFromPane);
    els.input?.addEventListener('input', () => {
      autoGrow(els.input);
      if (els.input.value.trim().length > 0) pingTypingPane();
      else stopTypingPane();
    });
    els.input?.addEventListener('blur', stopTypingPane);
    els.input?.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendFromPane(e); }
    });

    // Emoji + attachment — bound to a tiny shim that reads/writes the active
    // state's attachmentFile so the picker/preview interact with the live thread.
    bindEmojiButton(els.emojiBtn, els.input, els.form);
    if (els.attachBtn && els.fileInput) {
      els.attachBtn.addEventListener('click', () => els.fileInput.click());
      els.fileInput.addEventListener('change', () => {
        const f = els.fileInput.files?.[0];
        if (!f) return;
        if (!f.type.startsWith('image/')) {
          toast('Only image files are supported', 'bad');
          els.fileInput.value = ''; return;
        }
        if (f.size > 5 * 1024 * 1024) {
          toast('Image must be 5 MB or smaller', 'bad');
          els.fileInput.value = ''; return;
        }
        if (!state) return;
        state.attachmentFile = f;
        els.previewImg.src = URL.createObjectURL(f);
        els.preview.hidden = false;
      });
      els.previewX?.addEventListener('click', () => {
        if (state) clearAttachment(state, els.fileInput, els.preview, els.previewImg);
      });
    }

    loadThreads();

    let initial = null;
    try { initial = JSON.parse(page.dataset.initialPeer || 'null'); } catch (_) {}
    if (initial && initial.id) openInPane(initial);
  }

  /* ═══════════════════════════════════════════════════════════════════
     Boot
     ═══════════════════════════════════════════════════════════════════ */

  function bindNavbarMessagesButton() {
    const btn = document.querySelector('[data-action="messages"]');
    if (!btn) return;
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      e.preventDefault();
      const panel = ensureThreadsPanel();
      if (!panel) return;
      const willOpen = panel.classList.contains('hidden');
      document.querySelectorAll('[data-menu-panel]').forEach((p) => p.classList.add('hidden'));
      panel.classList.toggle('hidden');
      if (willOpen) renderThreadsDropdown();
    }, true);
  }

  window.Tanbat = window.Tanbat || {};
  window.Tanbat.openChat = openPopup;

  document.addEventListener('DOMContentLoaded', () => {
    bindNavbarMessagesButton();
    refreshUnreadBadge();
    setInterval(refreshUnreadBadge, 12_000);

    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-message-user-id]');
      if (!trigger) return;
      e.preventDefault();
      const peer = {
        id:              Number(trigger.dataset.messageUserId),
        name:            trigger.dataset.messageUserName || '',
        username:        trigger.dataset.messageUserUsername || '',
        profile_picture: trigger.dataset.messageUserAvatar || null,
        online:          trigger.dataset.messageUserOnline === '1',
      };
      if (!peer.id || peer.id === APP.user.id) return;
      openPopup(peer);
    });

    initFullPage();
  });

})();
