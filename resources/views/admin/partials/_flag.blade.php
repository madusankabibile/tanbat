{{-- A country flag for an ISO-3166 alpha-2 code.

     $code  the country code; null, '' , 'XX' and 'T1' all render the
            "unknown" placeholder rather than a broken image.
     $label optional accessible name (the country's English name). The flag is
            decorative whenever the name is already printed next to it, which
            is every current caller — so alt="" and the title carries the name.

     Styles (.flag-img / .flag-unknown) live in admin/layout.blade.php. --}}
@php
  $flagUrl = \App\Services\CountryFlag::url($code ?? null);
  $flagLbl = trim((string) ($label ?? '')) ?: 'Unknown country';
@endphp

@if($flagUrl)
  <img class="flag-img" src="{{ $flagUrl }}" alt="" title="{{ $flagLbl }}" width="20" height="15" loading="lazy" decoding="async">
@else
  <span class="flag-unknown" title="{{ $flagLbl }}" aria-hidden="true"></span>
@endif
