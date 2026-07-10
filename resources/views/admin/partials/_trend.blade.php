{{-- Period-over-period chip for a KPI. $t = ['current','prior','pct'].
     A null pct means the prior window was empty — no honest percentage exists
     against a zero baseline, so we state the raw count instead. --}}
@php $pct = $t['pct']; @endphp

@if($pct === null)
  <span class="trend trend--flat">+{{ number_format($t['current']) }}</span>
  <span>in last 30 days</span>
@else
  @php $dir = $pct > 0 ? 'up' : ($pct < 0 ? 'down' : 'flat'); @endphp
  <span class="trend trend--{{ $dir }}">
    @if($dir === 'up')
      <svg viewBox="0 0 8 8" fill="currentColor" aria-hidden="true"><path d="M4 0 8 7H0z"/></svg>
    @elseif($dir === 'down')
      <svg viewBox="0 0 8 8" fill="currentColor" aria-hidden="true"><path d="M4 8 0 1h8z"/></svg>
    @else
      <svg viewBox="0 0 8 8" fill="currentColor" aria-hidden="true"><rect y="3" width="8" height="2"/></svg>
    @endif
    {{ $pct > 0 ? '+' : '' }}{{ number_format($pct, 1) }}%
  </span>
  <span>vs prior 30 days</span>
@endif
