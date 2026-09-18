{{-- Native banner ad (omrms.com) — managed via Admin › Ad Spaces --}}
@if(\App\Support\AdSpace::isEnabled('omrms_native'))
  {!! \App\Support\AdSpace::render('omrms_native') !!}
@endif
