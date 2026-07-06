@extends('layouts.app')
@section('title', 'Privacy Policy — Tanbat')

@push('head')
<meta name="robots" content="index,follow">
<style>
:root{
  --p:#6C63FF;--pd:#5A52D5;--pl:#EEF0FF;
  --a:#FF6584;--al:#FFF0F3;
  --td:#1E1B4B;--tm:#4B5563;--tl:#9CA3AF;
  --bg:#F7F8FF;--white:#FFFFFF;--bd:#E5E7EB;
}
.lg{min-height:100vh;color:var(--td);position:relative;overflow-x:hidden;
  background:
    radial-gradient(60% 50% at 12% 4%,rgba(108,99,255,.14) 0%,transparent 55%),
    radial-gradient(55% 50% at 92% 96%,rgba(255,101,132,.12) 0%,transparent 55%),
    linear-gradient(180deg,#FAFBFF 0%,#F1F2FE 100%);}
/* Top bar */
.lg-nav{position:sticky;top:0;z-index:50;display:flex;align-items:center;justify-content:space-between;
  padding:14px 24px;background:rgba(255,255,255,.7);
  backdrop-filter:saturate(140%) blur(14px);-webkit-backdrop-filter:saturate(140%) blur(14px);
  border-bottom:1px solid rgba(255,255,255,.6);}
.lg-brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--td)}
.lg-brand-ico{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;
  background:linear-gradient(135deg,var(--p),var(--a));color:#fff;font-weight:800;font-size:18px;
  box-shadow:0 6px 16px rgba(108,99,255,.35)}
.lg-brand-txt{font-weight:800;font-size:19px;letter-spacing:-.02em}
.lg-back{display:inline-flex;align-items:center;gap:7px;text-decoration:none;color:var(--p);
  font-weight:600;font-size:14px;padding:9px 16px;border-radius:10px;background:var(--pl);
  transition:background .15s}
.lg-back:hover{background:#E3E6FF}
/* Page shell */
.lg-wrap{position:relative;z-index:1;max-width:840px;margin:0 auto;padding:40px 24px 80px}
.lg-card{background:var(--white);border:1px solid var(--bd);border-radius:20px;
  box-shadow:0 20px 60px rgba(108,99,255,.10),0 4px 14px rgba(30,27,75,.04);
  padding:clamp(28px,5vw,56px)}
.lg-eyebrow{display:inline-block;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  color:var(--p);background:var(--pl);padding:6px 12px;border-radius:9999px;margin-bottom:18px}
.lg-title{font-size:clamp(28px,5vw,40px);font-weight:800;letter-spacing:-.03em;margin:0 0 10px}
.lg-updated{color:var(--tl);font-size:14px;margin:0 0 8px}
.lg-intro{color:var(--tm);font-size:16px;line-height:1.7;margin:18px 0 0}
.lg-hr{border:0;border-top:1px solid var(--bd);margin:32px 0}
/* TOC */
.lg-toc{background:var(--bg);border:1px solid var(--bd);border-radius:14px;padding:20px 22px;margin:28px 0}
.lg-toc h2{font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--tm);margin:0 0 12px}
.lg-toc ol{margin:0;padding-left:20px;columns:2;column-gap:32px}
@media(max-width:600px){.lg-toc ol{columns:1}}
.lg-toc li{margin:6px 0;break-inside:avoid}
.lg-toc a{color:var(--pd);text-decoration:none;font-size:14px;font-weight:500}
.lg-toc a:hover{text-decoration:underline}
/* Sections */
.lg-sec{scroll-margin-top:84px;margin-top:38px}
.lg-sec h2{font-size:21px;font-weight:800;letter-spacing:-.02em;margin:0 0 14px;display:flex;align-items:baseline;gap:10px}
.lg-sec h2 .num{color:var(--p);font-size:15px;font-weight:700;min-width:22px}
.lg-sec h3{font-size:16px;font-weight:700;margin:22px 0 8px;color:var(--td)}
.lg-sec p{color:var(--tm);font-size:15.5px;line-height:1.75;margin:0 0 14px}
.lg-sec ul{margin:0 0 16px;padding-left:22px}
.lg-sec li{color:var(--tm);font-size:15.5px;line-height:1.7;margin:7px 0}
.lg-sec a{color:var(--pd);font-weight:600}
.lg-sec strong{color:var(--td)}
.lg-callout{background:var(--al);border:1px solid #FFD7E0;border-radius:12px;padding:16px 18px;margin:18px 0}
.lg-callout p{margin:0;color:#9F1239;font-size:14.5px}
.lg-contact{background:var(--pl);border:1px solid #D9DCFF;border-radius:14px;padding:22px 24px;margin-top:36px}
.lg-contact h2{margin-top:0}
.lg-foot{text-align:center;color:var(--tl);font-size:13px;margin-top:36px}
.lg-foot a{color:var(--tl)}
</style>
@endpush

@section('content')
@php
  $appName   = 'Tanbat';
  $appUrl    = rtrim(config('app.url'), '/');
  $contact   = config('mail.from.address', 'privacy@example.com');
  $effective = 'June 2, 2026';
@endphp

<div class="lg">
  {{-- Top bar --}}
  <nav class="lg-nav">
    <a href="{{ url('/') }}" class="lg-brand">
      <span class="lg-brand-ico">T</span>
      <span class="lg-brand-txt">{{ $appName }}</span>
    </a>
    <a href="{{ auth()->check() ? url('/home') : url('/') }}" class="lg-back">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Back {{ auth()->check() ? 'home' : 'to site' }}
    </a>
  </nav>

  <div class="lg-wrap">
    <div class="lg-card">
      <span class="lg-eyebrow">Legal</span>
      <h1 class="lg-title">Privacy Policy</h1>
      <p class="lg-updated">Last updated · {{ $effective }}</p>
      <p class="lg-intro">
        This Privacy Policy explains how {{ $appName }} (“{{ $appName }}”, “we”, “us”, or “our”)
        collects, uses, shares, and protects your information when you use our website, applications,
        and related services (collectively, the “Service”). By creating an account or using the
        Service you agree to the practices described here.
      </p>

      {{-- Table of contents --}}
      <div class="lg-toc">
        <h2>On this page</h2>
        <ol>
          <li><a href="#info">Information we collect</a></li>
          <li><a href="#use">How we use information</a></li>
          <li><a href="#cookies">Cookies &amp; tracking</a></li>
          <li><a href="#ads">Advertising &amp; analytics</a></li>
          <li><a href="#sharing">How we share information</a></li>
          <li><a href="#thirdparty">Third-party content</a></li>
          <li><a href="#retention">Data retention</a></li>
          <li><a href="#rights">Your rights &amp; choices</a></li>
          <li><a href="#security">Security</a></li>
          <li><a href="#children">Children’s privacy</a></li>
          <li><a href="#international">International transfers</a></li>
          <li><a href="#changes">Changes to this policy</a></li>
          <li><a href="#contact">Contact us</a></li>
        </ol>
      </div>

      {{-- 1 --}}
      <section id="info" class="lg-sec">
        <h2><span class="num">1.</span> Information we collect</h2>
        <p>We collect information in three ways: information you provide, information collected automatically, and information from third parties.</p>

        <h3>Information you provide</h3>
        <ul>
          <li><strong>Account details</strong> — your name, username, email address, password (stored only as a salted hash), and country.</li>
          <li><strong>Profile content</strong> — profile picture, banner image, bio, and any details you add to your profile.</li>
          <li><strong>Content you create</strong> — posts, articles, images, videos, comments, reactions, saved items, and direct messages you send to other members.</li>
          <li><strong>Communications</strong> — messages you send to us for support or feedback.</li>
        </ul>

        <h3>Information collected automatically</h3>
        <ul>
          <li><strong>Usage &amp; interaction data</strong> — the posts you view, click, like, share, save, or hide. We use this to build your personalized feed and to count post impressions and views.</li>
          <li><strong>Device &amp; log data</strong> — IP address, browser type, device information, referring pages, and timestamps.</li>
          <li><strong>Activity status</strong> — we record your last-active time so we can show whether members are online.</li>
          <li><strong>Cookies &amp; session data</strong> — see <a href="#cookies">Cookies &amp; tracking</a> below.</li>
        </ul>

        <h3>Information from third parties</h3>
        <ul>
          <li>When you use the {{ $appName }} Assistant or book search, results may be retrieved from external sources you query.</li>
          <li>Advertising and analytics partners may share aggregate measurement data with us (see <a href="#ads">Advertising &amp; analytics</a>).</li>
        </ul>
      </section>

      {{-- 2 --}}
      <section id="use" class="lg-sec">
        <h2><span class="num">2.</span> How we use information</h2>
        <p>We use the information we collect to:</p>
        <ul>
          <li>Create and manage your account and authenticate you securely.</li>
          <li>Operate core features — feeds, posts, comments, messaging, notifications, follows, and search.</li>
          <li><strong>Personalize your feed</strong> by ranking content based on your interactions and interests.</li>
          <li>Measure reach and engagement (views, likes, comments, shares) on your content.</li>
          <li>Serve and measure advertising shown on the Service.</li>
          <li>Maintain safety — detect, prevent, and respond to fraud, abuse, spam, and policy violations.</li>
          <li>Communicate with you about service updates, security notices, and support requests.</li>
          <li>Comply with legal obligations and enforce our terms.</li>
        </ul>
      </section>

      {{-- 3 --}}
      <section id="cookies" class="lg-sec">
        <h2><span class="num">3.</span> Cookies &amp; tracking</h2>
        <p>
          We use cookies and similar technologies to keep you signed in, remember your preferences,
          protect against cross-site request forgery (CSRF), and understand how the Service is used.
        </p>
        <ul>
          <li><strong>Essential cookies</strong> — required for login sessions and security; the Service cannot function without them.</li>
          <li><strong>Preference cookies</strong> — remember settings such as your saved folders and display choices.</li>
          <li><strong>Analytics &amp; advertising cookies</strong> — set by us and our partners to measure usage and personalize ads.</li>
        </ul>
        <p>Most browsers let you block or delete cookies. If you disable essential cookies, parts of the Service may not work.</p>
      </section>

      {{-- 4 --}}
      <section id="ads" class="lg-sec">
        <h2><span class="num">4.</span> Advertising &amp; analytics</h2>
        <p>
          {{ $appName }} displays advertising, including ads served through <strong>Google AdSense</strong>.
          Third-party vendors, including Google, use cookies to serve ads based on your prior visits to
          this and other websites.
        </p>
        <ul>
          <li>Google’s use of advertising cookies enables it and its partners to serve ads to you based on your visits to our Service and/or other sites on the Internet.</li>
          <li>You can opt out of personalized advertising by visiting <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener">Google Ads Settings</a>.</li>
          <li>For more on how third parties use data for advertising, see <a href="https://www.aboutads.info" target="_blank" rel="noopener">aboutads.info</a> and <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener">Google’s partner policies</a>.</li>
        </ul>
        <div class="lg-callout">
          <p>We do not sell your personal information. Advertising partners receive only the data needed to serve and measure ads, governed by their own privacy policies.</p>
        </div>
      </section>

      {{-- 5 --}}
      <section id="sharing" class="lg-sec">
        <h2><span class="num">5.</span> How we share information</h2>
        <p>We share information only in these circumstances:</p>
        <ul>
          <li><strong>With other members</strong> — your profile, posts, comments, and public activity are visible to others as part of the social experience. Direct messages are visible only to you and your recipient.</li>
          <li><strong>With service providers</strong> — hosting, storage, email delivery, and analytics partners who process data on our behalf under confidentiality obligations.</li>
          <li><strong>With advertising partners</strong> — as described in <a href="#ads">Advertising &amp; analytics</a>.</li>
          <li><strong>For legal reasons</strong> — to comply with the law, respond to lawful requests, or protect the rights, safety, and property of {{ $appName }}, our members, or the public.</li>
          <li><strong>Business transfers</strong> — in connection with a merger, acquisition, or sale of assets, where information may be transferred subject to this policy.</li>
        </ul>
        <p>Some content you create may be cross-posted to external platforms (for example, Reddit) where an administrator has explicitly enabled and authorized that integration.</p>
      </section>

      {{-- 6 --}}
      <section id="thirdparty" class="lg-sec">
        <h2><span class="num">6.</span> Third-party content &amp; links</h2>
        <p>
          The Service may surface content, books, or links retrieved from external sources, and may
          embed media (such as video players) hosted by third parties. We do not control these third
          parties, and their privacy practices are governed by their own policies. We encourage you to
          review them before providing information.
        </p>
      </section>

      {{-- 7 --}}
      <section id="retention" class="lg-sec">
        <h2><span class="num">7.</span> Data retention</h2>
        <p>
          We keep your information for as long as your account is active or as needed to provide the
          Service. We retain and use information as necessary to comply with legal obligations, resolve
          disputes, and enforce our agreements. When you delete content or your account, we remove or
          anonymize the associated data within a reasonable period, except where retention is required
          by law or for legitimate business purposes.
        </p>
      </section>

      {{-- 8 --}}
      <section id="rights" class="lg-sec">
        <h2><span class="num">8.</span> Your rights &amp; choices</h2>
        <p>Depending on where you live, you may have the right to:</p>
        <ul>
          <li><strong>Access</strong> the personal information we hold about you.</li>
          <li><strong>Correct</strong> inaccurate or incomplete information — you can update most details directly in your profile settings.</li>
          <li><strong>Delete</strong> your content or your account.</li>
          <li><strong>Object to or restrict</strong> certain processing, including personalized advertising.</li>
          <li><strong>Export</strong> a copy of the information you have provided.</li>
        </ul>
        <p>To exercise these rights, manage them in your account settings or contact us at <a href="mailto:{{ $contact }}">{{ $contact }}</a>. We may need to verify your identity before acting on a request.</p>
      </section>

      {{-- 9 --}}
      <section id="security" class="lg-sec">
        <h2><span class="num">9.</span> Security</h2>
        <p>
          We use technical and organizational measures to protect your information, including encrypted
          transport (HTTPS), hashed passwords, and CSRF protection on authenticated requests. No method
          of transmission or storage is completely secure, however, so we cannot guarantee absolute
          security. Keep your password confidential and notify us of any unauthorized use of your account.
        </p>
      </section>

      {{-- 10 --}}
      <section id="children" class="lg-sec">
        <h2><span class="num">10.</span> Children’s privacy</h2>
        <p>
          The Service is not directed to children under 13 (or the minimum age required in your
          jurisdiction). We do not knowingly collect personal information from children. If you believe
          a child has provided us with personal information, please contact us and we will delete it.
        </p>
      </section>

      {{-- 11 --}}
      <section id="international" class="lg-sec">
        <h2><span class="num">11.</span> International data transfers</h2>
        <p>
          {{ $appName }} may process and store information in countries other than your own. Where we
          transfer information across borders, we take steps to ensure it receives an adequate level of
          protection consistent with this policy and applicable law.
        </p>
      </section>

      {{-- 12 --}}
      <section id="changes" class="lg-sec">
        <h2><span class="num">12.</span> Changes to this policy</h2>
        <p>
          We may update this Privacy Policy from time to time. When we make material changes, we will
          revise the “Last updated” date above and, where appropriate, provide additional notice. Your
          continued use of the Service after changes take effect constitutes acceptance of the updated policy.
        </p>
      </section>

      {{-- 13 --}}
      <section id="contact" class="lg-sec lg-contact">
        <h2><span class="num">13.</span> Contact us</h2>
        <p>If you have questions or requests regarding this Privacy Policy or your information, reach us at:</p>
        <ul>
          <li><strong>Email</strong> — <a href="mailto:{{ $contact }}">{{ $contact }}</a></li>
          <li><strong>Website</strong> — <a href="{{ $appUrl }}">{{ $appUrl }}</a></li>
        </ul>
      </section>

      <hr class="lg-hr">
      <p class="lg-foot">© {{ date('Y') }} {{ $appName }}. All rights reserved. · <a href="{{ url('/') }}">Home</a></p>
    </div>
  </div>
</div>
@endsection
