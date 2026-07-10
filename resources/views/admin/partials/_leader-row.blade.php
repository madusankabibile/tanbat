{{-- One member in a leaderboard. $u = User, $value = the ranked number,
     $unit = its micro-label. Used three times on the statistics page. --}}
<div class="row-item">
  @if($u->profile_picture)
    <img class="avatar-circle" src="{{ asset('storage/'.$u->profile_picture) }}" alt="">
  @else
    <span class="avatar-circle">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
  @endif
  <div class="row-item__body">
    <a href="{{ route('admin.users.edit', $u) }}" class="row-item__name">{{ $u->name }}</a>
    <div class="row-item__meta"><span class="mono">{{ '@' . $u->username }}</span></div>
  </div>
  <div class="row-item__num">
    <b>{{ number_format($value) }}</b>
    <span>{{ $unit }}</span>
  </div>
</div>
