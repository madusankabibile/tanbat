{{-- OMRMS tab. omrms.com is the article-only companion site served from this
     same codebase and database as tanbat.com — so "reach" here means views that
     were actually recorded on an omrms.com host, which the visitor tables now
     store alongside the path.

     Two kinds of number live on this tab, and they are kept apart on purpose:
       reach   — traffic that landed on omrms.com (today, the window, top pages,
                 referrers). Measured per host, so it is a true domain split.
       content — articles, categories, authors, all-time reads. Database-wide:
                 an article is the same article whichever site serves it. --}}

<div class="strip">
  <div class="kpi">
    <div class="kpi-label">Views today</div>
    <div class="kpi-value">{{ number_format($omrms['today']['views']) }}</div>
    <div class="kpi-foot">
      @include('admin.partials._trend', [
        't' => $omrms['today']['views_trend'], 'currentText' => 'today', 'compareText' => 'vs yesterday',
      ])
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Visitors today</div>
    <div class="kpi-value">{{ number_format($omrms['today']['uniques']) }}</div>
    <div class="kpi-foot">
      @include('admin.partials._trend', [
        't' => $omrms['today']['uniques_trend'], 'currentText' => 'today', 'compareText' => 'vs yesterday',
      ])
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Reading now</div>
    <div class="kpi-value">{{ number_format($omrms['today']['live']) }}</div>
    <div class="kpi-foot"><span>on omrms.com in the last {{ $omrms['today']['live_minutes'] }} minutes</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Views &middot; {{ $days }}d</div>
    <div class="kpi-value">{{ number_format($omrms['window']['views']) }}</div>
    <div class="kpi-foot">
      @include('admin.partials._trend', [
        't' => $omrms['window']['views_trend'],
        'currentText' => 'this window',
        'compareText' => 'vs previous ' . $days . ' days',
      ])
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Share of traffic</div>
    <div class="kpi-value">{{ $omrms['window']['share'] }}%</div>
    <div class="kpi-foot">
      <span>
        {{ number_format($omrms['window']['views']) }} of
        {{ number_format($omrms['window']['site_views']) }} views, all sites
      </span>
    </div>
  </div>
</div>

<div class="strip mt-3">
  <div class="kpi">
    <div class="kpi-label">Visitors &middot; {{ $days }}d</div>
    <div class="kpi-value">{{ number_format($omrms['window']['uniques']) }}</div>
    <div class="kpi-foot">
      @include('admin.partials._trend', [
        't' => $omrms['window']['uniques_trend'],
        'currentText' => 'this window',
        'compareText' => 'vs previous ' . $days . ' days',
      ])
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Views per visitor</div>
    <div class="kpi-value">{{ $omrms['window']['views_per_visitor'] }}</div>
    <div class="kpi-foot"><span>on omrms.com, last {{ $days }} days</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Articles</div>
    <div class="kpi-value">{{ number_format($omrms['headline']['articles']) }}</div>
    <div class="kpi-foot"><span>live on omrms.com</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">New articles</div>
    <div class="kpi-value">{{ number_format($omrms['newArticles']) }}</div>
    <div class="kpi-foot"><span>published in the last {{ $days }} days</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Total reads</div>
    <div class="kpi-value">{{ number_format($omrms['headline']['reads']) }}</div>
    <div class="kpi-foot"><span>article views, all time, both sites</span></div>
  </div>
</div>

<div class="mt-4 grid gap-4 lg:grid-cols-2">

  {{-- Reach: the omrms.com URLs people actually opened in the window. --}}
  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Most reached omrms.com pages</div>
        <div class="panel__sub">Views recorded on omrms.com, last {{ $days }} days &mdash; links open there</div>
      </div>
    </div>
    <div class="panel__body">
      @php $maxReach = collect($omrms['topPages'])->max('views') ?: 1; @endphp
      @forelse($omrms['topPages'] as $p)
        <div class="barline">
          <div class="barline__head">
            <span class="barline__name">
              <a class="mono" href="{{ $p['url'] }}" target="_blank" rel="noopener">{{ $p['path'] }}</a>
            </span>
            <span class="barline__val">
              {{ number_format($p['views']) }}
              <span class="barline__sub">/ {{ number_format($p['uniques']) }} visitors</span>
            </span>
          </div>
          <div class="barline__track">
            <div class="barline__fill" style="width: {{ max(round($p['views'] / $maxReach * 100), 1) }}%"></div>
          </div>
        </div>
      @empty
        <div class="empty">
          <div class="empty__title">No omrms.com views recorded yet</div>
          <div class="empty__hint">
            Host tracking starts from the first view logged after the <code>host</code> column was added —
            earlier views belong to no site and are not counted here.
          </div>
        </div>
      @endforelse
    </div>
  </div>

  {{-- Content: engagement with the articles themselves, all time, both sites. --}}
  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Most-read articles</div>
        <div class="panel__sub">By all-time reads on either site &mdash; links open on omrms.com</div>
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
            <span class="barline__val">{{ number_format($art['views']) }} <span class="barline__sub">reads</span></span>
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
</div>

{{-- The same referrer panel the Referrers tab renders, narrowed to arrivals that
     landed on omrms.com. Clicking a host opens the exact links people followed
     in, and the omrms.com page each one opened. --}}
<div class="mt-4">
  @include('admin.statistics._referrer-panel', [
    'title'     => 'Referrers into omrms.com',
    'sub'       => 'External sites whose links landed visitors on omrms.com, last ' . $visitorTableDays . ' days',
    'emptyHint' => 'No visitor has arrived on omrms.com from an external link yet — arrivals so far were direct or from within the site.',
  ])
</div>

<div class="note">
  Reach figures count only views recorded on an <code>omrms.com</code> host. Views logged before
  per-host tracking existed carry no host and belong to neither site: they are excluded from every
  omrms figure, but still counted in the all-sites denominator behind "share of traffic", which
  therefore understates omrms's share until those rows age out (30 days for visitors, 90 for views).
  Reads and article counts are database-wide — an article is the same article on either site.
</div>
