<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

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
