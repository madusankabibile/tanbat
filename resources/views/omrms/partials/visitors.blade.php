{{-- Recent-visitors card for omrms.com (mirror of tanbat's stat-counter widget,
     restyled for the omrms theme). Server-rendered from Omrms::recentVisitors()
     and refreshed live from /api/omrms/visitors. --}}
@php use App\Support\Omrms; $visitors = Omrms::recentVisitors(8); @endphp
<section class="omr-vw" data-vw data-api="{{ Omrms::url('/api/omrms/visitors') }}">
  <div class="omr-vw-head">
    <span class="omr-rail-h" style="margin:0;border:0;padding:0">Recent visitors</span>
    <span class="omr-vw-live"><i></i>live</span>
  </div>
  <ul class="omr-vw-list" data-vw-list>
    @forelse($visitors as $v)
      <li class="omr-vw-row">
        @if($v['country_code'])
          <span class="omr-vw-flag"><img src="https://flagcdn.com/w40/{{ $v['country_code'] }}.png" alt="{{ strtoupper($v['country_code']) }}" loading="lazy"
            onerror="this.parentNode.className='omr-vw-flag omr-vw-flag--code';this.parentNode.textContent='{{ strtoupper($v['country_code']) }}';"></span>
        @else
          <span class="omr-vw-flag omr-vw-flag--code">🌐</span>
        @endif
        <span class="omr-vw-who">
          <span class="omr-vw-country">{{ $v['country_name'] }}</span>
          <span class="omr-vw-meta"><span class="omr-vw-page">{{ $v['page'] }}</span>
            @if($v['referrer']) · via {{ $v['referrer'] }}@else · direct @endif
          </span>
        </span>
        <span class="omr-vw-when">{{ $v['when'] }}</span>
      </li>
    @empty
      <li class="omr-vw-empty">No visitors recorded yet.</li>
    @endforelse
  </ul>
</section>

<script>
  (function () {
    var el = document.querySelector('[data-vw]');
    if (!el) return;
    var list = el.querySelector('[data-vw-list]');
    var api = el.getAttribute('data-api');
    function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
    function flag(v){
      if(!v.country_code) return '<span class="omr-vw-flag omr-vw-flag--code">🌐</span>';
      var cc=esc(v.country_code), up=esc(v.country_code.toUpperCase());
      return '<span class="omr-vw-flag"><img src="https://flagcdn.com/w40/'+cc+'.png" alt="'+up+'" loading="lazy" onerror="this.parentNode.className=\'omr-vw-flag omr-vw-flag--code\';this.parentNode.textContent=\''+up+'\';"></span>';
    }
    function row(v){
      var meta='<span class="omr-vw-page">'+esc(v.page||'/')+'</span>'+(v.referrer?' · via '+esc(v.referrer):' · direct');
      return '<li class="omr-vw-row">'+flag(v)+'<span class="omr-vw-who"><span class="omr-vw-country">'+esc(v.country_name||'Unknown')+'</span><span class="omr-vw-meta">'+meta+'</span></span><span class="omr-vw-when">'+esc(v.when||'')+'</span></li>';
    }
    function load(){
      fetch(api,{headers:{'X-Requested-With':'XMLHttpRequest'},credentials:'same-origin'})
        .then(function(r){return r.ok?r.json():Promise.reject();})
        .then(function(d){var v=d.visitors||[];list.innerHTML=v.length?v.map(row).join(''):'<li class="omr-vw-empty">No visitors recorded yet.</li>';})
        .catch(function(){});
    }
    setInterval(load, 60000);
  })();
</script>
