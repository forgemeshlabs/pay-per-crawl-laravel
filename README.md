# ForgeMesh Pay-per-Crawl for Laravel

Laravel middleware that charges AI crawlers and scrapers per page with [x402](https://x402.org) (v1 and v2) and MPP USDC micropayments on Base. Humans and verified search engines read free; bots get an HTTP `402 Payment Required` until they pay, and the money goes straight to **your** wallet.

- Service provider, `pay-per-crawl` middleware (auto-added to the `web` group), `config/pay-per-crawl.php`
- Routes: `/.well-known/x402.json`, `/llms.txt`, `/agents.txt`
- Hit log (migration + `Hit` model) and `php artisan pay-per-crawl:stats`
- 85-agent classifier, search engines verified by reverse DNS (impostors are charged)
- Laravel 10 and 13 tested (11 and 12 are allowed by the version constraints but not separately tested); PHP 8.1+

## Money flow

- You keep **100%**. Payments are USDC transfers from the paying agent to the wallet in `PAY_PER_CRAWL_PAY_TO`.
- No custody, no account, no signup, and **no private key** on your server. Only a public address is configured.
- The facilitator (PayAI by default: no key, no seller fee, Base mainnet) verifies and submits the transfer. Your page is served only when it returns a transaction hash.
- ForgeMesh takes no cut. An optional Pro tier (hosted dashboard, alerts) exists; nothing leaves your app without a Pro license key.

> **Cloudflare:** AI Crawl Control (and any bot-fight or WAF rule that blocks AI crawlers) must be set to **Allow**. A crawler blocked at the edge never reaches Laravel and never sees the 402.

## Install

```bash
composer require forgemesh/pay-per-crawl-laravel
php artisan migrate
```

Until the packages are listed on Packagist, add the repositories first:

```bash
composer config repositories.ppc-core vcs https://github.com/forgemeshlabs/pay-per-crawl-php
composer config repositories.ppc-laravel vcs https://github.com/forgemeshlabs/pay-per-crawl-laravel
composer require forgemesh/pay-per-crawl-laravel:^0.1
php artisan migrate
```

The package starts in **monitor** mode: it classifies and logs bots and never charges, so you can see what you would earn. Then, in `.env`:

```dotenv
PAY_PER_CRAWL_MODE=gate
PAY_PER_CRAWL_PAY_TO=0xYourBaseWalletAddress
PAY_PER_CRAWL_PRICE_USD=0.01
```

Use a shared cache store (`redis`, `database`, `file`) for the replay lock: the `array` store does not survive between requests.

## Configure

`php artisan vendor:publish --tag=pay-per-crawl-config` (optional) copies `config/pay-per-crawl.php`:

| Key | Default | |
|---|---|---|
| `mode` | `monitor` | `monitor` or `gate`. Without a valid wallet, `gate` silently stays `monitor`. |
| `pay_to` | `''` | Your Base wallet. |
| `price_usd` | `0.01` | Minimum `0.005`. |
| `facilitator_url`, `facilitator_auth` | PayAI | Any x402 facilitator supporting Base `exact`. |
| `class_policy` | search/social/monitor `free`; seo/ai/automation/bot `paid` | `free`, `paid` or `block` per class. |
| `bot_overrides` | `[]` | `['GPTBot' => 'paid', 'Bytespider' => 'block']`. |
| `allow_paths` | `[]` | Prefixes never gated, on a segment boundary (`/pricing` exempts `/pricing/plans`, not `/pricing-secret`). |
| `trusted_ip_header` | `''` | Header with the real client IP behind a proxy. **Must be a header your proxy overwrites or appends to.** For `X-Forwarded-For` the right-most entry is used; a client-supplied left-most value is ignored. `CF-Connecting-IP` and other single-value edge headers are taken as-is. Never set it if the origin is reachable directly. Alternatively configure Laravel's trusted proxies; `$request->ip()` is used first. |
| `middleware_groups` | `['web']` | Groups the gate joins automatically. `[]` = apply `->middleware('pay-per-crawl')` yourself, per route or group. |
| `skip_authenticated` | `true` | Signed-in session users are never gated. |
| `discovery`, `site_name`, `site_description`, `llms_pages` | on | Content of the three discovery documents. |
| `site_url` | request origin | Pin the origin behind a rewriting proxy. |
| `cache_store` | default store | Store for replay locks and DNS lookups. |
| `log_humans`, `retention_days` | `true`, `90` | Hit log. Stores daily-salted IP hashes, never IPs. |
| `pro_key` | `''` | Optional ForgeMesh Pro license. |

`public/llms.txt` or `public/robots.txt` files shadow the routes (the web server serves them first). To advertise the manifest in `robots.txt`, add:

```
# Manifest: https://example.com/.well-known/x402.json
# LLMs: https://example.com/llms.txt
```

## Use

```php
// default: every web route is covered. Or opt in per route:
Route::get('/articles/{slug}', ...)->middleware('pay-per-crawl');
```

```bash
php artisan pay-per-crawl:stats --days=7        # bot hits, revenue, per-crawler table, last settlements
php artisan pay-per-crawl:stats --json
php artisan pay-per-crawl:prune                 # scheduled daily automatically
php artisan pay-per-crawl:pro-sync              # only with a Pro key; scheduled hourly automatically
```

Sanity check from outside, unpaid:

```bash
curl -si -A 'GPTBot/1.2' https://example.com/        # 402 + PAYMENT-REQUIRED header + JSON body
curl -si -A 'Mozilla/5.0 ... Chrome/129 ...' -H 'Accept: text/html' https://example.com/   # 200
```

The 402 carries all three rails at once: `PAYMENT-REQUIRED` (x402 v2), a v1 JSON body (stock `x402-fetch` 1.x), and `WWW-Authenticate: Payment` (MPP).

## Tests and verification

`tests/integration.sh` provisions nothing itself: point `APP_DIR` at a Laravel app with this package installed in gate mode, and it runs the 42-check HTTP matrix (human 200, GPTBot 402 with v2 header + v1 body + MPP header, HEAD 402, discovery 200, paid v1/v2/MPP through a mock facilitator, replay 402, underpayment 402) plus 8 Laravel checks (stats command, hits table).

Results at v0.1.0: Laravel 13.17 on PHP 8.3: 42/42 + 8/8. Laravel 10.50 on PHP 8.1: 42/42 + 8/8. Core unit tests: 156 checks.

Real paid calls through PayAI against this package, $0.01 each, receipts verified on `mainnet.base.org`:

| Rail | Transaction |
|---|---|
| x402 v1 | `0xfc7d0a33ab2baeb50f91109db26d4e882e627f66c0cb68b13363a8f7b09e3b68` |
| x402 v2 | `0xff1dfa134999dc4ad19bd8a9f9ae05e286e5c9e9714bc13c4979a3c073d7b63d` |

## License

MIT. Built by [ForgeMesh Labs](https://forgemesh.io) - hello@forgemesh.io
