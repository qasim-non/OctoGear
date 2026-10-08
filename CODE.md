# OctoGear (YARDY) - API Code Guide

## Customer notification inbox addition — 2026-10-06

Shared authenticated routes now include `GET /api/notifications/inbox` (optional
boolean `unread` and validated opaque `cursor`) and
`GET /api/notifications/unread-count`. The inbox returns
`data: {items, unread_count, next_cursor}` with up to 20 NotificationResource rows,
ordered by descending created_at and UUID. Cursor paging remains stable under new
arrivals and read actions. Queries use the authenticated user's morph relation.
The read policy checks model type as well as user ID; existing single/all read
endpoints and legacy `GET /api/notifications` remain compatible.

No migration or new dependency is needed. Deploy these endpoints before the
Flutter notification inbox. Existing new-offer and provider-message events feed
the customer inbox. Payment/completion events still notify providers only. This
does not introduce FCM, push permissions or token registration. Feature coverage
is in `NotificationInboxTest` alongside the existing `SharedFeaturesTest`.

> Latest verification (2026-10-02): **373 passed / 2,239 assertions** after
> the request gallery, offer-selection, and whole-request payment updates. Historical sections describe their
> respective implementation slices.

## Project Overview

- **Framework:** Laravel 12 (PHP 8.2+)
- **Auth:** Laravel Sanctum (token-based personal access tokens)
- **Database:** MySQL (production), SQLite `:memory:` (testing)
- **Purpose:** Car-parts marketplace connecting customers with service-provider stores.
  Customers request parts, stores bid with offers, customer accepts an offer, pays,
  and the store delivers. Conversations, ratings, notifications, and store requests
  (store onboarding) round out the platform.
- **Localization:** `ar` / `en` via the `Accept-Language` header (`app.locale` default).
  All user-facing messages are localization keys (`auth.*`, `auth.validation.*`,
  `auth.middleware.*`).
- **Response envelope:** every JSON response uses `{ "success": bool, "message": ...,
  "data": ..., "meta": ... }` via the `ApiResponse` trait.

---

## Customer-car transmission contract (2026-10-01)

This API-only slice adds optional `transmission_type` to customer cars. Accepted
values are `automatic`, `manual`, `unknown`, or null (not recorded). Create may
omit it; update omission preserves the existing value, explicit null clears it.
Create, update, list and detail responses return the same nullable machine value
regardless of locale. Validation messages are Arabic/English. No new endpoint,
repository, permission or controller business logic is needed: the existing
owner-authorized controller delegates validated data to CustomerCarService.

Use an additive nullable migration with no guessed backfill. The PHP backed enum
and request validation constrain new values. Idempotency includes non-null
transmission values; omitted/null values retain the previous fingerprint format
so older clients and in-flight retries remain compatible. Provider cars, orders,
payment, SMS and Flutter are outside this slice. Verify create/read/update,
invalid values, omission/null/unknown semantics, ownership, idempotent replay and
conflict, migration preservation/rollback, then the full Laravel suite.

Verification: all eight transmission tests passed (156 assertions), the full
Laravel suite passed (348 tests / 1,590 assertions), and targeted Pint checks
passed. Migration down/up preservation was exercised only in isolated SQLite;
the additive migration was applied to the local MySQL database without a reset.

## General request details: API-only contract

General creation uses POST /api/customer/orders with a required Idempotency-Key.
Exactly one vehicle source: customer_car_id or vehicle containing car_name_id,
manufacturing_year, transmission_type, color_id and fuel_type. All manual fields
are required. Saved cars must have available color/fuel references; otherwise
return a localized 422 asking the owner to update the garage car. Manual details
may set save_to_my_cars=true. Garage saves include color/fuel. Plate numbers have
been removed from customer and provider cars across schema and API; customer-car
photo support remains intact.

Exactly one part source: component_id or component_name. The latter is custom
text in any language, stored once. Catalog names come from the component relation
with soft-deleted history included; renames appear on earlier orders. Referenced
catalog components cannot be hard deleted. No bilingual part-name copies are
stored on orders. Load component alongside vehicleDetails for order responses.
Description is optional; general quantity, model_id, notes and store inventory
IDs are prohibited input. An internal quantity of one remains for schema
compatibility only; it does not multiply general offer or payment totals.

order_vehicle_details stores the submitted vehicle, year, transmission and
bilingual color/fuel labels. Reference IDs may become null after hard deletion;
labels remain unchanged. The optional garage-car link is private to its owner
and admins. Vehicle and part references resolve after idempotent replay, inside
the creation transaction. Retries cannot duplicate cars, orders, files or events.
Color and fuel participate in the general submission fingerprint.

Order creation accepts optional images[] uploads for both request types. Use the
shared ImageRules and ImageStorageService limits; persist ordered metadata in
order_images and load images alongside order relations in every order response.
Image content and order participate in idempotency. Protected media URLs verify
both the parent order and viewer access. Soft deletion retains files; instance
force deletion records cleanup jobs atomically and deletes files after commit.
Order scalar image columns and the old single-image route have been removed.

Existing development data is disposable, as explicitly agreed with the user.
The affected migrations define the desired fresh schema, with no legacy-model
conversion, data-preservation loops or rollback restoration. Rebuild development
with migrate:fresh and DemoDataSeeder rather than adding compatibility migrations.
Keep updates scoped and complete across schema, validation, services, resources,
seeders and tests. Do not reset any non-development database.

See README.md for payloads, response fields and rebuild instructions. This slice
contains no Flutter or SMS changes. Verify manual and saved vehicles,
catalog/custom parts, localization, ownership, retry conflicts, rollback, order
images, offer acceptance, general totals, fresh schema and demo seeding, then the
complete Laravel suite.

Verification (2026-10-02): the prior request/vehicle slice passed 366 tests. The
request-image and offer/payment changes passed 373 tests / 2,239 assertions,
targeted Pint, isolated SQLite seeder verification, and a fresh local MySQL
migration/seed run. General offers lock one selected total, record competitors as
not selected, and charge that total once.

## Architecture Rules (governing conventions)

These rules steer all development in this codebase:

1. **Never duplicate shared services.** Business logic lives in services; reuse them,
   do not copy/paste logic into controllers or other services.
2. **Design policy-first.** Use authorization policies for authorization — not service
   layer checks and not ad-hoc controller `if`s. Policies are registered centrally in
   `AppServiceProvider::configurePolicies()`.
3. **Resources never resolve services via `app()`.** Resources are lightweight
   transformers. (Pre-existing `app()->getLocale()` locale lookups in resources are the
   accepted exception.)
4. **Domain exceptions handled centrally.** Business rule violations throw
   `BusinessRuleException`; `bootstrap/app.php` renders them with the success/message
   envelope. No broad `try/catch` around business flows.
5. **External payment calls are NOT DB-rollbackable.** `PaymentService` wraps only the
   local payment record; a failed external gateway call does not roll back order state
   (see Payments).
6. **Use focused repositories for shared or substantial queries.** Reuse Eloquent
   scopes for small composable filters. Put repeated retrieval, pagination, or a
   substantial single-use query in a feature repository when it makes the service
   clearer. Avoid generic CRUD wrappers and keep business decisions in services.
7. **Controllers stay thin.** Controllers authorize → validate (Form Request) → delegate
   to a service → respond via `ApiResponse`. No business logic in controllers.
8. **Enums everywhere, magic strings nowhere.** Every DB enum column maps to a backed
   enum in `app/Enums`; statuses and types are compared using enum cases.

---

## Project Structure

```
OctoGear-api/
├─ app/
│  ├─ Enums/                    # PHP 8.2 backed enums (one per DB enum column)
│  │  ├─ UserType.php           # customer | service provider
│  │  ├─ UserStatus.php         # unblocked | blocked
│  │  ├─ OrderType.php          # general | specific
│  │  ├─ OrderStatus.php        # pending | rejected | awaiting_payment | paid | completed | cancelled
│  │  ├─ PaymentMethod.php      # cash | credit_card
│  │  ├─ PaymentStatus.php      # pending | paid | failed | refunded
│  │  ├─ StoreStatus.php        # active | inactive
│  │  ├─ AdminStatus.php        # active | inactive | blocked
│  │  ├─ AdminRole.php          # admin | manager | employee | hr | developer
│  │  ├─ RequestStatus.php      # pending | accepted | rejected
│  │  ├─ SectionCondition.php   # okay | damaged
│  │  └─ DevicePlatform.php     # ios | android
│  │
│  ├─ Exceptions/
│  │  └─ BusinessRuleException.php   # domain rule violations (default status 400)
│  │
│  ├─ Http/
│  │  ├─ Controllers/
│  │  │  ├─ Controller.php                 # base; uses ApiResponse trait
│  │  │  ├─ Auth/AuthController.php        # thin; delegates to AuthService (sendOtp/verifyOtp/register/adminLogin)
│  │  │  ├─ Api/
│  │  │  │  ├─ CmsController.php           # /cms/{type}
│  │  │  │  ├─ OrderOfferController.php    # customer view/reject offers
│  │  │  │  ├─ Customer/
│  │  │  │  │  ├─ CustomerCarController.php        # customer's saved cars CRUD
│  │  │  │  │  ├─ CustomerOrderController.php      # order lifecycle (store/accept/pay/etc.)
│  │  │  │  │  └─ ProfileController.php            # customer profile show/update
│  │  │  │  ├─ StoreController.php                 # shared marketplace browse — thin wiring over StorefrontQueryService
│  │  │  │  ├─ Provider/
│  │  │  │  │  ├─ ProviderOrderController.php      # general/specific/offers/paid + CRUD/offer/reject
│  │  │  │  │  ├─ ProviderProfileController.php    # provider profile show/update
│  │  │  │  │  ├─ ProviderStoreController.php      # provider store index (my stores) + update
│  │  │  │  │  ├─ ProviderStoreCarController.php   # provider cars create/update/destroy (StoreCarService)
│  │  │  │  │  ├─ ProviderStoreCarComponentController.php  # components create (incl. batch)/update/destroy
│  │  │  │  │  └─ ProviderStoreRequestController.php       # store requests + mobile OTP
│  │  │  │  ├─ Shared/
│  │  │  │  │  ├─ ConversationController.php
│  │  │  │  │  ├─ NotificationController.php
│  │  │  │  │  └─ RatingController.php
│  │  │  │  └─ Reference/
│  │  │  │     ├─ CarNameController.php
│  │  │  │     ├─ CarSectionController.php
│  │  │  │     ├─ CityController.php
│  │  │  │     ├─ ColorController.php
│  │  │  │     ├─ CompanyController.php
│  │  │  │     └─ FuelTypeController.php
│  │  │  │
│  │  │  ├─ Middleware/
│  │  │  │  ├─ Authenticate.php             # JSON 401 (no redirect) — alias `auth`
│  │  │  │  ├─ SetLocale.php                # `locale` — Accept-Language → ar/en
│  │  │  │  ├─ EnsureUserIsActive.php       # `user.active` — blocks blocked users (403)
│  │  │  │  ├─ EnsureAdminIsActive.php      # `admin.active` — blocks blocked admins
│  │  │  │  ├─ EnsureIsCustomer.php         # `customer` — type === Customer
│  │  │  │  ├─ EnsureIsProvider.php         # `provider` — type === ServiceProvider
│  │  │  │  └─ EnsureIsCustomerOrProvider.php # `auth.provider` — customer or provider
│  │  │  │
│  │  │  ├─ Requests/                       # Form Request validation (extends BaseRequest)
│  │  │  │  ├─ BaseRequest.php              # authorize()=true; failedValidation → 422 envelope
│  │  │  │  ├─ Auth/ (SendOtpRequest, VerifyOtpRequest, RegisterRequest, AdminLoginRequest)
│  │  │  │  ├─ Api/OrderOffer/ (RejectOfferRequest)
│  │  │  │  ├─ Customer/  Provider/  Shared/
│  │  │  │  └─ ...
│  │  │  │
│  │  │  └─ Resources/                      # API Resource transformers
│  │  │     ├─ CmsResource  ComponentCarResource  ConversationResource
│  │  │     ├─ CustomerCarResource  MessageResource  NotificationResource
│  │  │     ├─ OrderOfferResource  OrderResource  PaymentResource
│  │  │     ├─ ProviderPaidOrderResource  RatingResource  ReferenceResource
│  │  │     ├─ StoreCarComponentResource  StoreCarResource  StoreRequestResource
│  │  │     └─ StoreResource  UserResource
│  │  │
│  │  └─ Traits/
│  │     └─ ApiResponse.php                 # success/created/error/notFound/forbidden/unauthorized/paginated
│  │
│  ├─ Models/                  # Eloquent models (relationships/fillable/casts/soft deletes)
│  │  ├─ User  Store  StoresCar  StoreCarComponent
│  │  ├─ StoreSection  StoreRequest
│  │  ├─ Car  CarName  CarModel  CarCompany
│  │  ├─ Order  OrderOffer  Payment
│  │  ├─ CustomerCar  Conversation  Message  Rating
│  │  ├─ Admin  Cms  DeviceToken
│  │  └─ ...
│  │
│  ├─ Notifications/
│  │  ├─ NewMessageNotification  NewOfferNotification  NewOrderNotification
│  │  ├─ OrderCompletedNotification  OrderPaidNotification
│  │  └─ (device/push + database rows)
│  │
│  ├─ Policies/                # 12 authorization policies (see AppServiceProvider)
│  │  ├─ OrderPolicy  OrderOfferPolicy  PaymentPolicy
│  │  ├─ StorePolicy  StoresCarPolicy  StoreCarComponentPolicy  StoreRequestPolicy
│  │  ├─ CustomerCarPolicy  RatingPolicy  ConversationPolicy  MessagePolicy
│  │  └─ NotificationPolicy
│  │
│  ├─ Providers/
│  │  ├─ AppServiceProvider.php    # policies + rate limiters (api/customerLogin/adminLogin)
│  │  ├─ EventServiceProvider.php  # event → listener mappings (all sync)
│  │  └─ LoggingServiceProvider.php
│  │
│  ├─ Services/
│  │  ├─ AuthService.php           # OTP verification/registration choices + admin login
│  │  ├─ OrderService.php          # order lifecycle business rules
│  │  ├─ OrderOfferService.php     # offer CRUD + rejection rules
│  │  ├─ PaymentService.php        # amount/commission/gateway
│  │  ├─ CustomerCarService.php    # customer car ownership logic
│  │  ├─ StoreCarService.php       # provider store-car logic
│  │  ├─ StoreRequestService.php   # store onboarding + mobile OTP
│  │  ├─ OtpService.php            # simple OTP send/verify (no rate limit in service)
│  │  ├─ SoldQuantityService.php   # sold-quantity tracking on components
│  │  └─ StorefrontQueryService.php # all marketplace reads (stores/cars/components/search)
│  │
│  ├─ Support/
│  │  └─ MobileNumber.php          # Saudi mobile → E.164 normalization (+9665XXXXXXXX)
│  │
│  ├─ Events/                  # all sync, non-broadcast, do NOT implement ShouldBroadcast
│  │  ├─ MessageSent  OfferCreated  OrderCompleted  OrderCreated  OrderPaid
│  │  └─ ...
│  │
│  └─ Listeners/               # all sync, do NOT implement ShouldQueue
│     ├─ NotifyConversationParticipant  NotifyCustomerOfOffer
│     ├─ NotifyProviderOfCompletion  NotifyProviderOfPayment  NotifyStoresOfNewOrder
│     └─ ...
│
├─ bootstrap/
│  └─ app.php                  # middleware aliases, throttleApi, central exception renderers
├─ config/
│  └─ payments.php             # driver (default "stub") + commission_rate (5%)
├─ database/
│  ├─ migrations/              # ordered schema (see DB Rules)
│  ├─ factories/  seeders/     # DeveloperSeeder, CmsSeeder, reference seeders, etc.
├─ routes/
│  ├─ api.php                  # the API surface (no admin routes beyond admin login)
│  ├─ web.php  console.php
├─ tests/
│  ├─ Feature/                 # endpoint-level tests
│  ├─ Unit/                    # incl. OrderServiceTest (9 tests)
│  └─ TestCase.php             # RefreshDatabase, SQLite :memory:
└─ composer.json  phpunit.xml  README.md  CODE.md
```

---

## Response & Exception Conventions

### ApiResponse trait (`app/Http/Traits/ApiResponse.php`)

Every controller delegates its response to these helpers:

| Method        | HTTP | Notes                                                |
| ------------- | ---- | ---------------------------------------------------- |
| `success`     | 200  | `{ success: true, message/, data }`                  |
| `created`     | 201  | Resource created                                     |
| `error`       | 400  | **default** error status (message + errors)          |
| `notFound`    | 404  |                                                      |
| `forbidden`   | 403  |                                                      |
| `unauthorized`| 401  |                                                      |
| `paginated`   | 200  | builds `data` + `meta` (pagination)                  |

### BaseRequest (`app/Http/Requests/BaseRequest.php`)

- `authorize()` always returns `true` (authorization delegated to policies via
  `$this->authorize()` in controllers).
- `failedValidation` throws an HTTP 422 with:
  `{ success: false, message: auth.general.validation_failed, errors: {...} }`.

### BusinessRuleException (`app/Exceptions/BusinessRuleException.php`)

- **Default `statusCode` is 400** (aligned with `ApiResponse::error()`, which defaults
  to 400).
- Carries: `message`, `messageKey`, `messageParams`, `statusCode`.
- Renderer set per-status where the rule needs a non-400 code (e.g. `StoreRequestService`
  throws OTP/register errors with explicit **422**).

### Central renderers (`bootstrap/app.php`)

- `AuthenticationException` → 401 `{ success: false, message: auth.general.unauthenticated }`.
- `BusinessRuleException` → `statusCode()` with `{ success: false, message }`, where the
  message is `__($messageKey, $params)` when a key is present, else the raw message.

### Middleware aliases (`bootstrap/app.php`)

```
'auth'          => Authenticate::class          (JSON 401)
'locale'        => SetLocale::class             (Accept-Language → ar/en)
'user.active'   => EnsureUserIsActive::class    (blocked users → 403)
'admin.active'  => EnsureAdminIsActive::class
'customer'      => EnsureIsCustomer::class
'provider'      => EnsureIsProvider::class
'auth.provider' => EnsureIsCustomerOrProvider::class
```

`$middleware->throttleApi()` applies the global `api` limiter (30/min per IP).

### Rate limiters (`AppServiceProvider::configureRateLimiting()`)

- `api`: 30 req/min by IP.
- `customerLogin`: 3 req/min per `mobile` AND 3 req/min per IP (applied to OTP send/verify).
- `adminLogin`: 5 req/min per `email` AND 5 req/min per IP.

---

## Services (business logic)

All services are constructor-injected into controllers. They encapsulate domain rules
and throw `BusinessRuleException` with localization keys under `auth.validation.order.*`
(or `auth.*`).

### AuthService
OTP-based login/registration and admin login decisions. `verifyOtp` distinguishes
existing users (returns `token`, `is_new: false`, and the user's **`type`**
`customer`/`service provider` so the frontend picks the right interface) from new
mobiles (returns `temp_token`, `is_new: true`, `type: null`). `register` consumes the
pending registration token and creates the user via its own `createUser`
(full_name/mobile/city/type/status aggregation — a user-domain concern, not an OTP
concern); `adminLogin` checks credentials and blocked status and issues a scoped
`['admin']` token. Failures throw `BusinessRuleException`:
`auth.otp.rate_limited` (422), `auth.register.token_invalid` (422),
`auth.login.invalid` (401), `auth.admin.blocked` (403). `AuthController` delegates
without business logic.

### OrderService
Order lifecycle transitions and payment/delegation logic. Throws localized rule errors:
`cannot_accept_offer`, `cannot_cancel`, `cannot_complete`, `cannot_reject_general`,
`cannot_reject_not_pending`, `already_offered`, `cannot_edit_offer`, `cannot_delete_offer`,
etc.

### OrderOfferService
Offer creation, update, deletion and rejection with state-machine guards
(`reject`, `cannot_*` keys listed above).

### PaymentService
- `amountFor(order)` uses the selected offer's price once for general requests;
  specific orders retain `offered_price` × `quantity`.
- `commissionFor(amount)` = 5% commission (`config/payments.commission_rate`).
- Gateway driver: `config/payments.driver` defaults to **"stub"**
  (`PAYMENT_DRIVER` env). Provides a `charge()` that is **not** DB-rollbackable.
- A failed gateway charge records a `PaymentStatus::Failed` audit row; **order state is
  unchanged** (no rollback of order status) — see Rules #5.

### StoreRequestService
Provider onboarding workflow. Two creation variants:
- `becomeProvider(...)` — verified flow: requires a `temp_token` (one-time), creates a
  `RequestStatus::Pending` store request, **promotes the user to
  `ServiceProvider`** (`users.type`) inside a transaction; the promotion is immediate,
  so the response carries the new `type` so clients switch interface.
- `createForProvider(...)` — direct flow: no temp token, `mobile` taken from the
  payload; for an already-onboarded provider adding another store.
- Both reject a store `mobile` that equals the user's own account mobile
  (`auth.store.validation.mobile.same_as_account`, **422**).
- `sendMobileOtp` / `verifyMobileOtp`: verify failure throws `auth.otp.rate_limited`
  with `['max' => 5]`, **422**; invalid token throws `auth.register.token_invalid`, **422**.

### CustomerCarService
Customer-car ownership & validation logic used by the customer car CRUD.

### StoreCarService
Provider store-car create/update/destroy logic, delegating to policies for access.

### SoldQuantityService
Tracks sold quantity on `store_car_components` as offers are accepted/completed.

### StorefrontQueryService
All marketplace reads shared by customers and providers: `activeStores`, `storeDetail`,
`carList`, `carDetail`, `componentList`, `componentDetail`, `componentCarSearch`. It
enforces the "car belongs to this store" invariant (`BusinessRuleException` 404
`auth.general.not_found`) and enriches results (ratings/sold quantity). `StoreController`
is thin wiring only — it maps the returned models/cursors onto `*Resource` classes.

### OtpService (SIMPLE — current behavior)
- **No rate limiting and no `Cache::lock()`** — the current implementation is minimal.
- `sendOtp`: stores a hashed 4-digit OTP, deletes any prior, expiry 5 minutes; logs the
  OTP in local env for debugging.
- `verifyOtp(mobile, code)`: boolean.
- `findByMobile`, `createPendingToken`, `consumePendingToken`, `createToken`.
- Shared low-level OTP/token utility — reused by `StoreRequestService` (store-request
  mobile verification) and `AuthService`. It does **not** own user creation; user
  aggregation lives in `AuthService::createUser`.

> Note: rate limiting for OTP routes is enforced by the HTTP middleware
> (`throttle:customerLogin`), not inside `OtpService`.

---

## State Machines

### OrderStatus (`App\Enums\OrderStatus`)
`pending | rejected | awaiting_payment | paid | completed | cancelled`

Transitions enforced in `OrderService`/`OrderOfferService`:
- **pending** — general requests await offers; customers may cancel, and providers
  may reject only their targeted specific orders.
- **awaiting_payment** — the customer selected the final whole-request offer total;
  that offer is linked by `accepted_offer_id`, pending competitors become
  `not_selected`, and the customer may pay or cancel.
- **paid** — payment recorded (`OrderPaid` event → `NotifyProviderOfPayment`).
- **completed** — customer confirms received (`OrderCompleted` event →
  `NotifyProviderOfCompletion` + `SoldQuantityService` update).
- **cancelled / rejected** — terminal states.

### OfferStatus (`App\Enums\OfferStatus`)
Guards in `OrderOfferService` serialize offer changes against customer selection.
Accepted offers stay `accepted`; offers still pending when another is chosen become
`not_selected`; customer refusals remain `rejected`. Offers cannot be changed after
selection.

---

## Database Rules

- **Tests run on SQLite `:memory:`**; production is MySQL. Migrations must be compatible
  with both.
- **Unique constraints already in place:**
  - `order_offers(order_id, store_id)` — added by migration
    `2026_09_04_155756_add_unique_constraint_to_order_offers_table.php`
    (down: drops the index). One offer per store per order.
  - The `payments(order_id)` unique constraint was **intentionally omitted** — payment
    retries legitimately create multiple rows (a "failed" audit row per attempt).
- `request_status` (RequestStatus enum) drives store-request lifecycle.
- `users.type` = UserType; `users.status` = UserStatus; `stores.status` = StoreStatus;
  `payments.status` = PaymentStatus; `payments.payment_method` = PaymentMethod;
  `store_car_sections.condition` = SectionCondition; `device_tokens.platform` =
  DevicePlatform; `admin.status` = AdminStatus; `admin.assigned_role` = AdminRole.

---

## Transactions

- Service methods that mutate multiple aggregate rows use Eloquent transactions
  (`DB::transaction`) where the operation is local and atomic.
- Payments are the declared exception: the external gateway call is **not** wrapped in a
  DB transaction with order state (see Rules #5). The local payment record is persisted
  separately; failures are audited as `PaymentStatus::Failed`.

---

## Events / Listeners / Notifications

All events and listeners run **synchronously**:

- **Events** do NOT implement `ShouldBroadcast`; all carry `Dispatchable` +
  `SerializesModels` and dispatch inline.
- **Listeners** do NOT implement `ShouldQueue`.

| Event              | Listener(s)                          | Purpose                          |
| ------------------ | ------------------------------------ | -------------------------------- |
| `MessageSent`      | `NotifyConversationParticipant`      | notify conversation participant  |
| `OfferCreated`     | `NotifyCustomerOfOffer`              | notify customer of new offer     |
| `OrderCreated`     | `NotifyStoresOfNewOrder`             | notify stores of new order       |
| `OrderPaid`        | `NotifyProviderOfPayment`            | notify provider of payment       |
| `OrderCompleted`   | `NotifyProviderOfCompletion`         | notify provider of completion    |

Notifications (`NewOrderNotification`, `NewOfferNotification`, `NewMessageNotification`,
`OrderPaidNotification`, `OrderCompletedNotification`) persist database rows
(`notifications`) and may target device tokens for push.

---

## Query Philosophy

- Repositories or query services eager-load the relationships needed by responses.
  Resources serialize prepared data, using `whenLoaded(...)` for optional relations;
  serialization must not introduce additional database queries.
- Listing endpoints use `paginate` and the `paginated` response helper.
- Resources use `whenLoaded` for nullable relations (`acceptedOffer.store`,
  `storeCarComponent`, `offers`, `vehicleDetails`, etc.).
- Locale-aware fields (e.g. `name_en`/`name_ar`) are selected per `Accept-Language`
  inside resources using `$request->header('Accept-Language', app()->getLocale())`.

---

## Dependencies & Instrumentation

- **Laravel Sanctum** — API tokens.
- Rate limiting via Laravel's `RateLimiter` facades in `AppServiceProvider`.
- Payments via a driver abstraction (`config/payments.php`: `driver` default `"stub"`,
  `commission_rate` = 5%).
- Logging via `LoggingServiceProvider` (structured channel logs; key business events /
  blocked-user attempts logged).
- No caching layer used by CmsController beyond what exists; no Redis cache dependence in
  tests (`CACHE_STORE=array`).

---

## API Contract (routes/api.php)

### Auth (public, `throttle:customerLogin` on OTP routes)
```
POST /auth/otp/send
POST /auth/otp/verify
POST /auth/register
POST /auth/admin/login          (throttle:adminLogin)
```

### Reference (public)
```
GET  /reference/cities
GET  /reference/companies
GET  /reference/companies/{company}/names
GET  /reference/names/{name}/models
GET  /reference/components?search=&section_id=&page=&per_page=
GET  /reference/fuel-types
GET  /reference/colors
GET  /reference/sections
GET  /reference/sections/{section}/components
```
Cities, companies, company names, models and components accept `search`, `page`
and a bounded `per_page` (maximum 50), and return the standard `data` plus
`meta` pagination envelope. Fuel types, colors and sections remain complete
small selector lists. Component search can be scoped with `section_id`.

### CMS (public)
```
GET  /cms/{type}               (route-model-bound to Cms by `type`)
```

### Provider (`auth:sanctum`, `user.active`)
```
# Onboarding (customers only) — becoming a provider
POST    /provider/store-requests/verify-mobile       (customer)
POST    /provider/store-requests/verify-code         (customer)
POST    /provider/store-requests                     (customer; requires temp_token,
                                                       promotes user to service provider,
                                                       response includes new type;
                                                       closed to providers once onboarded)

# Provider-only
GET/PUT /provider/profile
GET     /provider/stores                    (my stores incl. inactive; personal list)
PUT     /provider/store/{store}             (update only — shows/browse are shared, see Marketplace)
POST    /provider/store/{store}/cars
PUT/DELETE /provider/store/{store}/cars/{storeCar}
POST    /provider/store/{store}/cars/{storeCar}/components   (+/batch)
PUT/DELETE /provider/store/{store}/cars/{storeCar}/components/{component}
GET  /provider/store-requests[/{storeRequest}]
POST /provider/store-requests/direct                 (no temp_token; direct store request)
GET  /provider/orders/general
GET  /provider/orders/specific
GET  /provider/orders/offers         (provider's own offers listing — unaffected by viewAny change)
GET  /provider/orders/paid
GET  /provider/orders/{order}
POST /provider/orders/{order}/offer          (optional `images[]` uploads)
PUT/DELETE /provider/orders/{order}/offer/{offer}
POST /provider/orders/{order}/reject
```

### Customer (`auth:sanctum`, `user.active`, `customer`)
```
GET/PATCH /customer/profile
GET/POST/PATCH/DELETE /customer/customer-cars[/{customerCar}]
GET/POST /customer/orders?order_type=&status=&page=   /orders/{order}
POST /customer/orders/{order}/accept-offer
POST /customer/orders/{order}/pay
POST /customer/orders/{order}/received
POST /customer/orders/{order}/cancel
GET /customer/orders/{order}/offers[/{offer}]        (OrderOfferController)
POST /customer/orders/{order}/offers/{offer}/reject
```

Offer photos use the private `offer_images` gallery. Supplying `images[]`
during offer creation attaches optional ordered photos; supplying it on update
replaces the gallery, while omission preserves current photos. URLs use
authorized `/media/offers/{offer}/images/{image}` routes. An order stores only
`accepted_offer_id`; the selected store is derived from that offer. Rejecting
an individual offer changes only its offer status.

### Marketplace — shared read-only browsing (`auth:sanctum`, `user.active`, `auth.provider`)
```
GET /component-cars
GET /stores[/{store}]           (active stores only, paginated)
GET /stores/{store}/cars[/{car}]
GET /stores/{store}/cars/{car}/components[/{component}]
```
Same storefront for both roles. Resources carry `can_manage` (ownership check via the
`manage` policy — `user_id === store.user_id`), so the frontend shows edit/delete
affordances (calling the `/provider` endpoints) only for the user's own stores. The
providers' write CRUD stays under `/provider/store/...` (see Provider section). Provider
frontends read stores/cars/components through these same browse routes — there is no
separate provider read route for them.

### Shared (customer or provider: `auth.sanctum`, `user.active`, `auth.provider`)
```
GET/POST /conversations
GET/POST /conversations/{conversation}/messages
GET/POST /ratings
GET /notifications
PATCH /notifications/read-all
PATCH /notifications/{notification}/read
```

> **Note:** The `OrderOfferPolicy::viewAny` restricts the offer listing
> (`/customer/orders/{order}/offers`) to the **order's customer** (plus `offersAreVisible`).
> The provider's separate `/provider/orders/offers` route is unaffected.

---

## Security

- Sanctum tokens; all non-public routes require `auth:sanctum`.
- Role gating via `customer` / `provider` / `auth.provider` middleware.
- Blocked users (`UserStatus::Blocked`) rejected with 403 by `EnsureUserIsActive`.
- Object-level authorization via policies (order/offer/payment/store/car/rating/
  conversation/message/notification ownership).
- Mobile numbers normalized to E.164 (`+9665XXXXXXXX`) by `Support\MobileNumber` and
  validated with a strict regex.
- Rate limiting on auth endpoints (OTP + admin login) plus the global API throttle.
- `PaymentMethod::CreditCard` flows only through the gateway driver; intended for
  integration with a real PSP in production.

---

## Testing

- **Framework:** PHPUnit via `phpunit.xml` (tests use SQLite `:memory:`,
  `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `RefreshDatabase`).
- **Suite:** `tests/Unit` + `tests/Feature`.
- **Notable test file (refactor-specific):**
  - `tests/Unit/OrderServiceTest.php` — **9 tests** using a real `City::factory()`, a
    real `StoreCarComponent` (not the id `1` stub) for FK integrity, and an explicit full
    `store_requests` payload.
  - `tests/Feature/CustomerOrderCancelTest.php` — `pendingOrderWithOffers` creates
    **2 offers on two different stores** (the former `count(2)` on the same store would
    violate the new `order_offers(order_id, store_id)` unique constraint).
- Run the suite with: `php vendor/bin/phpunit` (or `php artisan test`).
- Current status: **249 passed / 737 assertions**.

---

## Known Concerns / Future Considerations

- `payments` has no `order_id` unique constraint by design (payment retries create
  multiple rows). If the business ever wants a single authoritative payment per order,
  add a derived column (e.g. `is_successful`) rather than a naive unique on `order_id`.
- `OtpService` performs no in-service rate limiting or locking — currently handled at the
  HTTP throttle layer only. A cache-lock/attempt counter may be desired for production
  hardening.
- The payment gateway `"stub"` driver is a placeholder; wire a real PSP and keep
  external-charge semantics (no DB rollback of order state) in mind.
- `CmsController` route-model-binds `Cms` by a `{type}` parameter; confirm the model's
  route key is `type` (not the default `id`) to avoid 404s on named slugs.
- No broadcast/queue workers are configured; events/notifications run synchronously
  (acceptable at current scale).
- Resources rely on `whenLoaded`; ensure list endpoints eager-load the nested relations
  they serialize to avoid N+1 queries.

---

## Future AI Engineering Rules

When working in this repository, follow the Architecture Rules at the top of this file,
preserve the response envelope and exception conventions, keep business logic in services
behind policies, and keep the test suite green (run `php artisan test` after changes).
Do not add redundant comment blocks; match the existing code style (backed enums,
Service-injected controllers, `ApiResponse` responses, localized rule keys).

## Offer text chat (2026-10-04)

### Chat responsibility boundaries (2026-10-07)

ConversationController coordinates Form Requests, policies, ChatService and API
Resources. Shared chat requests live under Requests/Shared; the offer first-send
request remains customer-specific. UUID, timeline cursor and read-boundary input
rules use BaseRequest's standard localized 422 envelope. Inbox boolean inputs
retain true/false, 1/0 and on/off/yes/no spellings; invalid booleans and nonpositive
or noninteger page numbers now return validation errors.

ConversationRepository owns participant-scoped inbox queries, display eager
loading, offer lookup and conversation persistence. MessageRepository owns both
pagination variants, scoped read updates, retry-key lookup and message writes.
The service retains transactions, lock ordering, idempotency, notification dispatch
and read-state rules. ChatEligibility shares existing-chat and before-creation
eligibility without constructing an unsaved conversation. Policies own participant
and nested-offer authorization; writes recheck authorization after acquiring locks.

Typed chat result objects carry computed eligibility into Resources. Resources
only serialize prepared data and never resolve services. ChatStoreResource exposes
only the existing id/name/employee_name fields. Routes, success payloads, pagination
order, legacy optional retry keys and read-only offer lookup remain compatible.
Invalid read boundaries and unavailable chats use localized business exceptions.
No schema change, data migration or live-account mutation is part of this refactor.

Verification: 41 conversation/shared endpoint tests pass (269 assertions), including
13 new regressions for request envelopes, authorization, cursor paging, per-thread
unread/latest-message queries, read boundaries, eligibility and retry behavior.
Prepared response serialization issues zero database queries; both new-message and
retry results carry their loaded conversation. Targeted Pint and diff checks pass.
All tests use an isolated SQLite in-memory database.

The full backend suite ran 430 tests: 422 passed, seven failed and one errored.
The eight unrelated failures/errors also reproduce in a separate focused run:
OrderServiceTest's completion-instance expectation; two AuthRateLimitTest cases;
CustomerOrderCancelTest's awaiting-payment cancellation; three
CustomerOrderLifecycleTest cases; and GeneralOrderDetailsTest's demo seed count.
Existing order cancellation/state changes, OTP limits and demo-seed expectations
were not changed by this chat refactor.

GET /customer/orders/{order}/offers/{offer}/conversation is a read-only preview,
returning conversation (nullable), can_send, order_id, offer_id and store
{id,name,employee_name}. POST the same URL plus /messages requires content and a
client_message_id UUID; creates the chat plus its first message atomically and
returns {conversation,message}. Only the owning customer can start an offer chat;
the provider is resolved from the offer store. The store employee name is exposed
only to authorized chat participants, without exposing phone or registration data.

GET /conversations/{id} returns participant-authorized metadata. GET .../timeline
accepts before_id OR after_id and returns {messages:[newest first],has_more}; pages
contain at most 40 messages. Forward pages select the earliest 40 after the cursor
so bursts cannot skip messages. PATCH .../read accepts through_id belonging to that
chat and marks only incoming messages up to it. Existing POST .../messages accepts
optional client_message_id for compatibility; Flutter always supplies it. A retry
with the same sender/key/content/chat returns the stored message; conflicts return
409. Existing database notifications fire once per new message, inside the write
transaction. There are no push, realtime, call or attachment changes.

GET /conversations?with_messages=true hides empty legacy chats for the mobile app.
Default legacy listing behavior remains. Inbox ordering and last-message previews
use message IDs to break same-second timestamp ties. New sends require unblocked
participants and an active matching store for offer chats; historical messages stay
readable. Each offer has one conversation, so different requests stay separate.

Deploy additive migration 2026_10_04_120000_add_offer_chat_context before the app.
It preserves legacy chats, adds nullable unique offer_id and per-sender message
retry keys, and expands content to TEXT to match the existing 2000-character rule.
No backfill guesses and no database reset. Applied to local development on Oct 4.

Chat verification: OfferChatTest plus SharedFeaturesTest pass all 28 tests (109
assertions); targeted Pint passes. Full backend regression has 399 passing tests
and three existing expectation mismatches: AuthRateLimitTest expects three attempts
although configuration permits six (two cases); GeneralOrderDetailsTest expects
30 demo general requests although the seeder creates 50. No auth/seed changes.

## Customer pickup order lifecycle — 2026-10-06

CustomerOrderResource adds can_cancel, can_confirm_received and payment_summary.
Store summaries include employee_name and url_location for pickup. Cancellation
requires pending/awaiting_payment with no retained payment attempt, including
soft-deleted payments. The API is authoritative; missing flags disable mobile
actions. Cancellation and receipt confirmation lock/reload the order and safely
return an already-reached terminal state. Completion dispatches once after commit.
Payment initialization now takes the same order lock before inserting its durable
pending record, preventing stale payment attempts after cancellation.

The mobile checkout is prepared for online payment and store pickup. Delivery is
Coming soon. The gateway is unselected; by user decision Pay online stays disabled
and the mobile app never invokes the stub payment path. No schema migration is
needed. Six new lifecycle tests and related payment/cancellation/history/admin
tests pass: 41 tests, 219 assertions. No real orders or payments were mutated.
