{{-- Referrers tab: every external arrival, on either site. The OMRMS tab renders
     the same panel narrowed to arrivals onto omrms.com. --}}

@include('admin.statistics._referrer-panel', [
  'sub' => 'External sites sending visitors here, last ' . $visitorTableDays . ' days',
])
