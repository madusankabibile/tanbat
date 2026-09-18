{{-- Native banner ad — managed via Admin › Ad Spaces --}}
@if(\App\Support\AdSpace::isEnabled('tv_native'))
  {!! \App\Support\AdSpace::render('tv_native') !!}
@endif
