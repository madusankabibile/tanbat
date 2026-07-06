{{-- SuperCounters visitor-stats widget, wrapped in a panel card.
     Same counter used in the logged-in right rail (partials/side-rails). --}}
<section class="statcard">
  <div class="statcard__head">Visitor stats</div>
  <div class="statcard__body">
    <center><script type="text/javascript" src="//widget.supercounters.com/ssl/vt.js"></script><script type="text/javascript">var sc_visitor_var = sc_visitor_var || [];sc_vt(1678595,"FFFFFF","ffffff","000000",5)</script></center>
  </div>
</section>

@once
@push('head')
<style>
.statcard { background:#fff; border:1px solid #E5E7EB; border-radius:14px; overflow:hidden;
  box-shadow:0 1px 2px rgba(20,20,50,.04); }
.statcard__head { padding:12px 16px 10px; font-size:13px; font-weight:800; color:#1E1B4B;
  border-bottom:1px solid #F1F5F9; }
.statcard__body { padding:12px; display:flex; justify-content:center; }
.statcard__body img { max-width:100%; height:auto; }
</style>
@endpush
@endonce
