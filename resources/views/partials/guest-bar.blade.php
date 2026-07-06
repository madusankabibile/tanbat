{{-- Slim brand bar shown on guest-accessible pages (e.g. /books and /books/{slug}
     for non-logged visitors). Gives them a clear path back to the landing page
     to sign in or register without exposing the full member navbar. --}}
<nav class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/85 backdrop-blur-xl">
  <div class="mx-auto flex h-14 sm:h-16 max-w-[1480px] items-center gap-2 px-3 sm:gap-3 sm:px-5 lg:px-6">
    <a href="{{ url('/') }}" class="flex items-center gap-2.5">
      <span class="flex h-9 w-9 sm:h-10 sm:w-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-accent-500 text-base sm:text-lg font-extrabold tracking-tighter text-white shadow-soft">T</span>
      <span class="hidden sm:inline text-base font-bold tracking-tight text-slate-900">Tanbat</span>
    </a>

    <div class="ml-auto flex items-center gap-2">
      <a href="{{ url('/') }}" class="hidden sm:inline-flex items-center rounded-lg px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-brand-600">
        Sign in
      </a>
      <a href="{{ url('/') }}" class="inline-flex items-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 px-3.5 py-2 text-sm font-semibold text-white shadow-soft transition hover:-translate-y-0.5 hover:shadow-pop">
        Create account
      </a>
    </div>
  </div>
</nav>
