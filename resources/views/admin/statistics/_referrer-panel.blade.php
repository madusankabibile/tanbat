{{-- Referrer panel, shared by the Referrers tab (all arrivals) and the OMRMS tab
     (arrivals onto omrms.com only). RecordVisitor nulls out same-host referers,
     so everything here is genuinely external; a visitor with no referrer arrived
     directly.

     Each host opens in place (`?ref=<host>`, a plain link like the tabs
     themselves) into the exact links behind it and the page each one opened.
     The link carries the *current* tab, so opening a host never moves the reader.

     Expects: $referrers, $refHost, $refLinks, $tab, $days, $visitorTableDays,
     and optionally $title / $sub / $emptyHint. --}}

@php
  /* Referring links are shown without their scheme: the host is already the row
     above, and what the reader is scanning is the path. */
  $short = fn (string $url) => \Illuminate\Support\Str::limit(preg_replace('#^https?://#i', '', $url), 72);

  /* The page was reached on the site the visitor's host says it was, so the link
     out has to point at that site — a landing page on omrms.com is not on tanbat. */
  $pageUrl = fn (string $host, string $path) => \App\Support\Omrms::isHost($host)
      ? \App\Support\Omrms::CANONICAL_URL . $path
      : url($path);

  /* Naming the site is only worth a line where both can appear. */
  $showSite = $tab !== 'omrms';

  $tabUrl = fn (?string $ref) => route('admin.statistics.index', array_filter([
      'tab'  => $tab,
      'days' => $days,
      'ref'  => $ref,
  ])) . '#tabpanel';
@endphp

<div class="panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">{{ $title ?? 'Referrers' }}</div>
      <div class="panel__sub">{{ $sub ?? 'External sites sending visitors here, last ' . $visitorTableDays . ' days' }}</div>
    </div>
  </div>
  <div class="panel__body">
    @php $maxRef = collect($referrers)->max('visitors') ?: 1; @endphp
    @forelse($referrers as $ref)
      @php $open = $refHost === $ref['host']; @endphp

      <a class="barline barline--open {{ $open ? 'is-open' : '' }}"
         href="{{ $tabUrl($open ? null : $ref['host']) }}"
         aria-expanded="{{ $open ? 'true' : 'false' }}"
         title="{{ $open ? 'Hide' : 'Show' }} the exact links from {{ $ref['host'] }}">
        <div class="barline__head">
          <span class="barline__name">
            <span class="barline__caret" aria-hidden="true">›</span>
            <span class="mono">{{ $ref['host'] }}</span>
          </span>
          <span class="barline__val">
            {{ number_format($ref['visitors']) }}
            <span class="barline__sub">/ {{ number_format($ref['hits']) }} hits</span>
          </span>
        </div>
        <div class="barline__track">
          <div class="barline__fill barline__fill--brass" style="width: {{ max(round($ref['visitors'] / $maxRef * 100), 1) }}%"></div>
        </div>
      </a>

      @if($open)
        <div class="drill">
          <div class="drill__head">
            <span>
              Exact links from <span class="mono">{{ $ref['host'] }}</span> —
              {{ number_format(count($refLinks)) }} {{ Str::plural('link', count($refLinks)) }},
              and the page each one opened
            </span>
            <a class="drill__close" href="{{ $tabUrl(null) }}">Close</a>
          </div>
          <div class="drill__scroll">
            <table>
              <thead>
                <tr>
                  <th>Referring link</th>
                  <th>Page they landed on</th>
                  <th class="num">Visitors</th>
                  <th class="num">Hits</th>
                  <th>Last seen</th>
                </tr>
              </thead>
              <tbody>
                @foreach($refLinks as $link)
                  <tr>
                    <td>
                      <a class="drill__link" href="{{ $link['url'] }}" title="{{ $link['url'] }}"
                         target="_blank" rel="noopener noreferrer nofollow">{{ $short($link['url']) }}</a>
                    </td>
                    <td>
                      @if($link['page'] !== '')
                        <a class="drill__link drill__link--in" href="{{ $pageUrl($link['host'], $link['page']) }}"
                           title="{{ $link['page'] }}" target="_blank" rel="noopener">{{ $link['page'] }}</a>
                        @if($showSite && $link['host'] !== '')
                          <span class="drill__on">on {{ $link['host'] }}</span>
                        @endif
                      @else
                        <span class="drill__none">—</span>
                      @endif
                    </td>
                    <td class="num">{{ number_format($link['visitors']) }}</td>
                    <td class="num">{{ number_format($link['hits']) }}</td>
                    <td class="drill__when">{{ $link['last_seen']?->diffForHumans() ?? '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      @endif
    @empty
      <div class="empty">
        <div class="empty__title">No external referrers</div>
        <div class="empty__hint">{{ $emptyHint ?? 'Every visitor so far arrived directly or from within the site.' }}</div>
      </div>
    @endforelse

    <div class="note">
      Each visitor contributes only their <em>most recent</em> arrival — <code>visitors</code>
      overwrites the referrer and the page together on every view — so this counts how visitors
      last arrived, not every arrival they ever made. Because both columns are written by the
      same request, the link and the page beside it are a true pair. <code>Hits</code> is the
      visitor's all-time view count, not views from that link.
    </div>
  </div>
</div>
