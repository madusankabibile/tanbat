{{-- Floating chat-popup container. Rendered once globally; messenger.js
     mounts/unmounts individual popup boxes here.

     Desktop (≥768px): floats at bottom-right, multiple popups side-by-side.
     Mobile (<768px): each popup becomes a full-screen chat surface; only the
     most-recently opened popup is visible (older ones still in DOM, hidden). --}}
<div id="chatDock" class="pointer-events-none fixed bottom-0 right-4 z-[80] flex items-end gap-3" aria-live="polite"></div>

<style>
  /* ────── Mobile (<768px): each chat popup becomes a full-screen sheet.
     We pin each .chat-popup itself with position:fixed (instead of relying
     on the floating dock as a container). The dock collapses to a 0×0
     touch-transparent shell so it never blocks the page. ────── */
  @media (max-width: 767px) {
    #chatDock {
      position: fixed !important;
      inset: auto !important;
      bottom: 0 !important;
      right: 0 !important;
      width: 0 !important;
      height: 0 !important;
      pointer-events: none !important;
      z-index: 90 !important;
    }

    .chat-popup {
      position: fixed !important;
      inset: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
      height: 100dvh !important;
      max-height: none !important;
      border-radius: 0 !important;
      border: 0 !important;
      box-shadow: none !important;
      pointer-events: auto !important;
      z-index: 95 !important;
      display: flex !important;
      flex-direction: column !important;
    }
    /* If somehow more than one popup is opened, only show the most recent */
    .chat-popup ~ .chat-popup { z-index: 96 !important; }

    .chat-popup.is-minimized {
      top: auto !important;
      height: 52px !important;
    }
    .chat-popup.is-minimized .chat-popup__body,
    .chat-popup.is-minimized .chat-popup__form,
    .chat-popup.is-minimized .chat-popup__typing { display: none !important; }

    /* Bigger touch targets in the header */
    .chat-popup__head { padding: 12px 14px; }
    .chat-popup__avatar { width: 36px; height: 36px; font-size: 14px; }
    .chat-popup__name { font-size: 15px; }
    .chat-popup__status { font-size: 11px; }
    .chat-icon { width: 36px !important; height: 36px !important; border-radius: 9px; }
    .chat-icon svg { width: 18px !important; height: 18px !important; }

    /* Hide expand/minimize on mobile — we're already full screen */
    .chat-popup [data-cp-expand],
    .chat-popup [data-cp-min] { display: none !important; }
    /* Emphasise the close button so users see how to exit */
    .chat-popup [data-cp-close] { background: rgba(255,255,255,.18); }
    .chat-popup [data-cp-close]:hover { background: rgba(255,255,255,.3); }

    .chat-popup__body  { padding: 12px 14px; font-size: 14px; }
    .chat-popup__form  { padding: 10px 10px 12px; }
    .chat-popup__form textarea { font-size: 15px; max-height: 110px; }
    .chat-popup__send  { width: 40px; height: 40px; }
    .chat-popup__send svg { width: 16px; height: 16px; }

    /* Prevent the body from scrolling behind a full-screen chat */
    body:has(#chatDock .chat-popup) { overflow: hidden; }
  }
</style>

{{-- Templates pulled into JS via .innerHTML — kept here so Blade owns the markup. --}}
<template id="tplChatPopup">
  <div class="chat-popup pointer-events-auto">
    <header class="chat-popup__head" data-cp-head>
      <a class="chat-popup__peer" data-cp-peerlink>
        <span class="chat-popup__avatar" data-cp-avatar></span>
        <span class="chat-popup__id">
          <span class="chat-popup__name" data-cp-name>…</span>
          <span class="chat-popup__status" data-cp-status></span>
        </span>
      </a>
      <div class="chat-popup__actions">
        <button type="button" class="chat-icon" title="Open full chat" data-cp-expand>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
        </button>
        <button type="button" class="chat-icon" title="Minimize" data-cp-min>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/></svg>
        </button>
        <button type="button" class="chat-icon" title="Close" data-cp-close>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>
    </header>
    <div class="chat-popup__body" data-cp-body></div>
    <div class="chat-popup__typing" data-cp-typing style="display: none;">
      <span class="dot-jump"></span><span class="dot-jump"></span><span class="dot-jump"></span>
    </div>
    <div class="composer-preview" data-cp-preview hidden>
      <img data-cp-preview-img alt="">
      <button type="button" class="preview-x" data-cp-preview-x aria-label="Remove">×</button>
    </div>
    <form class="chat-popup__form" data-cp-form>
      <button type="button" class="composer-btn" data-cp-emoji aria-label="Emoji">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
      </button>
      <button type="button" class="composer-btn" data-cp-attach aria-label="Add image">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
      </button>
      <input type="file" accept="image/*" data-cp-file hidden>
      <textarea rows="1" data-cp-input placeholder="Write a message…"></textarea>
      <button type="submit" class="chat-popup__send" title="Send">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      </button>
    </form>
  </div>
</template>
