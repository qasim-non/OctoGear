<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## Provider notifications and bounded page reads — 2026-10-17

Shared notification inbox/read APIs and Android device registration support active
customers and providers. Provider entry acknowledges only the fetched 20-row page
through `PATCH /notifications/read-page` with `ids` (1–20 distinct UUIDs). The
response `data` contains the acknowledged IDs and authoritative `unread_count`.
`ReadNotificationPageRequest` validates input; `NotificationReadService` locks the
authenticated user's morph-scoped rows, rejects any foreign/missing row atomically,
and sets only previously null read timestamps. GET, legacy reads and cursor ordering
remain unchanged. No migration or backfill is required.

`OrderService::acceptOffer` emits `OfferAccepted` only on the actual transition;
replays do not notify again. Admin store approval/rejection emits
`StoreRequestReviewed` for both applications and changes. Their database inbox rows
are written synchronously within the existing business transaction and roll back
with it. Existing payment/completion/direct-request/message notifications are reused.

`PushNotificationPayload` is the shared recipient-role and identifier allowlist at
enqueue and delivery. Device/session ownership, expiry, read state, blocked users,
privacy, retry and conditional invalid-token deletion rules remain intact. The
legacy `SendCustomerPush`/`QueueCustomerPush` names remain compatible with existing
queued jobs/integrations, but now serve both roles. All pushes remain after commit.
General-order inbox alerts retain the existing audience; only new direct orders
trigger new-order push. Read PUSH_NOTIFICATIONS.md for deployment and tests.

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Store change approval (2026-10-16)

Provider edits now submit proposals; live details and photos change only after
admin acceptance. The existing store mobile is immutable. Apply additive migration
`2026_10_16_000003_add_store_change_requests` after the store-request idempotency
migration, before deploying the updated API and Flutter app.

- Owner `GET /api/provider/stores/{store}` returns `edit_version` and
  `pending_change_request_id`. POST `/api/provider/stores/{store}/change-requests`
  sends editable fields plus required `change_reason` (10–1000 characters),
  `base_version` and UUID `idempotency_key`. The response is a request with
  `request_type=store_change`, `store_id`, proposed details and private photo URLs.
- The legacy PUT `/api/provider/store/{store}` follows the same review rules.
  Supplying `mobile` is rejected. No provider endpoint updates approved details
  immediately. Missing gallery/brand arrays preserve values; explicit empty arrays
  clear the proposal. Multipart empty strings represent empty arrays.
- Existing admin GET `/api/admin/store-requests` and `/{request}` include new-store
  and change requests. Review details include `change_reason`, `previous_details`,
  proposed fields/photos, `processed_by` and `processed_at`. POST `/{request}/accept`
  applies changes to the same store; POST `/{request}/reject` with `reason` leaves
  the store unchanged. This project exposes the admin API, not an admin UI.
- Store/owner locking and a unique pending-store key prevent duplicate pending
  proposals. Version checks reject stale submission/approval with 409. Approved
  changes retain store ID, owner, phone and active/inactive status. Rejected change
  requests remain history; corrections create a new request from current store
  details. Existing rejected new-store application resubmission is unchanged.
- Proposal media is copied privately and readable only by its owner or an active
  admin. Approval makes independent live copies and schedules old-file cleanup
  after commit. Failures roll back metadata and clean newly written copies.
- Identical submission keys return the existing receipt. The provider's own
  request resources expose `idempotency_key` for exact timeout reconciliation;
  storage paths and request fingerprints remain hidden.

Focused regression: 88 tests / 520 assertions passed across store changes,
onboarding, media, profile, admin dashboard/approval and marketplace access.

## Provider dashboard and order chats (2026-10-16)

- `GET /api/provider/dashboard?store_id=...&period=today|week|month` returns owned
  stores, current operational counts, attention previews, recent orders and recorded
  sales. Omit store_id for all owned stores; month is the default period.
- `GET /api/provider/dashboard/orders?section=pending_direct|awaiting_payment|paid|recent&store_id=...&page=1`
  paginates the same ownership scope. Paid attention excludes completed orders.
- Sales use actual paid payment amounts on paid/completed orders and Payment.updated_at
  (no paid_at exists), using the shared business timezone from calendar start to
  request time. Weeks begin on Saturday at 00:00 in that timezone.
  Missing payment records never produce estimated sales. Values are SAR minor units.
- Summary and paginated reads use short transactions for consistent counts/rows;
  production MySQL/MariaDB must retain REPEATABLE READ isolation for this guarantee.
- `GET /api/provider/orders/{order}/conversation` resolves an eligible owned-order
  chat without writing. POST its `/messages` child with content/client_message_id
  creates/reuses the chat and sends one message. General orders reuse offer chats;
  direct orders use unique conversations.order_id. No arbitrary customer ID is accepted.
- Run migration `2026_10_16_000001_add_direct_order_context_to_conversations` before
  using direct-order chats. It adds a nullable FK/index and preserves existing rows.
  Completed-order reconciliation can only read an existing participant conversation
  or replay an identical first-message receipt; it cannot create a new message/chat.

## Shared business timezone

Set `BUSINESS_TIMEZONE=Asia/Riyadh` in `.env` (also included in `.env.example`).
Dashboard periods and customer/provider vehicle-year validation use this setting.
All business calendar calculations must read `config('app.business_timezone')`;
use `env()` only in configuration files so `config:cache` works correctly.
The API returns that timezone in `sales.timezone`; the app displays it instead of
assuming Saudi time. Day/month boundaries and Saturday-start weeks use that zone,
then convert to UTC for database comparisons and ISO timestamps.

Laravel's persistence/framework timezone remains UTC to preserve existing stored
timestamps. Changing the business timezone does not reinterpret order/payment
history. After changing the environment value, rebuild any production config cache
with `php artisan config:cache` and restart long-running workers.

Timezone follow-up verification (2026-10-16): 9 dashboard API tests passed
(151 assertions), including Saturday rollover, alternate zones, DST, and UTC
storage. The vehicle/order validation regression run passed 47 tests, including
all six year validators around New Year. Two existing GeneralOrderDetailsTest
failures remain: the paid response contains a field expected absent, and the
demo seeder creates 50 general orders where the test expects 30. Both reproduce
in an isolated process with the original date('Y') validation behavior.
All 24 Flutter dashboard tests passed and targeted static analysis was clean.
The updated app was rebuilt and launched on the connected Android emulator.

## General request details (API step 2)

`POST /api/customer/orders` requires a UUID `Idempotency-Key` header for general
requests. Select exactly one vehicle source and exactly one part source:

```json
{
  "order_type": "general",
  "customer_car_id": 12,
  "component_id": 7,
  "description": "Left and right mirrors"
}
```

Alternatively, enter a vehicle for this request and a custom part name:

```json
{
  "order_type": "general",
  "vehicle": {
    "car_name_id": 4,
    "manufacturing_year": 2022,
    "transmission_type": "automatic",
    "color_id": 2,
    "fuel_type": 1
  },
  "save_to_my_cars": true,
  "component_name": "All mirrors"
}
```

IDs are examples; use real catalog/owned-car IDs. Either vehicle mode works with
either part mode. Description is optional (1000 characters maximum). Custom part
names allow 255 characters in any language and are stored exactly as submitted
after normal request whitespace trimming. They are not translated or copied
into separate language columns.

`component_id` identifies a catalog selection; its order `component_name` column
stays null. Response `component_name` (and customer history `part_name`) resolves
the current catalog name using `Accept-Language`. Renaming a catalog component
therefore changes the name shown on earlier orders. Soft-deleted components remain
readable on their existing orders but cannot be selected for new requests. The
foreign key prevents permanent deletion of a component referenced by orders.
Custom selections instead store only `component_name`, with a null `component_id`.
There is no `component_name_ar` or `component_name_en` on orders.

Manual vehicle details require all five fields shown above. Transmission accepts
`automatic`, `manual`, or `unknown`; year must be 1970 through the current year.
Color and fuel IDs must be active catalog entries. Manufacturer comes from the
selected car name. A selected saved car supplies its own color and fuel; if either
is missing/unavailable, submission returns a localized 422 on `customer_car_id`
asking the customer to complete the car. Update it through the existing garage
PATCH endpoint, then retry. Existing saved-car transmission can remain null.

`save_to_my_cars` is optional, defaults to false, and is only allowed for manual
vehicle details. Saving persists car name, year, transmission, color and fuel.
Customer and provider cars do not collect or store plate numbers. Customer-car
photos remain supported through the existing private photo endpoints.

Customer, provider and admin order responses include `vehicle_details`: vehicle
IDs/names, year, transmission, `color_id`, localized `color_name`, `fuel_type` (ID),
and localized `fuel_type_name`. Vehicle names, color and fuel labels are copied at
submission, so later garage/catalog edits do not alter a request. If a referenced
vehicle/color/fuel entry is permanently deleted, the snapshot keeps its labels
and its corresponding reference becomes null. The private
`vehicle_details.customer_car_id` is visible only to the customer and admins.

General input rejects `quantity`, `model_id`, `notes` and
`store_car_component_id`. Use optional `description`, currently stored in `notes`.
General responses omit quantity. General rows retain an internal quantity of one
for current schema compatibility; it does not affect offer or payment totals.

Identical same-key retries return the original request even if selected entries
were later deleted. Changed payloads (including color/fuel) or retries of deleted
orders return 409. Order, vehicle snapshot, optional garage save and optional
`images[]` uploads share a transaction; retries do not duplicate them or their
notifications. Image bytes and their order participate in the retry fingerprint.

### Editing a customer order

`PATCH /api/customer/orders/{order}` requires the current `edit_token`.
An editable general request accepts optional `description`, a part source
(`component_id` or `component_name`), and a complete `vehicle` object with the
five manual fields shown above. `customer_car_id` and `save_to_my_cars` are
prohibited during updates. Saved-car selection remains supported on POST creation.

Omitting `vehicle` preserves the existing snapshot and its saved-car reference.
Providing `vehicle` replaces only the order snapshot with request-specific
details; it does not edit, create or delete a garage car. The replacement has no
`customer_car_id`. Existing ownership, edit eligibility, concurrency-token and
transaction checks remain in force. Specific orders accept quantity/notes edits
and cannot change vehicle details.

### Fresh database setup

Development data is disposable. These migrations define the current schema,
without legacy order conversion, legacy model metadata or restoration code.
Because original migrations changed, rebuild the local development database:

```sh
php artisan migrate:fresh --seed --seeder=DemoDataSeeder
```

This removes the current database records and creates fresh demo/reference data.
Demo requests cover both catalog selections and custom part names, with complete
vehicle color/fuel snapshots. Do not apply this reset to a database whose data
must be retained. See `database/seeders/README.md` for demo accounts.

When a customer selects an offer, the order records its `accepted_offer_id`, moves
to `awaiting_payment`, and keeps the selected offer's whole-request price. That
offer becomes `accepted`; pending competitors become `not_selected`, which records
that the customer chose another offer without claiming they explicitly refused it.
Offer selection is atomic, and provider price changes are closed once an offer is
selected. General payment charges the accepted offer total once, regardless of the
internal quantity. Specific-order payment retains its existing price-times-quantity
behavior. The buy route, payment stub and test SMS configuration are unchanged.

Payment preserves all received offers and their photos. Customers can continue
using `GET /api/customer/orders/{order}/offers` and
`GET /api/customer/orders/{order}/offers/{offer}` after payment and completion;
order history/detail responses also retain the offers. The accepted offer keeps
its whole-request total, notes, photos and store details; other offers keep their
`not_selected` or `rejected` status. These records are read-only after selection:
providers cannot edit/delete them and customers cannot reject or switch offers.
Photo URLs still require authentication and offer ownership authorization.

Customer order history can be filtered with `order_type` and `status`, for
example `GET /api/customer/orders?status=awaiting_payment&page=1`. Rejecting an
individual offer changes that offer to `rejected` and leaves the overall request
`pending`, so the customer can consider other offers.

Provider offer creation accepts optional `images[]`; sending `images[]` on an
offer update replaces the private gallery, while omitting it preserves existing
photos. Large reference catalogs support `search`, `page`, and `per_page` (up to
50) on cities, companies, company names, car models, and components. For example,
use `GET /api/reference/components?search=wheel&section_id=1&page=1`; responses
include the standard pagination `meta`. Small fixed selectors (fuel types,
colors, and sections) remain complete lists.

This is an API-only contract change; update Flutter to send the selected offer ID,
display `awaiting_payment`, and use the returned total before enabling this flow.

## Customer-car transmission

Customer car `POST /api/customer/customer-cars` and
`PATCH /api/customer/customer-cars/{id}` accept optional `transmission_type`:
`automatic`, `manual`, `unknown`, or JSON null. `unknown` means the customer is
not sure; null means no value is recorded. Omission on create stores null;
omission on update leaves the current value intact. Explicit null clears it.
Create/update responses and the existing customer-car list/detail endpoints
return `transmission_type` as a nullable machine value, identical in both locales.
Invalid values return the existing 422 envelope with localized field errors.

Apply the additive migration
`2026_10_01_000001_add_transmission_type_to_customer_cars.php` before deploying
the updated API. Existing cars remain null; no historical transmission is
guessed. Old clients may continue omitting the field. The creation
`Idempotency-Key` contract is unchanged: identical retries replay, but a changed
transmission selection conflicts. Pre-upgrade omitted-field fingerprints remain
compatible. Existing customer ownership and provider restrictions still apply.
No provider-car, order, payment, SMS, or Flutter changes are part of this step.

## Marketplace car catalog

Authenticated marketplace clients can read `GET /api/stores/{store}/cars/{car}`
and its paginated `/components?page=1` child. Both enforce active-store visibility
and nested ownership. Car list/detail responses use `MarketplaceStoreCarResource`
to omit management flags and creation timestamps; detail includes
localized manufacturer and section-condition data. Provider management responses
keep their existing resource.

Component `price` remains an integer in halalas, consistent with the existing
payment service and fixtures. Additive `currency: "SAR"` and `price_scale: 100`
metadata define the display conversion (52025 is SAR 520.25). Stock zero is retained
so the catalog can truthfully show out-of-stock parts. The list also includes the
localized component section when present. Removed stock/component references are
excluded from both list and count; removed component detail returns 404. Pagination
uses descending creation time and ID for deterministic ordering.

No schema migration, SMS configuration change, real payment integration, or new
ordering behavior is needed for this read-only feature.

## Shared image storage

All image uploads use the injected `App\Services\ImageStorageService`.
`App\Support\ImageRules` and `config/images.php` define shared validation and
limits. Feature services retain their ownership checks, relationships, ordering,
and transaction boundaries. The database stores a disk, relative path, MIME type,
and byte size; API clients receive authenticated API-relative URLs.

New uploads default to `IMAGE_DISK=images_local`, rooted at
`storage/app/private/images`. Defaults are JPEG/PNG/WebP, 5 MiB per image,
4096 × 4096 maximum dimensions, and five gallery photos. Configure these in
`config/images.php` (or its documented environment variables). The optional
`CUSTOMER_CAR_MEDIA_DISK` override remains supported, and existing rows keep
their original disk so changing the upload default does not break older files.

| API | Multipart image field |
| --- | --- |
| `POST /api/customer/customer-cars` and its `/pictures` endpoint | `pictures[]` |
| `POST /api/provider/store/{store}/cars` | `pictures[]` |
| `PUT /api/provider/store/{store}/cars/{car}` | `pictures[]` replaces gallery |
| `PUT /api/provider/store/{store}` | `pictures[]` replaces gallery; `commercial_registration_picture` replaces registration |
| `POST /api/provider/store-requests` and `/direct` | `commercial_registration_picture` |
| `POST /api/customer/orders` | optional `images[]` for general and specific requests |

Send actual file parts, not local paths, URLs, or base64 strings. For multipart
updates use HTTP `POST` with the form field `_method=PUT`; PHP then parses the
file parts while Laravel routes the request as PUT. Other required fields and
existing authorization requirements still apply. Omitting a gallery preserves
it; a JSON update with `pictures: []` clears it.

Gallery responses use `{id, url, mime_type, size_bytes, sort_order}` objects.
Orders return an ordered `images` array (empty when no files were submitted).
The `commercial_registration_picture` response field contains a protected URL or
null. Send the same bearer token when loading images.
Store galleries follow marketplace access; registration documents require the
owner or an active administrator; order images follow order access rules.
Customer-car photo routes and their existing response format are unchanged.

Order images live in `order_images`; orders have no scalar image columns.
Submit zero to five images on creation (subject to the configured limits above)
and read them at `GET /api/media/orders/{order}/images/{orderImage}`. The image
must belong to that order, and the viewer must have access to the order. This
step does not add image editing endpoints. The old `customer_image` input is
rejected with a message directing clients to `images[]`.

Uploads are cleaned up if database persistence fails. Replacement deletions
are recorded in `pending_image_deletions` in the same database transaction and
run only after commit. Failed cleanup remains available to `php artisan
images:cleanup`, scheduled every fifteen minutes by Laravel's scheduler. The
scheduler must be running for automatic retries. Store-request approval copies
the registration image so request and store records have independent files.

Deploy both `2026_09_26_160000_standardize_image_storage` and
`2026_09_26_170000_create_pending_image_deletions_table` before serving the new
upload APIs. The schema migration preserves historical path/URL strings but
leaves their disk null because they do not identify verified stored files. Such
records are not exposed as working image URLs; upload the real images through
the relevant API to replace them. No remote URL is downloaded automatically.

Soft deletion retains private files. Force-deleting an order queues its image
cleanup in the deletion transaction and removes files only after commit. Failed
file deletions remain queued for retry. Other image-owning models retain their
existing cleanup behavior. Bulk/raw database
deletions and unrelated foreign-key cascades bypass Eloquent events; any future
account/reference-data purge must explicitly clean its dependent media through
the feature services before deleting records.

## Customer-car media operations

Customer-car photos are private application media. The mobile client uploads
JPEG, PNG, or WebP files using multipart form data and receives an
authenticated API-relative image URL; it must never receive a filesystem path
or a public storage URL. Creation requests require an `Idempotency-Key` UUID
header so an explicit mobile retry returns the original car rather than adding
a duplicate. A key is replayable for at most 24 hours by default
(`CUSTOMER_CAR_IDEMPOTENCY_RETENTION_HOURS`): the retry must have the same
vehicle fields and the same ordered image content/MIME types. Reusing the key
for a different submission returns a safe `409` and creates nothing. The key
and its server-only fingerprint are never exposed in API responses. The create
flow enforces expiry even if the scheduler is delayed; deploy the Laravel
scheduler so `customer-car-media:purge-expired-idempotency-keys` can release
expired key metadata hourly.

Earlier customer photos remain on `customer_car_media_local` below
`storage/app/private/customer-cars`. New uploads follow the shared storage
configuration above. Copy existing files before changing a saved disk's root
or removing its configuration.

Before production, configure PHP and the reverse proxy to accept the feature
limits (`upload_max_filesize` at least 5 MiB and `post_max_size` at least 26
MiB). The current server validates MIME type, size, and image dimensions, but
does not normalize orientation or strip EXIF because this host has neither GD
nor Imagick. Provision one of those processors or a managed image service and
add normalization/EXIF-removal coverage before public production release.

Normal customer-car deletion is a soft delete and deliberately keeps the
private files for the record-retention lifecycle. An Eloquent instance
`$car->forceDelete()` first removes every active and soft-deleted private file;
if a physical deletion fails, it aborts before the database cascade can remove
the file metadata. Do not use bulk/raw database deletes, `forceDeleteQuietly`,
or user/account cascade deletion for customer cars. Before adding an account
purge or another direct cascading delete, implement and test an explicit purge
lifecycle which force-deletes each customer car through this guarded model
path.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Android customer push

See [PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md) for authenticated device registration, Firebase server credentials, queue deployment and live-delivery verification. Push is disabled by default; the inbox works independently.

## Provider general-request discovery

`GET /api/provider/orders/general` returns pending general requests from **all
cities** when `city_id` is omitted or empty. A positive existing `city_id` filters
by the customer's current city; there is no implicit provider/store-city fallback.
`page` must be a positive integer. Pages contain up to 15 records ordered by
creation time descending, then ID descending for stable ties.

Provider general-list and general-detail responses include localized
`city: {id, name}` (or null). The city relations are eager loaded. The resource
does not expose customer contact information, and existing policies and own-offer
restrictions continue to apply. Customer and specific-order contracts are unchanged.

Regression checks: `php artisan test tests/Feature/ProviderOrderTest.php
tests/Feature/ProviderGeneralDiscoveryTest.php` (run as one command).
## Provider offer management

Provider offer list/create/update responses now use ProviderOrderOfferResource,
adding order_id, order_status, localized part_name, can_edit and can_withdraw.
GET /api/provider/orders/offers accepts positive page and optional order_id.
GET /api/provider/orders/offers/{offer} returns only the owning provider's offer,
including historical offers, without embedding customer data or winning offers.
Existing customer offer resources and routes are unchanged.

Create requires an active owned store and integer price in halalas (1 through
2147483647). Update supports clear_images=true, mutually exclusive with images.
Omitting images preserves them; supplying images replaces them. Multipart clients
use POST with _method=PUT on the existing update endpoint. Pending-state policies
and service transaction locks remain authoritative. Resubmission after withdrawal
restores the unique order/store offer with new data and images.

Migration 2026_10_15_120000_expand_order_offer_notes widens notes to TEXT to match
the existing 1,000-character rule. Its down method intentionally leaves the column
wide so rollback never truncates submitted notes. Apply this migration before app
rollout. Regression suites: ProviderOfferWorkflowTest, ProviderOrderTest and
ProviderOfferImagesTest.

## Provider direct requests and paid fulfillment

`GET /api/provider/orders/specific` supports optional `status` and positive `page`.
Omitting status preserves the existing all-status listing. Supported statuses are
pending, awaiting_payment, paid, completed, rejected and cancelled. The app starts
with pending. Both lists use created_at/id descending and 15 records per page.

`POST /api/provider/orders/{order}/accept` accepts a pending specific request at
its saved `requested_unit_price`. Send `expected_unit_price` (integer halalas) and
`expected_quantity` from the reviewed details. The service locks/reloads the order,
checks both values, active store, available inventory and positive bounded total,
then changes status to awaiting_payment. Retrying the same acceptance returns the
current accepted/paid/completed state without a duplicate effect. Reject also locks
and reloads the request, and repeating an already completed rejection is safe.
Acceptance does not charge or reserve/decrement stock. Legacy requests lacking a
valid price snapshot cannot be accepted. Existing customer cancellation policy is
unchanged: only pending orders without payment history are cancellable.

`GET /api/provider/orders/paid` defaults to paid; `status=completed` accesses
completed purchases. `GET /api/provider/orders/paid/{order}` reads one owned paid
or completed purchase. Existing authorization and soft-deleted inventory/store
visibility are preserved. No other provider's purchases or customer contact data
are exposed, and no provider receipt-confirmation endpoint is introduced.

ProviderManagedOrderResource supplies localized part/vehicle/city, store, quantity,
unit_price/total_amount, capability flags, private images and accepted_offer_id.
Existing direct response fields are retained. Currency is SAR and price_scale is
100. Paid totals use the recorded paid payment when available, falling back to the
existing order amount for older records. Commission/net_amount use the current
configured commission rate and are explicitly marked `commission_is_estimate`;
they are not payout records. No migration or payment gateway changes are required.

Regression coverage includes provider ownership and visibility, filters/pagination,
saved-price acceptance, changed quantity/stock/store guards, idempotence, stale
rejection, recorded payment amounts, and acceptance -> payment -> customer receipt.
Run ProviderManagedOrdersTest alongside existing provider and customer lifecycle
tests. Customer lifecycle regression expectations follow the existing rule in
commit 341b780 that disallows cancellation after acceptance.
