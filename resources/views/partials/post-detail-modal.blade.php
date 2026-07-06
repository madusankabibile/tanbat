<style>
  /* Plyr sizing inside the post-detail stage */
  #postLeftStage .plyr {
    --plyr-color-main: #6C63FF;
    width: 100%;
    max-height: 100%;
  }
  #postLeftStage .plyr__video-embed,
  #postLeftStage .plyr--video {
    width: 100%;
    height: 100%;
  }
  #postLeftStage video.plyr-stage { max-height: 100%; }
</style>
<div id="modalPost"
     class="post-modal fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/70 p-3 backdrop-blur-sm">
  <div class="post-modal__shell grid h-[94vh] w-full max-w-6xl grid-cols-1 overflow-hidden rounded-3xl bg-white shadow-pop animate-fup md:grid-cols-[1.4fr_1fr]">

    {{-- ─────────────── LEFT: media stage ─────────────── --}}
    <div class="relative flex flex-col bg-slate-950 text-white">
      <button type="button" data-close-modal
              class="absolute right-3 top-3 z-20 grid h-9 w-9 place-items-center rounded-full bg-black/40 text-white backdrop-blur transition hover:bg-black/60 md:hidden"
              aria-label="Close">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
      <div id="postLeftStage" class="flex min-h-0 flex-1 items-center justify-center overflow-hidden bg-black"></div>
      <div id="postLeftMeta"
           class="flex flex-wrap items-center gap-2 border-t border-white/10 bg-black/30 px-4 py-3 text-xs text-white/80"></div>
    </div>

    {{-- ─────────────── RIGHT: details + comments ─────────────── --}}
    <div class="flex min-h-0 flex-col bg-white">
      {{-- Author header --}}
      <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
        <div id="postAuthor" class="flex min-w-0 flex-1 items-center gap-3"></div>
        <button type="button" id="postFollowBtn" class="hidden rounded-full bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-brand-700">Follow</button>
        <button type="button" class="grid h-9 w-9 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" aria-label="More">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
        </button>
        <button type="button" data-close-modal
                class="grid h-9 w-9 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
                aria-label="Close">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
      </div>

      {{-- Caption / description --}}
      <div id="postDescription" class="border-b border-slate-100 px-4 py-3 text-sm leading-relaxed text-slate-700"></div>

      {{-- Engagement bar --}}
      <div class="flex items-center gap-4 border-b border-slate-100 px-4 py-2.5 text-sm">
        <div class="reaction-wrap" id="modalReactionWrap">
          <div class="reaction-pop" role="menu" aria-label="Pick a reaction">
            <button type="button" class="reaction-pop-btn" data-modal-react="like"  title="Like"  aria-label="Like"><span class="re">👍</span></button>
            <button type="button" class="reaction-pop-btn" data-modal-react="love"  title="Love"  aria-label="Love"><span class="re">❤️</span></button>
            <button type="button" class="reaction-pop-btn" data-modal-react="haha"  title="Haha"  aria-label="Haha"><span class="re">😆</span></button>
            <button type="button" class="reaction-pop-btn" data-modal-react="wow"   title="Wow"   aria-label="Wow"><span class="re">😮</span></button>
            <button type="button" class="reaction-pop-btn" data-modal-react="sad"   title="Sad"   aria-label="Sad"><span class="re">😢</span></button>
            <button type="button" class="reaction-pop-btn" data-modal-react="angry" title="Angry" aria-label="Angry"><span class="re">😡</span></button>
          </div>
          <button id="modalLikeBtn" type="button"
                  class="group inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-slate-600 transition hover:bg-slate-100"
                  data-modal-act="like" data-reaction="" aria-pressed="false">
            <span class="re-emoji"></span>
            <svg class="re-default h-5 w-5 transition group-aria-pressed:scale-110" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/></svg>
            <span id="modalReactLabel" class="lbl font-semibold">Like</span>
            <span id="postLikes" class="font-semibold">0</span>
          </button>
        </div>
        <button type="button"
                class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-slate-600 transition hover:bg-brand-50 hover:text-brand-600"
                data-modal-act="comment-focus">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          <span id="postCommentsCount" class="font-semibold">0</span>
        </button>
        <button type="button"
                class="inline-flex items-center gap-1.5 rounded-full px-2 py-1 text-slate-600 transition hover:bg-brand-50 hover:text-brand-600"
                data-modal-act="share">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        </button>
        <span id="postViews"
              class="ml-auto inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-extrabold tracking-tight text-rose-600 ring-1 ring-rose-200/80 shadow-[0_1px_0_rgba(225,29,72,.08)]"
              style="background: linear-gradient(135deg,#FFE4EC 0%,#FFF1D6 100%);"
              title="views">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>
          <span data-modal-views class="text-rose-700">0</span>
          <span class="text-[10px] font-bold uppercase tracking-wider text-pink-600/90">views</span>
        </span>
        <span id="postCreatedAt" class="text-xs text-slate-400"></span>
      </div>

      {{-- Comment thread --}}
      <div id="postComments"
           class="flex-1 space-y-4 overflow-y-auto bg-slate-50/40 px-4 py-4"
           aria-label="Comments"></div>

      {{-- Composer --}}
      <div class="border-t border-slate-100 bg-white">
        {{-- Replying-to indicator --}}
        <div id="replyBar" class="hidden items-center gap-2 border-b border-slate-100 bg-brand-50/60 px-4 py-2 text-xs">
          <svg class="h-4 w-4 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
          <span class="text-slate-600">Replying to <strong id="replyTarget" class="font-semibold text-brand-700">@user</strong></span>
          <button type="button" id="replyCancel" class="ml-auto rounded-full p-1 text-slate-400 transition hover:bg-white hover:text-slate-700" aria-label="Cancel reply">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          </button>
        </div>

        {{-- Image attach preview --}}
        <div id="commentImagePreview" class="hidden border-b border-slate-100 px-4 py-2">
          <div class="relative inline-block">
            <img id="commentImageThumb" src="" alt="" class="h-20 w-20 rounded-lg border border-slate-200 object-cover">
            <button type="button" id="commentImageClear"
                    class="absolute -right-2 -top-2 grid h-6 w-6 place-items-center rounded-full bg-slate-900 text-white shadow-soft transition hover:bg-rose-600"
                    aria-label="Remove image">
              <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
        </div>

        <form id="commentForm" class="relative flex items-end gap-2 px-3 py-3" autocomplete="off">
          <div id="commentAvatar" class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-xs font-bold text-white">U</div>

          <div class="relative flex-1 rounded-2xl bg-slate-100 px-3 py-1.5 focus-within:bg-white focus-within:ring-2 focus-within:ring-brand-500/30">
            <textarea id="commentBody" rows="1" maxlength="1000" placeholder="Write a comment…"
                      class="w-full resize-none bg-transparent text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none"
                      style="max-height: 8rem;"></textarea>

            <div class="-mx-1 flex items-center gap-0.5 pt-0.5">
              <button type="button" id="commentEmojiBtn"
                      class="grid h-7 w-7 place-items-center rounded-full text-slate-500 transition hover:bg-amber-100 hover:text-amber-600"
                      aria-label="Insert emoji" aria-haspopup="true" aria-expanded="false">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
              </button>
              <label for="commentImage"
                     class="grid h-7 w-7 cursor-pointer place-items-center rounded-full text-slate-500 transition hover:bg-emerald-100 hover:text-emerald-600"
                     aria-label="Attach image">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              </label>
              <input id="commentImage" type="file" accept="image/*" class="hidden">
            </div>

            {{-- Emoji picker panel --}}
            <div id="commentEmojiPanel"
                 class="absolute bottom-full right-0 z-30 mb-2 hidden w-64 rounded-2xl border border-slate-200 bg-white p-2 shadow-pop"
                 role="dialog" aria-label="Emoji picker">
              <div class="mb-1 px-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">Quick</div>
              <div class="grid grid-cols-8 gap-0.5 text-lg" data-emoji-grid>
                @php
                  $emojis = ['😀','😁','😂','🤣','😊','😍','🥰','😘',
                             '😎','🤩','🤔','😴','🙄','😅','😢','😭',
                             '😡','🤯','🥳','🤗','😜','🤤','😱','🤝',
                             '👍','👎','👏','🙏','💪','🔥','✨','💯',
                             '❤️','💔','💕','💖','💙','💚','💛','💜',
                             '🎉','🎊','🎁','🌟','⭐','☀️','🌈','💡',
                             '☕','🍕','🍔','🍰','🍻','🥂','🎵','📸'];
                @endphp
                @foreach ($emojis as $e)
                  <button type="button" data-emoji="{{ $e }}"
                          class="grid h-7 w-7 place-items-center rounded transition hover:bg-slate-100">{{ $e }}</button>
                @endforeach
              </div>
            </div>
          </div>

          <button type="submit" id="commentSubmit"
                  class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-soft transition hover:-translate-y-0.5 hover:shadow-pop disabled:cursor-not-allowed disabled:opacity-40"
                  aria-label="Send">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
