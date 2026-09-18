{{-- 300x250 display banner slot — managed via Admin › Ad Spaces --}}
@php
  $adSpaceKey = $placement ?? 'sidebar';
@endphp
@if(\App\Support\AdSpace::isEnabled($adSpaceKey))
  {!! \App\Support\AdSpace::render($adSpaceKey) !!}
@endif
