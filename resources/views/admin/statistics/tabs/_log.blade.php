{{-- Visitor log tab: the raw `visitors` rows, filterable and paginated.
     Every link out of here (filter, reset, pager) carries `tab=log` so paging
     never drops the reader back onto the default tab. --}}

<div class="panel">
  <div class="panel__head">
    <div>
      <div class="panel__title">Visitor log</div>
      <div class="panel__sub">
        One row per visitor (IP + user agent), last {{ $visitorTableDays }} days ·
        {{ number_format($visitorLog->total()) }} matching
      </div>
    </div>
  </div>

  <div class="panel__body">
    <form method="GET" action="{{ route('admin.statistics.index') }}" class="flex flex-wrap items-end gap-3">
      <input type="hidden" name="tab" value="log">
      <input type="hidden" name="days" value="{{ $days }}">
      <div class="flex-1 min-w-[220px]">
        <label class="label">Search</label>
        <input type="text" name="q" value="{{ $logFilters['q'] }}" placeholder="IP, page, country or referrer" class="input">
      </div>
      <div>
        <label class="label">Country</label>
        <select name="country" class="input">
          <option value="">All</option>
          @foreach($countryOptions as $c)
            <option value="{{ $c['code'] }}" @selected($logFilters['country'] === $c['code'])>{{ $c['name'] }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="label">Traffic</label>
        <select name="traffic" class="input">
          <option value="all"   @selected($logFilters['traffic'] === 'all')>All</option>
          <option value="human" @selected($logFilters['traffic'] === 'human')>Humans</option>
          <option value="bot"   @selected($logFilters['traffic'] === 'bot')>Bots</option>
        </select>
      </div>
      <div>
        <label class="label">Sort</label>
        <select name="sort" class="input">
          <option value="recent" @selected($logFilters['sort'] === 'recent')>Last seen</option>
          <option value="first"  @selected($logFilters['sort'] === 'first')>First seen</option>
          <option value="hits"   @selected($logFilters['sort'] === 'hits')>Most hits</option>
        </select>
      </div>
      <div class="flex gap-2">
        <button class="btn-primary">Filter</button>
        <a href="{{ route('admin.statistics.index', ['tab' => 'log', 'days' => $days]) }}" class="btn-outline">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-card" style="border: 0; border-top: 1px solid var(--rule); border-radius: 0;">
    <table>
      <thead>
        <tr>
          <th>IP address</th>
          <th>Country</th>
          <th>Last page</th>
          <th>Referrer</th>
          <th>Client</th>
          <th class="text-right">Hits</th>
          <th>First seen</th>
          <th>Last seen</th>
        </tr>
      </thead>
      <tbody>
        @forelse($visitorLog as $v)
          @php $ua = \App\Services\UserAgentParser::parse($v->user_agent); @endphp
          <tr>
            <td class="cell-mono">{{ $v->ip_address ?? '—' }}</td>
            <td>
              <span class="country-cell">
                @include('admin.partials._flag', ['code' => $v->country_code, 'label' => $v->country_name ?: 'Unknown'])
                <span class="text-slate-600">{{ $v->country_name ?: 'Unknown' }}</span>
                @if($v->country_code)<span class="flag">{{ $v->country_code }}</span>@endif
              </span>
            </td>
            <td><span class="cell-path" title="{{ $v->page }}">{{ $v->page ?: '—' }}</span></td>
            <td>
              @if($v->referrer)
                <span class="cell-path" title="{{ $v->referrer }}">{{ parse_url($v->referrer, PHP_URL_HOST) ?: $v->referrer }}</span>
              @else
                <span class="text-slate-400 text-xs">Direct</span>
              @endif
            </td>
            <td>
              @if($ua['is_bot'])
                <span class="badge badge-user">bot</span>
              @else
                <span class="badge badge-type">{{ $ua['device'] }}</span>
              @endif
              <span class="cell-ua" title="{{ $v->user_agent }}">
                {{ $ua['is_bot'] ? Str::limit($v->user_agent, 40) : $ua['browser'] . ' · ' . $ua['platform'] }}
              </span>
            </td>
            <td class="text-right font-semibold">{{ number_format($v->hits) }}</td>
            <td class="text-slate-500 text-xs">{{ $v->created_at?->format('M j, H:i') }}</td>
            <td class="text-slate-500 text-xs">{{ $v->updated_at?->diffForHumans() }}</td>
          </tr>
        @empty
          <tr><td colspan="8" class="py-8 text-center text-sm text-slate-500">No visitors match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($visitorLog->hasPages())
    <div class="panel__body">
      <div class="pager flex items-center gap-1.5">
        {{ $visitorLog->links() }}
      </div>
    </div>
  @endif
</div>
