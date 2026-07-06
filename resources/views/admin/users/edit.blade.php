@extends('admin.layout')
@section('title', 'Edit user · ' . $user->name)
@section('breadcrumb', 'Manage › Users')
@section('heading', 'Edit user')

@section('content')

<div class="mb-4">
  <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">← Back to users</a>
</div>

<div class="grid gap-5 lg:grid-cols-3">

  <div class="card p-5 lg:col-span-2">
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="grid gap-4 sm:grid-cols-2">
      @csrf @method('PUT')

      <div class="sm:col-span-2">
        <label class="label">Name</label>
        <input type="text" name="name" value="{{ old('name', $user->name) }}" class="input" required>
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
      </div>

      <div>
        <label class="label">Username</label>
        <input type="text" name="username" value="{{ old('username', $user->username) }}" class="input" required>
        @error('username') <p class="field-error">{{ $message }}</p> @enderror
      </div>

      <div>
        <label class="label">Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input" required>
        @error('email') <p class="field-error">{{ $message }}</p> @enderror
      </div>

      <div>
        <label class="label">Role</label>
        <select name="role" class="input">
          <option value="user"  @selected(old('role', $user->role) === 'user')>User</option>
          <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
        </select>
      </div>

      <div>
        <label class="label">Country (code)</label>
        <input type="text" name="country" value="{{ old('country', $user->country) }}" maxlength="5" class="input">
      </div>

      <div>
        <label class="label">Gender</label>
        <input type="text" name="gender" value="{{ old('gender', $user->gender) }}" class="input">
      </div>

      <div>
        <label class="label">Age</label>
        <input type="number" name="age" value="{{ old('age', $user->age) }}" min="0" max="255" class="input">
      </div>

      <div class="sm:col-span-2">
        <label class="label">Reset password (leave blank to keep)</label>
        <input type="password" name="password" class="input" placeholder="New password">
        @error('password') <p class="field-error">{{ $message }}</p> @enderror
      </div>

      <div class="sm:col-span-2 flex items-center justify-end gap-2 pt-2">
        <a href="{{ route('admin.users.index') }}" class="btn-outline">Cancel</a>
        <button class="btn-primary">Save changes</button>
      </div>
    </form>
  </div>

  <div class="space-y-4">
    <div class="card p-5">
      <div class="flex items-center gap-3">
        @if($user->profile_picture)
          <img class="h-14 w-14 rounded-full object-cover" src="{{ asset('storage/'.$user->profile_picture) }}" alt="">
        @else
          <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-100 text-xl font-bold text-brand-700">{{ strtoupper(mb_substr($user->name,0,1)) }}</span>
        @endif
        <div>
          <div class="font-bold text-slate-900">{{ $user->name }}</div>
          <div class="text-xs text-slate-500">{{ '@' . $user->username }}</div>
        </div>
      </div>
      <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
        <div><dt class="text-xs uppercase text-slate-400">Posts</dt><dd class="font-bold text-slate-800">{{ $user->posts_count }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Comments</dt><dd class="font-bold text-slate-800">{{ $user->comments_count }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Joined</dt><dd class="font-semibold text-slate-700">{{ $user->created_at?->format('M j, Y') }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Verified</dt><dd class="font-semibold text-slate-700">{{ $user->email_verified_at ? 'Yes' : 'No' }}</dd></div>
      </dl>
    </div>

    <div class="card p-5 border-rose-100">
      <div class="text-sm font-bold text-rose-700">Danger zone</div>
      <p class="mt-1 text-xs text-slate-500">Deletes the user and cascades to their posts.</p>
      <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="mt-3" onsubmit="return confirm('Permanently delete this user?');">
        @csrf @method('DELETE')
        <button class="w-full rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white hover:bg-rose-700">Delete user</button>
      </form>
    </div>
  </div>

</div>

@endsection
