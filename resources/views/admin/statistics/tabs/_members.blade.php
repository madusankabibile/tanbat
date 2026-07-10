{{-- Members tab: registered accounts, independent of the visitor tables. --}}

<div class="strip">
  <div class="kpi">
    <div class="kpi-label">Members</div>
    <div class="kpi-value">{{ number_format($members['total']) }}</div>
    <div class="kpi-foot">@include('admin.partials._trend', ['t' => $members['new_trend'], 'window' => $days])</div>
  </div>
  <div class="kpi">
    <div class="kpi-label">New signups</div>
    <div class="kpi-value">{{ number_format($members['new']) }}</div>
    <div class="kpi-foot"><span>in the last {{ $days }} days</span></div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Active</div>
    <div class="kpi-value">{{ number_format($members['active']) }}</div>
    <div class="kpi-foot">
      <span class="kpi-figure">{{ number_format($members['active_share'], 1) }}%</span>
      <span>posted or commented</span>
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Verified email</div>
    <div class="kpi-value">{{ number_format($members['verified_share'], 1) }}%</div>
    <div class="kpi-foot">
      <span class="kpi-figure">{{ number_format($members['verified']) }}</span>
      <span>confirmed addresses</span>
    </div>
  </div>
  <div class="kpi">
    <div class="kpi-label">Never posted</div>
    <div class="kpi-value">{{ number_format($members['never_posted_share'], 1) }}%</div>
    <div class="kpi-foot">
      <span class="kpi-figure">{{ number_format($members['never_posted']) }}</span>
      <span>lurkers</span>
    </div>
  </div>
</div>

{{-- ───── Signups + gender ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">

  <div class="panel lg:col-span-2">
    <div class="panel__head">
      <div>
        <div class="panel__title">Signups</div>
        <div class="panel__sub">New members per day, last {{ $days }} days</div>
      </div>
      <a href="{{ route('admin.users.index') }}" class="panel__link">All users</a>
    </div>
    <div class="panel__body">
      <div class="chart-wrap chart-wrap--short"><canvas id="chartSignups"></canvas></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Gender</div>
        <div class="panel__sub">Self-reported at signup</div>
      </div>
    </div>
    <div class="panel__body">
      @php $genderTotal = array_sum($demographics['gender']); @endphp
      @if($genderTotal > 0)
        <div class="mix-bar">
          @foreach(array_keys($demographics['gender']) as $i => $g)
            <div class="mix-bar__seg" style="flex: {{ $demographics['gender'][$g] }}; background: {{ $hues[$i] ?? $hues[5] }};"
                 title="{{ Str::headline($g) }}"></div>
          @endforeach
        </div>
        <div class="mt-4">
          @foreach(array_keys($demographics['gender']) as $i => $g)
            <div class="mix-row">
              <span class="mix-row__dot" style="background: {{ $hues[$i] ?? $hues[5] }};"></span>
              <span class="mix-row__name">{{ Str::headline($g) }}</span>
              <span class="mix-row__count">{{ number_format($demographics['gender'][$g]) }}</span>
              <span class="mix-row__share">{{ number_format($share($demographics['gender'][$g], $genderTotal), 1) }}%</span>
            </div>
          @endforeach
        </div>
      @else
        <div class="empty"><div class="empty__title">No members yet</div></div>
      @endif
    </div>
  </div>
</div>

{{-- ───── Age + country + health ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Age</div>
        <div class="panel__sub">{{ number_format($demographics['age_total']) }} members with an age on file</div>
      </div>
    </div>
    <div class="panel__body">
      @php $maxAge = max(1, ...array_values($demographics['ages'])); @endphp
      @foreach($demographics['ages'] as $band => $n)
        <div class="barline">
          <div class="barline__head">
            <span class="barline__name">{{ $band }}</span>
            <span class="barline__val">
              {{ number_format($n) }}
              <span class="barline__sub">{{ number_format($share($n, max(1, $demographics['age_total'])), 1) }}%</span>
            </span>
          </div>
          <div class="barline__track">
            <div class="barline__fill" style="width: {{ $n > 0 ? max(round($n / $maxAge * 100), 1) : 0 }}%"></div>
          </div>
        </div>
      @endforeach
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Member countries</div>
        <div class="panel__sub">{{ number_format($demographics['country_known']) }} of {{ number_format($members['total']) }} have a country set</div>
      </div>
    </div>
    <div class="panel__body">
      @php $maxUserCountry = collect($demographics['countries'])->max('count') ?: 1; @endphp
      @forelse($demographics['countries'] as $c)
        <div class="barline">
          <div class="barline__head">
            <span class="barline__name">
              @include('admin.partials._flag', ['code' => $c['code'], 'label' => $c['name']])
              {{ $c['name'] }} <span class="flag">{{ $c['code'] }}</span>
            </span>
            <span class="barline__val">{{ number_format($c['count']) }}</span>
          </div>
          <div class="barline__track">
            <div class="barline__fill barline__fill--brass" style="width: {{ max(round($c['count'] / $maxUserCountry * 100), 1) }}%"></div>
          </div>
        </div>
      @empty
        <div class="empty"><div class="empty__title">No countries recorded</div></div>
      @endforelse

      @if($demographics['country_unknown'] > 0)
        <div class="note">
          {{ number_format($demographics['country_unknown']) }} members
          ({{ number_format($share($demographics['country_unknown'], max(1, $members['total'])), 1) }}%)
          have no country on file and are excluded from this ranking.
        </div>
      @endif
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Account health</div>
        <div class="panel__sub">Share of all {{ number_format($members['total']) }} members</div>
      </div>
    </div>
    <div class="panel__body">
      @foreach($health as $h)
        <div class="meter">
          <span class="meter__name">{{ $h['label'] }}</span>
          <span class="meter__val">{{ number_format($h['count']) }} <span>· {{ number_format($h['share'], 1) }}%</span></span>
          <div class="meter__track">
            <div class="meter__fill" style="width: {{ $h['count'] > 0 ? max(round($h['share']), 1) : 0 }}%"></div>
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>

{{-- ───── Leaderboards ───── --}}
<div class="mt-4 grid gap-4 lg:grid-cols-3">

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Top authors</div>
        <div class="panel__sub">Members by posts published</div>
      </div>
    </div>
    <div class="panel__body--flush">
      @forelse($leaders['byPosts'] as $u)
        @include('admin.partials._leader-row', ['u' => $u, 'value' => $u->posts_count, 'unit' => 'Posts'])
      @empty
        <div class="empty"><div class="empty__title">Nobody has posted yet</div></div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Top commenters</div>
        <div class="panel__sub">Members by comments written</div>
      </div>
    </div>
    <div class="panel__body--flush">
      @forelse($leaders['byComments'] as $u)
        @include('admin.partials._leader-row', ['u' => $u, 'value' => $u->comments_count, 'unit' => 'Replies'])
      @empty
        <div class="empty"><div class="empty__title">No comments yet</div></div>
      @endforelse
    </div>
  </div>

  <div class="panel">
    <div class="panel__head">
      <div>
        <div class="panel__title">Most liked</div>
        <div class="panel__sub">Likes received across all their posts</div>
      </div>
    </div>
    <div class="panel__body--flush">
      @forelse($leaders['byLikes'] as $row)
        @include('admin.partials._leader-row', ['u' => $row['user'], 'value' => $row['total'], 'unit' => 'Likes'])
      @empty
        <div class="empty"><div class="empty__title">No likes yet</div></div>
      @endforelse
    </div>
  </div>
</div>

@push('scripts')
<script>
(function () {
  var canvas = document.getElementById('chartSignups');
  if (!canvas || typeof Chart === 'undefined' || !window.Ledger) return;

  var series = @json($signupSeries);

  new Chart(canvas, {
    type: 'line',
    data: {
      labels: series.map(function (p) { return Ledger.label(p.date); }),
      datasets: [Ledger.line({
        label: 'Signups',
        data: series.map(function (p) { return p.count; }),
        borderColor: Ledger.PINE,
        backgroundColor: Ledger.PINE,
      })],
    },
    options: Ledger.options(),
  });
})();
</script>
@endpush
