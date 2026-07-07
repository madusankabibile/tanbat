@extends('layouts.app')
@section('title', $profile->name . ' — Tanbat')

@section('content')
@include('partials.navbar')

<main class="mx-auto w-full max-w-[1100px] px-3 py-6 sm:px-5">

  {{-- ─────────── Profile header (FB-style) ─────────── --}}
  <section class="profile-head panel">
    <div class="pf-cover" id="pfCover">
      <button type="button" id="pfCoverEdit" class="pf-cover-edit hidden" data-act="edit-banner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        <span>Change cover</span>
      </button>
    </div>

    <div class="pf-body">
      <div class="pf-avatar" id="pfAvatar">
        @if($profile->profile_picture)
          <img src="{{ asset('storage/' . $profile->profile_picture) }}" alt="">
        @else
          <span>{{ strtoupper(substr($profile->username ?? $profile->name ?? 'U', 0, 1)) }}</span>
        @endif
        <button type="button" id="pfAvatarEdit" class="pf-avatar-edit hidden" data-act="edit-avatar" aria-label="Change profile picture">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </button>
      </div>

      <div class="pf-id">
        <h1 class="pf-name">{{ $profile->name }}</h1>
        <div class="pf-handle">&#64;{{ $profile->username }}</div>
        <div class="pf-stats">
          <span><b id="pfFollowers">0</b> followers</span>
          <span><b id="pfFollowing">0</b> following</span>
          <span><b id="pfPosts">0</b> posts</span>
        </div>
      </div>

      <div class="pf-actions" id="pfActions">
        {{-- Buttons get filled in by JS based on whether viewer === profile --}}
      </div>
    </div>

    <nav class="pf-tabs">
      <button type="button" class="pf-tab active" data-tab="posts">Posts</button>
      <button type="button" class="pf-tab" data-tab="about">About</button>
      <button type="button" class="pf-tab" data-tab="photos">Photos</button>
      <button type="button" class="pf-tab" data-tab="videos">Videos</button>
    </nav>
  </section>

  {{-- ─────────── Layout below header ─────────── --}}
  <div class="profile-grid">

    {{-- Left: Intro / details --}}
    <aside class="pf-side">
      <section class="panel pf-intro">
        <h3>Intro</h3>
        <p id="pfBio" class="pf-bio {{ $profile->bio ? '' : 'hidden' }}">{{ $profile->bio }}</p>
        <ul class="pf-intro-list">
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span id="pfIntroName">{{ $profile->name }}</span>
          </li>
          <li id="pfCountryRow" class="{{ $profile->country ? '' : 'hidden' }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>From <span id="pfIntroCountry">{{ $profile->country }}</span></span>
          </li>
          <li>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <span>Joined <span id="pfJoined">…</span></span>
          </li>
          @if($profile->role === 'admin')
          <li class="role-admin">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
            <span>Tanbat admin</span>
          </li>
          @endif
        </ul>
      </section>

      <section class="panel pf-mini-stats">
        <h3>Activity</h3>
        <div class="mini-grid">
          <div><span class="num" id="pfReach">0</span><span class="lbl">Reach</span></div>
          <div><span class="num" id="pfLikes">0</span><span class="lbl">Likes</span></div>
          <div><span class="num" id="pfComments">0</span><span class="lbl">Comments</span></div>
        </div>
      </section>
    </aside>

    {{-- Right: tab content --}}
    <div class="pf-content">
      <div data-pane="posts" class="pf-pane">
        <div id="profileFeed" class="feed-stack"></div>
        <div id="profileEmpty" class="hidden py-12 text-center">
          <div class="text-sm font-semibold text-slate-700">No posts yet</div>
          <div class="text-xs text-slate-500">When this user shares something, it will appear here.</div>
        </div>
        <div id="profileLoading" class="py-10 text-center text-sm text-slate-500">Loading posts…</div>
      </div>
      <div data-pane="about" class="pf-pane hidden">
        <div class="panel p-5">
          <h3 class="mb-2 text-base font-bold">About {{ $profile->name }}</h3>
          <p class="text-sm text-slate-600 leading-relaxed">A Tanbat member who has shared
          <span id="pfAboutPosts">0</span> posts and reached <span id="pfAboutReach">0</span> viewers.</p>
        </div>
      </div>
      <div data-pane="photos" class="pf-pane hidden">
        <div id="photosGrid" class="photo-grid"></div>
        <div id="photosEmpty" class="hidden py-12 text-center text-sm text-slate-500">No photos yet.</div>
      </div>
      <div data-pane="videos" class="pf-pane hidden">
        <div id="videosGrid" class="video-grid"></div>
        <div id="videosEmpty" class="hidden py-12 text-center text-sm text-slate-500">No videos yet.</div>
      </div>
    </div>

  </div>
</main>

@include('partials.post-detail-modal')
@include('partials.share-modal')

{{-- ─────────── EDIT PROFILE MODAL (self-only) ─────────── --}}
<div id="modalEditProfile" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-sm">
  <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-pop animate-fup">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
      <h3 class="text-lg font-bold text-slate-900">Edit profile</h3>
      <button type="button" data-close-modal class="grid h-9 w-9 place-items-center rounded-full text-slate-500 hover:bg-slate-100">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>

    <form id="formEditProfile" class="mt-4 space-y-4">
      <label class="block">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Name</span>
        <input id="epName" type="text" maxlength="80" required
          class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
      </label>
      <label class="block">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Bio</span>
        <textarea id="epBio" rows="3" maxlength="280"
          class="mt-1 w-full resize-none rounded-lg border border-slate-200 px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          placeholder="A short description of yourself."></textarea>
        <span class="mt-1 block text-right text-[11px] text-slate-400"><span id="epBioCount">0</span>/280</span>
      </label>
      <label class="block">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Country</span>
        <input id="epCountry" type="text" maxlength="5"
          class="mt-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm uppercase focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
          placeholder="LK, US, IN…">
      </label>

      <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-3">
        <button type="button" data-close-modal class="rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Cancel</button>
        <button type="submit" class="rounded-lg bg-brand-500 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-600">Save</button>
      </div>
    </form>
  </div>
</div>

<input id="pfAvatarFile" type="file" accept="image/*" class="hidden">
<input id="pfBannerFile" type="file" accept="image/*" class="hidden">

<script>
  window.__PROFILE__ = {
    id:       {{ $profile->id }},
    name:     {!! json_encode($profile->name) !!},
    username: {!! json_encode($profile->username) !!},
    apiBase:  {!! json_encode(url('/api/users/' . $profile->id)) !!},
    followUrl:{!! json_encode(url('/api/users/' . $profile->id . '/follow')) !!},
    updateUrl:{!! json_encode(url('/api/users/' . $profile->id)) !!},
    avatarUrl:{!! json_encode(url('/api/users/' . $profile->id . '/avatar')) !!},
    bannerUrl:{!! json_encode(url('/api/users/' . $profile->id . '/banner')) !!},
  };
</script>

<style>
.panel { background:#fff; border:1px solid #E5E7EB; border-radius:12px; box-shadow:0 1px 2px rgba(20,20,50,.04); }

/* Header */
.profile-head { overflow:hidden; }
.pf-cover {
  position: relative;
  height: 220px;
  background-color: #5A52D5;
  background-position: center;
  background-size: cover;
  background-repeat: no-repeat;
}
.pf-cover-edit {
  position: absolute; right: 14px; bottom: 14px;
  z-index: 5;
  display: inline-flex; align-items: center; gap: 6px;
  padding: 8px 12px; border-radius: 8px;
  background: rgba(15,15,30,.6);
  color: #fff; font-size: 13px; font-weight: 600;
  cursor: pointer;
  backdrop-filter: blur(4px);
  transition: background .15s ease;
}
.pf-cover-edit:hover { background: rgba(15,15,30,.78); }
.pf-cover-edit svg { width: 16px; height: 16px; }
.pf-cover-edit.hidden { display: none; }
.pf-body {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: end;
  gap: 18px;
  padding: 0 24px 18px;
  position: relative;
  margin-top: -54px;
}
.pf-avatar {
  position: relative;
  height: 156px; width: 156px; border-radius: 9999px;
  background: linear-gradient(135deg,#6C63FF,#FF6584);
  border: 5px solid #fff;
  color:#fff; font-weight:800; font-size:54px;
  display:grid; place-items:center;
  overflow:hidden;
  box-shadow: 0 6px 24px rgba(20,20,50,.18);
}
.pf-avatar img { height:100%; width:100%; object-fit:cover; }
.pf-avatar-edit {
  position: absolute; right: 6px; bottom: 6px;
  display: grid; place-items: center;
  height: 36px; width: 36px; border-radius: 9999px;
  background: #1E1B4B; color: #fff;
  border: 3px solid #fff;
  box-shadow: 0 2px 8px rgba(20,20,50,.25);
  transition: background .15s ease;
}
.pf-avatar-edit:hover { background: #3730A3; }
.pf-avatar-edit svg { width: 16px; height: 16px; }
.pf-avatar-edit.hidden { display: none; }
.pf-id { padding-bottom: 8px; min-width: 0; }
.pf-name { font-size: 28px; font-weight: 800; color: #1E1B4B; letter-spacing: -.01em; line-height: 1.1; }
.pf-handle { margin-top: 2px; font-size: 14px; color: #6B7280; }
.pf-stats {
  margin-top: 8px;
  display: flex; gap: 16px; flex-wrap: wrap;
  font-size: 13px; color: #475569;
}
.pf-stats b { color: #1E1B4B; font-weight: 700; }

.pf-actions { display: flex; gap: 8px; padding-bottom: 12px; flex-wrap: wrap; justify-content: flex-end; }
.pf-actions .btn {
  display: inline-flex; align-items: center; gap: 6px;
  height: 38px; padding: 0 16px;
  border-radius: 8px;
  font-size: 14px; font-weight: 700;
  cursor: pointer;
  transition: background .15s ease, color .15s ease, transform .1s ease;
}
.pf-actions .btn svg { width: 16px; height: 16px; }
.pf-actions .btn-follow {
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  box-shadow: 0 4px 14px rgba(108,99,255,.32);
}
.pf-actions .btn-follow:hover { transform: translateY(-1px); }
.pf-actions .btn-follow.is-following {
  background: #F1F5F9; color: #1E1B4B; box-shadow: none;
}
.pf-actions .btn-follow.is-following:hover { background: #FEE2E2; color: #B91C1C; }
.pf-actions .btn-follow.is-following:hover .lbl::after { content: 'Unfollow'; }
.pf-actions .btn-follow.is-following:hover .lbl span { display: none; }
.pf-actions .btn-message {
  background: #F1F5F9; color: #1E1B4B;
}
.pf-actions .btn-message:hover { background: #E2E8F0; }
.pf-actions .btn-more {
  background: #F1F5F9; color: #1E1B4B;
  height: 38px; width: 38px; padding: 0; justify-content: center;
}
.pf-actions .btn-more:hover { background: #E2E8F0; }
.pf-actions .btn-edit {
  background: #F1F5F9; color: #1E1B4B;
}
.pf-actions .btn-edit:hover { background: #E2E8F0; }

.pf-tabs {
  display: flex; gap: 4px;
  border-top: 1px solid #F1F5F9;
  padding: 4px 16px;
  overflow-x: auto;
  scrollbar-width: none;
}
.pf-tabs::-webkit-scrollbar { display: none; }
.pf-tab {
  padding: 14px 16px;
  font-size: 14px; font-weight: 700;
  color: #6B7280;
  border-bottom: 3px solid transparent;
  white-space: nowrap;
  margin-bottom: -1px;
}
.pf-tab:hover { background: #F8FAFC; }
.pf-tab.active { color: #6C63FF; border-color: #6C63FF; }

/* Layout below header */
.profile-grid {
  margin-top: 18px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 18px;
}
@media (min-width: 1024px) {
  .profile-grid { grid-template-columns: 360px minmax(0,1fr); }
}
.pf-side { display: flex; flex-direction: column; gap: 18px; }

.pf-intro { padding: 14px 18px; }
.pf-intro h3 { font-size: 16px; font-weight: 800; color: #1E1B4B; }
.pf-bio { margin-top: 8px; font-size: 13px; color: #334155; line-height: 1.55; white-space: pre-wrap; word-break: break-word; }
.pf-bio.hidden { display: none; }
.pf-intro-list { margin-top: 10px; display: flex; flex-direction: column; gap: 8px; }
.pf-intro-list li.hidden { display: none; }
.pf-intro-list li { display: flex; align-items: center; gap: 10px; font-size: 13px; color: #475569; }
.pf-intro-list svg { width: 16px; height: 16px; color: #94A3B8; flex-shrink: 0; }
.pf-intro-list li.role-admin { color: #B45309; }
.pf-intro-list li.role-admin svg { color: #B45309; }

.pf-mini-stats { padding: 14px 18px; }
.pf-mini-stats h3 { font-size: 16px; font-weight: 800; color: #1E1B4B; }
.mini-grid { margin-top: 10px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.mini-grid > div { background: #F8FAFC; border-radius: 8px; padding: 10px; text-align: center; }
.mini-grid .num { display: block; font-size: 16px; font-weight: 800; color: #1E1B4B; font-variant-numeric: tabular-nums; }
.mini-grid .lbl { display: block; margin-top: 2px; font-size: 10px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: .3px; }

.pf-content { min-width: 0; }
.pf-pane { min-width: 0; }
.pf-pane.hidden { display: none; }

/* Photos / videos grids */
.photo-grid {
  display: grid; gap: 6px;
  grid-template-columns: repeat(3, 1fr);
}
@media (min-width: 640px) { .photo-grid { grid-template-columns: repeat(4, 1fr); } }
.photo-grid a {
  display: block; aspect-ratio: 1 / 1; overflow: hidden; border-radius: 8px; background: #F1F5F9;
}
.photo-grid img { width: 100%; height: 100%; object-fit: cover; transition: transform .25s ease; }
.photo-grid a:hover img { transform: scale(1.04); }

.video-grid {
  display: grid; gap: 12px;
  grid-template-columns: repeat(2, 1fr);
}
@media (min-width: 768px) { .video-grid { grid-template-columns: repeat(3, 1fr); } }
.video-grid a {
  display: block; position: relative; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 10px; background: #000;
}
.video-grid img { width: 100%; height: 100%; object-fit: cover; }
.video-grid .play {
  position: absolute; inset: 0; display: grid; place-items: center; pointer-events: none;
}
.video-grid .play span {
  width: 44px; height: 32px; border-radius: 8px;
  background: rgba(220,38,38,.92);
  display: grid; place-items: center;
}
.video-grid .play svg { width: 16px; height: 16px; fill: #fff; }

/* Reuse feed-stack/post-card styling from home.blade.php — re-declare here so
   the profile page still renders the same cards without depending on home. */
.feed-stack { display: flex; flex-direction: column; gap: 16px; max-width: 640px; margin: 0 auto; }
.post-card { background:#fff; border-radius:8px; border:1px solid #E5E7EB; box-shadow:0 1px 2px rgba(20,20,50,.04); overflow:hidden; }
.post-head { display:flex; align-items:center; gap:10px; padding:12px 14px 8px; }
.post-head .avatar { height:40px; width:40px; border-radius:9999px; background:linear-gradient(135deg,#6C63FF,#FF6584); display:grid; place-items:center; color:#fff; font-weight:700; font-size:15px; object-fit:cover; flex-shrink:0; }
.post-head .who { display:flex; flex-direction:column; line-height:1.2; min-width:0; flex:1; }
.post-head .name { font-size:14px; font-weight:700; color:#1E1B4B; }
.post-head .meta { font-size:12px; color:#6B7280; display:flex; gap:6px; align-items:center; margin-top:2px; flex-wrap:wrap; }
.post-head .meta .dot { width:3px; height:3px; border-radius:9999px; background:#CBD5E1; flex-shrink:0; }
.post-head .views {
  display:inline-flex; align-items:center; gap:5px;
  padding:2px 9px; border-radius:9999px;
  background:linear-gradient(135deg,#FFE4EC 0%,#FFF1D6 100%);
  color:#E11D48; font-weight:800; font-size:11.5px; letter-spacing:.2px;
  box-shadow:0 1px 0 rgba(225,29,72,.08), inset 0 0 0 1px rgba(225,29,72,.15);
  transition:transform .2s ease, box-shadow .2s ease;
}
.post-head .views svg { width:13px; height:13px; stroke:#E11D48; }
.post-head .views strong { font-weight:800; color:#BE123C; }
.post-head .views .views-label { font-weight:700; color:#DB2777; opacity:.85; }
.post-head .views.is-pulse { animation: viewsPulse .55s ease; }
@keyframes viewsPulse {
  0%   { transform:scale(1);    box-shadow:0 0 0 0 rgba(225,29,72,.45); }
  50%  { transform:scale(1.12); box-shadow:0 0 0 6px rgba(225,29,72,0);  }
  100% { transform:scale(1);    box-shadow:0 0 0 0 rgba(225,29,72,0);    }
}
.post-badge { display:inline-flex; padding:1px 7px; border-radius:9999px; font-size:10px; font-weight:800; letter-spacing:.3px; text-transform:uppercase; }
.post-badge.status   { background:#EEF2FF; color:#4338CA; }
.post-badge.image    { background:#ECFEFF; color:#0E7490; }
.post-badge.video    { background:#FEF2F2; color:#BE123C; }
.post-badge.article  { background:#FEFCE8; color:#A16207; }
.post-badge.book     { background:#F5F3FF; color:#6D28D9; }
.post-menu { margin-left:auto; height:32px; width:32px; border-radius:9999px; display:grid; place-items:center; color:#64748B; }
.post-body { padding:4px 14px 12px; font-size:14px; color:#1E293B; line-height:1.55; word-break:break-word; }
.post-media { background:#000; position:relative; }
.post-media img, .post-media video { display:block; width:100%; height:auto; }
.adult-pill { position:absolute; top:12px; right:12px; background:#E11D48; color:#fff; padding:3px 9px; border-radius:6px; font-size:11px; font-weight:800; z-index:3; }
.status-canvas { padding:36px 24px; min-height:180px; display:flex; align-items:center; justify-content:center; text-align:center; font-size:22px; font-weight:700; line-height:1.4; word-break:break-word; }
.image-card .gallery-wrap { position:relative; overflow:hidden; background:#000; }
.image-card .gallery-track { display:flex; overflow-x:auto; scroll-snap-type:x mandatory; scrollbar-width:none; }
.image-card .gallery-track::-webkit-scrollbar { display:none; }
.image-card .gallery-slide { flex:0 0 100%; width:100%; scroll-snap-align:center; display:block; }
.image-card .gallery-track.is-multi .gallery-slide { aspect-ratio:4/5; object-fit:cover; }
.image-card .gallery-btn { position:absolute; top:50%; transform:translateY(-50%); height:36px; width:36px; border-radius:9999px; background:rgba(255,255,255,.95); color:#1E1B4B; display:grid; place-items:center; cursor:pointer; box-shadow:0 2px 12px rgba(0,0,0,.3); opacity:0; transition:opacity .2s ease; z-index:2; }
.image-card .gallery-wrap:hover .gallery-btn { opacity:1; }
.image-card .gallery-prev { left:10px; }
.image-card .gallery-next { right:10px; }
.image-card .gallery-btn svg { width:18px; height:18px; }
.image-card .gallery-counter { position:absolute; top:12px; right:12px; background:rgba(15,15,30,.7); color:#fff; padding:3px 9px; border-radius:9999px; font-size:11px; font-weight:700; z-index:2; }
.image-card .gallery-dots { display:flex; justify-content:center; gap:5px; padding:8px 0 4px; }
.image-card .gallery-dots .dot { height:6px; width:6px; border-radius:9999px; background:#CBD5E1; transition:.2s; }
.image-card .gallery-dots .dot.active { background:#6C63FF; width:18px; border-radius:3px; }
.video-card .video-thumb-wrap { position:relative; overflow:hidden; background:#000; cursor:pointer; aspect-ratio:16/9; }
.video-card .video-thumb-wrap img { width:100%; height:100%; object-fit:cover; }
.video-card .play-btn { position:absolute; inset:0; display:grid; place-items:center; pointer-events:none; }
.video-card .play-disc { width:64px; height:44px; border-radius:12px; background:rgba(220,38,38,.92); display:grid; place-items:center; }
.video-card .play-disc svg { width:20px; height:20px; fill:#fff; }
.article-card .article-figure { position:relative; display:block; overflow:hidden; }
.article-card .article-figure img { width:100%; height:auto; display:block; }
.article-card .article-cat { position:absolute; top:12px; left:12px; background:rgba(255,255,255,.96); color:#4338CA; padding:4px 10px; border-radius:6px; font-size:10px; font-weight:800; text-transform:uppercase; }
.article-card .article-meta { padding:14px 16px 4px; background:#F8FAFC; }
.article-card .article-title { font-size:18px; font-weight:800; color:#1E1B4B; line-height:1.3; }
.article-card .article-desc { margin-top:4px; font-size:13px; color:#475569; line-height:1.55; }
.article-card .article-read { display:inline-flex; align-items:center; gap:6px; margin:8px 16px 14px; font-size:13px; font-weight:700; color:#6C63FF; background:#F8FAFC; }
.post-counts { display:flex; align-items:center; gap:6px; padding:10px 14px 8px; font-size:12px; color:#6B7280; }
.post-counts[hidden] { display:none; }
.post-counts .reaction-stack { display:inline-flex; align-items:center; }
.post-counts .re-chip { display:inline-grid; place-items:center; width:18px; height:18px; border-radius:9999px; background:#fff; box-shadow:0 0 0 1.5px #fff; font-size:11px; line-height:1; }
.post-counts .re-chip + .re-chip { margin-left:-5px; }
.post-counts [data-likes-count]:not(:empty) { margin-left:4px; }
.post-actions { display:grid; grid-template-columns:1fr 1fr 1fr; gap:4px; padding:4px 8px 8px; border-top:1px solid #F1F5F9; }
.post-actions button { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:9px 6px; border-radius:8px; background:transparent; color:#475569; font-size:13px; font-weight:600; }
.post-actions button:hover { background:#F1F5F9; }
.post-actions button svg { width:18px; height:18px; }

/* ── Facebook-style reactions ── */
.reaction-wrap { position:relative; display:flex; }
.reaction-wrap > .btn-like { width:100%; }
.btn-like .re-emoji { font-size:18px; line-height:1; }
.re-emoji:empty { display:none; }
.btn-like.is-reacted .re-default { display:none; }
.btn-like.is-reacted { color:#2563EB; }
.reaction-pop { position:absolute; bottom:calc(100% + 8px); left:0; display:flex; gap:2px; padding:5px 7px; background:#fff; border-radius:9999px; box-shadow:0 12px 32px rgba(15,23,42,.20); border:1px solid #EEF2F7; opacity:0; pointer-events:none; transform:translateY(8px) scale(.9); transform-origin:bottom left; transition:opacity .15s ease, transform .15s ease; z-index:50; white-space:nowrap; }
.reaction-pop::after { content:''; position:absolute; left:0; right:0; top:100%; height:12px; }
.reaction-wrap:hover .reaction-pop, .reaction-wrap:focus-within .reaction-pop, .reaction-wrap.is-open .reaction-pop { opacity:1; pointer-events:auto; transform:translateY(0) scale(1); }
.reaction-wrap.is-dismissed .reaction-pop { opacity:0 !important; pointer-events:none !important; transform:translateY(8px) scale(.9) !important; }
.reaction-pop .reaction-pop-btn { display:grid; place-items:center; width:40px; height:40px; padding:0; border-radius:9999px; background:transparent; font-size:26px; line-height:1; cursor:pointer; transition:transform .12s ease, background .12s ease; }
.reaction-pop .reaction-pop-btn:hover { transform:translateY(-7px) scale(1.3); background:#F8FAFC; }
.reaction-pop .reaction-pop-btn:active { transform:translateY(-3px) scale(1.15); }

/* ─────────── Mobile profile (<640px) ─────────── */
@media (max-width: 639px) {
  main.mx-auto { padding-left: 0; padding-right: 0; padding-top: 0; }
  .profile-head { border-radius: 0; border-left: 0; border-right: 0; }

  .pf-cover { height: 140px; }
  .pf-cover-edit { right: 10px; bottom: 10px; padding: 6px 10px; font-size: 12px; }

  /* Stack: avatar centered above id, actions below as full-width row */
  .pf-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 10px;
    padding: 0 16px 14px;
    margin-top: -48px;
  }
  .pf-avatar {
    height: 96px; width: 96px;
    border-width: 4px;
    font-size: 36px;
  }
  .pf-avatar-edit {
    height: 30px; width: 30px;
    right: 2px; bottom: 2px;
    border-width: 2px;
  }
  .pf-avatar-edit svg { width: 14px; height: 14px; }

  .pf-id { padding: 0; min-width: 0; width: 100%; }
  .pf-name { font-size: 22px; line-height: 1.15; }
  .pf-handle { font-size: 13px; }
  .pf-stats {
    margin-top: 10px;
    display: flex; justify-content: center; gap: 18px;
    font-size: 12.5px;
  }

  /* Actions: full-width buttons, evenly distributed */
  .pf-actions {
    width: 100%;
    padding: 4px 0 0;
    justify-content: stretch;
    gap: 8px;
  }
  .pf-actions .btn {
    flex: 1;
    min-width: 0;
    height: 40px;
    padding: 0 12px;
    font-size: 13.5px;
    justify-content: center;
  }
  .pf-actions .btn-more {
    flex: 0 0 40px;
    width: 40px;
  }

  /* Tabs: tighter padding, full-bleed scroll */
  .pf-tabs { padding: 0 8px; gap: 0; }
  .pf-tab { padding: 12px 12px; font-size: 13.5px; }

  /* Side rail panels: full-bleed cards with rounded top only */
  .profile-grid { margin-top: 10px; gap: 10px; padding: 0; }
  .pf-side { padding: 0 10px; gap: 10px; }
  .pf-intro, .pf-mini-stats { padding: 14px 16px; }
  .pf-intro h3, .pf-mini-stats h3 { font-size: 15px; }

  /* Content pane */
  .pf-content { padding: 0 8px; }
  .pf-pane > .feed-stack { max-width: 100%; }

  /* Photo / video grids — denser on mobile */
  .photo-grid { gap: 4px; padding: 0 6px; }
  .video-grid { padding: 0 10px; }
}

/* Tiny phones */
@media (max-width: 360px) {
  .pf-name { font-size: 20px; }
  .pf-stats { font-size: 12px; gap: 12px; }
  .pf-actions .btn { font-size: 12.5px; padding: 0 8px; }
  .pf-tab { padding: 11px 10px; font-size: 13px; }
}

/* ─────────── Book card (shared look with the home feed) ─────────── */
.book-card .book-body {
  display: grid; grid-template-columns: 110px 1fr; gap: 14px;
  padding: 4px 14px 14px;
}
.book-card .book-cover {
  width: 110px; aspect-ratio: 2 / 3;
  border-radius: 8px; overflow: hidden;
  background: linear-gradient(135deg,#EEF2FF,#FCE7F3);
  border: 1px solid #E5E7EB;
  display: grid; place-items: center; cursor: pointer;
}
.book-card .book-cover:hover { box-shadow: 0 6px 14px rgba(108,99,255,.18); }
.book-card .book-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.book-card .book-noimg { color: #94A3B8; font-size: 11px; text-align: center; padding: 8px; }
.book-card .book-info { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.book-card .book-title {
  display: block;
  font-size: 15px; font-weight: 800; color: #1E1B4B;
  line-height: 1.25; word-break: break-word;
  text-decoration: none;
  transition: color .15s;
}
.book-card .book-title:hover { color: #5A52D5; }
.book-card .book-author { font-size: 13px; color: #475569; }
.book-card .book-pub { font-size: 12px; color: #6B7280; }
.book-card .book-desc {
  font-size: 13px; color: #475569; line-height: 1.55;
  display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
  margin-top: 4px;
}
.book-card .book-tags { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 2px; }
.book-card .book-tag {
  font-size: 10px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase;
  padding: 2.5px 7px; border-radius: 6px;
}
.book-card .book-tag.ext  { background: #DBEAFE; color: #1D4ED8; }
.book-card .book-tag.size { background: #DCFCE7; color: #047857; }
.book-card .book-tag.year { background: #FCE7F3; color: #9D174D; }
.book-card .book-tag.lang { background: #FEF3C7; color: #92400E; }
.book-card .book-dl {
  margin-top: 8px;
  display: inline-flex; align-items: center; gap: 6px;
  align-self: flex-start;
  background: linear-gradient(135deg,#6C63FF,#5A52D5);
  color: #fff; padding: 7px 14px; border-radius: 9999px;
  border: 0;
  font-size: 12.5px; font-weight: 700;
  box-shadow: 0 4px 12px rgba(108,99,255,.28);
  transition: transform .12s, box-shadow .12s, background .15s, opacity .15s;
  cursor: pointer;
}
.book-card .book-dl:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(108,99,255,.36); }
.book-card .book-dl.is-counting {
  background: #E2E8F0; color: #64748B;
  box-shadow: none; cursor: not-allowed;
}
.book-card .book-dl.is-counting:hover { transform: none; box-shadow: none; }
.book-card .book-dl-counter {
  display: inline-grid; place-items: center;
  min-width: 26px; padding: 2px 8px;
  border-radius: 9999px;
  background: rgba(255,255,255,.18);
  color: inherit;
  font-size: 11px; font-weight: 800; font-variant-numeric: tabular-nums;
}
.book-card .book-dl.is-counting .book-dl-counter {
  background: #fff; color: #475569;
}
@media (max-width: 480px) {
  .book-card .book-body { grid-template-columns: 90px 1fr; }
  .book-card .book-cover { width: 90px; }
}
</style>

@push('scripts')
@vite(['resources/js/profile.js'])
@endpush
@endsection
