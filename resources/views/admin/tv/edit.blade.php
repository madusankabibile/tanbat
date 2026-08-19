@extends('admin.layout')
@section('title', 'Edit ' . $channel->name)
@section('breadcrumb', 'Manage › TV Channels')
@section('heading', 'Edit TV post')

@section('content')

<div class="mb-4 flex items-center justify-between gap-3">
  <a href="{{ route('admin.tv.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">← Back to TV channels</a>
  <a href="{{ route('tv.show', $channel->slug) }}" target="_blank" class="btn-xs">Open player</a>
</div>

<form method="POST" action="{{ route('admin.tv.update', $channel) }}" enctype="multipart/form-data" class="grid gap-5 lg:grid-cols-3">
  @csrf
  @method('PUT')
  @include('admin.tv._form', ['channel' => $channel])
</form>

@endsection
