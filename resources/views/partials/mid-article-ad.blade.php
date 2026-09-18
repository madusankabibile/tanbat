{{-- ─────────────────────────────────────────────────────────────────────────
     Mobile-only mid-article advertisement.
     Managed via Admin › Ad Spaces (slot: mid_article).
──────────────────────────────────────────────────────────────────────────── --}}
@if(\App\Support\AdSpace::isEnabled('mid_article'))
@push('scripts')
<style>
  .mid-article-ad { margin: 28px 0; min-height: 50px; text-align: center; }
  @media (min-width: 768px) { .mid-article-ad { display: none; } }
</style>
<script>
(function () {
  // Mobile devices only.
  if (!window.matchMedia || !window.matchMedia('(max-width: 767px)').matches) return;

  var customHtml = {!! json_encode(\App\Support\AdSpace::midArticleCustomHtml()) !!};
  var scriptUrl = {!! json_encode(\App\Support\AdSpace::midArticleScriptUrl()) !!};
  var containerId = {!! json_encode(\App\Support\AdSpace::midArticleContainerId()) !!};

  if (containerId && document.getElementById(containerId)) return; // guard against double-injection

  var body = document.querySelector('.prose-tanbat');
  if (!body) return;

  // Split on the article's block-level children.
  var host = body;
  while (host.children.length === 1 && host.firstElementChild.children.length > 1) {
    host = host.firstElementChild;
  }
  var blocks = Array.prototype.slice.call(host.children);
  if (blocks.length < 4) return; // too short to be worth a mid-roll

  var anchor = blocks[Math.floor(blocks.length / 2)];

  var wrap = document.createElement('div');
  wrap.className = 'mid-article-ad';

  if (customHtml && customHtml.trim() !== '') {
    wrap.innerHTML = customHtml;
    // Execute any script elements inside customHtml
    var scripts = wrap.getElementsByTagName('script');
    for (var i = 0; i < scripts.length; i++) {
      var s = document.createElement('script');
      if (scripts[i].src) {
        s.src = scripts[i].src;
      } else {
        s.textContent = scripts[i].textContent;
      }
      document.body.appendChild(s);
    }
  } else {
    var container = document.createElement('div');
    container.id = containerId;
    wrap.appendChild(container);

    if (scriptUrl) {
      var s = document.createElement('script');
      s.async = true;
      s.setAttribute('data-cfasync', 'false');
      s.src = scriptUrl;
      wrap.appendChild(s);
    }
  }

  anchor.parentNode.insertBefore(wrap, anchor.nextSibling);
})();
</script>
@endpush
@endif
