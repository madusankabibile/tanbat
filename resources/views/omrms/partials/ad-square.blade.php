{{-- 300x250 display ad (omrms.com) — managed via Admin › Ad Spaces --}}
@if(\App\Support\AdSpace::isEnabled('omrms_sidebar'))
  {!! \App\Support\AdSpace::render('omrms_sidebar') !!}
@endif
