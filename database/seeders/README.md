# Production and demo seed data

All code and generated source assets for this change live under `database/seeders`. Factories, application code, migrations, and the current application database are unchanged by preparing these files.

## Run

From the Laravel project directory:

```sh
# Permanent reference/bootstrap data only (safe entry point for deployments).
php artisan db:seed --class=ProductionDataSeeder --force

# Permanent data plus the complete local demo.
# APP_ENV must be local or testing.
php artisan db:seed --class=DemoDataSeeder

# Isolated verification: SQLite in memory + a temporary private image disk.
php database/seeders/verify.php
```

`DatabaseSeeder` seeds production data by default. The existing `DB_SEED_TEST_DATA=true` setting remains supported through `TestDataSeeder`, which delegates to `DemoDataSeeder`. Both demo entry points reject every environment except `local` and `testing`; `--force` does not bypass that guard. With cached configuration, change the existing configuration through your normal deployment process.

Run seeders as one deployment job, not concurrently. They do not truncate tables or disable foreign keys. Existing matching reference IDs are reused; translations and country relationships are corrected. Operator-edited settings/CMS and existing demo records are preserved. Soft-deleted records are not resurrected. Historical duplicate or mislinked rows from the old seeders are not deleted automatically because other records may reference them; these seeders are not a data-repair migration.

## Permanent data

- 92 countries, 46 Saudi cities, 5 fuel types, and 12 colors.
- 70 car marques, including discontinued marques useful for spare parts.
- A committed NHTSA vPIC snapshot for 52 marques, filtered to passenger cars, SUVs/MPVs, and trucks, plus verified regional/manufacturer nameplates. Motorcycles and incomplete chassis are excluded.
- 487 car names and model entries, plus 12 sections and 176 bilingual component types.
- Platform settings and basic CMS entries.

Vehicle data sources and retrieval dates are stored next to the data:

- [NHTSA vPIC API](https://vpic.nhtsa.dot.gov/api/) — `data/vehicles-vpic.json`, manufacturer-submitted make/model-year listings.
- `data/vehicles-regional.json` — individual official manufacturer or authorised regional catalogue URLs, including Saudi Toyota and Nissan catalogues.
- `data/model-arabic.json` — display transliterations; unlisted model codes retain their original designation.
- `Support/refresh-vehicle-catalogue.ps1` — optional maintainer script to refresh the NHTSA snapshot; ordinary seeding makes no network requests.

This is broad verified coverage, not every marque, trim, year, or market in the world. The NHTSA snapshot asserts only the returned year (2024 for current marques and selected historical years for discontinued ones). Regional catalogue entries without a verified model year use the nameplate alone in `models`; no year range or part compatibility is invented. Country is the marque's country of origin, not the current owner's country or the vehicle's assembly plant.

Business settings such as fees, order limits, and delivery limits are bootstrap defaults, not market facts. Existing values are never reset. Support contacts and policy URLs start empty rather than pointing to invented businesses. Terms and privacy entries explicitly say they are unpublished; the operator must publish their approved content before launch.

No default administrator is created in production. Optional first-time bootstrap uses exported `SEED_ADMIN_EMAIL`, `SEED_ADMIN_MOBILE` (+9665xxxxxxxx), and `SEED_ADMIN_PASSWORD` (at least 12 characters); `SEED_ADMIN_NAME` is optional. Provide all three required values together. Existing administrators' passwords and roles are never reset. Remove bootstrap secrets after initial setup; they are never printed by the seeder.

## Local demo

The dataset contains:

| Domain | Records |
| --- | ---: |
| Customers / providers | 15 / 15 |
| Admin role examples | 5 |
| Stores | 15 (13 active, 2 inactive) |
| Customer cars | 30 |
| Store cars | 45 |
| Store-car component stock records | 450 |
| Store requests | 25 (15 accepted, 5 pending, 5 rejected) |
| Legacy provider request records | 15 accepted |
| Orders | 60 |
| Ratings | 30 |
| Conversations / messages | 30 / 120 |

Orders include general and specific requests across pending, negotiating, paid, completed, rejected, and cancelled states. Bids belong only to general orders; accepted offers match their orders. Payments use the application's integer minor currency units, and paid specific orders consume fixture stock once. Ratings belong to completed purchases. Offers and conversations use active stores in the customer's city.

Demo administrators use `demo-admin@example.test`, `demo-manager@example.test`, `demo-employee@example.test`, `demo-hr@example.test`, and `demo-developer@example.test`; their local-only initial password is `DemoOnly-ChangeMe!`. Customer mobile fixtures begin at `+966500000100`; provider fixtures begin at `+966500000200`. All identities, phone values, businesses, registration numbers, inventory codes, prices, ratings, messages, and transactions are synthetic. Map links point to a city search, not a claimed real business address. No OTPs, login tokens, device tokens, deletion jobs, or gateway transactions are generated.

Rerunning preserves existing demo edits and does not reset passwords, stock, payments, or order states. Missing private image copies are repaired from the committed assets. To start an entirely new demo, use a separate disposable development database; these seeders do not clear your data.

## Images

`assets/images/manifest.json` is the final prompt set and asset-to-subject manifest. Its **36 source images** were generated with the built-in image generation tool:

- 8 intact-car photos, including two angles of one Camry.
- 8 damaged donor-car photos.
- 8 fictional shop photos.
- 10 generic automotive part photos.
- 2 documents clearly marked as demo/sample and invalid for official use.

Cars use Saudi-style plates with fictional identifiers. Images are synthetic illustrations, not evidence of a real vehicle, real business, official registration, OEM part number, or guaranteed part fitment. Photos are reused across fixtures to keep the generation count within the requested budget. Car records use the pictured make, model, year, color, and sample plate; repeated plates across different demo owners reflect this deliberate reuse.

The first three intact photos were edited only to replace their initially blank plates with Saudi-style plates. Original pre-edit renders are not included in this directory or the seed manifest. No more images need to be generated when running a seeder.

Source assets stay in this directory. `Support/DemoImages.php` uses dependency injection to call the application's existing `ImageStorageService`; it copies them into the configured private image disk with UUID paths and records disk/path/MIME/size metadata. Every owning row gets its own private copy, so deleting one record cannot remove another record's media. Repeated runs reuse existing copies. A failed seed rolls back its rows and cleans up newly written private files.

Coverage:

| Existing image fields/tables | Demo use |
| --- | --- |
| `customer_car_pictures` | Some cars have one or two photos; others have none |
| `store_car_pictures` | Intact and damaged cars; some cars have no photos |
| `store_pictures` | One or two gallery photos on some stores |
| `stores.commercial_registration_*` | Clearly marked sample document |
| `store_requests.commercial_registration_*` | Independent copy of sample document |
| `orders.customer_image_*` | Part photos attached to general and specific requests |

**Neither `components` nor `store_car_components` currently has an image field or picture relation.** To keep this change confined to seeders, their ten part photos are saved and used as order attachments. Adding component-gallery support requires a separately authorised schema/API change. No image paths are hidden in unrelated text fields.

## Verification

`verify.php` uses an isolated SQLite connection even when the project's configured database is MySQL. It checks non-sequential reference IDs, production idempotence, operator content preservation, demo counts, foreign keys, order/offer/payment/rating consistency, image coverage and metadata, rerun stability, missing-file repair, rollback cleanup after a simulated storage failure, and production guards. Its temporary image directory is removed afterward.
