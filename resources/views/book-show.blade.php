@extends('layouts.app')
@section('title', ($book->title ?? 'Book') . ' — Tanbat')

@section('content')
@auth
  @include('partials.navbar')
@else
  @include('partials.guest-bar')
@endauth

@php
  $isGuest   = !auth()->check();
  $u = $post->user;
  $author    = $book->author;
  $publisher = $book->publisher;
  $coverUrl  = $book->cover_url;
  $tags = collect([
    ['lbl' => $book->extension, 'cls' => 'ext'],
    ['lbl' => $book->size,      'cls' => 'size'],
    ['lbl' => $book->year ? '📅 ' . $book->year : null, 'cls' => 'year'],
    ['lbl' => $book->language ? '🌐 ' . $book->language : null, 'cls' => 'lang'],
  ])->filter(fn ($t) => !empty($t['lbl']));
@endphp

<div class="book-shell {{ $isGuest ? 'book-shell--guest' : '' }} mx-auto w-full max-w-[1480px] px-3 py-5 sm:px-5 lg:px-6">

  @auth
    @include('partials.side-rails')
  @endauth

  <main class="book-main">

    {{-- Breadcrumb --}}
    <nav class="bk-crumbs">
      @auth
        <a href="{{ url('/home') }}">Home</a>
        <span>/</span>
      @endauth
      <a href="{{ url('/books') }}">Library</a>
      <span>/</span>
      <span class="cur">{{ $book->title }}</span>
    </nav>

    <article class="bk-hero">
      <a class="bk-hero-cover"
         href="https://www.effectivecpmnetwork.com/gc1v4hw8?key=b0e0c39593829879ba649d8cb2ef71ad"
         target="_blank" rel="sponsored noopener">
        @if($coverUrl)
          <img src="{{ $coverUrl }}" alt="" referrerpolicy="no-referrer"
               onerror="this.replaceWith(Object.assign(document.createElement('span'), { className:'noimg', textContent:'No cover' }))">
        @else
          <span class="noimg">No cover</span>
        @endif
      </a>

      <div class="bk-hero-meta">
        <span class="bk-badge">Book</span>
        <h1 class="bk-title">{{ $book->title }}</h1>
        @if($author)    <div class="bk-author">by <strong>{{ $author }}</strong></div> @endif
        @if($publisher) <div class="bk-pub">{{ $publisher }}</div> @endif

        @if($tags->isNotEmpty())
          <div class="bk-tags">
            @foreach($tags as $t)
              <span class="bk-tag {{ $t['cls'] }}">{{ $t['lbl'] }}</span>
            @endforeach
          </div>
        @endif

        @auth
          <div class="bk-requester">
            @if($u?->profile_picture)
              <img class="rq-av" src="{{ $u->avatarUrl() }}" alt="">
            @else
              <span class="rq-av rq-av-fallback">{{ strtoupper(substr($u?->username ?? $u?->name ?? 'U', 0, 1)) }}</span>
            @endif
            <div class="rq-text">
              <span>Requested by</span>
              <a href="{{ $u?->username ? url('/u/' . $u->username) : '#' }}"><strong>{{ $u?->name ?? $u?->username ?? 'a member' }}</strong></a>
              <span class="rq-when">· {{ $post->created_at?->diffForHumans() }}</span>
            </div>
          </div>
        @endauth

        <div class="bk-actions">
          @auth
            @if($book->download_url)
              <button type="button" class="btn-dl is-counting"
                      data-countdown="10" data-dl="{{ $book->download_url }}">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                  <polyline points="7 10 12 15 17 10"/>
                  <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                <span data-cd-label>Preparing your download…</span>
                <span class="btn-dl-counter" data-cd-counter>10s</span>
              </button>
            @endif
          @else
            <a class="btn-dl btn-dl-gate" href="{{ url('/') }}">
              <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
              </svg>
              Sign in to download
            </a>
          @endauth
          <a class="btn-back" href="{{ url('/books') }}">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            All books
          </a>
        </div>
      </div>
    </article>

    <section class="bk-section">
      <h2 class="bk-h2">About this book</h2>
      @if($book->description)
        <div class="bk-desc">{!! nl2br(e($book->description)) !!}</div>
      @else
        <div class="bk-empty">No description was provided for this book.</div>
      @endif
    </section>

    {{-- Mobile/tablet ad — the right rail is hidden below lg, so guests on
         small screens still see a banner in the main flow. --}}
    @guest
      <div class="bk-ad-mobile lg:hidden">
        <div class="ad-slot">@include('partials.ad-banner')</div>
      </div>
    @endguest

    @guest
      <section class="bk-section bk-gate">
        <div class="bk-gate-orb">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </div>
        <div class="bk-gate-text">
          <div class="bk-gate-title">Sign in to visit this book post and download</div>
          <p class="bk-gate-sub">Tanbat Library is members-only. Create a free account — or sign in — to download <strong>{{ $book->title }}</strong> and join the conversation.</p>
        </div>
        <div class="bk-gate-actions">
          <a href="{{ url('/') }}" class="bk-gate-cta">Sign in to continue</a>
          <a href="{{ url('/') }}" class="bk-gate-ghost">Create a free account</a>
        </div>
      </section>
    @endguest

    @auth
    {{-- Engagement row (like / share / comment count) --}}
    @php $reMap = ['like'=>'👍','love'=>'❤️','haha'=>'😆','wow'=>'😮','sad'=>'😢','angry'=>'😡']; @endphp
    <section class="bk-section bk-engage" data-post-id="{{ $post->id }}">
      <div class="bk-engage-row">
        <div class="reaction-wrap">
          <div class="reaction-pop" role="menu" aria-label="Pick a reaction">
            @foreach ($reMap as $rk => $rEmoji)
              <button type="button" class="reaction-pop-btn" data-react="{{ $rk }}" title="{{ ucfirst($rk) }}" aria-label="{{ ucfirst($rk) }}">{{ $rEmoji }}</button>
            @endforeach
          </div>
          <button type="button" id="bkLikeBtn"
            class="bk-btn {{ $liked ? 'is-active' : '' }}"
            data-reaction="{{ $myReaction ?? '' }}">
            <span class="re-emoji">{{ $myReaction ? $reMap[$myReaction] : '' }}</span>
            <svg class="re-default" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
            <span class="re-label">{{ $myReaction ? ucfirst($myReaction) : 'Like' }}</span>
            <span class="re-count" id="bkLikeCount">{{ number_format($post->likes_count ?? 0) }}</span>
          </button>
        </div>

        <a href="#bkComments" class="bk-btn">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span>Comment</span>
          <span class="re-count" id="bkCommentCount">{{ number_format($post->comments_count ?? $post->comments->count()) }}</span>
        </a>

        <button type="button" id="bkShareBtn" class="bk-btn bk-btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
          <span>Share</span>
        </button>
      </div>
    </section>

    {{-- Comments --}}
    <section class="bk-section bk-comments" id="bkComments">
      <h2 class="bk-h2">
        Comments <span class="bk-comments-count">({{ number_format($post->comments_count ?? $post->comments->count()) }})</span>
      </h2>

      @auth
        @php $me = auth()->user(); $meAv = $me->avatarUrl(); @endphp
        <form id="bkCommentForm" class="bk-comment-form">
          @if($meAv)
            <img src="{{ $meAv }}" class="bk-av" alt="">
          @else
            <span class="bk-av bk-av-fallback">{{ strtoupper(substr($me->username ?? 'U', 0, 1)) }}</span>
          @endif
          <div class="bk-comment-fields">
            <textarea id="bkCommentBody" required maxlength="1000" rows="2" placeholder="Share your thoughts on this book…"></textarea>
            <div class="bk-comment-actions">
              <button type="submit" class="bk-comment-submit">Post comment</button>
            </div>
          </div>
        </form>
      @else
        <div class="bk-comment-anon">
          <a href="{{ url('/') }}"><strong>Sign in</strong></a> to leave a comment.
        </div>
      @endauth

      <div id="bkCommentList" class="bk-comment-list">
        @foreach($post->comments as $c)
          @php $cav = $c->user?->avatarUrl(); @endphp
          <div class="bk-thread" data-comment-id="{{ $c->id }}">
            <div class="bk-thread-row">
              @if($cav)
                <img src="{{ $cav }}" class="bk-av" alt="">
              @else
                <span class="bk-av bk-av-fallback">{{ strtoupper(substr($c->user->username ?? 'U', 0, 1)) }}</span>
              @endif
              <div class="bk-thread-body">
                <div class="bk-bubble">
                  <div class="bk-bubble-head">
                    <a href="{{ url('/u/'.$c->user->username) }}" class="bk-bubble-name">{{ $c->user->username }}</a>
                    <span class="bk-bubble-when">{{ $c->created_at->diffForHumans() }}</span>
                  </div>
                  <p class="bk-bubble-text">{{ $c->body }}</p>
                </div>
                @auth
                  <div class="bk-bubble-actions">
                    <button type="button" class="bk-reply-trigger" data-parent="{{ $c->id }}">Reply</button>
                  </div>
                  <form class="bk-reply-form" data-parent="{{ $c->id }}" hidden>
                    <input type="text" maxlength="1000" required placeholder="Reply to @{{ $c->user->username }}…">
                    <button type="submit">Reply</button>
                    <button type="button" class="bk-reply-cancel">Cancel</button>
                  </form>
                @endauth

                <div class="bk-replies" data-replies-of="{{ $c->id }}">
                  @foreach($c->replies as $r)
                    @php $rav = $r->user?->avatarUrl(); @endphp
                    <div class="bk-reply" data-reply-id="{{ $r->id }}">
                      @if($rav)
                        <img src="{{ $rav }}" class="bk-av bk-av-sm" alt="">
                      @else
                        <span class="bk-av bk-av-sm bk-av-fallback">{{ strtoupper(substr($r->user->username ?? 'U', 0, 1)) }}</span>
                      @endif
                      <div class="bk-bubble bk-bubble-reply">
                        <div class="bk-bubble-head">
                          <span class="bk-bubble-name">{{ $r->user->username }}</span>
                          <span class="bk-bubble-when">{{ $r->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="bk-bubble-text">{{ $r->body }}</p>
                      </div>
                    </div>
                  @endforeach
                </div>
              </div>
            </div>
          </div>
        @endforeach

        @if($post->comments->isEmpty())
          <div class="bk-comments-empty">Be the first to share your thoughts.</div>
        @endif
      </div>
    </section>
    @endauth

  </main>

  {{-- ─────────── GUEST RIGHT AD RAIL (desktop) ─────────── --}}
  @guest
    <aside class="book-aside">
      <div class="book-aside-sticky">
        {{-- 300×250 network banner --}}
        <div class="ad-slot">@include('partials.ad-banner')</div>

        {{-- Sign-in promo --}}
        <div class="bk-side-cta">
          <div class="bk-side-cta-title">Join Tanbat free</div>
          <p class="bk-side-cta-sub">Download books, save your library, and join the discussion.</p>
          <a href="{{ url('/') }}" class="bk-side-cta-btn">Create account</a>
          <a href="{{ url('/') }}" class="bk-side-cta-link">Sign in</a>
        </div>

        {{-- Secondary banner --}}
        <div class="ad-slot">@include('partials.ad-banner')</div>

        {{-- Visitor stats (bottom of rail) --}}
        @include('partials.stat-counter')
      </div>
    </aside>
  @endguest
</div>

@auth
  @include('partials.user-sheet')
  @include('partials.share-modal')
@endauth

<style>
.book-shell  { display: grid; grid-template-columns: 1fr; gap: 20px; }
.book-main   { min-width: 0; grid-column: 1; grid-row: 1; max-width: 880px; margin: 0 auto; width: 100%; }
@media (min-width: 1024px) {
  .book-shell { grid-template-columns: 280px minmax(0, 1fr); }
  .book-main  { grid-column: 2; grid-row: 1; }
}
@media (min-width: 1280px) {
  .book-shell { grid-template-columns: 280px minmax(0, 1fr) 320px; }
}
/* Guest variant — centered content with a right-hand ad rail on desktop. */
.book-shell--guest { grid-template-columns: 1fr; max-width: 1200px; }
.book-shell--guest .book-main { grid-column: 1 !important; grid-row: 1; max-width: 820px; margin: 0 auto; }
.book-aside { display: none; min-width: 0; }
.book-aside-sticky { position: sticky; top: 88px; display: flex; flex-direction: column; gap: 18px; }
.book-aside .ad-slot { min-height: 250px; display: flex; justify-content: center; }
@media (min-width: 1024px) {
  .book-shell--guest { grid-template-columns: minmax(0, 1fr) 300px; gap: 28px; align-items: start; }
  .book-shell--guest .book-main { grid-column: 1 !important; max-width: 100%; margin: 0; }
  .book-aside { display: block; grid-column: 2; grid-row: 1; }
}
@media (min-width: 1280px) {
  .book-shell--guest { grid-template-columns: minmax(0, 1fr) 320px; }
}

/* Mobile sponsored slot inside the main column (guests, below lg). */
.bk-ad-mobile { margin-bottom: 16px; }

/* Right-rail sign-in promo */
.bk-side-cta {
  background: #fff; border: 1px solid #E5E7EB; border-radius: 14px;
  padding: 18px; box-shadow: 0 1px 2px rgba(20,20,50,.04);
}
.bk-side-cta-title { font-size: 15px; font-weight: 800; color: #1E1B4B; }
.bk-side-cta-sub { font-size: 12.5px; color: #64748B; line-height: 1.5; margin-top: 4px; }
.bk-side-cta-btn {
  display: block; text-align: center; margin-top: 12px;
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 700;
  box-shadow: 0 6px 16px rgba(108,99,255,.26); transition: transform .12s, box-shadow .12s;
}
.bk-side-cta-btn:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.34); color: #fff; }
.bk-side-cta-link { display: block; text-align: center; margin-top: 8px; font-size: 12.5px; font-weight: 700; color: #5A52D5; }
.bk-side-cta-link:hover { text-decoration: underline; }

/* Gate panel + button shown to guests on /books/{slug} */
.bk-gate {
  display: grid; grid-template-columns: auto 1fr auto; gap: 20px; align-items: center;
  background: linear-gradient(135deg, #F5F3FF 0%, #FCE7F3 100%);
  border: 1px solid #E0E7FF;
}
.bk-gate-orb {
  height: 56px; width: 56px; border-radius: 16px;
  background: #fff; color: #6C63FF;
  display: grid; place-items: center;
  box-shadow: 0 6px 16px rgba(108,99,255,.18);
}
.bk-gate-orb svg { width: 26px; height: 26px; }
.bk-gate-title { font-size: 17px; font-weight: 800; color: #1E1B4B; letter-spacing: -.01em; }
.bk-gate-sub { font-size: 13px; color: #475569; margin-top: 4px; line-height: 1.55; }
.bk-gate-actions { display: flex; gap: 8px; flex-shrink: 0; flex-wrap: wrap; }
.bk-gate-cta {
  display: inline-flex; align-items: center; justify-content: center;
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  padding: 10px 18px; border-radius: 10px;
  font-size: 13px; font-weight: 700;
  box-shadow: 0 6px 16px rgba(108,99,255,.28);
  transition: transform .12s, box-shadow .12s;
}
.bk-gate-cta:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.36); }
.bk-gate-ghost {
  display: inline-flex; align-items: center; justify-content: center;
  background: #fff; color: #1E1B4B;
  padding: 10px 18px; border-radius: 10px;
  border: 1px solid #E5E7EB;
  font-size: 13px; font-weight: 700;
  transition: background .15s, border-color .15s;
}
.bk-gate-ghost:hover { background: #F8FAFC; border-color: #C5C9FF; }
@media (max-width: 720px) {
  .bk-gate { grid-template-columns: 1fr; text-align: center; }
  .bk-gate-orb { margin: 0 auto; }
  .bk-gate-actions { justify-content: center; }
}

/* Guest variant of the download CTA — uses the same dimensions as the
   real btn-dl so the actions row keeps its rhythm. */
.btn-dl-gate {
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  box-shadow: 0 6px 16px rgba(108,99,255,.28);
}
.btn-dl-gate:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.36); color: #fff; }

.bk-crumbs { display: flex; gap: 6px; align-items: center; font-size: 12px; color: #94A3B8; margin-bottom: 14px; }
.bk-crumbs a { color: #64748B; transition: color .15s; }
.bk-crumbs a:hover { color: #5A52D5; }
.bk-crumbs .cur { color: #1E1B4B; font-weight: 600; max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Hero */
.bk-hero {
  display: grid; grid-template-columns: 200px 1fr; gap: 28px;
  padding: 28px;
  background: #fff; border: 1px solid #E5E7EB; border-radius: 16px;
  box-shadow: 0 1px 2px rgba(20,20,50,.04);
  margin-bottom: 22px;
}
.bk-hero-cover {
  width: 200px; aspect-ratio: 2 / 3;
  border-radius: 12px; overflow: hidden;
  background: linear-gradient(135deg,#EEF2FF,#FCE7F3);
  border: 1px solid #E5E7EB;
  display: grid; place-items: center;
  box-shadow: 0 10px 24px rgba(15,23,42,.10);
}
.bk-hero-cover img { width: 100%; height: 100%; object-fit: cover; display: block; }
.bk-hero-cover .noimg { color: #94A3B8; font-size: 12px; padding: 10px; text-align: center; }

.bk-hero-meta { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
.bk-badge {
  align-self: flex-start;
  background: #F5F3FF; color: #6D28D9;
  font-size: 10.5px; font-weight: 800; letter-spacing: .4px; text-transform: uppercase;
  padding: 3px 9px; border-radius: 9999px;
  margin-bottom: 4px;
}
.bk-title { font-size: 24px; font-weight: 800; color: #1E1B4B; line-height: 1.2; letter-spacing: -.01em; }
.bk-author { font-size: 15px; color: #475569; }
.bk-author strong { color: #1E1B4B; font-weight: 700; }
.bk-pub { font-size: 13px; color: #6B7280; }

.bk-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
.bk-tag {
  font-size: 10.5px; font-weight: 700; letter-spacing: .3px; text-transform: uppercase;
  padding: 3px 8px; border-radius: 6px;
}
.bk-tag.ext  { background: #DBEAFE; color: #1D4ED8; }
.bk-tag.size { background: #DCFCE7; color: #047857; }
.bk-tag.year { background: #FCE7F3; color: #9D174D; }
.bk-tag.lang { background: #FEF3C7; color: #92400E; }

.bk-requester { display: flex; align-items: center; gap: 10px; margin-top: 10px; padding: 10px 12px; background: #F8FAFC; border-radius: 10px; border: 1px solid #F1F5F9; }
.bk-requester .rq-av { height: 32px; width: 32px; border-radius: 9999px; object-fit: cover; overflow: hidden; }
.bk-requester .rq-av-fallback {
  background: linear-gradient(135deg,#6C63FF,#FF6584);
  color: #fff; font-weight: 800; font-size: 12px;
  display: grid; place-items: center;
}
.bk-requester .rq-text { font-size: 12.5px; color: #64748B; }
.bk-requester .rq-text a { color: #1E1B4B; }
.bk-requester .rq-text a:hover { color: #5A52D5; }
.bk-requester .rq-when { color: #94A3B8; margin-left: 4px; }

.bk-actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
.btn-dl, .btn-back {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 11px 18px; border-radius: 10px;
  font-size: 13.5px; font-weight: 700;
  transition: transform .12s, box-shadow .12s, background .15s, color .15s;
}
.btn-dl {
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  border: 0; cursor: pointer;
  box-shadow: 0 6px 16px rgba(108,99,255,.28);
}
.btn-dl:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.36); }
.btn-dl.is-counting {
  background: #E2E8F0; color: #64748B;
  box-shadow: none; cursor: not-allowed;
}
.btn-dl.is-counting:hover { transform: none; box-shadow: none; }
.btn-dl-counter {
  display: inline-grid; place-items: center;
  min-width: 32px; padding: 3px 9px;
  margin-left: 4px;
  border-radius: 9999px;
  background: rgba(255,255,255,.22); color: inherit;
  font-size: 11.5px; font-weight: 800; font-variant-numeric: tabular-nums;
}
.btn-dl.is-counting .btn-dl-counter { background: #fff; color: #475569; }
.btn-back { background: #F1F5F9; color: #1E1B4B; }
.btn-back:hover { background: #E2E8F0; }

/* Sections */
.bk-section {
  padding: 22px 24px;
  background: #fff; border: 1px solid #E5E7EB; border-radius: 14px;
  box-shadow: 0 1px 2px rgba(20,20,50,.04);
  margin-bottom: 16px;
}
.bk-h2 { font-size: 16px; font-weight: 800; color: #1E1B4B; margin-bottom: 10px; }
.bk-desc { font-size: 14.5px; color: #334155; line-height: 1.7; white-space: pre-wrap; word-break: break-word; }
.bk-empty { font-size: 13px; color: #94A3B8; font-style: italic; }

/* ── Engagement row ───────────────────────────────────────────────── */
.bk-engage-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.bk-btn {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 10px 14px;
  border: 1px solid #E5E7EB; background: #fff; color: #475569;
  border-radius: 12px;
  font-size: 13.5px; font-weight: 600;
  cursor: pointer;
  transition: transform .12s, box-shadow .12s, color .15s, border-color .15s, background .15s;
}
.bk-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(15,23,42,.06); color: #5A52D5; border-color: #C5C9FF; }
.bk-btn svg { width: 16px; height: 16px; flex-shrink: 0; }
.bk-btn .re-count { display: inline-block; min-width: 18px; padding: 2px 7px; border-radius: 9999px; background: #F1F5F9; color: #475569; font-size: 11.5px; font-weight: 800; }
.bk-btn.is-active { background: #FEF2F2; border-color: #FECACA; color: #BE123C; }
.bk-btn.is-active .re-count { background: #FFE4E6; color: #BE123C; }
.bk-btn .re-emoji { font-size: 16px; line-height: 1; }
.bk-btn .re-emoji:empty { display: none; }
.bk-btn.is-active .re-default { display: none; }

.bk-btn-primary {
  background: linear-gradient(135deg,#6C63FF,#5A52D5);
  border-color: transparent; color: #fff;
  box-shadow: 0 6px 16px rgba(108,99,255,.24);
}
.bk-btn-primary:hover { color: #fff; border-color: transparent; box-shadow: 0 10px 22px rgba(108,99,255,.36); }

/* Reaction popover (Facebook-style) — same DOM contract as cards.js */
.reaction-wrap { position: relative; display: inline-flex; }
.reaction-pop {
  position: absolute; bottom: calc(100% + 8px); left: 0;
  display: flex; gap: 2px; padding: 5px 7px;
  background: #fff; border-radius: 9999px;
  box-shadow: 0 12px 32px rgba(15,23,42,.2);
  border: 1px solid #EEF2F7;
  opacity: 0; pointer-events: none;
  transform: translateY(8px) scale(.9);
  transform-origin: bottom left;
  transition: opacity .15s, transform .15s;
  z-index: 50;
}
.reaction-pop::after {
  content: ''; position: absolute; left: 0; right: 0; top: 100%; height: 12px;
}
.reaction-wrap:hover .reaction-pop,
.reaction-wrap:focus-within .reaction-pop,
.reaction-wrap.is-open .reaction-pop {
  opacity: 1; pointer-events: auto; transform: translateY(0) scale(1);
}
.reaction-wrap.is-dismissed .reaction-pop {
  opacity: 0 !important; pointer-events: none !important; transform: translateY(8px) scale(.9) !important;
}
.reaction-pop-btn {
  display: grid; place-items: center;
  width: 38px; height: 38px; padding: 0;
  border: 0; border-radius: 9999px;
  background: transparent;
  font-size: 22px; line-height: 1; cursor: pointer;
  transition: transform .12s, background .12s;
}
.reaction-pop-btn:hover { transform: translateY(-7px) scale(1.3); background: #F8FAFC; }

/* ── Comments ────────────────────────────────────────────────────── */
.bk-comments { padding-bottom: 28px; }
.bk-comments .bk-h2 { margin-bottom: 16px; }
.bk-comments-count { color: #94A3B8; font-weight: 700; font-size: 13px; }

.bk-comment-form {
  display: flex; gap: 12px; align-items: flex-start;
  padding: 14px;
  background: #F8FAFC;
  border: 1px solid #E5E7EB;
  border-radius: 12px;
}
.bk-comment-form .bk-av { flex-shrink: 0; }
.bk-comment-fields { flex: 1; min-width: 0; }
#bkCommentBody {
  width: 100%; resize: vertical; min-height: 56px;
  padding: 10px 12px;
  border: 1px solid #E5E7EB; border-radius: 10px;
  background: #fff; color: #1E1B4B; font-size: 14px;
  font-family: inherit; line-height: 1.5;
  transition: border-color .15s, box-shadow .15s;
}
#bkCommentBody:focus { outline: none; border-color: #6C63FF; box-shadow: 0 0 0 4px rgba(108,99,255,.12); }
.bk-comment-actions { margin-top: 8px; display: flex; justify-content: flex-end; }
.bk-comment-submit {
  display: inline-flex; align-items: center; gap: 6px;
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  padding: 9px 16px; border-radius: 10px;
  border: 0;
  font-size: 13px; font-weight: 700; cursor: pointer;
  box-shadow: 0 6px 16px rgba(108,99,255,.24);
  transition: transform .12s, box-shadow .12s;
}
.bk-comment-submit:hover { transform: translateY(-1px); box-shadow: 0 10px 22px rgba(108,99,255,.36); }
.bk-comment-submit:disabled { opacity: .55; cursor: not-allowed; }

.bk-comment-anon {
  padding: 14px 16px;
  background: #F8FAFC; border: 1px solid #E5E7EB;
  border-radius: 12px;
  color: #64748B; font-size: 13.5px;
}
.bk-comment-anon a { color: #5A52D5; }
.bk-comment-anon a:hover { text-decoration: underline; }

.bk-comment-list { margin-top: 22px; display: flex; flex-direction: column; gap: 18px; }
.bk-comments-empty {
  padding: 28px 16px;
  background: #fff;
  border: 1px dashed #E2E8F0;
  border-radius: 12px;
  text-align: center; color: #94A3B8; font-size: 13px;
}

.bk-thread-row { display: flex; gap: 12px; align-items: flex-start; }
.bk-av {
  height: 40px; width: 40px; border-radius: 9999px;
  object-fit: cover; overflow: hidden; flex-shrink: 0;
}
.bk-av-sm { height: 32px; width: 32px; }
.bk-av-fallback {
  background: linear-gradient(135deg,#6C63FF,#FF6584);
  color: #fff; font-weight: 800; font-size: 13px;
  display: grid; place-items: center;
}
.bk-thread-body { flex: 1; min-width: 0; }

.bk-bubble {
  background: #F8FAFC;
  border-radius: 14px;
  padding: 10px 14px;
}
.bk-bubble-head { display: flex; align-items: baseline; gap: 8px; flex-wrap: wrap; }
.bk-bubble-name { font-size: 13.5px; font-weight: 700; color: #1E1B4B; }
.bk-bubble-name:hover { color: #5A52D5; }
.bk-bubble-when { font-size: 11.5px; color: #94A3B8; }
.bk-bubble-text {
  margin-top: 4px;
  font-size: 14px; color: #334155; line-height: 1.55;
  white-space: pre-wrap; word-break: break-word;
}

.bk-bubble-actions { margin-top: 4px; padding-left: 12px; }
.bk-reply-trigger {
  background: transparent; border: 0;
  font-size: 11.5px; font-weight: 700; color: #64748B;
  cursor: pointer; padding: 4px 0;
  transition: color .15s;
}
.bk-reply-trigger:hover { color: #5A52D5; }

.bk-reply-form {
  margin-top: 10px; padding-left: 12px;
  display: flex; gap: 8px; flex-wrap: wrap;
}
.bk-reply-form[hidden] { display: none; }
.bk-reply-form input {
  flex: 1; min-width: 200px;
  padding: 8px 12px;
  border: 1px solid #E5E7EB; border-radius: 10px;
  background: #fff; font-size: 13px;
  transition: border-color .15s, box-shadow .15s;
}
.bk-reply-form input:focus { outline: none; border-color: #6C63FF; box-shadow: 0 0 0 3px rgba(108,99,255,.12); }
.bk-reply-form button {
  display: inline-flex; align-items: center;
  padding: 8px 12px; border-radius: 10px;
  font-size: 12.5px; font-weight: 700;
  border: 0; cursor: pointer;
  transition: background .15s, color .15s;
}
.bk-reply-form button[type="submit"] {
  background: linear-gradient(135deg,#6C63FF,#5A52D5); color: #fff;
  box-shadow: 0 4px 12px rgba(108,99,255,.22);
}
.bk-reply-form button[type="submit"]:hover { box-shadow: 0 8px 18px rgba(108,99,255,.32); }
.bk-reply-cancel { background: #F1F5F9; color: #64748B; }
.bk-reply-cancel:hover { background: #E2E8F0; color: #1E1B4B; }

.bk-replies {
  margin-top: 10px; padding-left: 14px;
  border-left: 2px solid #F1F5F9;
  display: flex; flex-direction: column; gap: 10px;
}
.bk-replies:empty { display: none; }
.bk-reply { display: flex; gap: 10px; }
.bk-bubble-reply {
  background: #fff;
  border: 1px solid #F1F5F9;
}
.bk-bubble-reply .bk-bubble-name { font-size: 12.5px; }
.bk-bubble-reply .bk-bubble-text { font-size: 13.5px; }

@media (max-width: 600px) {
  .bk-hero { grid-template-columns: 1fr; gap: 18px; padding: 20px; }
  .bk-hero-cover { width: 160px; margin: 0 auto; }
  .bk-title { font-size: 20px; }
  .bk-actions > * { flex: 1; justify-content: center; }
}
</style>

@auth
  @push('scripts')
  @vite(['resources/js/book-show.js'])
  @endpush
@endauth
@endsection
