// Profile page logic: load profile, follow toggle, tabs, post feed
import { cardHTML, bindGallery, esc, isLight, mountVideoStage, newsbotCtaHTML, hydrateAdSlots, formatPostTime,
         applyReactionUI, setReactionCount, renderReactionSummary, bindReactions, dismissReactionPanel,
         bindDownloadCountdown } from './cards.js';
import { applyBannerTo } from './banners.js';

const APP = window.__APP__;
const PROFILE = window.__PROFILE__;

const $  = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));

const toast = window.Tanbat.toast;
const api   = window.Tanbat.api;
const openModal  = window.Tanbat.openModal;
const closeModal = window.Tanbat.closeModal;

const fmt = (n) => Number(n || 0).toLocaleString();

let profileData = null;
let userPosts = [];
let currentPlyr = null;

function applyBanner(p) {
  applyBannerTo(document.getElementById('pfCover'), p);
}

// ─────────── Load profile meta + posts ───────────
async function loadProfile() {
  try {
    const { profile } = await api(PROFILE.apiBase);
    profileData = profile;
    renderHeader(profile);
  } catch (e) {
    console.warn(e);
  }
}

function renderHeader(p) {
  $('#pfFollowers').textContent = fmt(p.counts.followers);
  $('#pfFollowing').textContent = fmt(p.counts.following);
  $('#pfPosts').textContent     = fmt(p.counts.posts);

  // Side mini stats
  const setText = (sel, val) => { const el = $(sel); if (el) el.textContent = fmt(val); };
  setText('#pfReach',    p.counts.reach);
  setText('#pfLikes',    p.counts.likes);
  setText('#pfComments', p.counts.comments);
  setText('#pfJoined',   p.created_at || '—');
  setText('#pfAboutPosts', p.counts.posts);
  setText('#pfAboutReach', p.counts.reach);

  applyBanner(p);
  renderBio(p);

  // Show self-only edit overlays (camera buttons on avatar + banner).
  if (p.is_self) {
    $('#pfCoverEdit')?.classList.remove('hidden');
    $('#pfAvatarEdit')?.classList.remove('hidden');
  }

  // Action buttons depend on whether viewer === profile
  const wrap = $('#pfActions');
  const shareBtn = `
    <button type="button" class="btn btn-message" data-act="share-profile">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
      Share profile
    </button>
  `;
  if (p.is_self) {
    wrap.innerHTML = `
      <button type="button" class="btn btn-edit" data-act="edit-profile">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit profile
      </button>
      ${shareBtn}
      <button type="button" class="btn btn-more" aria-label="More">
        <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </button>
    `;
  } else {
    wrap.innerHTML = `
      <button type="button" class="btn btn-follow ${p.is_following ? 'is-following' : ''}" data-act="follow"
        aria-pressed="${p.is_following ? 'true' : 'false'}">
        ${followIcon(p.is_following)}
        <span class="lbl">${p.is_following ? '<span>Following</span>' : 'Follow'}</span>
      </button>
      <button type="button" class="btn btn-message" data-act="message">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Message
      </button>
      ${shareBtn}
      <button type="button" class="btn btn-more" aria-label="More">
        <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
      </button>
    `;
  }
}

function renderBio(p) {
  const bio = $('#pfBio');
  if (bio) {
    bio.textContent = p.bio || '';
    bio.classList.toggle('hidden', !p.bio);
  }
  const nameEl = $('#pfIntroName'); if (nameEl) nameEl.textContent = p.name || '';
  const countryRow = $('#pfCountryRow');
  if (countryRow) {
    countryRow.classList.toggle('hidden', !p.country);
    const c = $('#pfIntroCountry'); if (c) c.textContent = p.country || '';
  }
}

function followIcon(following) {
  return following
    ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>`
    : `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>`;
}

async function loadPosts() {
  const loading = $('#profileLoading');
  const empty = $('#profileEmpty');
  loading.classList.remove('hidden');
  empty.classList.add('hidden');
  try {
    const { data } = await api(`${PROFILE.apiBase}/posts`);
    userPosts = data || [];
    renderPosts();
    renderPhotos();
    renderVideos();
  } catch (e) {
    toast('Could not load posts.', 'bad');
  } finally {
    loading.classList.add('hidden');
  }
}

function renderPosts() {
  const wrap = $('#profileFeed');
  if (!userPosts.length) {
    wrap.innerHTML = '';
    $('#profileEmpty').classList.remove('hidden');
    return;
  }
  $('#profileEmpty').classList.add('hidden');
  wrap.innerHTML = userPosts.map(cardHTML).join('');
  wrap.querySelectorAll('.gallery-wrap').forEach(bindGallery);
  hydrateAdSlots(wrap);
  bindDownloadCountdown(wrap);
}

function renderPhotos() {
  const images = userPosts.filter((p) => p.type === 'image').flatMap((p) =>
    (p.media || []).filter((m) => m?.url).map((m) => ({ url: m.url, postId: p.id }))
  );
  const grid = $('#photosGrid');
  if (!images.length) {
    grid.innerHTML = '';
    $('#photosEmpty').classList.remove('hidden');
  } else {
    $('#photosEmpty').classList.add('hidden');
    grid.innerHTML = images.map((img) =>
      `<a href="#" data-post-id="${img.postId}" data-open><img src="${esc(img.url)}" loading="lazy" alt=""></a>`
    ).join('');
  }
}

function renderVideos() {
  const videos = userPosts.filter((p) => p.type === 'video');
  const grid = $('#videosGrid');
  if (!videos.length) {
    grid.innerHTML = '';
    $('#videosEmpty').classList.remove('hidden');
  } else {
    $('#videosEmpty').classList.add('hidden');
    grid.innerHTML = videos.map((p) => {
      const thumb = p.thumbnail || p.media?.[0]?.url || '';
      return `
        <a href="#" data-post-id="${p.id}" data-open>
          <img src="${esc(thumb)}" loading="lazy" alt="">
          <div class="play"><span><svg viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></div>
        </a>
      `;
    }).join('');
  }
}

// ─────────── Tab switching ───────────
function bindTabs() {
  $$('.pf-tab').forEach((btn) => {
    btn.addEventListener('click', () => {
      const tab = btn.dataset.tab;
      $$('.pf-tab').forEach((b) => b.classList.toggle('active', b === btn));
      $$('.pf-pane').forEach((p) => p.classList.toggle('hidden', p.dataset.pane !== tab));
    });
  });
}

// ─────────── Click handlers ───────────
function bindActions() {
  // Cover + avatar edit overlays live outside #pfActions, so listen on the
  // whole profile header instead.
  $('.profile-head').addEventListener('click', (e) => {
    const overlay = e.target.closest('[data-act="edit-avatar"], [data-act="edit-banner"]');
    if (!overlay) return;
    if (overlay.dataset.act === 'edit-avatar') $('#pfAvatarFile').click();
    else $('#pfBannerFile').click();
  });

  $('#pfActions').addEventListener('click', async (e) => {
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const act = b.dataset.act;
    if (act === 'edit-profile') {
      if (!profileData) return;
      $('#epName').value    = profileData.name || '';
      $('#epBio').value     = profileData.bio || '';
      $('#epCountry').value = profileData.country || '';
      $('#epBioCount').textContent = ($('#epBio').value || '').length;
      openModal('modalEditProfile');
      return;
    }
    if (act === 'message') {
      if (!APP.user) return toast('Sign in to send messages', 'bad');
      if (!profileData) return;
      window.Tanbat?.openChat?.({
        id:              PROFILE.id,
        name:            profileData.name || PROFILE.name,
        username:        profileData.username || PROFILE.username,
        profile_picture: profileData.profile_picture || null,
        online:          false,
      });
      return;
    }
    if (act === 'follow') {
      if (!APP.user) return toast('Sign in to follow people', 'bad');
      const wasFollowing = b.classList.contains('is-following');
      // optimistic
      setFollowUI(!wasFollowing);
      try {
        const res = await api(PROFILE.followUrl, { method: 'POST' });
        setFollowUI(res.following);
        $('#pfFollowers').textContent = fmt(res.followers);
        if (profileData) {
          profileData.is_following = res.following;
          profileData.counts.followers = res.followers;
        }
      } catch (err) {
        setFollowUI(wasFollowing);
        toast(err.message || 'Could not update follow', 'bad');
      }
    }
  });
}

function setFollowUI(following) {
  const b = $('#pfActions [data-act="follow"]');
  if (!b) return;
  b.classList.toggle('is-following', following);
  b.setAttribute('aria-pressed', following ? 'true' : 'false');
  const inner = b.innerHTML = `
    ${followIcon(following)}
    <span class="lbl">${following ? '<span>Following</span>' : 'Follow'}</span>
  `;
}

function bindFeedClicks() {
  const handle = async (e) => {
    // Gallery prev/next
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
    // Action buttons
    const actBtn = e.target.closest('[data-act]');
    if (actBtn) {
      const card = actBtn.closest('[data-post-id]');
      const id = card?.dataset.postId;
      if (!id) return;
      const act = actBtn.dataset.act;
      if (act === 'like')    return handleReact(card, actBtn.dataset.reaction || 'like');
      if (act === 'comment') return openPostModal(id);
      if (act === 'share')   return handleShare(id);
      return;
    }
    // Open detail modal from media click
    const opener = e.target.closest('[data-open]');
    if (!opener) return;
    e.preventDefault();
    const card = opener.closest('[data-post-id]');
    const id = card?.dataset.postId;
    if (id) openPostModal(id);
  };
  $('#profileFeed').addEventListener('click', handle);
  $('#photosGrid').addEventListener('click', handle);
  $('#videosGrid').addEventListener('click', handle);
  bindReactions($('#profileFeed'), handleReact);
  bindReactions($('#photosGrid'), handleReact);
  bindReactions($('#videosGrid'), handleReact);
}

async function handleReact(card, reactionKey) {
  if (!card) return;
  if (!APP.user) return toast('Sign in to react to posts', 'bad');
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
    const local = userPosts.find((p) => String(p.id) === String(id));
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

function handleShare(id) {
  const post = userPosts.find((p) => String(p.id) === String(id));
  const isArticle = post?.type === 'article';
  const url = isArticle && post.view_url ? post.view_url : `${APP.urls.home}#post-${id}`;
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

function bindProfilePostMenu() {
  if (!window.Tanbat?.bindPostMenu) return;
  const handlers = {
    getPost: (id) => userPosts.find((p) => String(p.id) === String(id)),
    onRemove: (id) => {
      userPosts = userPosts.filter((p) => String(p.id) !== String(id));
      renderPosts();
      renderPhotos();
      renderVideos();
    },
    onChange: (id, patch) => {
      const local = userPosts.find((p) => String(p.id) === String(id));
      if (local) Object.assign(local, patch);
      if (patch && (patch.status_text != null || patch.description != null || patch.title != null)) {
        renderPosts();
        renderPhotos();
        renderVideos();
      }
    },
  };
  const feed = $('#profileFeed');
  if (feed) window.Tanbat.bindPostMenu(feed, handlers);
}

function bindProfileShareButton() {
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-act="share-profile"]');
    if (!btn) return;
    const url = `${APP.urls.profileBase}/${encodeURIComponent(PROFILE.username)}`;
    const title = `${PROFILE.name} (@${PROFILE.username}) on Tanbat`;
    const image = profileData?.profile_picture || '';
    window.Tanbat?.openShare?.({ kind: 'profile', url, title, image });
  });
}

// Detail modal — reuse the same template that home uses (it lives in partials)
let currentPost = null;
function syncCardViews(postId, viewsCount) {
  const n = Number(viewsCount || 0);
  const local = userPosts.find((p) => String(p.id) === String(postId));
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
      void badge.offsetWidth;
      badge.classList.add('is-pulse');
    }
  }
  if (badge) badge.setAttribute('title', `${n.toLocaleString()} views`);
}

async function openPostModal(id) {
  try {
    const { post, comments } = await api(`${APP.urls.api.posts}/${id}`);
    currentPost = post;
    const stage = $('#postLeftStage');
    if (currentPlyr) { try { currentPlyr.destroy(); } catch (_) {} currentPlyr = null; }
    if (post.type === 'video') {
      currentPlyr = mountVideoStage(stage, post);
    } else if (post.type === 'image' && post.media?.length) {
      stage.innerHTML = `<div class="flex h-full w-full snap-x snap-mandatory overflow-x-auto scrollbar-none">${post.media.map((m) =>
        `<img src="${esc(m.url)}" class="h-full w-full shrink-0 snap-center object-contain" alt="">`).join('')}</div>`;
    } else if (post.type === 'status') {
      if (post.user?.username === 'robert_sheffield') {
        const img = post.media?.[0]?.url ? `<img src="${esc(post.media[0].url)}" class="w-full rounded-xl" alt="">` : '';
        const desc = post.description ? `<p class="news-desc">${esc(post.description)}</p>` : '';
        stage.innerHTML = `<div class="h-full w-full overflow-y-auto bg-white"><div class="mx-auto max-w-xl p-5">${img}<div class="news-topic">${esc(post.status_text || '')}</div>${desc}${newsbotCtaHTML()}</div></div>`;
      } else {
        const bg = post.bg_color || '#1E1B4B';
        const fg = post.font_color || (isLight(bg) ? '#1E1B4B' : '#FFFFFF');
        stage.innerHTML = `<div class="grid h-full w-full place-items-center p-10 text-center" style="background:${esc(bg)};color:${esc(fg)}"><div><div class="text-2xl font-semibold leading-snug">${esc(post.status_text || '')}</div></div></div>`;
      }
    }

    const u = post.user || {};
    const av = u.profile_picture
      ? `<img src="${esc(u.profile_picture)}" class="h-10 w-10 rounded-full object-cover" alt="">`
      : `<span class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white">${esc((u.username||'U').charAt(0).toUpperCase())}</span>`;
    $('#postAuthor').innerHTML = `${av}<div><div class="text-sm font-semibold text-slate-900">${esc(u.name || u.username)}</div><div class="text-xs text-slate-500">&#64;${esc(u.username || '')}</div></div>`;
    $('#postDescription').innerHTML = post.description
      ? esc(post.description)
      : (post.status_text ? esc(post.status_text) : '<span class="text-slate-400">No description.</span>');
    $('#postLikes').textContent = post.likes_count ?? 0;
    const viewsEl = document.querySelector('[data-modal-views]');
    if (viewsEl) viewsEl.textContent = Number(post.views_count || 0).toLocaleString();
    syncCardViews(post.id, post.views_count);
    $('#postCreatedAt').textContent = formatPostTime(post.created_at_iso);
    $('#postLeftMeta').innerHTML = '';
    renderComments(comments || []);
    openModal('modalPost');
  } catch (err) { toast(err.message, 'bad'); }
}

function renderComments(comments) {
  const wrap = $('#postComments');
  if (!comments.length) {
    wrap.innerHTML = `<div class="py-6 text-center text-xs text-slate-400">Be the first to comment.</div>`;
    return;
  }
  wrap.innerHTML = comments.map((c) => {
    const av = c.user?.profile_picture
      ? `<img src="${esc(c.user.profile_picture)}" class="h-8 w-8 rounded-full object-cover" alt="">`
      : `<span class="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-xs font-bold text-white">${esc((c.user?.username||'U').charAt(0).toUpperCase())}</span>`;
    return `<div class="flex gap-2">${av}<div class="flex-1 rounded-2xl bg-slate-50 px-3 py-2"><div class="flex items-baseline gap-2"><span class="text-xs font-semibold text-slate-900">${esc(c.user?.username || c.user?.name || 'User')}</span><span class="text-[10px] text-slate-400">${esc(c.created_at || '')}</span></div><div class="mt-0.5 text-sm text-slate-700">${esc(c.body)}</div></div></div>`;
  }).join('');
}

function bindCommentForm() {
  const form = $('#commentForm');
  if (!form) return;
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!currentPost) return;
    const body = $('#commentBody').value.trim();
    if (!body) return;
    try {
      const { comment } = await api(`${APP.urls.api.posts}/${currentPost.id}/comments`, { method: 'POST', body: { body } });
      $('#commentBody').value = '';
      const wrap = $('#postComments');
      if (wrap.querySelector('.text-center')) wrap.innerHTML = '';
      const av = comment.user?.profile_picture
        ? `<img src="${comment.user.profile_picture}" class="h-8 w-8 rounded-full object-cover" alt="">`
        : `<span class="grid h-8 w-8 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-xs font-bold text-white">${(comment.user?.username||'U').charAt(0).toUpperCase()}</span>`;
      wrap.insertAdjacentHTML('afterbegin', `<div class="flex gap-2">${av}<div class="flex-1 rounded-2xl bg-slate-50 px-3 py-2"><div class="flex items-baseline gap-2"><span class="text-xs font-semibold text-slate-900">${esc(comment.user?.username || 'You')}</span><span class="text-[10px] text-slate-400">${esc(comment.created_at)}</span></div><div class="mt-0.5 text-sm text-slate-700">${esc(comment.body)}</div></div></div>`);
    } catch (err) { toast(err.message, 'bad'); }
  });
}

// Tear down the Plyr instance whenever the post-detail modal closes so YouTube
// iframes don't keep playing audio in the background.
function bindPlyrTeardown() {
  const teardown = () => {
    if (currentPlyr) { try { currentPlyr.destroy(); } catch (_) {} currentPlyr = null; }
  };
  document.addEventListener('click', (e) => {
    const closer = e.target.closest('[data-close-modal]');
    if (closer && closer.closest('#modalPost')) teardown();
  }, true);
  document.addEventListener('click', (e) => {
    const m = document.getElementById('modalPost');
    if (m && e.target === m) teardown();
  }, true);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      const m = document.getElementById('modalPost');
      if (m && !m.classList.contains('hidden')) teardown();
    }
  });
}

// ─────────── Edit profile (self only) ───────────
function bindEditProfileForm() {
  const form = $('#formEditProfile');
  if (!form) return;
  const bioInput = $('#epBio');
  const bioCount = $('#epBioCount');
  bioInput?.addEventListener('input', () => {
    bioCount.textContent = bioInput.value.length;
  });
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!profileData?.is_self) return;
    const body = {
      name:    $('#epName').value.trim(),
      bio:     $('#epBio').value,
      country: $('#epCountry').value.trim().toUpperCase() || null,
    };
    try {
      const res = await api(PROFILE.updateUrl, { method: 'PATCH', body });
      Object.assign(profileData, res.profile);
      // Header name + intro details
      $('.pf-name').textContent = profileData.name;
      renderBio(profileData);
      closeModal('modalEditProfile');
      toast('Profile updated', 'good');
    } catch (err) {
      toast(err.message || 'Could not update profile', 'bad');
    }
  });
}

function bindMediaUploads() {
  const av = $('#pfAvatarFile');
  const bn = $('#pfBannerFile');
  av?.addEventListener('change', async () => {
    const f = av.files?.[0]; if (!f) return;
    const fd = new FormData(); fd.append('avatar', f);
    try {
      const res = await api(PROFILE.avatarUrl, { method: 'POST', body: fd });
      if (profileData) profileData.profile_picture = res.profile_picture;
      const slot = $('#pfAvatar');
      // Replace the inner avatar (img or letter span) but keep the edit button.
      const edit = $('#pfAvatarEdit');
      const old = slot.querySelector('img, span');
      if (old) old.remove();
      const img = document.createElement('img');
      img.src = res.profile_picture; img.alt = '';
      slot.insertBefore(img, edit);
      toast('Profile picture updated', 'good');
    } catch (err) {
      toast(err.message || 'Upload failed', 'bad');
    } finally {
      av.value = '';
    }
  });
  bn?.addEventListener('change', async () => {
    const f = bn.files?.[0]; if (!f) return;
    const fd = new FormData(); fd.append('banner', f);
    try {
      const res = await api(PROFILE.bannerUrl, { method: 'POST', body: fd });
      if (profileData) profileData.banner_image = res.banner_image;
      applyBanner({ id: PROFILE.id, banner_image: res.banner_image });
      toast('Cover updated', 'good');
    } catch (err) {
      toast(err.message || 'Upload failed', 'bad');
    } finally {
      bn.value = '';
    }
  });
}

// ─────────── Boot ───────────
document.addEventListener('DOMContentLoaded', () => {
  loadProfile();
  loadPosts();
  bindTabs();
  bindActions();
  bindFeedClicks();
  bindCommentForm();
  bindPlyrTeardown();
  bindProfilePostMenu();
  bindProfileShareButton();
  bindEditProfileForm();
  bindMediaUploads();
});
