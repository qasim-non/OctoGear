<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

## Admin web session and bearer authentication — 2026-10-19

The Angular/PrimeNG/Sakai web project lives at
`C:/Tamkkun/OctoGearProject/OctoGear-admin`. Admin and shared media/logout routes
now use `auth:sanctum,admin`: the existing user Sanctum guard remains bound to
users, and Laravel falls back to the existing admin guard for Admin-owned bearer
tokens. Admin routes retain `admin.active`; mobile-only routes retain their
original guard and role checks. This fixes tokens issued by the existing admin
login being rejected by the users-only guard. Login/logout response contracts,
inactive/blocked rules, token expiry, and role policy behavior are unchanged.

New `GET /api/admin/profile` returns the authenticated administrator through
`AdminProfileResource` in the standard success envelope: `employee_id`, `name`,
`email`, `assigned_role`, `status`. It never returns password, mobile or token
fields. The response is `Cache-Control: no-store, private`. Missing/invalid auth
returns 401; customer/provider identity or blocked admin returns 403. No input,
migration, new account or permission rule is required.

The browser calls existing `POST /api/auth/admin/login` with `{email,password}`,
then this profile endpoint with the returned bearer token before entering the
admin shell. Existing `POST /api/auth/logout` revokes the current token. No admin
self-service password-reset route exists; the web explains contacting an
administrator rather than simulating email delivery.

Verification: `AdminSessionTest` passes 5 real-bearer tests / 21 assertions. The
full suite ran 551 tests / 4679 assertions, with 548 passing and 3 failures.
The two OTP rate-limit expectations and the general-order missing-quantity
assertion reproduce on unchanged HEAD `dc378658e22b7a8eaea8967232a9603cd95221a2`;
they were not changed by this session slice. Tests used isolated SQLite memory
databases. See the web project's `docs/LARAVEL_SESSION_STEP.md` for the request
flow and review guide. No retained data or production deployment was modified.


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


## Integration replacement and private cloud media

See [INTEGRATIONS.md](INTEGRATIONS.md) for the SMS/payment adapter boundaries, private S3 image configuration, existing-media behavior and remaining live-provider activation work. `SMS_DRIVER=log` and `PAYMENT_DRIVER=stub` are simulations.

## Edit provider offers from chat — 2026-10-19

User action: A provider in an existing customer conversation sees an Edit offer
action with the sole editable offer's request, part, store and total. Multiple
editable offers open a paginated chooser for that customer, across owned stores
and requests; the conversation's offer is identified and ordered first. The
existing offer editor changes price, notes and optional photos and returns to chat.

Roles/permissions: Provider-only, participant-authorized lookup; the server derives
the customer from the conversation, never a supplied customer ID. Only the
provider's pending offers on pending requests are selectable. Existing locked
update policies and transactions remain authoritative if selection races editing.
Accepted/paid orders and customer-owned request fields retain their current rules.

API: GET /provider/conversations/7/offers?page=1 returns editable offers scoped
to an authorized conversation. Older servers return unavailable instead of silently
ignoring a customer filter. It returns the existing
ProviderOrderOfferResource envelope, e.g. data: [{id:11, order_id:1,
order_status:"pending", store:{id:1,name:"Parts"}, price:12345,
status:"pending", can_edit:true, can_withdraw:true, ...}], with existing meta.
Only editable offers for that conversation's customer are returned. The existing
/provider/orders/offers list and order_id behavior remain unchanged.
Existing multipart POST + _method=PUT saves the offer. No schema/backfill,
new payment transition, automatic message, notification or write retry is added.

Loading/empty/error/offline: Compact loading state; no-editable-offers explanation;
explicit refresh/retry. Pagination retains loaded rows after failure. Account and
locale changes discard stale results. No polling. Editor entry reloads the selected
offer; returning refreshes the chooser and existing offer caches are invalidated.

Navigation: A typed provider Chats child editor uses orderId/offerId only; pushing
and popping preserves the conversation and unsent message. No conversation is
created by browsing offers. A provider-order preview exposes the shortcut after
its first explicit message creates a conversation.

Locale/RTL: Arabic and English, directional layout, scrollable chooser and editor,
large text and keyboard-safe chat; use existing theme, money and error helpers.
Tests: scope/ownership, legacy/direct/offer chats, pagination/validation, locked
and deleted records, single/multiple/empty/error states, stale session/locale and
pagination responses, edit-return/draft retention, Arabic/English large text.

Verification (2026-10-19): the full Flutter suite passed 506 tests. The two added
real-provider-shell integration tests also pass, including Chats branch retention,
back navigation, unsent drafts and keyboard layouts in both locales at 200% text.
After correcting the provider empty-chat copy, all 28 affected chat/offer tests
passed again. Arabic/English single-offer and chooser renders were inspected.
Flutter analysis and custom lint reported no issues; generated routing was rebuilt
with build_runner. Tests use fake repositories; no real customer message or offer
was changed. No APK/device or remote deployment verification was performed.

API checks: the existing offer/chat subset passed 36 tests (348 assertions), and
the new scoped-lookup suite passed 5 tests (41 assertions). Targeted Pint and diff
checks passed. The complete suite ran 556 tests / 4,347 assertions: 551 passed,
three failed and two errored. The failures are two AuthRateLimitTest expectations
and the GeneralOrderDetailsTest paid quantity-field expectation; errors are demo
image copies in GeneralOrderDetailsTest and ProviderDemoSeederTest. Loading the
committed controller/policy in a separate in-memory test process reproduced all
three assertion failures and the ProviderDemoSeederTest image-copy error; the
other seed case passed that focused rerun. Auth, payment, image storage and seed
behavior were not changed. Existing unrelated admin edits were preserved.

Rollout: serve the new API route with the updated Flutter build. No migration or
worker change is required. Restart Flutter after rebuilding to load the typed
route; hot reload alone may retain the old router. An older backend leaves the
chat lookup unavailable with explicit retry rather than showing unscoped offers.

## One conversation per customer/provider — 2026-10-20

All direct, offer and provider-order entry points reuse the customer/provider
pair, across stores and requests. GET previews remain read-only. The existing
offer/order fields retain the originating reference, not the chat's identity.
Existing authorization, eligibility, retry receipts, notifications and editor
lookup remain in force. Pair creation and sends lock participants in ID order;
a database unique pair constraint also protects against concurrent API callers.
Soft-deleted pairs are restored on an authorized creation rather than duplicated.

The additive pair-identity migration adds database uniqueness only when no
duplicate pairs exist. Otherwise it stops without modifying conversations or
messages. Consolidating existing histories is a separate operation requiring
explicit approval. No reset, history merge or live migration is part of this step.

Checks cover both roles and entry directions, multiple offers/orders/stores,
participant isolation, soft deletion, database uniqueness, non-destructive duplicate detection,
retry replay and isolation. Tests use isolated SQLite.

Verification (2026-10-20): 59 focused chat tests passed (586 assertions), Pint and
whitespace checks passed, and isolated seed verification passed with 75 chats and
344 messages. The full suite ran 560 tests / 4,757 assertions: 557 passed and the
three previously documented failures remain (two AuthRateLimitTest expectations
and GeneralOrderDetailsTest's quantity-field expectation). No retained database
migration was run. A read-only audit found 12 duplicate participant pairs with
24 conversations and 90 messages; consolidating those histories and applying
the unique constraint remain pending explicit approval. Existing unrelated admin
service/test changes were preserved.

## Admin user city filtering — 2026-10-20

GET /api/admin/users, /api/admin/users/customers and /api/admin/users/providers
accept optional city_id (positive integer, existing non-deleted city). Omitted,
null or empty retains all cities. City combines with existing name/mobile/status/
type filters before the fixed 25-row pagination; route type enforcement remains.
Invalid, unknown and deleted IDs return localized 422 validation errors.
The Angular admin lazily reuses GET /api/reference/cities for searchable options.
No new endpoint, response field, permission rule or migration is needed.
See the admin project's docs/CITY_FILTER.md and tests/Feature/AdminCityFilterTest.php.

### Complete admin city selector — 2026-10-20

GET /api/admin/cities/options returns the complete non-deleted city catalog in
the standard success/data envelope: each item contains only id, name_en, name_ar.
It is protected by auth:sanctum,admin and admin.active, ordered by name_en/id,
and has no pagination or search parameters. Cache-Control is no-store, private;
the admin app retains the reference data in memory for local bilingual search.
CityController shares its existing catalog query between this selector and the
unchanged public paginated /reference/cities endpoint. No migration or retained
data mutation. This supersedes the web's earlier paginated city-search design.
AdminCityOptionsTest covers full catalogs over 50 rows, fields, deleted cities,
ordering, both locales, empty data and guest/customer/provider/blocked-admin denial.

Web refresh now revalidates its tab-scoped saved bearer using the existing
admin/profile endpoint. Laravel login, token expiry, guards and logout are unchanged.

Verification: city options/filter/reference/admin-session subset passes 22 tests
with 238 assertions; targeted Pint and PHP syntax pass. Full suite: 567 tests,
4,573 assertions, 562 passing, three failures (two existing OTP-limit expectations
and the general-order quantity expectation) and two demo image write/copy errors
in GeneralOrderDetailsTest and ProviderDemoSeederTest. These match previously
documented problem areas; their implementations were not changed. Tests use
isolated SQLite memory databases, not retained application data.

## Admin provider stores — 2026-10-20

GET /api/admin/stores accepts optional owner_id: a positive integer referencing
an existing non-deleted user. It combines with name/mobile/status before the
existing 15-store pagination and newest-first ordering. Omitted/null/empty owner
preserves the all-store view. Invalid or missing owners return localized 422
errors. This general owner filter does not add a provider-type restriction.

StoreIndexRequest validates the new input; the unchanged AdminStoreController
passes it to AdminStoreService, which applies stores.user_id. Existing admin
middleware, eager-loaded owner/city, counts, resource fields and store status
behavior are unchanged. No new route, migration or worker change is required.

The Angular provider detail loads owned stores only on Show stores, rejects rows
with another owner, and offers pagination/refresh/retry. Provider user lists keep
25-row pages. Account block/unblock does not alter store activation. Read the web
project's docs/PROVIDERS_STEP.md for the full incremental review walkthrough.
Ship this filter with the new web screen; no production deployment was performed.

Verification: AdminProviderStoresTest and existing store/user/city/session checks
passed 35 tests / 213 assertions; targeted Pint and PHP syntax passed. Full suite:
573 tests / 5,002 assertions, 569 passing, three failures (the documented two
AuthRateLimitTest expectations and general-order quantity-field expectation) and
one demo image-write error in GeneralOrderDetailsTest. These unrelated areas were
not changed. Tests used isolated SQLite memory databases. The web production build,
typecheck and all 37 mocked browser tests passed, including provider behavior and
existing customer/authentication regressions.

### 2026-10-21: admin store request review page

The Angular admin now has `/store-requests` and `/store-requests/:id` for initial
applications, additional stores and proposed store changes. The existing API
routes remain unchanged. GET `/api/admin/store-requests` additionally accepts:

- `search`: optional string, maximum 100 characters; matches store/applicant name
  or mobile. Search conditions are grouped with the other filters.
- `request_type`: `new_store` or `store_change`, based on whether store_id exists.
- `city_id`: optional positive ID of a non-deleted city.
- `page`: positive integer. Existing 15-row pagination is retained; timestamp ties
  now use descending ID for deterministic page boundaries.

The existing optional status filter and unfiltered API default are preserved.
The web selects pending by default. City choices reuse its existing lazy catalog;
typing in that chooser does not issue a search request to Laravel.

AdminStoreRequestService prepares detail relationships and reference labels.
Detail and decision responses add `review_companies: [{id, name}]` and
`previous_city: {id, name} | null`. Names follow Accept-Language; historical
soft-deleted references remain resolvable. Serialization performs no label queries.
The list omits these detail-only additions. A missing loaded city/user is null.

Accept/reject continue through the existing service locks, transactions,
pending/version checks, store creation/change application and inbox notifications.
No business-rule transition, route, schema or worker configuration was changed.
The web confirms decisions, requires a rejection reason, and reads fresh details
after success or uncertainty. Protected images use existing authenticated media
routes on demand, with tokens in headers and no public storage paths.

Review the web project's `docs/STORE_REQUESTS_STEP.md` for the incremental file
walkthrough and UI limits. Deploy these additive API fields/filters before or with
the web page. No production deployment or retained-account mutation occurred.

Verification: the focused review/onboarding/store-change/media set passed
50 tests / 389 assertions. Changed PHP syntax and targeted Pint passed. The full
isolated SQLite suite ran 579 tests / 5,059 assertions: 576 passed, with the same
three documented failures in the two OTP-rate-limit expectations and the
general-order quantity-field expectation. No errors occurred on this run; the
previous image-write error did not recur. The full suite remains non-green.
Angular typecheck/build and all 45 mocked browser tests passed; desktop and Arabic
mobile request-review layouts were inspected. No live authenticated review was
performed against retained records.

### 2026-10-21: admin stores directory and details

The Angular admin now has `/stores` and `/stores/:id`. Existing GET
`/api/admin/stores` accepts optional `city_id` (positive, non-deleted city) and
positive `page` validation, alongside name/mobile/status/owner_id. Filters combine
before the existing 15-row pagination. Name/mobile searches accept the literal
term `0`; descending ID breaks equal creation timestamps deterministically.

AdminStoreService prepares the ratings average for list/detail responses rather
than querying ratings once per serialized row. AdminStoreResource preserves the
float-or-null average and existing fields, adding employee_name, url_location, and
commercial_registration_number. Detail and status responses also include loaded
`companies: [{id, name}]`, `pictures` using the existing protected media resource,
and `pending_change_request_id: integer | null`. Company/city names follow the
request locale. Lists omit these detail-only relations; deleted owner/city
relations serialize as null. Storage paths and disks remain private.

The unchanged controller/status request/service operation and admin middleware
remain authoritative. Activation changes only the store's status, preserving its
owner's account, existing orders, and pending store-change proposal. No routes,
migrations, approval rules, or worker settings were added or changed. Deploy these
additive detail fields before or together with the web page.

The web reuses StoreApi for provider-owned stores and the new general directory,
the lazy local city catalog, and authenticated on-demand media. Confirmed writes
are followed by fresh detail reads; uncertain responses require refresh before
retry. Read the web project's `docs/STORES_STEP.md` for the review order and limits.
`tests/Feature/AdminStoreDirectoryTest.php` covers the added contract, filters,
stable pagination, serialization, authorization, and status isolation. Verification
results are recorded in the web project's `PROJECT_DOCUMENTATION.md`.

## Account language preference — 2026-10-21

Customers and providers have a nullable `users.preferred_language` (`ar` or `en`).
Null means the account has not selected a language; existing accounts are not
assigned a guessed language. Registration accepts optional `preferred_language`,
falling back to an exact supported `Accept-Language` header. Ordinary requests
and sign-ins never overwrite an existing account preference.

Authenticated `PATCH /api/profile/language` accepts
`{"preferred_language":"ar"}` and returns that saved field in the standard
success envelope. The route changes only the current active customer's/provider's
account, validates the two supported values, and preserves unrelated profile data.
`GET /api/profile` and profile-update responses include `preferred_language`.
Flutter restores a saved account language after session validation; accounts with
no preference save their current app language once. Explicit language changes
update the UI immediately and save through this endpoint; failed saves offer Retry.
Push registrations retain their existing per-device locale behavior.

Deploy the additive `2026_10_21_120000_add_preferred_language_to_users_table.php`
migration before the API/mobile update. No database reset or preference backfill.

## Administrator language and Orders web review — 2026-10-21

Administrators use nullable `admin.preferred_language` with the shared
PreferredLanguage enum (`ar` / `en`). Admin login accepts optional
`preferred_language`; after credential/status checks it initializes only a null
preference. Existing choices win across browsers, and older login clients may
omit it. GET `/api/admin/profile` includes the preference and never writes it.
PATCH `/api/admin/profile/language` validates and updates only the authenticated
admin, returning the saved value with private/no-store caching. Existing admin
middleware remains authoritative. Accept-Language still controls response locale
without changing database preferences. The shared request/service also retains
its customer/provider behavior.

Deploy `2026_10_21_130000_add_preferred_language_to_admin_table.php` before the
updated admin API/web. Only this explicit additive migration was applied to the
verified local MySQL database; no resets, seeders or preference backfill were run.
Existing accounts choose a language on their next successful login or explicit
signed-in change. No retained administrator account was modified for testing.

Existing GET `/api/admin/orders` adds optional positive `order_id`, `city_id`,
and `page`, plus `date_from` / `date_to` in YYYY-MM-DD format. City means the
customer's current city. Date bounds include both selected UTC calendar days;
invalid ranges are rejected. Filters combine before the existing 15-row
pagination, with descending ID resolving timestamp ties. Name/mobile searches
accept `0`. Detail/list fields add `currency: SAR`, `price_scale: 100`, specific
requested_unit_price, localized cities and linked inventory part/vehicle labels.
The service prepares relationships before resource serialization. Existing
pricing rules, lifecycle operations, routes and permissions remain unchanged.

The web Orders pages are read-only: request, vehicle, customer, store, offers,
private photos and the existing payment record. No fabricated commission or
complete payment-history claim. Read the web project's `docs/ORDERS_STEP.md`
and `docs/LANGUAGE_PREFERENCE_STEP.md` for the incremental code review. Exact
verification results and known baseline failures are in its PROJECT_DOCUMENTATION.md.

## Admin payment directory — 2026-10-21

GET `/api/admin/payments` is a read-only 25-row directory protected by existing
admin middleware and PaymentPolicy::viewAdminDirectory. Standard pagination,
private/no-store headers and localized 422 errors are preserved. It accepts
`search` (customer name/phone or fulfillment-store name), positive `order_id`,
`city_id`, `page`, payment `status` (pending/paid/failed/refunded), `method`
(cash/credit_card), order `type` (general/specific), and `date_from`/`date_to`
(YYYY-MM-DD). Filters combine; dates include both selected UTC calendar days
based on payment.created_at, with timestamp/ID descending ordering.

AdminPaymentQueryService prepares the required relationships, retaining
soft-deleted payments/order/inventory/store context only for this admin query.
Rows are separate payment records, not one aggregate per order. `archived`
identifies retained soft-deleted payments. AdminPaymentResource extends the
existing payment fields with currency SAR, price_scale 100, updated_at and a
minimal order/customer/store/part context. `order.available` prevents web links
to soft-deleted orders. Labels use current related records, not invented payment
snapshots. Resource serialization performs no queries; missing context is null.

Recorded payment.amount is authoritative. Pending/failed amounts are not revenue;
this endpoint does not calculate commission or payout totals, invoke a gateway,
or change charging/retry/refund eligibility. Existing customer payment policy
methods and payment lifecycle APIs remain unchanged. No migration, data mutation,
worker change or gateway activation is needed. Deploy this read endpoint before
or with the web page; no production deployment was performed.

Review `tests/Feature/AdminPaymentDirectoryTest.php` and the web project's
`docs/PAYMENTS_STEP.md`. Its PROJECT_DOCUMENTATION.md records actual verification
results and any remaining baseline failures.

## Admin vehicle catalog — 2026-10-21

The web Companies and Car models wireframes now use three read-only endpoints
under existing `auth:sanctum,admin` and `admin.active` middleware:

- GET `/api/admin/car-companies`: optional `search` (maximum 100 characters),
  positive `page`; 25 records containing id, localized name, country and
  models_count (current CarName count).
- GET `/api/admin/car-names`: the same search/page contract plus optional
  `company_id` referencing an existing non-deleted company. Rows contain id,
  localized name and company with its country.
- GET `/api/admin/car-companies/options`: the complete small company selector,
  containing only id, name_en and name_ar. No pagination or search is needed;
  the web loads it lazily and searches locally.

VehicleCatalogPolicy follows current allowed-admin access; sub-admin role/rule
changes are deferred. AdminVehicleCatalogController delegates to the injected
AdminVehicleCatalogQueryService. Search matches both language columns, accepts
`0`, and combines with company scope before stable name_en/id pagination.
Removed companies/names are excluded; models with removed parents are excluded.
Country relations and counts are prepared before resources serialize, with no
per-row queries. Missing/deleted countries return null. All three endpoints use
private/no-store headers and the standard success/validation envelopes.

Car models in this web wireframe means CarName (`cars_names`), such as Camry,
not the separate CarModel (`models`) variant/year catalog. Country means the
manufacturer's country of origin, not the assembly location. Existing public
reference endpoints, catalog records, soft-delete rules and seeders are unchanged.
No write routes, migrations, seed runs, worker changes or deployment are included.

Review `tests/Feature/AdminVehicleCatalogTest.php` and the web project's
`docs/VEHICLE_CATALOG_STEP.md`. Verification results are recorded in that project's
PROJECT_DOCUMENTATION.md. Deploy these read endpoints before or with the web
pages; the existing frontend API host setting remains the only URL setting.
