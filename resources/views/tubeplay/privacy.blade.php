{{--
    Privacy policy for the TubePlay app (WebView video player).

    Standalone on purpose: this view does NOT extend layouts.app, so the page
    ships no ads, no analytics, no cookies and no JavaScript at all. App-store
    reviewers open it directly, and a policy that says "we collect nothing"
    should not itself load trackers. Change the app name, contact address or
    effective date in the PHP block right below.

    Careful: never write Blade directive tokens inside this comment. Blade
    extracts raw PHP blocks BEFORE it strips comments, so a bare directive name
    here starts a match that swallows the rest of the head.
--}}
<!DOCTYPE html>
<html lang="en">
@php
  // NB: not $app — Blade already shares the container as $app in every view.
  $appName   = 'TubePlay';
  $contact   = 'madusankabibile@gmail.com';
  $effective = 'July 30, 2026';
  $canonical = 'https://tanbat.com/tubeplay/privacy';
@endphp
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Policy — {{ $appName }}</title>
<meta name="description" content="{{ $appName }} is a WebView video player that collects no personal data. No accounts, no tracking, no location access, no data sharing, no adult content.">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="index, follow">
<meta property="og:type" content="website">
<meta property="og:title" content="Privacy Policy — {{ $appName }}">
<meta property="og:description" content="{{ $appName }} collects no personal data. No accounts, no tracking, no location, no data sharing.">
<meta property="og:url" content="{{ $canonical }}">
<style>
:root{
  --p:#6C63FF;--pd:#5A52D5;--pl:#EEF0FF;
  --ok:#0F766E;--okl:#ECFDF5;--okb:#A7F3D0;
  --td:#1E1B4B;--tm:#4B5563;--tl:#9CA3AF;
  --white:#fff;--bg:#F7F8FF;--bd:#E5E7EB;
}
*{box-sizing:border-box}
html,body{margin:0;padding:0}
body{
  font-family:'Inter',ui-sans-serif,system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;
  -webkit-font-smoothing:antialiased;color:var(--td);line-height:1.6;
  background:
    radial-gradient(60% 50% at 12% 3%,rgba(108,99,255,.13) 0%,transparent 55%),
    radial-gradient(55% 45% at 92% 97%,rgba(255,101,132,.10) 0%,transparent 55%),
    linear-gradient(180deg,#FAFBFF 0%,#F1F2FE 100%);
  background-attachment:fixed;min-height:100vh;
}
.wrap{max-width:820px;margin:0 auto;padding:40px 20px 72px}
.card{background:var(--white);border:1px solid var(--bd);border-radius:20px;
  box-shadow:0 20px 60px rgba(108,99,255,.10),0 4px 14px rgba(30,27,75,.04);
  padding:clamp(24px,5vw,52px)}
/* Header */
.brand{display:flex;align-items:center;gap:12px;margin-bottom:26px}
.brand-ico{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;flex:0 0 auto;
  background:linear-gradient(135deg,var(--p),#FF6584);box-shadow:0 8px 20px rgba(108,99,255,.32)}
.brand-ico svg{display:block}
.brand-name{font-weight:800;font-size:20px;letter-spacing:-.02em}
.brand-sub{font-size:13px;color:var(--tl);margin-top:1px}
.eyebrow{display:inline-block;font-size:11.5px;font-weight:700;letter-spacing:.09em;text-transform:uppercase;
  color:var(--p);background:var(--pl);padding:6px 12px;border-radius:9999px;margin-bottom:16px}
h1{font-size:clamp(27px,5vw,38px);font-weight:800;letter-spacing:-.03em;margin:0 0 8px;line-height:1.15}
.updated{color:var(--tl);font-size:13.5px;margin:0}
.intro{color:var(--tm);font-size:16px;margin:18px 0 0}
/* "At a glance" summary */
.glance{background:var(--okl);border:1px solid var(--okb);border-radius:16px;padding:20px 22px;margin:28px 0 0}
.glance h2{font-size:12.5px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--ok);margin:0 0 12px}
.glance ul{list-style:none;margin:0;padding:0;display:grid;gap:9px}
.glance li{display:flex;gap:10px;align-items:flex-start;font-size:15px;color:#134E4A}
.glance svg{flex:0 0 auto;margin-top:3px;color:var(--ok)}
/* TOC */
.toc{background:var(--bg);border:1px solid var(--bd);border-radius:14px;padding:18px 22px;margin:26px 0 0}
.toc h2{font-size:12.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--tm);margin:0 0 10px}
.toc ol{margin:0;padding-left:20px;columns:2;column-gap:30px}
@media(max-width:620px){.toc ol{columns:1}}
.toc li{margin:5px 0;break-inside:avoid}
.toc a{color:var(--pd);text-decoration:none;font-size:14px;font-weight:500}
.toc a:hover{text-decoration:underline}
/* Sections */
section{scroll-margin-top:20px;margin-top:36px}
h2{font-size:20px;font-weight:800;letter-spacing:-.02em;margin:0 0 12px;display:flex;align-items:baseline;gap:10px}
h2 .num{color:var(--p);font-size:14.5px;font-weight:700;min-width:20px}
h3{font-size:15.5px;font-weight:700;margin:20px 0 8px}
p{color:var(--tm);font-size:15.5px;margin:0 0 13px}
ul,ol.body{margin:0 0 15px;padding-left:22px}
li{color:var(--tm);font-size:15.5px;margin:6px 0}
strong{color:var(--td)}
a{color:var(--pd);font-weight:600}
table{width:100%;border-collapse:collapse;margin:6px 0 16px;font-size:15px}
th,td{text-align:left;padding:11px 12px;border-bottom:1px solid var(--bd);color:var(--tm);vertical-align:top}
th{font-size:12px;letter-spacing:.05em;text-transform:uppercase;color:var(--tl);font-weight:700}
td strong{color:var(--td)}
.tablewrap{overflow-x:auto}
.note{background:var(--pl);border:1px solid #D9DCFF;border-radius:12px;padding:15px 18px;margin:16px 0}
.note p{margin:0;color:var(--pd);font-size:14.5px}
.contact{background:var(--pl);border:1px solid #D9DCFF;border-radius:14px;padding:22px 24px;margin-top:34px}
.contact h2{margin-top:0}
hr{border:0;border-top:1px solid var(--bd);margin:32px 0 0}
footer{text-align:center;color:var(--tl);font-size:13px;margin-top:22px}
footer a{color:var(--tl);font-weight:500}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">

    <div class="brand">
      <span class="brand-ico">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.53.85l10-6.5a1 1 0 0 0 0-1.7l-10-6.5A1 1 0 0 0 8 5.5Z"/></svg>
      </span>
      <div>
        <div class="brand-name">{{ $appName }}</div>
        <div class="brand-sub">Video player app</div>
      </div>
    </div>

    <span class="eyebrow">Legal</span>
    <h1>Privacy Policy</h1>
    <p class="updated">Effective {{ $effective }} · Last updated {{ $effective }}</p>

    <p class="intro">
      This Privacy Policy describes how the <strong>{{ $appName }}</strong> mobile application
      (“{{ $appName }}”, “the app”, “we”, “us”) handles information. {{ $appName }} is a simple
      <strong>WebView-based video player</strong>: it opens and plays publicly available video
      content inside the app. <strong>It does not collect, store, sell, or share any personal
      information.</strong> There is no account, no sign-in, and no user profile.
    </p>

    {{-- Plain-language summary, matching what is declared in the store listing. --}}
    <div class="glance">
      <h2>At a glance</h2>
      <ul>
        @foreach ([
          'No personal data is collected — none at all.',
          'No account, no login, no registration, no password.',
          'No data is shared with or sold to anyone.',
          'No location access and no GPS tracking.',
          'No contacts, photos, microphone, camera, call logs or SMS access.',
          'No advertising or analytics SDKs are built into the app.',
          'No adult, explicit, or age-restricted content.',
        ] as $point)
        <li>
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
          <span>{{ $point }}</span>
        </li>
        @endforeach
      </ul>
    </div>

    <div class="toc">
      <h2>On this page</h2>
      <ol>
        <li><a href="#what">What the app does</a></li>
        <li><a href="#nodata">Information we do not collect</a></li>
        <li><a href="#device">Data stored on your device</a></li>
        <li><a href="#permissions">Permissions</a></li>
        <li><a href="#noaccount">No account or login</a></li>
        <li><a href="#nosharing">No data sharing or selling</a></li>
        <li><a href="#nolocation">No location tracking</a></li>
        <li><a href="#thirdparty">Third-party content</a></li>
        <li><a href="#content">Content standards</a></li>
        <li><a href="#children">Children’s privacy</a></li>
        <li><a href="#security">Security &amp; retention</a></li>
        <li><a href="#rights">Your choices</a></li>
        <li><a href="#changes">Changes to this policy</a></li>
        <li><a href="#contact">Contact us</a></li>
      </ol>
    </div>

    <section id="what">
      <h2><span class="num">1.</span> What the app does</h2>
      <p>
        {{ $appName }} is a lightweight player built around Android’s system WebView component. When you
        open a video, the app loads that page or media stream from the internet and displays it inside
        the app window. That is the entire function of the app.
      </p>
      <p>
        {{ $appName }} has <strong>no server of its own</strong> that receives information about you. We
        operate no user database, no login service, and no tracking backend. Requests for video
        content go directly from your device to the third-party website or platform that hosts it.
      </p>
    </section>

    <section id="nodata">
      <h2><span class="num">2.</span> Information we do not collect</h2>
      <p>We want to be explicit, because “we collect nothing” is easy to say and rarely detailed. {{ $appName }} does <strong>not</strong> collect, request, transmit, or retain:</p>
      <ul>
        <li>Names, email addresses, phone numbers, or any contact details.</li>
        <li>Usernames, passwords, or any account credentials.</li>
        <li>Location data — GPS, network-based location, or any approximate location.</li>
        <li>Your contacts, call logs, SMS or messages.</li>
        <li>Photos, videos, files, or other media stored on your device.</li>
        <li>Microphone or camera input.</li>
        <li>Device identifiers used for tracking, such as an advertising ID or IMEI.</li>
        <li>Analytics events, crash-reporting payloads, usage profiles, or watch history sent to us.</li>
        <li>Payment or financial information — the app takes no payments.</li>
      </ul>
      <div class="note">
        <p>We do not build advertising profiles, and we do not use your activity for personalization, because we never receive that activity in the first place.</p>
      </div>
    </section>

    <section id="device">
      <h2><span class="num">3.</span> Data stored on your device</h2>
      <p>
        Because playback happens in a WebView, Android’s WebView may keep its normal technical data
        on your device — for example a page cache or cookies set by the website you are viewing. This
        data:
      </p>
      <ul>
        <li>stays <strong>local to your phone</strong> and is never sent to us;</li>
        <li>exists only to make playback work and load faster;</li>
        <li>can be removed at any time by clearing the app’s cache and data in Android Settings, or by uninstalling the app.</li>
      </ul>
      <p>
        The app may also save simple local preferences (for example your last-used setting) in its own
        storage on the device. These never leave your phone.
      </p>
    </section>

    <section id="permissions">
      <h2><span class="num">4.</span> Permissions the app uses</h2>
      <div class="tablewrap">
        <table>
          <thead>
            <tr><th>Permission</th><th>Why it is needed</th></tr>
          </thead>
          <tbody>
            <tr>
              <td><strong>Internet / network access</strong></td>
              <td>Required to load and stream the video content you choose to watch. Without it the app cannot display anything.</td>
            </tr>
            <tr>
              <td><strong>Network state</strong> (if requested)</td>
              <td>Only to detect whether you are online, so the app can show a “no connection” message instead of a blank screen.</td>
            </tr>
          </tbody>
        </table>
      </div>
      <p>
        {{ $appName }} requests <strong>no sensitive or runtime permissions</strong> — no location, no
        storage access, no camera, no microphone, no contacts, no phone state. You can review the full
        permission list on the app’s store listing or under <em>Settings → Apps → {{ $appName }} →
        Permissions</em>.
      </p>
    </section>

    <section id="noaccount">
      <h2><span class="num">5.</span> No account or login</h2>
      <p>
        {{ $appName }} works immediately after installation. There is no registration, no sign-in, no
        social login, no email verification, and no profile to create or delete. Nothing you do in the
        app is tied to an identity, because no identity exists.
      </p>
    </section>

    <section id="nosharing">
      <h2><span class="num">6.</span> No data sharing or selling</h2>
      <p>
        We do not share, disclose, rent, trade, or sell personal information to advertisers, data
        brokers, analytics companies, or any other third party — we hold none to share. There are no
        third-party advertising or analytics SDKs embedded in the app.
      </p>
      <p>
        Because we hold no personal information, we have nothing to hand over in response to a legal
        request, and no data can be exposed by a breach of our systems.
      </p>
    </section>

    <section id="nolocation">
      <h2><span class="num">7.</span> No location tracking</h2>
      <p>
        The app never asks for location permission and never accesses GPS, Wi-Fi-based location,
        cell-tower location, or any approximate location signal. We do not track, log, or infer where
        you are.
      </p>
    </section>

    <section id="thirdparty">
      <h2><span class="num">8.</span> Third-party content and websites</h2>
      <p>
        The videos and pages you play are hosted by <strong>independent third-party platforms</strong>
        that we do not own or control. When content loads, your device connects directly to that
        provider, and — as with any web browser — the provider can see technical information such as
        your IP address, device type, and browser user agent, and may set its own cookies or show its
        own advertising.
      </p>
      <p>
        That processing is governed by <strong>the third party’s own privacy policy and terms</strong>,
        not by this one. We recommend reviewing the policy of any platform whose content you view. All
        trademarks and content shown in the app belong to their respective owners; {{ $appName }} is an
        independent player and is not affiliated with, endorsed by, or sponsored by any of the
        platforms whose publicly available content it can display.
      </p>
    </section>

    <section id="content">
      <h2><span class="num">9.</span> Content standards</h2>
      <p>
        {{ $appName }} does not host, produce, promote, or provide <strong>adult, pornographic, sexually
        explicit, violent, or otherwise age-restricted content</strong>. The app is intended for
        general-audience video playback only. We also do not knowingly facilitate access to illegal
        content; if you believe content reachable through the app is unlawful or infringing, contact us
        at the address below and we will review it.
      </p>
    </section>

    <section id="children">
      <h2><span class="num">10.</span> Children’s privacy</h2>
      <p>
        {{ $appName }} does not knowingly collect personal information from anyone, including children
        under 13 (or the minimum age in your jurisdiction). Since no data is collected and no account
        exists, there is no personal information about a child for us to store or delete. Parents who
        have questions can reach us at <a href="mailto:{{ $contact }}">{{ $contact }}</a>.
      </p>
    </section>

    <section id="security">
      <h2><span class="num">11.</span> Security and data retention</h2>
      <p>
        The strongest privacy protection is not collecting data, and that is the approach {{ $appName }}
        takes. We keep <strong>no user data on any server</strong>, so there is nothing for us to
        retain, and no retention period to apply. Content is loaded over the secure connections
        (HTTPS) offered by the source platform. Any locally cached data is under your control and is
        erased when you clear the app’s data or uninstall it.
      </p>
    </section>

    <section id="rights">
      <h2><span class="num">12.</span> Your choices and rights</h2>
      <p>
        Privacy laws such as the GDPR and CCPA give you rights to access, correct, delete, or export
        your personal data, and to opt out of its sale. We honour those rights fully — in practice
        they resolve to the same answer: <strong>we hold no personal data about you</strong>, we sell
        none, and there is nothing to export or erase. You can remove all locally stored app data at
        any time by clearing the app’s storage or uninstalling {{ $appName }}. If you would like written
        confirmation of this for your own records, email us and we will provide it.
      </p>
    </section>

    <section id="changes">
      <h2><span class="num">13.</span> Changes to this policy</h2>
      <p>
        If the app’s functionality ever changes in a way that affects privacy, we will update this
        page and revise the “Last updated” date above before that change takes effect. We encourage
        you to review this page occasionally. Continued use of the app after an update means you
        accept the revised policy.
      </p>
    </section>

    <section id="contact" class="contact">
      <h2><span class="num">14.</span> Contact us</h2>
      <p>Questions about this policy, or about privacy in {{ $appName }}? We’re happy to answer.</p>
      <ul>
        <li><strong>Email</strong> — <a href="mailto:{{ $contact }}">{{ $contact }}</a></li>
        <li><strong>Policy URL</strong> — <a href="{{ $canonical }}">{{ $canonical }}</a></li>
      </ul>
    </section>

    <hr>
    <footer>© {{ date('Y') }} {{ $appName }}. All rights reserved.</footer>
  </div>
</div>
</body>
</html>
