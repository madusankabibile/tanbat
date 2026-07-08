{{-- Recent-visitors widget. Replaces the old SuperCounters script.
     Self-fetching from GET /api/visitors — works on any page that includes it
     (article-show, assistant, book-show, books) and inside the right rail
     (partials/side-rails). Data is logged site-wide by RecordVisitor middleware. --}}
<section class="visitor-widget" data-visitor-widget>
  <div class="vw-head">
    <span class="vw-title">Recent visitors</span>
    <span class="vw-live"><i></i>live</span>
  </div>
  <ul class="vw-list" data-vw-list>
    <li class="vw-skeleton"></li>
    <li class="vw-skeleton"></li>
    <li class="vw-skeleton"></li>
  </ul>
</section>

@once
@push('head')
<style>
.visitor-widget {
  background:#fff; border:1px solid #E5E7EB; border-radius:14px; overflow:hidden;
  box-shadow:0 1px 2px rgba(20,20,50,.04);
}
.visitor-widget .vw-head {
  display:flex; align-items:center; justify-content:space-between;
  padding:12px 16px 10px; border-bottom:1px solid #F1F5F9;
}
.visitor-widget .vw-title { font-size:13px; font-weight:800; color:#1E1B4B; }
.visitor-widget .vw-live {
  display:inline-flex; align-items:center; gap:5px;
  font-size:10px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; color:#10B981;
}
.visitor-widget .vw-live i {
  height:7px; width:7px; border-radius:9999px; background:#10B981;
  box-shadow:0 0 0 0 rgba(16,185,129,.5); animation:vw-pulse 1.8s infinite;
}
@keyframes vw-pulse {
  0% { box-shadow:0 0 0 0 rgba(16,185,129,.5); }
  70% { box-shadow:0 0 0 6px rgba(16,185,129,0); }
  100% { box-shadow:0 0 0 0 rgba(16,185,129,0); }
}
.visitor-widget .vw-list { padding:6px; margin:0; list-style:none; }
.visitor-widget .vw-row {
  display:flex; align-items:center; gap:10px;
  padding:8px 10px; border-radius:10px; transition:background .15s ease;
  text-decoration:none; color:inherit; cursor:pointer;
}
.visitor-widget .vw-row:hover { background:#F8FAFC; }
.visitor-widget .vw-flag {
  width:22px; height:16px; border-radius:3px; flex-shrink:0; overflow:hidden;
  box-shadow:0 0 0 1px rgba(15,23,42,.08); background:#EEF2FF;
  display:grid; place-items:center; font-size:11px; line-height:1;
}
.visitor-widget .vw-flag img { width:100%; height:100%; object-fit:cover; display:block; }
.visitor-widget .vw-flag--code { font-size:8.5px; font-weight:800; color:#475569; letter-spacing:.4px; }
.visitor-widget .vw-who { display:flex; flex-direction:column; min-width:0; line-height:1.25; flex:1; }
.visitor-widget .vw-country { font-size:12.5px; font-weight:700; color:#1E1B4B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.visitor-widget .vw-meta { font-size:11px; color:#64748B; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.visitor-widget .vw-meta .vw-page { color:#6C63FF; font-weight:600; }
.visitor-widget .vw-meta .vw-ref { color:#94A3B8; }
.visitor-widget .vw-when { font-size:10px; color:#94A3B8; flex-shrink:0; white-space:nowrap; }
.visitor-widget .vw-empty { padding:18px 12px; text-align:center; font-size:12px; color:#94A3B8; }
.visitor-widget .vw-skeleton {
  height:38px; margin:4px 4px; border-radius:10px;
  background:linear-gradient(90deg,#F1F5F9 25%,#F8FAFC 50%,#F1F5F9 75%);
  background-size:200% 100%; animation:vw-shimmer 1.4s infinite linear; list-style:none;
}
@keyframes vw-shimmer { from { background-position:200% 0; } to { background-position:-200% 0; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
  var APP = window.__APP__ || {};
  var url = APP.urls && APP.urls.api && APP.urls.api.visitors;
  if (!url) return;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
      .replace(/"/g,'&quot;').replace(/'/g,'&#039;');
  }

  var BASE = (APP.urls && APP.urls.base) || '';

  function flag(v) {
    if (!v.country_code) return '<span class="vw-flag vw-flag--code">🌐</span>';
    var cc = esc(String(v.country_code).toLowerCase());
    var up = esc(String(v.country_code).toUpperCase());
    // Flag image; if flagcdn is unreachable the onerror swaps in the 2-letter code
    // so the visitor still has a country marker.
    return '<span class="vw-flag"><img alt="' + up + '" loading="lazy"' +
      ' src="https://flagcdn.com/w40/' + cc + '.png"' +
      ' onerror="this.parentNode.className=\'vw-flag vw-flag--code\';this.parentNode.textContent=\'' + up + '\';"></span>';
  }

  function row(v) {
    var page = v.page || '/';
    var href = BASE + page;
    var meta = '<span class="vw-page">' + esc(page) + '</span>';
    if (v.referrer) meta += ' <span class="vw-ref">&middot; via ' + esc(v.referrer) + '</span>';
    else meta += ' <span class="vw-ref">&middot; direct</span>';
    return '<li><a class="vw-row" href="' + esc(href) + '" title="Open ' + esc(page) + '">' + flag(v) +
      '<span class="vw-who">' +
        '<span class="vw-country">' + esc(v.country_name || 'Unknown') + '</span>' +
        '<span class="vw-meta">' + meta + '</span>' +
      '</span>' +
      '<span class="vw-when">' + esc(v.when || '') + '</span>' +
    '</a></li>';
  }

  function render(list, visitors) {
    if (!visitors || !visitors.length) {
      list.innerHTML = '<li class="vw-empty">No visitors recorded yet.</li>';
      return;
    }
    list.innerHTML = visitors.map(row).join('');
  }

  function load() {
    var lists = document.querySelectorAll('[data-vw-list]');
    if (!lists.length) return;
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
      .then(function (data) {
        lists.forEach(function (l) { render(l, data.visitors); });
      })
      .catch(function () {
        lists.forEach(function (l) { l.innerHTML = '<li class="vw-empty">Couldn\'t load visitors.</li>'; });
      });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
  // Light auto-refresh so the panel stays current while the tab is open.
  setInterval(load, 60000);
})();
</script>
@endpush
@endonce
