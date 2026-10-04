# Production and demo seed data

Seeders supply reference data and complete local demo scenarios for the current API schema.

## Run

From the Laravel project directory:

```sh
# Permanent reference/bootstrap data only (safe entry point for deployments).
php artisan db:seed --class=ProductionDataSeeder --force

# Permanent data plus the complete local demo.
# APP_ENV must be local or testing.
php artisan db:seed --class=DemoDataSeeder

# Rebuild disposable local data after changing the development schema.
php artisan migrate:fresh --seed --seeder=DemoDataSeeder

# Isolated verification: SQLite in memory + a temporary private image disk.
php database/seeders/verify.php
```

`DatabaseSeeder` seeds production data by default. The existing `DB_SEED_TEST_DATA=true` setting remains supported through `TestDataSeeder`, which delegates to `DemoDataSeeder`. Both demo entry points reject every environment except `local` and `testing`; `--force` does not bypass that guard. With cached configuration, change the existing configuration through your normal deployment process.

Run seeders as one deployment job, not concurrently. They do not truncate tables or disable foreign keys. Existing matching reference IDs are reused; translations and country relationships are corrected. Operator-edited settings/CMS and existing demo records are preserved. Soft-deleted records are not resurrected. Historical duplicate or mislinked rows from the old seeders are not deleted automatically because other records may reference them; these seeders are not a data-repair migration.

## Permanent data

- 92 countries, 34 owner-approved Saudi cities, 5 fuel types, and 12 colors.
- 103 car marques, including discontinued marques useful for spare parts.
- Committed NHTSA vPIC snapshots: 52 marques with explicitly returned model years, and 54 marques with all returned historical nameplates, filtered to passenger cars, SUVs/MPVs, and trucks. These are combined with Saudi new/used-market and regional manufacturer catalogues. Motorcycles and incomplete chassis are excluded.
- 1,978 distinct car names and 2,399 model entries, plus 12 sections and 176 bilingual component types.
- Platform settings and basic CMS entries.

Vehicle data sources and retrieval dates are stored next to the data:

- [NHTSA vPIC API](https://vpic.nhtsa.dot.gov/api/) — `data/vehicles-vpic.json`, manufacturer-submitted make/model-year listings.
- `data/vehicles-vpic-history.json` — all nameplates returned by the same typed API without the model-year filter, including discontinued vehicles such as Crown Victoria and Caprice. Exact query URLs are retained per marque.
- `data/vehicles-regional.json` — individual official manufacturer or authorised regional catalogue URLs, including Saudi Toyota and Nissan catalogues.
- `data/vehicles-saudi.json` — Saudi new/used-market nameplates from [YallaMotor KSA](https://ksa.yallamotor.com/new-cars), supplemented with official [Toyota Saudi](https://www.toyota.com.sa/en/sitemap), [Chery Saudi](https://www.cheryksa.com/en/models/1), [Geely Saudi](https://geelyksa.com/home/), [BYD Saudi](https://www.byd.sa/en/new-cars/), [MG Middle East](https://www.mgmotor.me/models/), [Hongqi Saudi](https://www.hongqi-ksa.com/en), [Bestune Saudi](https://bestune-sa.com/), [KGM Saudi](https://kgm.sa/public/en), and [iCAUR Saudi](https://www.icaursaudi.com/) catalogues. Each group retains its source URL.
- `data/model-arabic.json` — display transliterations; unlisted model codes retain their original designation.
- `Support/refresh-vehicle-catalogue.ps1` — optional maintainer script to refresh the year-specific NHTSA snapshot. Add `-AllYears` to refresh the historical snapshot. Ordinary seeding makes no network requests.

`data/saudi-cities.json` contains exactly the 34 bilingual city pairs supplied by the owner. `ReferenceDataSeeder` reads this list directly; there is no separate runtime city allowlist. Demo users reference the same city names, including `Madinah`. Seeding adds or updates these cities but does not delete older database rows; a fresh database contains only this city list. Updating existing data is an operator-managed step.

Vehicle coverage includes current, discontinued and imported nameplates; it cannot establish every privately imported vehicle or every historical trim in Saudi Arabia. A market listing does not imply current official Saudi distribution. The year-specific NHTSA snapshot asserts only the returned year (2024 for current marques and selected historical years for discontinued ones). Historical and regional entries without a verified model year use the nameplate alone in `models`; no year range or part compatibility is invented. Overlapping sources are merged case-insensitively before writing, preserving the original catalogue spelling and explicit Arabic translations. Country is the marque's country of origin, not the current owner's country or the vehicle's assembly plant.

Business settings such as fees, order limits, and delivery limits are bootstrap defaults, not market facts. Existing values are never reset. Support contacts and policy URLs start empty rather than pointing to invented businesses. Terms and privacy entries explicitly say they are unpublished; the operator must publish their approved content before launch.

No default administrator is created in production. Optional first-time bootstrap uses exported `SEED_ADMIN_EMAIL`, `SEED_ADMIN_MOBILE` (+9665xxxxxxxx), and `SEED_ADMIN_PASSWORD` (at least 12 characters); `SEED_ADMIN_NAME` is optional. Provide all three required values together. Existing administrators' passwords and roles are never reset. Remove bootstrap secrets after initial setup; they are never printed by the seeder.

## Local demo

The dataset contains:

| Domain | Records |
| --- | ---: |
| Customers / providers | 25 / 15 |
| Admin role examples | 5 |
| Stores | 15 (13 active, 2 inactive) |
| Customer cars | 39 (four customers have no saved cars) |
| Store cars | 45 |
| Store-car component stock records | 450 |
| Store requests | 25 (15 accepted, 5 pending, 5 rejected) |
| Legacy provider request records | 15 accepted |
| Orders | 100 (four per customer) |
| Offers / offer pictures | 90 / 90 |
| Ratings | 50 |
| Conversations / messages | 50 / 200 |

Orders include general and specific requests across pending, awaiting-payment, paid, completed, rejected, and cancelled states. Bids belong only to general orders; each selected offer is linked from the order and competing pending offers are marked not selected. General offer prices are whole-request totals; specific-order payments retain their existing quantity behavior. Payments use the application's integer minor currency units, and paid specific orders consume fixture stock once. Ratings belong to completed purchases. Offers and conversations use active stores in the customer's city.

The 50 general requests split between 25 catalog selections (`component_id` only)
and 25 custom part names (`component_name` only). Every customer has a completed general request with two offers, including customers without saved cars. Each general request has a vehicle snapshot with
year, transmission, color and fuel type, including bilingual vehicle/color/fuel
labels. Part names are not duplicated into language columns on orders.

Demo administrators use `demo-admin@example.test`, `demo-manager@example.test`, `demo-employee@example.test`, `demo-hr@example.test`, and `demo-developer@example.test`; their local-only initial password is `DemoOnly-ChangeMe!`. Customer mobiles are `+966500000100` through `+966500000124`; provider mobiles are `+966500000200` through `+966500000214`. All identities, phone values, businesses, registration numbers, inventory codes, prices, ratings, messages, and transactions are synthetic. Map links point to a city search, not a claimed real business address. No OTPs, login tokens, device tokens, deletion jobs, or gateway transactions are generated.

Rerunning preserves existing demo edits and does not reset passwords, stock, payments, or order states. Missing private image copies are repaired from the committed assets. To start an entirely new demo, use a separate disposable development database; these seeders do not clear your data.

## Images

`assets/images/manifest.json` is the final prompt set and asset-to-subject manifest. Its **36 source images** were generated with the built-in image generation tool:

This expansion creates **zero new source images**, within the requested maximum of five. It reuses the existing 36 assets and their matching database-backed private copies.

- 8 intact-car photos, including two angles of one Camry.
- 8 damaged donor-car photos.
- 8 fictional shop photos.
- 10 generic automotive part photos.
- 2 documents clearly marked as demo/sample and invalid for official use.

Images are synthetic illustrations, not evidence of a real vehicle, real business, official registration, OEM part number, or guaranteed part fitment. Photos are reused across fixtures to keep the generation count within the requested budget. Car records use the pictured make, model, year and color. Plate numbers are not stored in car records or asset metadata. Demo cars are identified by owner/store and catalog car name, with distinct car names per owner/store so reruns reuse the same records.

The first three intact photos were edited only to replace their initially blank plates with Saudi-style plates. Original pre-edit renders are not included in this directory or the seed manifest. No more images need to be generated when running a seeder.

Source assets stay in this directory. `Support/DemoImages.php` uses dependency injection to call the application's existing `ImageStorageService`; it reuses matching stored images when available and falls back to the committed assets. Existing images are matched by content hash before reuse, so tester-uploaded replacements are preserved without being mislabelled as another car or part. Every owning row gets its own private copy with UUID paths and disk/path/MIME/size metadata, so deleting one record cannot remove another record's media. Repeated runs reuse existing copies. A failed seed rolls back its rows and cleans up newly written private files. Customer-specific storage disks remain respected.

Coverage:

| Existing image fields/tables | Demo use |
| --- | --- |
| `customer_car_pictures` | Some cars have one or two photos; others have none |
| `store_car_pictures` | Intact and damaged cars; some cars have no photos |
| `store_pictures` | One or two gallery photos on some stores |
| `stores.commercial_registration_*` | Clearly marked sample document |
| `store_requests.commercial_registration_*` | Independent copy of sample document |
| `order_images` | Optional galleries on general and specific requests; general requests demonstrate multiple photos |
| `offer_images` | One matching part photo per offer; reruns also backfill older demo offers and repair missing files |

**Neither `components` nor `store_car_components` currently has an image field or picture relation.** Their ten existing part photos are reused as order and offer attachments through the existing media tables. No image paths are hidden in unrelated text fields.

## Verification

`verify.php` uses an isolated SQLite connection even when the project's configured database is MySQL. It checks the 34 approved bilingual cities, representative Saudi/historical nameplates, case-insensitive catalogue duplicates, non-sequential reference IDs, production idempotence, operator content preservation, demo counts, per-customer orders and offers, foreign keys, order/offer/payment/rating consistency, image coverage and metadata, reuse of exactly the existing 36 source assets, rerun stability, missing-file repair and older-offer gallery backfill, rollback cleanup after a simulated storage failure, and production guards. Its temporary image directory is removed afterward.
