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

  var iframe = document.createElement('iframe');
  iframe.sandbox = 'allow-scripts allow-same-origin allow-popups';
  iframe.style.border = 'none';
  iframe.style.width = '100%';
  iframe.style.minHeight = '70px';
  iframe.style.overflow = 'hidden';
  iframe.scrolling = 'no';
  iframe.loading = 'lazy';

  var innerContent = '';
  if (customHtml && customHtml.trim() !== '') {
    innerContent = customHtml;
  } else if (containerId && scriptUrl) {
    innerContent = '<div id="' + containerId + '"></div>' +
      '<script async data-cfasync="false" src="' + scriptUrl + '"><\/script>';
  }

  if (innerContent) {
    iframe.srcdoc = '<!DOCTYPE html><html><head><meta charset="utf-8">' +
      '<style>body{margin:0;padding:0;display:flex;justify-content:center;align-items:center;background:transparent;overflow:hidden;}</style>' +
      '</head><body>' + innerContent + '</body></html>';
    wrap.appendChild(iframe);
  }

  anchor.parentNode.insertBefore(wrap, anchor.nextSibling);
})();
</script>
@endpush
@endif
