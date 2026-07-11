{{-- OMRMS tab. The omrms.com companion article site runs off this same
     database. Figures here are content + engagement (article counts, reads =
     article views); the visitor tables store only paths, not hosts, so this is
     not a raw-traffic split by domain. --}}

<div class="strip">
  <div class="kpi">
    <div class="kpi-label">Articles</div>
    <div class="kpi-value">{{ number_format($omrms['headline']['articles']) }}</div>
    <div class="kpi-foot"><span>live on omrms.com</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Total reads</div>
    <div class="kpi-value">{{ number_format($omrms['headline']['reads']) }}</div>
    <div class="kpi-foot"><span>article views, all time</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Categories</div>
    <div class="kpi-value">{{ number_format($omrms['headline']['categories']) }}</div>
    <div class="kpi-foot"><span>with published articles</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Contributors</div>
    <div class="kpi-value">{{ number_format($omrms['headline']['authors']) }}</div>
    <div class="kpi-foot"><span>distinct article authors</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">New articles</div>
    <div class="kpi-value">{{ number_format($omrms['newArticles']) }}</div>
    <div class="kpi-foot"><span>published in the last {{ $days }} days</span></div>
  </div>
</div>

<div class="mt-4 panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">Top articles</div>
      <div class="panel__sub">Most-read articles, by views &mdash; links open on omrms.com</div>
    </div>
  </div>
  <div class="panel__body">
    @php $maxViews = collect($omrms['top'])->max('views') ?: 1; @endphp
    @forelse($omrms['top'] as $art)
      <div class="barline">
        <div class="barline__head">
          <span class="barline__name">
            <a href="{{ $art['url'] }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($art['title'], 80) }}</a>
          </span>
          <span class="barline__val">{{ number_format($art['views']) }} <span class="barline__sub">views</span></span>
        </div>
        <div class="barline__track">
          <div class="barline__fill" style="width: {{ max(round($art['views'] / $maxViews * 100), 1) }}%"></div>
        </div>
      </div>
    @empty
      <div class="empty">
        <div class="empty__title">No articles yet</div>
        <div class="empty__hint">Articles published on Tanbat appear on omrms.com automatically.</div>
      </div>
    @endforelse
  </div>
</div>

<div class="mt-4 panel">
  <div class="panel__body" style="font-size:13px;color:var(--muted,#6b7280);line-height:1.6">
    <strong>About these numbers.</strong> omrms.com and tanbat.com share one database, and page-view
    logging records the path only (not the domain), so the figures above are article content and
    engagement — not a raw traffic split by domain. A true omrms.com-vs-tanbat.com traffic breakdown
    would need per-host view tracking; ask if you'd like that added.
  </div>
</div>
