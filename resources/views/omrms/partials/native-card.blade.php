{{-- Native ad occupying the first grid cell, styled to match an article card. --}}
@if(\App\Support\AdSpace::isEnabled('omrms_native'))
<article class="omr-card omr-card-ad">
  <div class="omr-card-ad-tag">Sponsored</div>
  @include('omrms.partials.ad-native')
</article>
@endif
