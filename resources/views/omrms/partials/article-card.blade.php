@php
    use App\Support\Omrms;
    $url   = Omrms::articleUrl($post);
    $cover = Omrms::img($post->featured_image_url);
@endphp
<article class="omr-card">
  <a class="omr-card-fig" href="{{ $url }}">
    @if($cover)
      <img src="{{ $cover }}" alt="{{ $post->title }}" loading="lazy">
    @else
      <span class="omr-card-noimg">{{ \Illuminate\Support\Str::substr($post->title, 0, 1) }}</span>
    @endif
  </a>
  <div class="omr-card-body">
    @if($post->category)
      <a class="omr-cat" href="{{ Omrms::url('/category/' . $post->category->slug) }}">{{ $post->category->name }}</a>
    @endif
    <h2><a href="{{ $url }}">{{ $post->title }}</a></h2>
    <div class="omr-card-meta">
      {{ optional($post->created_at)->format('M j, Y') }}
      @if($post->user) · {{ $post->user->name ?: $post->user->username }}@endif
    </div>
  </div>
</article>
