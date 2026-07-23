@extends('admin.layout')
@section('title', 'Posts')
@section('breadcrumb', 'Manage')
@section('heading', 'Posts')

@section('content')

<form method="GET" class="mb-4 card flex flex-wrap items-end gap-3 p-4">
  <div class="flex-1 min-w-[220px]">
    <label class="label">Search</label>
    <input type="text" name="q" value="{{ $q }}" placeholder="Title, status or description" class="input">
  </div>
  <div>
    <label class="label">Type</label>
    <select name="type" class="input">
      <option value="">All</option>
      @foreach(['status', 'image', 'video', 'article'] as $t)
        <option value="{{ $t }}" @selected($type === $t)>{{ ucfirst($t) }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="label">Category</label>
    <select name="category" class="input">
      <option value="">All</option>
      @foreach($categories as $c)
        <option value="{{ $c->id }}" @selected((int) $categoryId === $c->id)>{{ $c->name }}</option>
      @endforeach
    </select>
  </div>
  <div>
    <label class="label">Sort</label>
    <select name="sort" class="input">
      <option value="newest"      @selected($sort === 'newest')>Newest</option>
      <option value="oldest"      @selected($sort === 'oldest')>Oldest</option>
      <option value="most_liked"  @selected($sort === 'most_liked')>Most liked</option>
      <option value="most_viewed" @selected($sort === 'most_viewed')>Most viewed</option>
    </select>
  </div>
  <div class="flex gap-2">
    <button class="btn-primary">Filter</button>
    <a href="{{ route('admin.posts.index') }}" class="btn-outline">Reset</a>
  </div>
</form>

<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>Post</th>
        <th>Author</th>
        <th>Category</th>
        <th>Type</th>
        <th class="text-right">Likes</th>
        <th class="text-right">Comments</th>
        <th class="text-right">Views</th>
        <th>Created</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($posts as $p)
        <tr>
          <td class="max-w-xs">
            <a href="{{ route('admin.posts.show', $p) }}" class="block truncate font-semibold text-slate-900 hover:text-brand-600">
              {{ $p->title ?: \Illuminate\Support\Str::limit(strip_tags($p->status_text ?: $p->description ?: '(no text)'), 80) }}
            </a>
            @if($p->slug)
              <div class="text-xs text-slate-500 truncate">{{ $p->slug }}</div>
            @endif
          </td>
          <td data-label="Author">
            <div class="flex items-center gap-2">
              @if($p->user?->profile_picture)
                <img class="avatar-circle" style="width:28px;height:28px;font-size:.7rem" src="{{ asset('storage/'.$p->user->profile_picture) }}" alt="">
              @else
                <span class="avatar-circle" style="width:28px;height:28px;font-size:.7rem">{{ strtoupper(mb_substr($p->user?->name ?? '?',0,1)) }}</span>
              @endif
              <div>
                <div class="text-sm font-semibold text-slate-800">{{ $p->user?->name ?? '—' }}</div>
                <div class="text-[11px] text-slate-500">{{ $p->user ? '@'.$p->user->username : '' }}</div>
              </div>
            </div>
          </td>
          <td class="text-slate-600" data-label="Category">{{ $p->category?->name ?? '—' }}</td>
          <td data-label="Type"><span class="badge badge-type">{{ $p->type }}</span></td>
          <td class="text-right font-semibold" data-label="Likes">{{ $p->likes_count }}</td>
          <td class="text-right font-semibold" data-label="Comments">{{ $p->comments_count }}</td>
          <td class="text-right font-semibold" data-label="Views">{{ $p->views_count }}</td>
          <td class="text-xs text-slate-500" data-label="Created">{{ $p->created_at?->format('M j, Y') }}</td>
          <td class="text-right cell-actions">
            <div class="inline-flex gap-1.5">
              <a href="{{ route('admin.posts.show', $p) }}" class="btn-xs">View</a>
              <a href="{{ route('admin.posts.edit', $p) }}" class="btn-xs">Edit</a>
              <form method="POST" action="{{ route('admin.posts.destroy', $p) }}" onsubmit="return confirm('Delete this post?');">
                @csrf @method('DELETE')
                <button class="btn-xs btn-xs-danger">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="9" class="py-8 text-center text-sm text-slate-500">No posts match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager mt-5 flex items-center gap-1.5">
  {{ $posts->links() }}
</div>

@endsection
