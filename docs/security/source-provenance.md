# Source Provenance and Redistribution Review

## Current finding

The repository currently has no root `LICENSE` file. Its `composer.json` contains a `"license": "MIT"` metadata value, but that value is not sufficient by itself to establish the licensing status of every first-party file, inherited vendor file or third-party dependency.

The repository contains several indicators that the current Laravel application has a pre-existing commercial product lineage:

- The application configuration includes purchase-code and marketplace product-ID settings.
- The environment template includes an external SDK API-key setting.
- Composer declares `reachead/laravel-sdk` and an external Composer repository at `https://reachead.com/composer`.
- The original README identified a QRPay release and included installation/update instructions.

These findings are a source-provenance warning, not a legal conclusion about ownership or the exact terms of any purchase.

## Required before relicensing or redistributing the full application

- [ ] Identify the upstream product/vendor and the applicable purchase or distribution agreement.
- [ ] Confirm whether the agreement permits public source hosting, modification, redistribution and sublicensing.
- [ ] Inventory first-party, vendor-supplied and generated files.
- [ ] Review dependency licenses and notices, including packages from custom Composer repositories.
- [ ] Preserve required copyright notices and attribution.
- [ ] Decide which newly authored Sanctum Scan components can be licensed independently.
- [ ] Obtain legal review before adding a root license that purports to cover the entire repository.

Until this review is complete, do not claim that the complete inherited application is MIT- or Apache-2.0-licensed merely because of Composer metadata or repository visibility.

## Environment template hardening

The previously committed `.env.example` contained a pre-generated Laravel `APP_KEY`. A shared example key must not be reused by deployments. The template now leaves `APP_KEY` blank so each installation must generate its own key.

If any deployed environment used the published example key, treat it as exposed and plan a controlled key rotation. Laravel application-key rotation can invalidate sessions and affect encrypted application data; assess and back up the deployment before changing a live key.

## Scope

This file records repository observations and engineering safeguards. It is not legal advice and does not replace review of the applicable upstream contract or dependency licenses.
