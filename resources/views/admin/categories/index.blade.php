@extends('admin.layout')
@section('title', 'Categories')
@section('breadcrumb', 'Manage')
@section('heading', 'Categories')

@section('content')

<div class="grid gap-5 lg:grid-cols-3">

  <div class="card p-5 lg:col-span-2">
    <form method="GET" class="mb-4 flex gap-2">
      <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or slug" class="input flex-1">
      <button class="btn-primary">Search</button>
      @if($q)<a href="{{ route('admin.categories.index') }}" class="btn-outline">Reset</a>@endif
    </form>

    {{-- Place forms outside the table so we can reference them via the form="" attribute. --}}
    @foreach($categories as $cat)
      <form id="cat-edit-{{ $cat->id }}" method="POST" action="{{ route('admin.categories.update', $cat) }}">
        @csrf @method('PUT')
      </form>
      <form id="cat-del-{{ $cat->id }}" method="POST" action="{{ route('admin.categories.destroy', $cat) }}"
            onsubmit="return confirm('Delete this category? Posts will keep their content but lose the category.');">
        @csrf @method('DELETE')
      </form>
    @endforeach

    <div class="overflow-hidden rounded-xl border border-slate-200">
      <table class="w-full">
        <thead class="bg-slate-50">
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Name</th>
            <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">Slug</th>
            <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500">Posts</th>
            <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wide text-slate-500">Actions</th>
          </tr>
        </thead>
        <tbody>
          @forelse($categories as $cat)
            <tr class="border-t border-slate-100">
              <td class="px-4 py-2.5">
                <input form="cat-edit-{{ $cat->id }}" name="name" value="{{ $cat->name }}" class="input" required>
              </td>
              <td class="px-4 py-2.5">
                <input form="cat-edit-{{ $cat->id }}" name="slug" value="{{ $cat->slug }}" class="input" placeholder="(auto)">
              </td>
              <td class="px-4 py-2.5 text-right text-sm font-semibold text-slate-700">{{ $cat->posts_count }}</td>
              <td class="px-4 py-2.5 text-right">
                <div class="inline-flex gap-1.5">
                  <button form="cat-edit-{{ $cat->id }}" type="submit" class="btn-xs btn-xs-primary">Save</button>
                  <button form="cat-del-{{ $cat->id }}"  type="submit" class="btn-xs btn-xs-danger">Delete</button>
                </div>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">No categories yet.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <div class="pager mt-5 flex items-center gap-1.5">
      {{ $categories->links() }}
    </div>
  </div>

  <div class="card p-5">
    <div class="text-sm font-bold text-slate-900">Add a new category</div>
    <p class="mt-1 text-xs text-slate-500">Slug auto-generated if left blank.</p>

    <form method="POST" action="{{ route('admin.categories.store') }}" class="mt-4 space-y-3">
      @csrf
      <div>
        <label class="label">Name</label>
        <input type="text" name="name" value="{{ old('name') }}" class="input" required>
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
      </div>
      <div>
        <label class="label">Slug (optional)</label>
        <input type="text" name="slug" value="{{ old('slug') }}" class="input" placeholder="auto-generated">
        @error('slug') <p class="field-error">{{ $message }}</p> @enderror
      </div>
      <button class="btn-primary w-full">Create category</button>
    </form>
  </div>

</div>

@endsection
