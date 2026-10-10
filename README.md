# SilverShop Analytics

Catalog analytics for [SilverShop](https://github.com/silvershop/silvershop-core) (Silverstripe CMS 6).

This first release ships **product-view tracking** and a **Most-viewed products** report. It is built so that
search-term reporting can be added later without a redesign (see [Roadmap](#roadmap)).

## Requirements

- Silverstripe CMS `^6.0`
- SilverShop core `^6`
- `silverstripe/reports` `^6.0`

## Installation

```bash
composer require silvershop/analytics
```

Then run `dev/build?flush=1`.

## What it does

- Records one anonymous row per product-page view (`SilverShop_ProductView`).
- Adds a **Shop → Most-viewed products** report (Reports admin) with view counts and unique-viewer counts over a
  date range.
- Ships a prune task to keep the raw log bounded.

Tracking attaches to `SilverShop\Page\ProductController` and fires only on the product page's own `GET` request —
never on form posts, ajax calls, or sub-actions — so counts reflect genuine page views.

## Privacy

No raw PII is stored:

- **Unique viewers** are counted from a one-way hash (`sha1`) of an anonymous per-session token minted on the
  first view — never an IP address or a login.
- The **member link is off by default** (`track_members: false`); enable it only if you want views attributed to
  logged-in members.
- Set `respect_consent: true` to gate tracking behind a cookie-consent flag in the session, so the module can sit
  behind a consent banner.

```yaml
# app/_config/analytics.yml
SilverShop\Analytics\Extension\ProductViewTrackingExtension:
  enabled: true            # master switch
  track_members: false     # attribute views to logged-in members
  respect_consent: false   # only track once the consent flag is set
  consent_session_key: 'analytics_consent'
```

## Retention

The report reads the raw view log directly, so figures are live with no rollup to run. Raw rows are pruned after
`retention_days` (default 365) — this also bounds how far back the report can look.

```yaml
SilverShop\Analytics\Model\ProductView:
  retention_days: 365      # 0 disables pruning
```

Schedule the prune task daily (cron or `symbiote/silverstripe-queuedjobs`):

```bash
vendor/bin/sake tasks:SilverShop-Analytics-Task-PruneProductViewsTask
```

## Roadmap

- **Search-term & zero-result reports** — this module will expose a `record($term, $resultCount)` API that a
  search module (e.g. a future `silvershop/search`) calls. SilverShop core ships no search, so there is nothing to
  record yet; the reports arrive with that integration.
- **Daily rollups** — an optional aggregate table for very high-traffic catalogs that want short raw retention
  while keeping long report history.

## License

BSD-3-Clause. See [LICENSE](LICENSE).
