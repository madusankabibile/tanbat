@extends('admin.layout')
@section('title', 'Add TV post')
@section('breadcrumb', 'Manage › TV Channels')
@section('heading', 'Add TV post')

@section('content')

<div class="mb-4">
  <a href="{{ route('admin.tv.index') }}" class="text-sm font-semibold text-brand-600 hover:underline">← Back to TV channels</a>
</div>

<form method="POST" action="{{ route('admin.tv.store') }}" enctype="multipart/form-data" class="grid gap-5 lg:grid-cols-3">
  @csrf
  @include('admin.tv._form', ['channel' => null])
</form>

@endsection
