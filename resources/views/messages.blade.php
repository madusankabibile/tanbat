@extends('layouts.app')
@section('title', 'Messages — Tanbat')

@section('content')
@include('partials.navbar')

@php
  $peerPayload = isset($peer) && $peer ? [
    'id'              => $peer->id,
    'name'            => $peer->name,
    'username'        => $peer->username,
    'profile_picture' => $peer->avatarUrl(),
    'online'          => $peer->updated_at && $peer->updated_at->gte(now()->subMinutes(5)),
  ] : null;
@endphp

<div class="mx-auto w-full max-w-[1280px] px-3 py-5 sm:px-5 lg:px-6">
  <div id="messengerPage" class="grid grid-cols-1 md:grid-cols-[320px_1fr] gap-4 h-[calc(100vh-120px)]"
       data-initial-peer='@json($peerPayload)'>

    {{-- Threads sidebar --}}
    <aside class="card overflow-hidden flex flex-col">
      <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
        <h2 class="text-base font-bold text-slate-900">Messages</h2>
        <button type="button" id="threadsRefresh" class="text-xs font-semibold text-brand-600 hover:underline">Refresh</button>
      </div>
      <div class="px-3 py-2 border-b border-slate-100">
        <div class="relative">
          <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input id="threadsSearch" type="search" placeholder="Search conversations…"
                 class="w-full rounded-full border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm placeholder:text-slate-400 focus:border-brand-400 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-500/10">
        </div>
      </div>
      <ul id="threadsList" class="flex-1 overflow-y-auto">
        <li class="px-4 py-10 text-center text-xs text-slate-400">Loading conversations…</li>
      </ul>
    </aside>

    {{-- Active thread --}}
    <section id="threadPane" class="card overflow-hidden flex flex-col">
      <div id="threadEmpty" class="flex flex-1 flex-col items-center justify-center px-6 text-center">
        <div class="grid h-16 w-16 place-items-center rounded-full bg-brand-50 text-brand-500 mb-3">
          <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        </div>
        <p class="text-sm font-semibold text-slate-700">Select a conversation</p>
        <p class="mt-1 text-xs text-slate-500">Pick a chat on the left, or open a profile and click <span class="font-semibold">Message</span>.</p>
      </div>

      <div id="threadActive" class="hidden flex-1 min-h-0 flex flex-col">
        {{-- Header --}}
        <header class="flex items-center gap-3 px-4 py-3 border-b border-slate-100">
          <a id="peerLink" href="#" class="flex items-center gap-3 min-w-0">
            <span id="peerAvatar" class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-accent-500 text-sm font-bold text-white shrink-0"></span>
            <div class="min-w-0">
              <div id="peerName" class="truncate text-sm font-bold text-slate-900">…</div>
              <div id="peerStatus" class="truncate text-[11px] text-slate-500">@&hellip;</div>
            </div>
          </a>
          <div class="ml-auto flex items-center gap-1">
            <button type="button" id="peerCallVoice" class="nav-icon" title="Voice call (coming soon)">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.13.94.34 1.87.63 2.76a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.89.29 1.82.5 2.76.63A2 2 0 0122 16.92z"/></svg>
            </button>
            <button type="button" id="peerCallVideo" class="nav-icon" title="Video call (coming soon)">
              <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
            </button>
          </div>
        </header>

        {{-- Body --}}
        <div id="threadBody" class="flex-1 min-h-0 overflow-y-auto px-4 py-4 space-y-2 bg-slate-50/40">
          {{-- messages injected here --}}
        </div>

        {{-- Typing indicator --}}
        <div id="typingRow" class="px-4 pb-1" style="display: none;">
          <div class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 border border-slate-200 shadow-sm">
            <span class="dot-jump"></span><span class="dot-jump"></span><span class="dot-jump"></span>
            <span id="typingWho" class="text-[11px] text-slate-500 ml-1">typing…</span>
          </div>
        </div>

        {{-- Composer --}}
        <form id="threadComposer" class="flex items-end gap-2 border-t border-slate-100 px-3 py-2.5 bg-white">
          <div class="composer-preview" id="threadPreview" hidden>
            <img id="threadPreviewImg" alt="">
            <button type="button" class="preview-x" id="threadPreviewX" aria-label="Remove">×</button>
          </div>
          <button type="button" id="threadEmojiBtn" class="nav-icon" title="Add emoji">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
          </button>
          <button type="button" id="threadAttachBtn" class="nav-icon" title="Add image">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          </button>
          <input type="file" id="threadFile" accept="image/*" hidden>
          <textarea id="threadInput" rows="1" placeholder="Write a message…"
            class="flex-1 max-h-32 resize-none rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm placeholder:text-slate-400 focus:border-brand-400 focus:bg-white focus:outline-none focus:ring-4 focus:ring-brand-500/10"></textarea>
          <button type="submit" class="grid h-10 w-10 place-items-center rounded-full bg-gradient-to-br from-brand-500 to-brand-600 text-white shadow-soft transition hover:-translate-y-0.5 hover:shadow-pop disabled:opacity-60" title="Send">
            <svg class="h-5 w-5 -mr-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
          </button>
        </form>
      </div>
    </section>

  </div>
</div>

@include('partials.chat-dock')

@push('scripts')
  @vite('resources/js/messenger.js')
@endpush
@endsection
