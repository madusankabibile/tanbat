@extends('admin.layout')
@section('title', 'Post · '.$post->id)
@section('breadcrumb', 'Manage › Posts')
@section('heading', $post->title ?: 'Post #'.$post->id)

@section('content')

<div class="mb-4 flex items-center justify-between">
  <a href="{{ route('admin.posts.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">← Back to posts</a>
  <div class="flex gap-2">
    <a href="{{ route('admin.posts.edit', $post) }}" class="btn-outline">Edit</a>
    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" onsubmit="return confirm('Delete this post?');">
      @csrf @method('DELETE')
      <button class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">Delete</button>
    </form>
  </div>
</div>

<div class="grid gap-5 lg:grid-cols-3">

  <article class="card p-6 lg:col-span-2 space-y-4">
    <div class="flex items-center gap-2 text-xs">
      <span class="badge badge-type">{{ $post->type }}</span>
      @if($post->category) <span class="badge badge-user">{{ $post->category->name }}</span> @endif
      @if($post->is_adult) <span class="badge bg-rose-100 text-rose-700">18+</span> @endif
      <span class="text-slate-400">· {{ $post->created_at?->format('M j, Y H:i') }}</span>
    </div>

    @if($post->featured_image_url)
      <img src="{{ $post->featured_image_url }}" alt="" class="w-full rounded-xl object-cover max-h-[480px]">
    @endif

    @if($post->type === 'status' && $post->status_text)
      <div class="rounded-xl p-6 text-center text-xl font-bold text-white"
           style="background: {{ $post->bg_color ?: '#6C63FF' }}; color: {{ $post->font_color ?: '#fff' }}">
        {{ $post->status_text }}
      </div>
    @endif

    @if($post->title) <h2 class="text-2xl font-extrabold text-slate-900">{{ $post->title }}</h2> @endif
    @if($post->short_description) <p class="text-slate-600">{{ $post->short_description }}</p> @endif

    @if($post->description) <p class="text-slate-700 whitespace-pre-wrap">{{ $post->description }}</p> @endif
    @if($post->body) <div class="prose max-w-none text-slate-800">{!! $post->body !!}</div> @endif

    @if($post->media->isNotEmpty())
      <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        @foreach($post->media as $m)
          <img src="{{ asset('storage/'.$m->path) }}" class="aspect-square w-full rounded-lg object-cover">
        @endforeach
      </div>
    @endif

    @if($post->embed_url)
      <a href="{{ $post->embed_url }}" target="_blank" class="text-brand-600 hover:underline">{{ $post->embed_url }}</a>
    @endif
  </article>

  <aside class="space-y-4">
    <div class="card p-5">
      <div class="text-sm font-bold text-slate-900">Engagement</div>
      <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
        <div><dt class="text-xs uppercase text-slate-400">Likes</dt><dd class="mt-1 text-xl font-extrabold text-slate-800">{{ $post->likes_count }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Comments</dt><dd class="mt-1 text-xl font-extrabold text-slate-800">{{ $post->comments_count }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Views</dt><dd class="mt-1 text-xl font-extrabold text-slate-800">{{ $post->views_count }}</dd></div>
      </dl>
    </div>

    <div class="card p-5">
      <div class="text-sm font-bold text-slate-900">Author</div>
      <div class="mt-3 flex items-center gap-3">
        @if($post->user?->profile_picture)
          <img class="h-12 w-12 rounded-full object-cover" src="{{ asset('storage/'.$post->user->profile_picture) }}" alt="">
        @else
          <span class="grid h-12 w-12 place-items-center rounded-full bg-brand-100 text-base font-bold text-brand-700">{{ strtoupper(mb_substr($post->user?->name ?? '?',0,1)) }}</span>
        @endif
        <div>
          <div class="font-bold text-slate-900">{{ $post->user?->name ?? 'Deleted' }}</div>
          <div class="text-xs text-slate-500">{{ $post->user ? '@'.$post->user->username : '' }}</div>
        </div>
      </div>
      @if($post->user)
        <a href="{{ route('admin.users.edit', $post->user) }}" class="btn-outline mt-3 w-full justify-center">Open in users</a>
      @endif
    </div>

    <div class="card p-5">
      <div class="text-sm font-bold text-slate-900">Recent comments</div>
      <div class="mt-3 space-y-3">
        @forelse($post->comments->take(8) as $c)
          <div class="text-sm">
            <div class="font-semibold text-slate-800">{{ $c->user?->name ?? '—' }}</div>
            <div class="text-slate-600">{{ \Illuminate\Support\Str::limit($c->body, 140) }}</div>
            <div class="text-[11px] text-slate-400">{{ $c->created_at?->diffForHumans() }}</div>
          </div>
        @empty
          <p class="text-sm text-slate-500">No comments yet.</p>
        @endforelse
      </div>
    </div>
  </aside>

</div>

@endsection
