@extends('admin.layout')
@section('title', 'Users')
@section('breadcrumb', 'Manage')
@section('heading', 'Users')

@section('content')

<form method="GET" class="mb-4 card flex flex-wrap items-end gap-3 p-4">
  <div class="flex-1 min-w-[220px]">
    <label class="label">Search</label>
    <input type="text" name="q" value="{{ $q }}" placeholder="Name, username or email" class="input">
  </div>
  <div>
    <label class="label">Role</label>
    <select name="role" class="input">
      <option value="">All</option>
      <option value="user"  @selected($role === 'user')>User</option>
      <option value="admin" @selected($role === 'admin')>Admin</option>
    </select>
  </div>
  <div>
    <label class="label">Sort</label>
    <select name="sort" class="input">
      <option value="newest"     @selected($sort === 'newest')>Newest</option>
      <option value="oldest"     @selected($sort === 'oldest')>Oldest</option>
      <option value="most_posts" @selected($sort === 'most_posts')>Most posts</option>
      <option value="az"         @selected($sort === 'az')>A–Z</option>
    </select>
  </div>
  <div class="flex gap-2">
    <button class="btn-primary">Filter</button>
    <a href="{{ route('admin.users.index') }}" class="btn-outline">Reset</a>
  </div>
</form>

<div class="table-card">
  <table>
    <thead>
      <tr>
        <th>User</th>
        <th>Email</th>
        <th>Role</th>
        <th class="text-right">Posts</th>
        <th class="text-right">Comments</th>
        <th>Joined</th>
        <th class="text-right">Actions</th>
      </tr>
    </thead>
    <tbody>
      @forelse($users as $user)
        <tr>
          <td>
            <div class="flex items-center gap-3">
              @if($user->profile_picture)
                <img class="avatar-circle" src="{{ asset('storage/'.$user->profile_picture) }}" alt="">
              @else
                <span class="avatar-circle">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span>
              @endif
              <div class="min-w-0">
                <div class="font-semibold text-slate-900">{{ $user->name }}</div>
                <div class="text-xs text-slate-500">{{ '@' . $user->username }}</div>
              </div>
            </div>
          </td>
          <td class="text-slate-600">{{ $user->email }}</td>
          <td><span class="badge {{ $user->role === 'admin' ? 'badge-admin' : 'badge-user' }}">{{ $user->role }}</span></td>
          <td class="text-right font-semibold">{{ $user->posts_count }}</td>
          <td class="text-right font-semibold">{{ $user->comments_count }}</td>
          <td class="text-slate-500 text-xs">{{ $user->created_at?->format('M j, Y') }}</td>
          <td class="text-right">
            <div class="inline-flex gap-2">
              <a href="{{ url('/u/'.$user->username) }}" class="btn-xs" target="_blank">View</a>
              <a href="{{ route('admin.users.edit', $user) }}" class="btn-xs">Edit</a>
              <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Delete this user? Their posts will also be removed.');">
                @csrf @method('DELETE')
                <button class="btn-xs btn-xs-danger">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      @empty
        <tr><td colspan="7" class="py-8 text-center text-sm text-slate-500">No users match these filters.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager mt-5 flex items-center gap-1.5">
  {{ $users->links() }}
</div>

@endsection
