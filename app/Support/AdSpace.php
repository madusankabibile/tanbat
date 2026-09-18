<?php

namespace App\Support;

use App\Models\Advertisement;
use App\Models\Setting;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * Central registry and manager for all advertisement spaces across Tanbat and OMRMS.
 *
 * Each ad space has:
 *  - enabled status (bool)
 *  - display mode ('code' | 'campaign' | 'auto')
 *  - custom HTML/JS code or dedicated parameters
 *  - compiled-in default so the site works out-of-the-box without manual config
 *
 * Settings are stored via App\Models\Setting (persisted in DB + cached in memory/cache store).
 */
class AdSpace
{
    public const SPACES = [
        'sidebar' => [
            'key'         => 'sidebar',
            'name'        => 'Tanbat: Sidebar Display Ad (300x250)',
            'description' => 'Appears on the right rail across Tanbat (Home feed, Articles, Blog, Books, TV, Posts, User deleted).',
            'group'       => 'tanbat',
            'group_label' => 'Tanbat Placements',
            'locations'   => 'Feed right rail, Article sidebar (primary & secondary), TV sidebar, Blog sidebar, Books library, Book detail, Single post, User-deleted page',
            'type'        => 'display_banner',
            'default_enabled' => true,
            'default_mode'    => 'code',
            'default_code'    => '<div class="ad-banner" style="display:flex;justify-content:center;align-items:center;min-height:250px;">
  <script type="text/javascript">
    atOptions = {
      \'key\'    : \'5951768139638ce168687cbfb4814fce\',
      \'format\' : \'iframe\',
      \'height\' : 250,
      \'width\'  : 300,
      \'params\' : {}
    };
  </script>
  <script type="text/javascript" src="//www.highperformanceformat.com/5951768139638ce168687cbfb4814fce/invoke.js"></script>
</div>',
        ],

        'tv_native' => [
            'key'         => 'tv_native',
            'name'        => 'Tanbat: TV Player Native Banner',
            'description' => 'Banner displayed directly underneath the video player on TV channel pages (/tv/{slug}).',
            'group'       => 'tanbat',
            'group_label' => 'Tanbat Placements',
            'locations'   => 'Directly underneath the video player on TV channel pages (/tv/{slug})',
            'type'        => 'code',
            'default_enabled' => true,
            'default_mode'    => 'code',
            'default_code'    => '<div class="native-banner">
  <div id="container-36ce0149ae6c36811ff6c54b088c483c"></div>
  <script async data-cfasync="false" src="https://pl23865704.effectivecpmnetwork.com/36ce0149ae6c36811ff6c54b088c483c/invoke.js"></script>
</div>',
        ],

        'mid_article' => [
            'key'         => 'mid_article',
            'name'        => 'Tanbat: Mobile Mid-Article Ad',
            'description' => 'Mobile-only banner injected halfway through article content on /articles/{slug}, /blogs/{id}, /read-blog/{slug}.',
            'group'       => 'tanbat',
            'group_label' => 'Tanbat Placements',
            'locations'   => 'Phones only: dynamically injected midway through article text (/articles/{slug}, /blogs/{id}, /read-blog/{slug})',
            'type'        => 'mid_article',
            'default_enabled'      => true,
            'default_container_id' => 'container-36ce0149ae6c36811ff6c54b088c483c',
            'default_script_url'   => 'https://pl23865704.effectivecpmnetwork.com/36ce0149ae6c36811ff6c54b088c483c/invoke.js',
            'default_code'         => '',
        ],

        'feed' => [
            'key'         => 'feed',
            'name'        => 'Tanbat: Feed Sponsored Card & AdBot',
            'description' => 'Sponsored card pinned to the top of the Home feed and in ad-bot posts.',
            'group'       => 'tanbat',
            'group_label' => 'Tanbat Placements',
            'locations'   => 'Pinned card at top of Home feed & creative inside ad-bot posts',
            'type'        => 'feed_slot',
            'default_enabled'  => true,
            'default_slot_key' => '36ce0149ae6c36811ff6c54b088c483c',
            'default_slot_src' => 'https://pl23865704.effectivecpmnetwork.com/36ce0149ae6c36811ff6c54b088c483c/invoke.js',
        ],

        'sponsor_url' => [
            'key'         => 'sponsor_url',
            'name'        => 'Tanbat: Book & Newsbot Sponsor Link URL',
            'description' => 'Click target URL for book covers on /books/{slug} and "Continue reading…" button in newsbot cards.',
            'group'       => 'tanbat',
            'group_label' => 'Tanbat Placements',
            'locations'   => 'Click-through link behind book covers on /books/{slug} and the "Continue reading…" button on newsbot cards',
            'type'        => 'url',
            'default_enabled' => true,
            'default_url'     => 'https://www.effectivecpmnetwork.com/gc1v4hw8?key=b0e0c39593829879ba649d8cb2ef71ad',
        ],

        'assistant' => [
            'key'         => 'assistant',
            'name'        => 'Tanbat: Assistant Page Rails (Left & Right)',
            'description' => 'Left and right sponsored ad rails on the Tanbat Assistant wizard (/assistant).',
            'group'       => 'tanbat',
            'group_label' => 'Tanbat Placements',
            'locations'   => 'Tanbat Assistant wizard (/assistant) left and right sponsored rails',
            'type'        => 'assistant',
            'default_enabled'     => true,
            'default_use_sidebar' => true,
            'default_mode'        => 'code',
            'default_code'        => '',
        ],

        'omrms_native' => [
            'key'         => 'omrms_native',
            'name'        => 'OMRMS: Native Banner (Mid-Article & Home Card)',
            'description' => 'Native banner on omrms.com (mid-article and home sponsored grid card).',
            'group'       => 'omrms',
            'group_label' => 'OMRMS Placements',
            'locations'   => 'omrms.com article middle content and homepage sponsored article grid card',
            'type'        => 'code',
            'default_enabled' => true,
            'default_code'    => '<div class="omr-ad omr-ad-native">
  <script async="async" data-cfasync="false" src="//pl30310302.effectivecpmnetwork.com/1d080d933b8503323a23fe028650a3af/invoke.js"></script>
  <div id="container-1d080d933b8503323a23fe028650a3af"></div>
</div>',
        ],

        'omrms_sidebar' => [
            'key'         => 'omrms_sidebar',
            'name'        => 'OMRMS: Sidebar Display Ad (300x250)',
            'description' => 'Sidebar square display ad on omrms.com article pages.',
            'group'       => 'omrms',
            'group_label' => 'OMRMS Placements',
            'locations'   => 'omrms.com article right sidebar rail',
            'type'        => 'code',
            'default_enabled' => true,
            'default_code'    => '<div class="omr-ad omr-ad-square">
  <script type="text/javascript">
    atOptions = {
      \'key\'    : \'295f57d4e8e0947a5b3c7e7c065b4119\',
      \'format\' : \'iframe\',
      \'height\' : 250,
      \'width\'  : 300,
      \'params\' : {}
    };
  </script>
  <script type="text/javascript" src="//www.highperformanceformat.com/295f57d4e8e0947a5b3c7e7c065b4119/invoke.js"></script>
</div>',
        ],

        'global_head' => [
            'key'         => 'global_head',
            'name'        => 'Global: Header Ad / Auto-Ads Script (<head>)',
            'description' => 'Included inside the <head> tag of all pages (Google AdSense auto-ads, header scripts, popunders).',
            'group'       => 'global',
            'group_label' => 'Global Ad Injections',
            'locations'   => 'Injected directly inside <head> on all pages across Tanbat & OMRMS (Google AdSense auto-ads, header scripts, popunders)',
            'type'        => 'code',
            'default_enabled' => false,
            'default_code'    => '',
        ],

        'global_footer' => [
            'key'         => 'global_footer',
            'name'        => 'Global: Footer Ad / Script (before </body>)',
            'description' => 'Included at the very end of the <body> on all pages (popunders, slider widgets, analytics).',
            'group'       => 'global',
            'group_label' => 'Global Ad Injections',
            'locations'   => 'Injected directly before </body> on all pages across Tanbat & OMRMS (popunders, slider banners, analytics)',
            'type'        => 'code',
            'default_enabled' => false,
            'default_code'    => '',
        ],
    ];

    /**
     * Get all spaces with their current database settings merged with defaults.
     */
    public static function spaces(): array
    {
        $all = [];
        foreach (self::SPACES as $key => $meta) {
            $all[$key] = self::getSpaceConfig($key);
        }
        return $all;
    }

    /**
     * Get the full configuration for a single ad space.
     */
    public static function getSpaceConfig(string $key): ?array
    {
        if (!isset(self::SPACES[$key])) {
            return null;
        }

        $meta = self::SPACES[$key];
        $config = $meta;

        // Enabled
        $enabled = Setting::get("ad_space.{$key}.enabled");
        $config['enabled'] = $enabled === null ? $meta['default_enabled'] : ($enabled === '1');

        // Mode: 'code' | 'campaign' | 'auto'
        $mode = Setting::get("ad_space.{$key}.mode");
        $config['mode'] = $mode ?: ($meta['default_mode'] ?? 'code');

        // Code
        $code = Setting::get("ad_space.{$key}.code");
        $config['code'] = $code !== null ? $code : ($meta['default_code'] ?? '');

        // Space-specific fields
        if ($key === 'mid_article') {
            $cId = Setting::get("ad_space.mid_article.container_id");
            $sUrl = Setting::get("ad_space.mid_article.script_url");
            $config['container_id'] = $cId !== null && $cId !== '' ? $cId : $meta['default_container_id'];
            $config['script_url']   = $sUrl !== null && $sUrl !== '' ? $sUrl : $meta['default_script_url'];
        }

        if ($key === 'feed') {
            $sKey = Setting::get("ad_space.feed.slot_key");
            $sSrc = Setting::get("ad_space.feed.slot_src");
            $config['slot_key'] = $sKey !== null && $sKey !== '' ? $sKey : $meta['default_slot_key'];
            $config['slot_src'] = $sSrc !== null && $sSrc !== '' ? $sSrc : $meta['default_slot_src'];
        }

        if ($key === 'sponsor_url') {
            $url = Setting::get("ad_space.sponsor_url.url");
            $config['url'] = $url !== null ? $url : $meta['default_url'];
        }

        if ($key === 'assistant') {
            $useSb = Setting::get("ad_space.assistant.use_sidebar");
            $config['use_sidebar'] = $useSb === null ? ($meta['default_use_sidebar'] ?? true) : ($useSb === '1');
        }

        return $config;
    }

    /**
     * Check if an ad space is enabled.
     */
    public static function isEnabled(string $space): bool
    {
        if (!isset(self::SPACES[$space])) {
            return false;
        }

        $stored = Setting::get("ad_space.{$space}.enabled");
        if ($stored === null || $stored === '') {
            return (bool) self::SPACES[$space]['default_enabled'];
        }

        return $stored === '1';
    }

    /**
     * Get display mode for a space ('code', 'campaign', 'auto').
     */
    public static function mode(string $space): string
    {
        $stored = Setting::get("ad_space.{$space}.mode");
        if ($stored && in_array($stored, ['code', 'campaign', 'auto'], true)) {
            return $stored;
        }
        return self::SPACES[$space]['default_mode'] ?? 'code';
    }

    /**
     * Get custom code for a space, or fallback default.
     */
    public static function code(string $space): string
    {
        $stored = Setting::get("ad_space.{$space}.code");
        if ($stored !== null) {
            return $stored;
        }
        return self::SPACES[$space]['default_code'] ?? '';
    }

    /**
     * Render the ad space HTML.
     */
    public static function render(string $space): HtmlString
    {
        if (!self::isEnabled($space)) {
            return new HtmlString('');
        }

        // Assistant special-case: inherit sidebar ad if configured
        if ($space === 'assistant' && self::assistantUsesSidebar()) {
            return self::render('sidebar');
        }

        $mode = self::mode($space);

        // Direct campaign mode
        if ($mode === 'campaign') {
            $campHtml = self::renderCampaign($space === 'assistant' ? 'assistant' : 'sidebar');
            return $campHtml ?: new HtmlString('');
        }

        // Auto mode: try custom code; if empty, fallback to active campaign; if none, default code
        if ($mode === 'auto') {
            $code = trim(self::code($space));
            if ($code !== '') {
                return new HtmlString($code);
            }
            $campHtml = self::renderCampaign($space === 'assistant' ? 'assistant' : 'sidebar');
            if ($campHtml) {
                return $campHtml;
            }
            return new HtmlString(self::SPACES[$space]['default_code'] ?? '');
        }

        // Default 'code' mode
        return new HtmlString(self::code($space));
    }

    /**
     * Render a direct ad campaign banner from the database (records impression).
     */
    public static function renderCampaign(?string $placement = null): ?HtmlString
    {
        $ad = null;
        if ($placement) {
            $ad = Advertisement::pickFor($placement);
        }
        if (!$ad) {
            $ad = Advertisement::pickFor('sidebar')
                ?? Advertisement::pickFor('banner')
                ?? Advertisement::pickFor('assistant')
                ?? Advertisement::live()->first();
        }

        if (!$ad) {
            return null;
        }

        // Track impression
        $ad->recordImpression();

        $clickUrl = route('ad.click', $ad);
        $title    = e($ad->title);
        $body     = $ad->body ? e(Str::limit($ad->body, 120)) : null;
        $imgUrl   = $ad->image_url ? e($ad->image_url) : null;

        $html = '<div class="ad-campaign-card" style="width:100%;max-width:300px;margin:0 auto;border-radius:12px;overflow:hidden;background:#fff;border:1px solid #E2E8F0;box-shadow:0 1px 3px rgba(15,23,42,.06);text-align:center;font-family:Inter,ui-sans-serif,sans-serif;">';
        $html .= '<a href="' . $clickUrl . '" target="_blank" rel="noopener sponsored" style="display:block;text-decoration:none;color:inherit;">';

        if ($imgUrl) {
            $html .= '<img src="' . $imgUrl . '" alt="' . $title . '" style="width:100%;height:auto;max-height:220px;object-fit:cover;display:block;">';
        }

        $html .= '<div style="padding:14px 16px;">';
        $html .= '<span style="display:inline-block;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#6366F1;background:#EEF2FF;padding:2px 8px;border-radius:4px;margin-bottom:6px;">Sponsored</span>';
        $html .= '<div style="font-size:14px;font-weight:700;color:#0F172A;line-height:1.3;margin-top:2px;">' . $title . '</div>';
        if ($body) {
            $html .= '<p style="font-size:12px;color:#64748B;margin:6px 0 0;line-height:1.4;">' . $body . '</p>';
        }
        $html .= '<div style="margin-top:10px;display:inline-block;padding:6px 14px;background:#4F46E5;color:#fff;border-radius:8px;font-size:12px;font-weight:600;">Learn more &rarr;</div>';
        $html .= '</div></a></div>';

        return new HtmlString($html);
    }

    /**
     * Sponsor click-through URL.
     */
    public static function sponsorUrl(): ?string
    {
        if (!self::isEnabled('sponsor_url')) {
            return null;
        }
        $stored = Setting::get('ad_space.sponsor_url.url');
        if ($stored !== null) {
            return trim($stored) ?: null;
        }
        return self::SPACES['sponsor_url']['default_url'];
    }

    /**
     * Feed ad slot key.
     */
    public static function feedSlotKey(): string
    {
        $stored = Setting::get('ad_space.feed.slot_key');
        return $stored !== null && $stored !== '' ? $stored : self::SPACES['feed']['default_slot_key'];
    }

    /**
     * Feed ad slot script src.
     */
    public static function feedSlotSrc(): string
    {
        $stored = Setting::get('ad_space.feed.slot_src');
        return $stored !== null && $stored !== '' ? $stored : self::SPACES['feed']['default_slot_src'];
    }

    /**
     * Mid-article ad container id.
     */
    public static function midArticleContainerId(): string
    {
        $stored = Setting::get('ad_space.mid_article.container_id');
        return $stored !== null && $stored !== '' ? $stored : self::SPACES['mid_article']['default_container_id'];
    }

    /**
     * Mid-article ad script url.
     */
    public static function midArticleScriptUrl(): string
    {
        $stored = Setting::get('ad_space.mid_article.script_url');
        return $stored !== null && $stored !== '' ? $stored : self::SPACES['mid_article']['default_script_url'];
    }

    /**
     * Mid-article custom HTML override.
     */
    public static function midArticleCustomHtml(): string
    {
        $stored = Setting::get('ad_space.mid_article.code');
        return $stored !== null ? $stored : '';
    }

    /**
     * Does the assistant page inherit the sidebar ad?
     */
    public static function assistantUsesSidebar(): bool
    {
        $stored = Setting::get('ad_space.assistant.use_sidebar');
        if ($stored === null) {
            return (bool) (self::SPACES['assistant']['default_use_sidebar'] ?? true);
        }
        return $stored === '1';
    }

    /**
     * Save ad space configurations from request.
     */
    public static function save(array $data): void
    {
        $spaces = $data['spaces'] ?? [];

        foreach (self::SPACES as $key => $meta) {
            if (!isset($spaces[$key])) {
                continue;
            }
            $spaceData = $spaces[$key];

            // Enabled toggle
            $enabled = !empty($spaceData['enabled']) ? '1' : '0';
            Setting::put("ad_space.{$key}.enabled", $enabled);

            // Mode
            if (isset($spaceData['mode'])) {
                Setting::put("ad_space.{$key}.mode", $spaceData['mode']);
            }

            // Code
            if (isset($spaceData['code'])) {
                Setting::put("ad_space.{$key}.code", $spaceData['code']);
            }

            // Space specific fields
            if ($key === 'mid_article') {
                if (isset($spaceData['container_id'])) {
                    Setting::put("ad_space.mid_article.container_id", trim($spaceData['container_id']));
                }
                if (isset($spaceData['script_url'])) {
                    Setting::put("ad_space.mid_article.script_url", trim($spaceData['script_url']));
                }
            }

            if ($key === 'feed') {
                if (isset($spaceData['slot_key'])) {
                    Setting::put("ad_space.feed.slot_key", trim($spaceData['slot_key']));
                }
                if (isset($spaceData['slot_src'])) {
                    Setting::put("ad_space.feed.slot_src", trim($spaceData['slot_src']));
                }
            }

            if ($key === 'sponsor_url') {
                if (isset($spaceData['url'])) {
                    Setting::put("ad_space.sponsor_url.url", trim($spaceData['url']));
                }
            }

            if ($key === 'assistant') {
                $useSb = !empty($spaceData['use_sidebar']) ? '1' : '0';
                Setting::put("ad_space.assistant.use_sidebar", $useSb);
            }
        }
    }

    /**
     * Reset a single space or all spaces back to defaults.
     */
    public static function reset(?string $space = null): void
    {
        $targetSpaces = $space && isset(self::SPACES[$space]) ? [$space => self::SPACES[$space]] : self::SPACES;

        foreach ($targetSpaces as $key => $meta) {
            Setting::put("ad_space.{$key}.enabled", $meta['default_enabled'] ? '1' : '0');
            Setting::put("ad_space.{$key}.mode", $meta['default_mode'] ?? 'code');
            Setting::put("ad_space.{$key}.code", $meta['default_code'] ?? null);

            if ($key === 'mid_article') {
                Setting::put("ad_space.mid_article.container_id", $meta['default_container_id']);
                Setting::put("ad_space.mid_article.script_url", $meta['default_script_url']);
            }
            if ($key === 'feed') {
                Setting::put("ad_space.feed.slot_key", $meta['default_slot_key']);
                Setting::put("ad_space.feed.slot_src", $meta['default_slot_src']);
            }
            if ($key === 'sponsor_url') {
                Setting::put("ad_space.sponsor_url.url", $meta['default_url']);
            }
            if ($key === 'assistant') {
                Setting::put("ad_space.assistant.use_sidebar", $meta['default_use_sidebar'] ? '1' : '0');
            }
        }
    }
}
