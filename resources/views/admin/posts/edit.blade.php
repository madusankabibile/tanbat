@extends('admin.layout')
@section('title', 'Edit post · '.$post->id)
@section('breadcrumb', 'Manage › Posts')
@section('heading', 'Edit post')

@section('content')

<div class="mb-4">
  <a href="{{ route('admin.posts.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">← Back to posts</a>
</div>

<form method="POST" action="{{ route('admin.posts.update', $post) }}" class="card grid gap-4 p-6">
  @csrf @method('PUT')

  <div class="flex items-center gap-2 text-xs">
    <span class="badge badge-type">{{ $post->type }}</span>
    <span class="text-slate-400">{{ $post->created_at?->format('M j, Y H:i') }}</span>
  </div>

  @if(in_array($post->type, ['article'], true))
    <div>
      <label class="label">Title</label>
      <input type="text" name="title" value="{{ old('title', $post->title) }}" class="input">
      @error('title') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
      <label class="label">Short description</label>
      <textarea name="short_description" rows="2" class="input">{{ old('short_description', $post->short_description) }}</textarea>
    </div>
    <div>
      <label class="label">Body (HTML)</label>
      <textarea name="body" rows="14" class="input font-mono text-xs">{{ old('body', $post->body) }}</textarea>
    </div>
  @endif

  @if($post->type === 'status')
    <div>
      <label class="label">Status text</label>
      <textarea name="status_text" rows="4" class="input">{{ old('status_text', $post->status_text) }}</textarea>
    </div>
  @endif

  @if(in_array($post->type, ['image', 'video'], true))
    <div>
      <label class="label">Description / caption</label>
      <textarea name="description" rows="4" class="input">{{ old('description', $post->description) }}</textarea>
    </div>
  @endif

  <div class="grid gap-4 sm:grid-cols-2">
    <div>
      <label class="label">Category</label>
      <select name="category_id" class="input">
        <option value="">— none —</option>
        @foreach($categories as $c)
          <option value="{{ $c->id }}" @selected((int) old('category_id', $post->category_id) === $c->id)>{{ $c->name }}</option>
        @endforeach
      </select>
    </div>
    <div class="flex items-end">
      <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
        <input type="hidden" name="is_adult" value="0">
        <input type="checkbox" name="is_adult" value="1" @checked(old('is_adult', $post->is_adult))
               class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        Mark as 18+ (adult content)
      </label>
    </div>
  </div>

  <div class="flex items-center justify-end gap-2 pt-2">
    <a href="{{ route('admin.posts.show', $post) }}" class="btn-outline">Cancel</a>
    <button class="btn-primary">Save changes</button>
  </div>
</form>

@endsection
