{{-- ─────────────────────────────────────────────────────────────────────────
     Mobile-only mid-article advertisement (effectivecpmnetwork native banner).

     Injected into the MIDDLE of the article body (.prose-tanbat) on phones only —
     never rendered on desktop/tablet. Included by the article + legacy blog post
     views (article-show, legacy-article-show, read-blog-show).

     The <script> is created with document.createElement (not innerHTML) because a
     script inserted via innerHTML never executes, so invoke.js would never fill
     the container.
──────────────────────────────────────────────────────────────────────────── --}}
@push('scripts')
<style>
  .mid-article-ad { margin: 28px 0; min-height: 50px; text-align: center; }
  /* Belt-and-suspenders: even though we only inject on phones, never show it wider. */
  @media (min-width: 768px) { .mid-article-ad { display: none; } }
</style>
<script>
(function () {
  // Mobile devices only.
  if (!window.matchMedia || !window.matchMedia('(max-width: 767px)').matches) return;

  var AD_ID = 'container-36ce0149ae6c36811ff6c54b088c483c';
  if (document.getElementById(AD_ID)) return; // guard against double-injection

  var body = document.querySelector('.prose-tanbat');
  if (!body) return;

  // Split on the article's block-level children. If the body is wrapped in a
  // single container, descend into it so we split on the real paragraphs.
  var host = body;
  while (host.children.length === 1 && host.firstElementChild.children.length > 1) {
    host = host.firstElementChild;
  }
  var blocks = Array.prototype.slice.call(host.children);
  if (blocks.length < 4) return; // too short to be worth a mid-roll

  var anchor = blocks[Math.floor(blocks.length / 2)];

  var wrap = document.createElement('div');
  wrap.className = 'mid-article-ad';

  var container = document.createElement('div');
  container.id = AD_ID;
  wrap.appendChild(container);

  var s = document.createElement('script');
  s.async = true;
  s.setAttribute('data-cfasync', 'false');
  s.src = 'https://pl23865704.effectivecpmnetwork.com/36ce0149ae6c36811ff6c54b088c483c/invoke.js';
  wrap.appendChild(s);

  anchor.parentNode.insertBefore(wrap, anchor.nextSibling);
})();
</script>
@endpush
