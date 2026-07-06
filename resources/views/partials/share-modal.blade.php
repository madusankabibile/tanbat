{{-- ─────────── SHARE MODAL ───────────
     Reusable share popup. Two modes:
       • post     — shares a post URL (#post-{id})
       • profile  — shares /u/{username}
     Mode + payload are set by JS via window.Tanbat.openShare({...}). --}}

<div id="modalShare" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/55 p-4 backdrop-blur-sm">
  <div class="share-card animate-fup">
    <div class="share-head">
      <div>
        <h3 class="share-title">Share</h3>
        <p class="share-sub" data-share-sub>Choose where to send this.</p>
      </div>
      <button type="button" data-close-modal aria-label="Close" class="share-close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <div class="share-preview" data-share-preview>
      <div class="share-preview-icon" data-share-icon>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
      </div>
      <div class="share-preview-meta">
        <div class="share-preview-title" data-share-preview-title>Loading…</div>
        <div class="share-preview-url" data-share-preview-url></div>
      </div>
    </div>

    <div class="share-grid">
      @auth
      <button type="button" data-share-target="my_profile" class="share-target">
        <span class="ic ic-myprofile"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg></span>
        <span class="lbl">Your profile</span>
      </button>
      @endauth
      <button type="button" data-share-target="copy" class="share-target">
        <span class="ic ic-copy"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></span>
        <span class="lbl">Copy link</span>
      </button>
      <button type="button" data-share-target="whatsapp" class="share-target">
        <span class="ic ic-whatsapp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.5 3.5A11.7 11.7 0 0 0 12 .2 11.8 11.8 0 0 0 1.7 17.9L.2 23.8l6-1.6A11.8 11.8 0 0 0 23.8 12a11.7 11.7 0 0 0-3.3-8.5zM12 21.5a9.5 9.5 0 0 1-4.8-1.3l-.3-.2-3.5 1 1-3.4-.2-.4A9.5 9.5 0 1 1 21.5 12 9.6 9.6 0 0 1 12 21.5zm5.2-7.1c-.3-.1-1.7-.8-2-.9s-.4-.1-.6.2-.7.8-.8 1-.3.1-.6 0a7.8 7.8 0 0 1-3.8-3.3c-.3-.5.3-.5.8-1.6a.5.5 0 0 0 0-.5c0-.1-.6-1.5-.9-2s-.5-.5-.6-.5h-.5a1 1 0 0 0-.8.4 3.1 3.1 0 0 0-1 2.3 5.4 5.4 0 0 0 1.1 2.9 12.4 12.4 0 0 0 4.7 4.2c2.8 1.1 2.8.7 3.3.7a2.8 2.8 0 0 0 1.8-1.3 2.2 2.2 0 0 0 .2-1.3c-.1-.1-.3-.2-.6-.3z"/></svg></span>
        <span class="lbl">WhatsApp</span>
      </button>
      <button type="button" data-share-target="facebook" class="share-target">
        <span class="ic ic-facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.3c-1.3 0-1.7.8-1.7 1.6V12h2.9l-.5 2.9h-2.4v7A10 10 0 0 0 22 12z"/></svg></span>
        <span class="lbl">Facebook</span>
      </button>
      <button type="button" data-share-target="twitter" class="share-target">
        <span class="ic ic-twitter"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.9 2H22l-7.5 8.6L23 22h-6.8l-5.3-7-6.1 7H1.7l8-9.2L1 2h7l4.8 6.4L18.9 2zm-1.2 18h1.8L6.4 4H4.5l13.2 16z"/></svg></span>
        <span class="lbl">X / Twitter</span>
      </button>
      <button type="button" data-share-target="linkedin" class="share-target">
        <span class="ic ic-linkedin"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.4 20.4h-3.6v-5.6c0-1.3 0-3-1.9-3s-2.2 1.5-2.2 3v5.6H9.1V9h3.4v1.6h.1a3.8 3.8 0 0 1 3.4-1.9c3.6 0 4.4 2.4 4.4 5.5v6.2zM5.1 7.4a2.1 2.1 0 1 1 2.1-2.1 2.1 2.1 0 0 1-2.1 2.1zM6.9 20.4H3.4V9h3.5v11.4zM22 0H2a2 2 0 0 0-2 2v20a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2z"/></svg></span>
        <span class="lbl">LinkedIn</span>
      </button>
      <button type="button" data-share-target="telegram" class="share-target">
        <span class="ic ic-telegram"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9.8 15.6 9.6 19a.7.7 0 0 0 1.2.5l2.7-2.6 5.6 4.1c1 .6 1.8.3 2-.9l3.6-17C24.9 1.4 24-.1 22.7.5L1.4 8.7c-1.5.6-1.4 1.4-.3 1.7l5.5 1.7L19.5 4.2c.6-.4 1.2-.2.7.2"/></svg></span>
        <span class="lbl">Telegram</span>
      </button>
      <button type="button" data-share-target="reddit" class="share-target">
        <span class="ic ic-reddit"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-1.2-1-2.2-2.2-2.2a2.2 2.2 0 0 0-1.5.6c-1.4-1-3.3-1.5-5.4-1.6l.9-4.3 3 .7v.1a1.7 1.7 0 1 0 .2-.7l-3.3-.7a.3.3 0 0 0-.4.3l-1 4.6c-2.1.1-4 .7-5.4 1.6a2.2 2.2 0 1 0-2.4 3.6 4 4 0 0 0 0 .7c0 3.4 4 6.1 9 6.1s9-2.7 9-6.1a4 4 0 0 0 0-.7A2.2 2.2 0 0 0 22 12zM7 13.6a1.5 1.5 0 1 1 1.5 1.5A1.5 1.5 0 0 1 7 13.6zm8.4 4a5.1 5.1 0 0 1-3.4 1 5.1 5.1 0 0 1-3.4-1 .4.4 0 0 1 .5-.5 4.4 4.4 0 0 0 2.9.8 4.4 4.4 0 0 0 2.9-.8.4.4 0 1 1 .5.5zm-.1-2.5a1.5 1.5 0 1 1 1.5-1.5 1.5 1.5 0 0 1-1.5 1.5z"/></svg></span>
        <span class="lbl">Reddit</span>
      </button>
      <button type="button" data-share-target="email" class="share-target">
        <span class="ic ic-email"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
        <span class="lbl">Email</span>
      </button>
    </div>

    <div class="share-link-row">
      <input type="text" data-share-link-input readonly class="share-link-input" aria-label="Share link">
      <button type="button" data-share-target="copy" class="share-link-copy">Copy</button>
    </div>
  </div>
</div>

<style>
  #modalShare .share-card {
    width: 100%; max-width: 480px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 24px 60px rgba(20,20,50,.25), 0 2px 4px rgba(20,20,50,.06);
    overflow: hidden;
  }
  .share-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 20px 22px 14px;
    border-bottom: 1px solid #F1F5F9;
  }
  .share-title { font-size: 18px; font-weight: 800; color: #1E1B4B; letter-spacing: -.01em; }
  .share-sub { font-size: 12.5px; color: #6B7280; margin-top: 2px; }
  .share-close {
    grid-template-columns: 1fr; display: grid; place-items: center;
    height: 36px; width: 36px; border-radius: 9999px;
    color: #64748B; transition: background .15s ease, color .15s ease;
  }
  .share-close:hover { background: #F1F5F9; color: #1E1B4B; }
  .share-close svg { height: 18px; width: 18px; }

  .share-preview {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 22px;
    background: linear-gradient(135deg, #F8FAFC 0%, #F5F3FF 100%);
    border-bottom: 1px solid #F1F5F9;
  }
  .share-preview-icon {
    height: 44px; width: 44px; border-radius: 12px;
    background: linear-gradient(135deg, #6C63FF, #FF6584); color: #fff;
    display: grid; place-items: center; flex-shrink: 0;
    box-shadow: 0 6px 14px rgba(108,99,255,.25);
    overflow: hidden;
  }
  .share-preview-icon img { height: 100%; width: 100%; object-fit: cover; }
  .share-preview-icon svg { height: 20px; width: 20px; }
  .share-preview-meta { min-width: 0; }
  .share-preview-title {
    font-size: 14px; font-weight: 700; color: #1E1B4B;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .share-preview-url {
    margin-top: 2px;
    font-size: 11.5px; color: #6B7280;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }

  .share-grid {
    display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px;
    padding: 18px 22px 8px;
  }
  @media (max-width: 420px) { .share-grid { grid-template-columns: repeat(3, 1fr); } }
  .share-target {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    padding: 12px 4px;
    border-radius: 12px;
    background: transparent;
    transition: background .15s ease, transform .12s ease;
  }
  .share-target:hover { background: #F8FAFC; }
  .share-target:active { transform: scale(.96); }
  .share-target .ic {
    display: grid; place-items: center;
    height: 44px; width: 44px; border-radius: 9999px;
    color: #fff; transition: transform .12s ease, box-shadow .12s ease;
  }
  .share-target:hover .ic { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(20,20,50,.18); }
  .share-target .ic svg { height: 20px; width: 20px; }
  .share-target .lbl { font-size: 11.5px; font-weight: 600; color: #475569; }

  .ic-copy       { background: linear-gradient(135deg, #6C63FF, #5A52D5); }
  .ic-myprofile  { background: linear-gradient(135deg, #6C63FF, #FF6584); }
  .ic-whatsapp { background: #25D366; }
  .ic-facebook { background: #1877F2; }
  .ic-twitter  { background: #0F141A; }
  .ic-linkedin { background: #0A66C2; }
  .ic-telegram { background: #229ED9; }
  .ic-reddit   { background: #FF4500; }
  .ic-email    { background: #475569; }

  .share-link-row {
    display: flex; gap: 8px;
    padding: 10px 22px 22px;
  }
  .share-link-input {
    flex: 1; min-width: 0;
    border: 1px solid #E5E7EB; border-radius: 10px;
    padding: 9px 12px;
    font-size: 12.5px; color: #1E1B4B;
    background: #F8FAFC;
  }
  .share-link-input:focus { outline: none; border-color: #6C63FF; background: #fff; }
  .share-link-copy {
    border-radius: 10px;
    padding: 0 16px;
    font-size: 13px; font-weight: 700; color: #fff;
    background: linear-gradient(135deg, #6C63FF, #5A52D5);
    transition: transform .12s ease, box-shadow .15s ease;
  }
  .share-link-copy:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(108,99,255,.32); }

  /* ─────────── Post-card 3-dot dropdown ─────────── */
  .post-menu-wrap { margin-left: auto; position: relative; }
  .post-menu-wrap .post-menu { margin-left: 0; }
  .post-menu-panel {
    position: absolute; top: calc(100% + 6px); right: 0;
    min-width: 230px;
    background: #fff;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(20,20,50,.14), 0 1px 2px rgba(20,20,50,.05);
    padding: 6px;
    z-index: 25;
    opacity: 0; transform: translateY(-4px) scale(.98);
    pointer-events: none;
    transition: opacity .12s ease, transform .12s ease;
  }
  .post-menu-wrap.is-open .post-menu-panel { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
  .post-menu-item {
    display: flex; align-items: flex-start; gap: 10px;
    width: 100%;
    padding: 9px 10px;
    border-radius: 8px;
    text-align: left;
    color: #1E293B;
    transition: background .12s ease, color .12s ease;
  }
  .post-menu-item:hover { background: #F1F5F9; }
  .post-menu-item .pmi-ic {
    display: grid; place-items: center; flex-shrink: 0;
    height: 26px; width: 26px; border-radius: 8px;
    background: #F1F5F9; color: #475569;
  }
  .post-menu-item .pmi-ic svg { height: 15px; width: 15px; }
  .post-menu-item .pmi-text { display: flex; flex-direction: column; line-height: 1.25; }
  .post-menu-item .pmi-title { font-size: 13.5px; font-weight: 600; color: #1E1B4B; }
  .post-menu-item .pmi-sub { font-size: 11px; color: #6B7280; margin-top: 1px; }
  .post-menu-item.is-danger:hover { background: #FEF2F2; color: #B91C1C; }
  .post-menu-item.is-danger:hover .pmi-ic { background: #FECACA; color: #B91C1C; }
  .post-menu-item.is-danger .pmi-title { color: inherit; }
  .post-menu-sep { height: 1px; background: #F1F5F9; margin: 4px 6px; }
  .post-menu-item.is-active .pmi-ic { background: #EEF2FF; color: #4338CA; }

  /* ─────────── Save + Save-to-folder split control ─────────── */
  .post-menu-save-wrap { position: relative; display: flex; align-items: stretch; }
  .post-menu-save-wrap .post-menu-item { flex: 1; min-width: 0; }
  .post-menu-save-toggle {
    width: 28px;
    display: grid; place-items: center;
    border-radius: 8px;
    color: #94A3B8;
    transition: background .12s ease, color .12s ease, transform .12s ease;
  }
  .post-menu-save-toggle:hover { background: #EEF2FF; color: #4338CA; }
  .post-menu-save-wrap.is-saved .post-menu-save-toggle { color: #4338CA; }
  .post-save-submenu {
    position: absolute; top: 0; right: calc(100% + 6px);
    width: 240px;
    background: #fff;
    border: 1px solid #E5E7EB;
    border-radius: 12px;
    box-shadow: 0 12px 32px rgba(20,20,50,.14), 0 1px 2px rgba(20,20,50,.05);
    padding: 6px;
    z-index: 26;
    opacity: 0; transform: translateX(4px) scale(.98);
    pointer-events: none;
    transition: opacity .12s ease, transform .12s ease;
    max-height: 320px; overflow-y: auto;
  }
  .post-save-submenu.is-open { opacity: 1; transform: translateX(0) scale(1); pointer-events: auto; }
  .post-save-submenu .psm-head {
    padding: 6px 10px 4px;
    font-size: 11px; font-weight: 700; color: #94A3B8;
    text-transform: uppercase; letter-spacing: .4px;
  }
  .post-save-submenu .psm-item {
    display: flex; align-items: center; gap: 8px;
    width: 100%; padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px; color: #1E293B;
    transition: background .12s ease, color .12s ease;
  }
  .post-save-submenu .psm-item:hover { background: #F1F5F9; }
  .post-save-submenu .psm-item.is-active { background: #EEF2FF; color: #4338CA; font-weight: 700; }
  .post-save-submenu .psm-item svg { color: #94A3B8; flex-shrink: 0; }
  .post-save-submenu .psm-item.is-active svg { color: #4338CA; }
  .post-save-submenu .psm-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .post-save-submenu .psm-count {
    flex-shrink: 0;
    font-size: 11px; font-weight: 600; color: #94A3B8;
    background: #F1F5F9; padding: 1px 7px; border-radius: 9999px;
  }
  .post-save-submenu .psm-item.is-active .psm-count { background: #C7D2FE; color: #4338CA; }
  .post-save-submenu .psm-sep { height: 1px; background: #F1F5F9; margin: 4px 6px; }
  .post-save-submenu .psm-new {
    display: flex; align-items: center; gap: 6px;
    padding: 4px 6px;
  }
  .post-save-submenu .psm-new input {
    flex: 1; min-width: 0;
    background: #F8FAFC;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    padding: 7px 9px;
    font-size: 12.5px;
    outline: none;
  }
  .post-save-submenu .psm-new input:focus { border-color: #C5C9FF; background: #fff; }
  .post-save-submenu .psm-new button {
    display: grid; place-items: center;
    height: 28px; width: 28px; border-radius: 8px;
    background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
    flex-shrink: 0;
  }
  .post-save-submenu .psm-loading {
    padding: 16px 10px; text-align: center; color: #94A3B8; font-size: 12.5px;
  }

  /* ─────────── Edit Post Modal ─────────── */
  #modalEditPost .edit-card {
    width: 100%; max-width: 560px;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 24px 60px rgba(20,20,50,.25);
    overflow: hidden;
  }
  #modalEditPost .edit-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 18px 22px;
    border-bottom: 1px solid #F1F5F9;
  }
  #modalEditPost .edit-head h3 { font-size: 17px; font-weight: 800; color: #1E1B4B; }
  #modalEditPost .edit-body { padding: 18px 22px; }
  #modalEditPost .edit-label {
    display: block; margin-bottom: 6px;
    font-size: 12px; font-weight: 700; color: #475569;
    text-transform: uppercase; letter-spacing: .3px;
  }
  #modalEditPost .edit-input {
    width: 100%;
    border: 1px solid #E5E7EB; border-radius: 10px;
    padding: 10px 12px;
    font-size: 14px; color: #1E1B4B;
    background: #F8FAFC;
  }
  #modalEditPost .edit-input:focus { outline: none; border-color: #6C63FF; background: #fff; }
  #modalEditPost textarea.edit-input { resize: vertical; min-height: 110px; line-height: 1.5; }
  #modalEditPost .edit-actions {
    display: flex; justify-content: flex-end; gap: 8px;
    padding: 12px 22px 22px;
    border-top: 1px solid #F1F5F9;
  }
  #modalEditPost .edit-btn {
    padding: 9px 18px; border-radius: 10px;
    font-size: 13.5px; font-weight: 700;
  }
  #modalEditPost .edit-btn-cancel { background: #F1F5F9; color: #1E1B4B; }
  #modalEditPost .edit-btn-cancel:hover { background: #E2E8F0; }
  #modalEditPost .edit-btn-save {
    background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
    box-shadow: 0 4px 14px rgba(108,99,255,.32);
  }
  #modalEditPost .edit-btn-save:hover { transform: translateY(-1px); }
</style>

{{-- ─────────── CONFIRM DIALOG ───────────
     Generic yes/no confirmation. Driven by Tanbat.confirmDialog({...}). --}}
<div id="modalConfirm" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-900/60 p-4 backdrop-blur-sm">
  <div class="confirm-card animate-fup" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="confirm-icon" data-confirm-icon>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>
    </div>
    <h3 id="confirmTitle" class="confirm-title" data-confirm-title>Are you sure?</h3>
    <p class="confirm-message" data-confirm-message>This action can't be undone.</p>
    <div class="confirm-actions">
      <button type="button" class="confirm-btn confirm-btn-cancel" data-confirm-cancel>Cancel</button>
      <button type="button" class="confirm-btn confirm-btn-ok" data-confirm-ok>Confirm</button>
    </div>
  </div>
</div>

{{-- ─────────── SAVED POSTS MODAL ─────────── --}}
@auth
<div id="modalSaved" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/55 p-4 backdrop-blur-sm">
  <div class="saved-card animate-fup">
    <div class="saved-head">
      <div>
        <h3 class="saved-title">Saved posts</h3>
        <p class="saved-sub">Organize your bookmarks into folders.</p>
      </div>
      <button type="button" data-close-modal aria-label="Close" class="share-close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="saved-layout">
      <aside class="saved-sidebar">
        <ul class="saved-folders" id="savedFolders">
          <li class="saved-folder is-active" data-folder="all">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
            <span class="sf-name">All saved</span>
            <span class="sf-count" data-folder-total>0</span>
          </li>
        </ul>
        <form class="saved-newfolder" id="savedNewFolder">
          <input type="text" maxlength="60" placeholder="New folder…" required>
          <button type="submit" aria-label="Create folder">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          </button>
        </form>
      </aside>
      <div class="saved-body" id="savedList">
        <div class="saved-loading">Loading saved posts…</div>
      </div>
    </div>
  </div>
</div>
@endauth

<style>
  /* ─────────── Confirm dialog ─────────── */
  #modalConfirm .confirm-card {
    width: 100%; max-width: 400px;
    background: #fff;
    border-radius: 18px;
    padding: 24px 22px 20px;
    text-align: center;
    box-shadow: 0 24px 60px rgba(20,20,50,.28);
  }
  .confirm-icon {
    margin: 0 auto 14px;
    display: grid; place-items: center;
    height: 56px; width: 56px; border-radius: 9999px;
    background: #FEE2E2; color: #B91C1C;
  }
  .confirm-icon svg { height: 24px; width: 24px; }
  .confirm-icon.is-info { background: #EEF2FF; color: #4338CA; }
  .confirm-title { font-size: 18px; font-weight: 800; color: #1E1B4B; letter-spacing: -.01em; }
  .confirm-message { margin-top: 6px; font-size: 13.5px; color: #475569; line-height: 1.5; }
  .confirm-actions {
    margin-top: 20px;
    display: flex; gap: 8px;
  }
  .confirm-btn {
    flex: 1;
    padding: 10px 14px; border-radius: 10px;
    font-size: 13.5px; font-weight: 700;
    transition: background .15s ease, transform .12s ease, box-shadow .15s ease;
  }
  .confirm-btn-cancel { background: #F1F5F9; color: #1E1B4B; }
  .confirm-btn-cancel:hover { background: #E2E8F0; }
  .confirm-btn-ok {
    background: linear-gradient(135deg, #6C63FF, #5A52D5); color: #fff;
    box-shadow: 0 4px 14px rgba(108,99,255,.32);
  }
  .confirm-btn-ok:hover { transform: translateY(-1px); }
  .confirm-btn-ok.is-danger {
    background: linear-gradient(135deg, #EF4444, #B91C1C);
    box-shadow: 0 4px 14px rgba(239,68,68,.32);
  }

  /* ─────────── Saved posts modal ─────────── */
  #modalSaved .saved-card {
    width: 100%; max-width: 920px;
    max-height: 86vh;
    display: flex; flex-direction: column;
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 24px 60px rgba(20,20,50,.25);
    overflow: hidden;
  }
  .saved-head {
    display: flex; align-items: flex-start; justify-content: space-between;
    padding: 20px 22px 14px;
    border-bottom: 1px solid #F1F5F9;
  }
  .saved-title { font-size: 18px; font-weight: 800; color: #1E1B4B; letter-spacing: -.01em; }
  .saved-sub { font-size: 12.5px; color: #6B7280; margin-top: 2px; }
  .saved-layout {
    flex: 1; min-height: 0;
    display: grid;
    grid-template-columns: 220px 1fr;
  }
  @media (max-width: 640px) {
    .saved-layout { grid-template-columns: 1fr; }
    .saved-sidebar { border-right: 0 !important; border-bottom: 1px solid #F1F5F9; max-height: 200px; }
  }
  .saved-sidebar {
    border-right: 1px solid #F1F5F9;
    background: #fff;
    display: flex; flex-direction: column;
    overflow: hidden;
  }
  .saved-folders {
    flex: 1; overflow-y: auto;
    padding: 8px;
    margin: 0;
    list-style: none;
  }
  .saved-folder {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px;
    border-radius: 8px;
    font-size: 13px;
    color: #1E293B;
    cursor: pointer;
    transition: background .12s ease, color .12s ease;
    position: relative;
  }
  .saved-folder:hover { background: #F1F5F9; }
  .saved-folder.is-active { background: #EEF2FF; color: #4338CA; font-weight: 700; }
  .saved-folder svg { color: #94A3B8; flex-shrink: 0; }
  .saved-folder.is-active svg { color: #4338CA; }
  .saved-folder .sf-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .saved-folder .sf-count {
    flex-shrink: 0;
    font-size: 11px; font-weight: 700; color: #94A3B8;
    background: #F1F5F9; padding: 1px 7px; border-radius: 9999px;
  }
  .saved-folder.is-active .sf-count { background: #C7D2FE; color: #4338CA; }
  .saved-folder .sf-delete {
    display: none;
    background: transparent; color: #94A3B8;
    padding: 2px; border-radius: 6px;
  }
  .saved-folder:hover .sf-delete { display: inline-grid; place-items: center; }
  .saved-folder .sf-delete:hover { background: #FEE2E2; color: #B91C1C; }
  .saved-newfolder {
    border-top: 1px solid #F1F5F9;
    padding: 8px;
    display: flex; gap: 6px;
  }
  .saved-newfolder input {
    flex: 1; min-width: 0;
    background: #F8FAFC;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    padding: 7px 9px;
    font-size: 12.5px;
    outline: none;
  }
  .saved-newfolder input:focus { border-color: #C5C9FF; background: #fff; }
  .saved-newfolder button {
    display: grid; place-items: center;
    height: 32px; width: 32px; border-radius: 8px;
    background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
    flex-shrink: 0;
  }
  .saved-body {
    flex: 1; overflow-y: auto;
    padding: 18px 22px 22px;
    background: #F8FAFC;
    min-height: 0;
  }
  .saved-body .feed-stack { gap: 14px; }
  .saved-loading, .saved-empty {
    padding: 40px 8px; text-align: center; color: #94A3B8; font-size: 13px;
  }
  .saved-empty .empty-icon {
    margin: 0 auto 10px; display: grid; place-items: center;
    height: 56px; width: 56px; border-radius: 9999px;
    background: #EEF2FF; color: #4338CA;
  }
  .saved-empty .empty-icon svg { height: 22px; width: 22px; }
  .saved-empty .empty-title { color: #1E1B4B; font-weight: 700; font-size: 14px; margin-bottom: 2px; }

  /* ─────────── Left-panel Saved CTA ─────────── */
  .saved-cta {
    display: flex; align-items: center; gap: 12px;
    padding: 14px 16px;
    width: 100%; text-align: left;
    background: linear-gradient(135deg,#FFFFFF 0%,#FFF1F5 100%);
    border: 1px solid #FFD2DE;
    cursor: pointer;
    transition: transform .15s ease, box-shadow .15s ease;
  }
  .saved-cta:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(244,63,94,.16); }
  .saved-cta .saved-orb {
    height: 36px; width: 36px; border-radius: 12px;
    background: linear-gradient(135deg,#F43F5E,#FF6584); color: #fff;
    display: grid; place-items: center; flex-shrink: 0;
    box-shadow: 0 6px 16px rgba(244,63,94,.3);
  }
  .saved-cta .saved-orb svg { width: 18px; height: 18px; }
  .saved-cta .saved-text { display: flex; flex-direction: column; line-height: 1.2; min-width: 0; }
  .saved-cta .saved-title-row { display: flex; align-items: center; gap: 6px; }
  .saved-cta .saved-title-row strong { font-size: 14px; font-weight: 700; color: #1E1B4B; }
  .saved-cta .saved-count-pill {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 22px; height: 18px; padding: 0 6px;
    border-radius: 9999px;
    background: #F43F5E; color: #fff;
    font-size: 10.5px; font-weight: 800;
  }
  .saved-cta .saved-count-pill.hidden { display: none; }
  .saved-cta .saved-sub-text { font-size: 11px; color: #6B7280; margin-top: 3px; }
  .saved-cta .saved-arrow { margin-left: auto; color: #94A3B8; }
</style>

{{-- ─────────── EDIT POST MODAL ─────────── --}}
<div id="modalEditPost" class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/55 p-4 backdrop-blur-sm">
  <div class="edit-card animate-fup">
    <div class="edit-head">
      <h3>Edit post</h3>
      <button type="button" data-close-modal aria-label="Close" class="share-close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <form id="formEditPost">
      <div class="edit-body space-y-3">
        <div data-edit-field="title" class="hidden">
          <label class="edit-label">Title</label>
          <input type="text" name="title" class="edit-input" maxlength="240">
        </div>
        <div data-edit-field="short_description" class="hidden">
          <label class="edit-label">Short description</label>
          <textarea name="short_description" class="edit-input" rows="3" maxlength="2000"></textarea>
        </div>
        <div data-edit-field="status_text" class="hidden">
          <label class="edit-label">Status text</label>
          <textarea name="status_text" class="edit-input" rows="4" maxlength="2000"></textarea>
        </div>
        <div data-edit-field="description" class="hidden">
          <label class="edit-label">Description</label>
          <textarea name="description" class="edit-input" rows="4" maxlength="2000"></textarea>
        </div>
      </div>
      <div class="edit-actions">
        <button type="button" data-close-modal class="edit-btn edit-btn-cancel">Cancel</button>
        <button type="submit" class="edit-btn edit-btn-save">Save changes</button>
      </div>
    </form>
  </div>
</div>
