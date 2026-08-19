<?php

/*
|--------------------------------------------------------------------------
| TV channels / HLS stream proxy
|--------------------------------------------------------------------------
|
| The public player never receives a channel's real .m3u8 URL. It asks for a
| playback session, gets back a signed, expiring, session-bound proxy URL, and
| every manifest we serve has its segment/key URIs rewritten to point back at
| the proxy too. These knobs tune that pipeline.
|
| Worth being blunt about the limits: this hides the ORIGIN manifest and makes
| a copied URL useless within minutes. It is not DRM. A determined viewer can
| still capture the decrypted proxy stream — only real DRM (Widevine/FairPlay)
| prevents that.
|
*/

return [
    // Lifetime of a signed SEGMENT/KEY token, in seconds. These are re-minted
    // on every manifest reload, so this can be brutally short: long enough to
    // fetch the segment, short enough that a scraped URL is dead on arrival.
    'token_ttl' => (int) env('TV_TOKEN_TTL', 90),

    // Lifetime of a MANIFEST token. Longer, because the player re-reads the
    // live playlist off one URL for as long as someone is watching. Session
    // binding is what keeps a leaked manifest URL worthless, not this number.
    'manifest_ttl' => (int) env('TV_MANIFEST_TTL', 14400),

    // Bind tokens to the requesting session + IP + user-agent, so a URL lifted
    // out of devtools is worthless in another browser. Turn off only if a CDN
    // or mobile network rotates client IPs mid-stream.
    'bind_session' => (bool) env('TV_BIND_SESSION', true),

    // Verify the upstream's TLS certificate. True everywhere except a local box
    // with no CA bundle (XAMPP). Never switch this off in production.
    'verify_ssl' => (bool) env('TV_VERIFY_SSL', true),

    // Upstream request timeouts (seconds).
    'manifest_timeout' => (int) env('TV_MANIFEST_TIMEOUT', 12),
    'segment_timeout'  => (int) env('TV_SEGMENT_TIMEOUT', 25),

    // Sent upstream when a channel doesn't override it. Some CDNs 403 an
    // unfamiliar agent.
    'user_agent' => env('TV_USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36'),

    // Client-side tamper deterrents on the player page.
    'protect' => [
        // Swallow F12 / Ctrl+Shift+I,J,C / Ctrl+U and the context menu.
        'block_shortcuts' => (bool) env('TV_BLOCK_SHORTCUTS', true),
        // Pause playback and curtain the player when devtools looks open.
        'detect_devtools' => (bool) env('TV_DETECT_DEVTOOLS', true),
        // The `debugger`-statement trap. Effective but hostile to anyone with
        // devtools open for unrelated reasons, so it's opt-in.
        'debugger_trap'   => (bool) env('TV_DEBUGGER_TRAP', false),
    ],
];
