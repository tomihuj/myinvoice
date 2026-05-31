# SK layer — Slovak localization fork

This fork adds a Slovak (SK) localization layer on top of upstream `radekhulan/myinvoice`.
Slovak behaviour activates when `cfg.local.php` sets `country.profile = 'SK'`. On the
default `CZ` profile the codebase behaves exactly like upstream.

## Enabling the SK profile

Add to `cfg.local.php` (or, in single-volume Docker, `${MYINVOICE_DATA_DIR}/cfg.local.php`):

```php
<?php
return [
    'country' => [
        'profile'        => 'SK',     // switches registry, currency, invoice wording
        'local_currency' => 'EUR',    // accounting/home currency for payment matching
    ],
    // optional — defaults to the public RPO endpoint:
    'sk' => ['rpo_api' => 'https://api.statistics.sk/rpo/v1'],
];
```

Then run the migration (`db/migrations/0083_vat_rates_slovak.sql`) to seed Slovak VAT
rates and restart. See the conflict-surface workflow at the bottom for the full deploy.

### What the SK profile changes

| Area | CZ profile (default) | SK profile |
|---|---|---|
| Company registry lookup | ARES | RPO (`RegistryGateway` routes by profile) |
| Home currency | CZK | EUR (`country.local_currency`) — payment matching + invoice currency fallback |
| Invoice PDF locale | `cs` / `en` per invoice | forced `sk` (Stage 1) |
| Doc titles & VAT-payer labels | Czech | Slovak (`SkInvoiceLabels`) |
| Reverse-charge legal text | § 92a (CZ) | § 69 zákona č. 222/2004 Z. z. (SK) |
| VAT rates | Czech seed | + `SK-23 / SK-19 / SK-5 / SK-0 / SK-RC` |

## Additive files (no upstream conflict possible)
- api/src/Service/Registry/RegistryLookup.php (shared interface)
- api/src/Service/Registry/RpoClient.php
- api/src/Service/Registry/RegistryGateway.php
- api/src/Service/Sk/SkInvoiceLabels.php
- db/migrations/0083_vat_rates_slovak.sql
- api/tests/Unit/Sk/*, api/tests/Fixtures/rpo_response.json

## Core files we edit (CONFLICT SURFACE — re-verify each on upstream rebase)
All items below are **implemented**. The "Re-check on rebase" column flags the exact
upstream symbol whose drift would silently break the SK behaviour.

| File | What we changed | Re-check on rebase |
|---|---|---|
| api/src/Bootstrap.php | DI defs for `RpoClient` + `RegistryGateway` (profile-driven) | the `addDefinitions([...])` block |
| api/src/Service/Pdf/InvoicePdfRenderer.php | effective 'sk' locale; 3-arg t(); 'sk' label set; locale-aware docTitle() | docTypeLabel()/docTitle()/t() signatures |
| api/templates/invoice/invoice.twig | Slovak 3rd arg on the 3 legal-text t() calls (non-payer, reverse charge, proforma) | those specific t() lines |
| api/src/Action/AresVies/AresLookupAction.php | depend on RegistryGateway | ctor + lookup call |
| api/src/Action/Auth/SetupAresLookupAction.php | depend on RegistryGateway; generic "Registr" error | ctor + lookup call |
| api/src/Service/Ares/AresClient.php | implements `RegistryLookup` | class signature |
| api/src/Service/Invoice/InvoiceDefaults.php | config-driven local currency fallback | the currency fallback `SELECT ... code = ?` block |
| api/src/Action/Auth/SetupAction.php | default currency from `country.local_currency` | the `$defaultCurrencyCode` line |
| api/src/Service/Bank/StatementMatcher.php | `localCurrency` from config (optional trailing ctor param) | the const→property + `expectedMatch()` |
| api/src/Infrastructure/Config/Config.php | added `fromArray()` test seam (additive) | n/a |
| cfg.sample.php | documents the `country` + `sk` blocks | n/a |

### Backward-compat note — StatementMatcher
`Config` is an **optional trailing** constructor param (`?Config $config = null`).
The DI container autowires the real `Config`; the existing 2-arg call sites
(`api/bin/cron-bank-scan.php`, `StatementMatcherTest`) keep the `CZK` default
unchanged. Do not promote it to a required param without updating those call sites.

## Tests
- `api/tests/Unit/Sk/*` — registry gateway routing, RPO client mapping, SK labels, VAT seed
- `api/tests/Unit/Service/Bank/StatementMatcherTest` — adds 2 SK cases (EUR as home
  currency, foreign-to-foreign rejected) via `Config::fromArray()`

## Update workflow
    git fetch upstream --tags
    git rebase upstream/<new-tag>     # conflicts only in the files above
    cd api && composer test           # full suite must stay green
    docker compose -f docker-compose.production.yml build
    docker compose -f docker-compose.production.yml up -d
