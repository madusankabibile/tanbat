{{-- Referrers tab. RecordVisitor nulls out same-host referers, so everything
     here is genuinely external; a visitor with no referrer arrived directly. --}}

<div class="panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">Referrers</div>
      <div class="panel__sub">External sites sending visitors here, last {{ $visitorTableDays }} days</div>
    </div>
  </div>
  <div class="panel__body">
    @php $maxRef = collect($referrers)->max('visitors') ?: 1; @endphp
    @forelse($referrers as $ref)
      <div class="barline">
        <div class="barline__head">
          <span class="barline__name"><span class="mono">{{ $ref['host'] }}</span></span>
          <span class="barline__val">
            {{ number_format($ref['visitors']) }}
            <span class="barline__sub">/ {{ number_format($ref['hits']) }} hits</span>
          </span>
        </div>
        <div class="barline__track">
          <div class="barline__fill barline__fill--brass" style="width: {{ max(round($ref['visitors'] / $maxRef * 100), 1) }}%"></div>
        </div>
      </div>
    @empty
      <div class="empty">
        <div class="empty__title">No external referrers</div>
        <div class="empty__hint">Every visitor so far arrived directly or from within the site.</div>
      </div>
    @endforelse

    <div class="note">
      Each visitor contributes only their <em>most recent</em> referrer — <code>visitors</code>
      overwrites it on every page view — so this counts how visitors last arrived, not every
      arrival they ever made.
    </div>
  </div>
</div>
