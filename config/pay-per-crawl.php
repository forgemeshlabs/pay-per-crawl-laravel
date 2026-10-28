<?php

return [

    /*
    | monitor: classify and log, never charge (safe default; shows which bots you would have charged).
    | gate:    answer HTTP 402 to bots until they pay. Needs a valid pay_to wallet, otherwise
    |          the package silently stays in monitor mode.
    */
    'mode' => env('PAY_PER_CRAWL_MODE', 'monitor'),

    // Your Base (USDC) wallet address. Payments settle straight to it. No custody, no account.
    'pay_to' => env('PAY_PER_CRAWL_PAY_TO', ''),

    // Price per page in USD (minimum 0.005).
    'price_usd' => (float) env('PAY_PER_CRAWL_PRICE_USD', 0.01),

    // Empty = PayAI (https://facilitator.payai.network): no key, no seller fee, Base mainnet.
    'facilitator_url' => env('PAY_PER_CRAWL_FACILITATOR_URL', ''),
    // Optional Authorization header value for a facilitator that needs one, e.g. "Bearer ...".
    'facilitator_auth' => env('PAY_PER_CRAWL_FACILITATOR_AUTH', ''),

    // free | paid | block per crawler class.
    'class_policy' => [
        'search' => 'free',      // Googlebot, Bingbot ... (reverse-DNS verified; impostors are charged)
        'social' => 'free',      // link-preview fetchers
        'monitor' => 'free',     // uptime monitors, feed readers, archive.org
        'seo' => 'paid',
        'ai' => 'paid',
        'automation' => 'paid',  // curl, python-requests, headless browsers, empty User-Agent
        'bot' => 'paid',         // unknown self-declared bots
    ],

    // Per-crawler override by catalog name, e.g. ['GPTBot' => 'paid', 'Bytespider' => 'block'].
    'bot_overrides' => [],

    // Path prefixes that are never gated, e.g. ['/api/health', '/feed'].
    'allow_paths' => [],

    // Header carrying the real client IP behind a proxy, e.g. CF-Connecting-IP or X-Forwarded-For.
    // It MUST be one your proxy overwrites or appends to: for a multi-value header the RIGHT-MOST
    // entry is used (the one your proxy added); a client-supplied left-most value is ignored.
    // Used for search-engine reverse-DNS verification. Never set it if the origin is reachable directly.
    'trusted_ip_header' => env('PAY_PER_CRAWL_TRUSTED_IP_HEADER', ''),

    // Middleware groups the gate is added to automatically. [] = add the `pay-per-crawl`
    // middleware yourself, per route or group.
    'middleware_groups' => ['web'],

    // Signed-in users (session auth) are never gated.
    'skip_authenticated' => true,

    // Serve /.well-known/x402.json, /llms.txt and /agents.txt.
    'discovery' => true,
    'site_name' => env('PAY_PER_CRAWL_SITE_NAME', env('APP_NAME', '')),
    'site_description' => env('PAY_PER_CRAWL_SITE_DESCRIPTION', ''),
    'publish_contact' => false,
    'site_email' => env('PAY_PER_CRAWL_SITE_EMAIL', ''),
    // Shown in /llms.txt: [['Title', 'https://example.com/page'], ...]
    'llms_pages' => [],
    // Canonical origin when the app sits behind a proxy that rewrites the host. Empty = request origin.
    'site_url' => env('PAY_PER_CRAWL_SITE_URL', ''),

    // Record human page views too (counts only, no IPs: sources are daily-salted hashes).
    'log_humans' => true,
    'retention_days' => 90,

    // Cache store for replay locks and DNS lookups. null = default store. Use a shared store
    // (redis, database, file) in production; `array` does not survive between requests.
    'cache_store' => env('PAY_PER_CRAWL_CACHE_STORE'),

    // Secret for MPP challenge signing. Empty = derived from APP_KEY.
    'mpp_secret' => env('PAY_PER_CRAWL_MPP_SECRET', ''),

    // Optional ForgeMesh Pro license (hosted dashboard, alerts, crawler-intel feed).
    'pro_key' => env('PAY_PER_CRAWL_PRO_KEY', ''),
    'pro_listed' => true,
];
