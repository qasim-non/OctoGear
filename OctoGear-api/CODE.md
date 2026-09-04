# OctoGear (YARDY) - API Code Guide

> Status: reflects the CURRENT state of the project **after** the completed
> service-layer refactor. Tests green: **175 passed / 470 assertions**.

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
6. **Repositories only with justification.** No generic repository layers; use Eloquent
   directly. Only introduce one when there is a real reason.
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
│  │  ├─ OrderStatus.php        # pending | rejected | negotiating | paid | completed | cancelled
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
│  │  │  │  │  ├─ CustomerStoreController.php      # browse stores/cars/components
│  │  │  │  │  └─ ProfileController.php            # customer profile show/update
│  │  │  │  ├─ Provider/
│  │  │  │  │  ├─ ProviderOrderController.php      # general/specific/offers/paid + CRUD/offer/reject
│  │  │  │  │  ├─ ProviderProfileController.php    # provider profile show/update
│  │  │  │  │  ├─ ProviderStoreController.php      # provider store index/show/update
│  │  │  │  │  ├─ ProviderStoreCarController.php   # provider store cars CRUD (StoreCarService)
│  │  │  │  │  ├─ ProviderStoreCarComponentController.php  # components CRUD + batch
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
│  │  └─ SoldQuantityService.php   # sold-quantity tracking on components
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
- `amountFor(order)` = `offered_price` × `quantity`.
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
`pending | rejected | negotiating | paid | completed | cancelled`

Transitions enforced in `OrderService`/`OrderOfferService`:
- **pending** — awaiting offers (customer may cancel if no accepted offer; provider may
  reject with a reason).
- **negotiating** — an offer was accepted but not yet paid; customer may cancel here.
- **paid** — payment recorded (`OrderPaid` event → `NotifyProviderOfPayment`).
- **completed** — customer confirms received (`OrderCompleted` event →
  `NotifyProviderOfCompletion` + `SoldQuantityService` update).
- **cancelled / rejected** — terminal states.

### OfferStatus (`App\Enums\OfferStatus`)
Guards in `OrderOfferService` prevent editing/deleting offers once an order is accepted
or the offer is in a locked state (`already_offered`, `cannot_edit_offer`,
`cannot_delete_offer`).

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

- Controllers use eager loading (`with`/`whenLoaded`) and rely on resource
  `whenLoaded(...)` guards so no N+1 leaks from serialization.
- Listing endpoints use `paginate` and the `paginated` response helper.
- Resources use `whenLoaded` for nullable relations (`acceptedStore`,
  `storeCarComponent`, `offers`, `carModel`, etc.).
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
GET  /reference/fuel-types
GET  /reference/colors
GET  /reference/sections
GET  /reference/sections/{section}/components
```

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
GET     /provider/stores          /provider/store/{store}          GET/PUT
GET/POST/PUT/DELETE /provider/store/{store}/cars[/{storeCar}]
GET/POST/PUT/DELETE /provider/store/{store}/cars/{storeCar}/components[/{component}]
                        POST .../components/batch                  (batch store)
GET  /provider/store-requests[/{storeRequest}]
POST /provider/store-requests/direct                 (no temp_token; direct store request)
GET  /provider/orders/general
GET  /provider/orders/specific
GET  /provider/orders/offers         (provider's own offers listing — unaffected by viewAny change)
GET  /provider/orders/paid
GET  /provider/orders/{order}
POST /provider/orders/{order}/offer          PUT/DELETE /offer/{offer}
POST /provider/orders/{order}/reject
```

### Customer (`auth:sanctum`, `user.active`, `customer`)
```
GET/PATCH /customer/profile
GET/POST/PATCH/DELETE /customer/customer-cars[/{customerCar}]
GET/POST /customer/orders   /orders/{order}
POST /customer/orders/{order}/accept-offer
POST /customer/orders/{order}/pay
POST /customer/orders/{order}/received
POST /customer/orders/{order}/cancel
GET /customer/orders/{order}/offers[/{offer}]        (OrderOfferController)
POST /customer/orders/{order}/offers/{offer}/reject
GET /customer/component-cars
GET /customer/stores[/{store}]
GET /customer/stores/{store}/cars[/{car}][/components[/{component}]]
```

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
- Current status: **175 passed / 470 assertions**.

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
