# OctoGear (YARDY) - API Code Guide

## Project Overview
- **Framework:** Laravel 12 (PHP 8.2+)
- **Auth:** Laravel Sanctum (token-based)
- **Database:** MySQL (production), SQLite (testing)
- **Purpose:** Car parts marketplace connecting customers with service provider stores

---

## Project Structure

```
OctoGear-api/
├── app/
│   ├── Enums/                          # PHP 8.2 enum classes (one per DB enum column)
│   │   ├── UserType.php                # customer | service_provider
│   │   ├── UserStatus.php              # blocked | unblocked
│   │   ├── OrderType.php               # general | specific
│   │   ├── OrderStatus.php             # pending | rejected | negotiating | paid | completed | cancelled
│   │   ├── PaymentMethod.php           # cash | credit_card
│   │   ├── PaymentStatus.php           # pending | paid | failed | refunded
│   │   ├── StoreStatus.php             # active | inactive
│   │   ├── AdminStatus.php             # active | inactive | blocked
│   │   ├── AdminRole.php               # admin | manager | employee | hr | developer
│   │   └── RequestStatus.php           # pending | accepted | rejected
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                   # Authentication controllers
│   │   │   │   ├── OtpController.php
│   │   │   │   ├── RegisterController.php
│   │   │   │   └── ProfileController.php
│   │   │   ├── Customer/               # Customer-specific endpoints
│   │   │   │   ├── CarController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   └── SearchController.php
│   │   │   ├── Provider/               # Service provider endpoints
│   │   │   │   ├── StoreController.php
│   │   │   │   ├── InventoryController.php
│   │   │   │   ├── ComponentController.php
│   │   │   │   └── OrderController.php
│   │   │   ├── Chat/                   # Messaging
│   │   │   │   └── ConversationController.php
│   │   │   ├── Admin/                  # Admin panel endpoints
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   ├── CustomerController.php
│   │   │   │   ├── ServiceProviderController.php
│   │   │   │   ├── ServiceProviderRequestController.php
│   │   │   │   ├── StoreRequestController.php
│   │   │   │   ├── StoreController.php
│   │   │   │   ├── OrderController.php
│   │   │   │   ├── PaymentController.php
│   │   │   │   ├── AdminUserController.php
│   │   │   │   ├── ReferenceDataController.php
│   │   │   │   └── CmsController.php
│   │   │   └── Controller.php          # Base abstract controller
│   │   │
│   │   ├── Middleware/
│   │   │   ├── Authenticate.php          # Overrides default auth — returns JSON 401 (no redirects)
│   │   │   ├── SetLocale.php             # Accept-Language header → ar/en locale
│   │   │   ├── EnsureUserIsActive.php    # Blocks blocked users (customer/provider)
│   │   │   ├── EnsureAdminIsActive.php   # Blocks blocked admins
│   │   │   ├── EnsureIsCustomer.php      # Checks type === Customer
│   │   │   └── EnsureIsProvider.php      # Checks type === ServiceProvider
│   │   │
│   │   ├── Requests/                   # Form Request validation classes
│   │   │   ├── Auth/
│   │   │   ├── Customer/
│   │   │   ├── Provider/
│   │   │   └── Admin/
│   │   │
│   │   └── Resources/                  # API Resource transformers
│   │       ├── UserResource.php
│   │       ├── StoreResource.php
│   │       ├── StoreCarResource.php
│   │       ├── ComponentResource.php
│   │       ├── SectionResource.php
│   │       ├── OrderResource.php
│   │       ├── OrderOfferResource.php
│   │       ├── ConversationResource.php
│   │       ├── MessageResource.php
│   │       ├── PaymentResource.php
│   │       ├── CustomerCarResource.php
│   │       ├── CmsResource.php
│   │       └── ReferenceDataResource.php
│   │
│   ├── Models/                         # Eloquent models (one per table)
│   │   ├── User.php
│   │   ├── Admin.php
│   │   ├── Country.php
│   │   ├── City.php
│   │   ├── FuelType.php
│   │   ├── CarCompany.php
│   │   ├── CarName.php
│   │   ├── CarModel.php
│   │   ├── Color.php
│   │   ├── Store.php
│   │   ├── StorePicture.php
│   │   ├── StoreCompany.php
│   │   ├── StoresCar.php
│   │   ├── StoreCarPicture.php
│   │   ├── CarSection.php              # NEW - main sections (front, rear, engine...)
│   │   ├── Component.php               # NEW - universal parts catalog
│   │   ├── StoreCarComponent.php        # NEW - store-specific pricing
│   │   ├── CustomerCar.php
│   │   ├── CustomerCarPicture.php
│   │   ├── Order.php
│   │   ├── OrderOffer.php
│   │   ├── Payment.php
│   │   ├── Conversation.php
│   │   ├── Message.php
│   │   ├── ServiceProviderRequest.php
│   │   ├── StoreRequest.php            # NEW - store approval workflow
│   │   ├── DeviceToken.php             # NEW - push notification tokens
│   │   ├── OtpCode.php                 # NEW - OTP verification codes (hashed)
│   │   ├── Rating.php                  # NEW - customer ratings
│   │   ├── PlatformSetting.php         # NEW - platform config (fees etc)
│   │   └── Cms.php
│   │
│   ├── Policies/                       # Authorization rules
│   │   ├── CustomerCarPolicy.php
│   │   ├── StorePolicy.php
│   │   ├── StoresCarPolicy.php
│   │   ├── StoreCarComponentPolicy.php
│   │   ├── OrderPolicy.php
│   │   ├── ConversationPolicy.php
│   │   └── MessagePolicy.php
│   │
│   ├── Services/                       # Business logic layer
│   │   ├── OtpService.php
│   │   ├── OrderService.php
│   │   ├── PaymentService.php
│   │   ├── ChatService.php
│   │   ├── RegistrationService.php
│   │   └── ImageService.php
│   │
│   └── Providers/
│       └── AppServiceProvider.php
│
├── config/
│   ├── auth.php
│   ├── database.php
│   ├── filesystems.php
│   └── sanctum.php                     # Published Sanctum config
│
├── database/
│   ├── migrations/                     # Database schema
│   ├── factories/                      # Model factories for testing
│   └── seeders/                        # Database seeders
│
├── routes/
│   ├── api.php                         # All API routes
│   ├── web.php
│   └── console.php
│
└── tests/
    ├── Feature/                        # HTTP endpoint tests
    └── Unit/                           # Service/model unit tests
```

---

## Database Schema

### Car Hierarchy (Reference Data - Admin Managed)
```
countries (id, name_en, name_ar)
  └── cities (id, name_en, name_ar, country_id FK)
  └── cars_companies (id, name_en, name_ar, country_id FK)
      └── cars_names (id, name_en, name_ar, car_company_id FK)
          └── models (id, name_en, name_ar, car_name_id FK)

fuel_types (id, type_en, type_ar)
colors (id, name_en, name_ar)
car_sections (id, name_en, name_ar)               # NEW - front, rear, engine, interior...
  └── components (id, name_en, name_ar, section_id FK)  # NEW - universal parts catalog
```

### Users & Auth
```
users (id, full_name, mobile[unique], type[enum], city_id FK, status[enum], timestamps, soft_deletes)
admin (employee_id PK, name, assigned_role[enum], mobile, email[unique], password, status[enum], timestamps, soft_deletes)
otp_codes (id, hashed_otp, identifier, expires_at, timestamps, soft_deletes)
personal_access_tokens (Sanctum)
device_tokens (id, user_id FK, token[unique], platform, timestamps)  # NEW
```

### Stores
```
stores (id, name, mobile[unique], nick_name, employee_name, url_location, status[enum], commercial_registration_number, commercial_registration_picture, city_id FK, user_id FK, timestamps, soft_deletes)
store_pictures (id, picture, store_id FK, timestamps, soft_deletes)
store_companies (id, store_id FK, company_id FK, timestamps, soft_deletes)  # M:N pivot
store_requests (id, user_id FK, store details, request_status[enum], timestamps, soft_deletes)  # NEW
```

### Inventory
```
stores_cars (id, manufacturing_year, vehicle_plat_number, car_name_id FK, color_id FK, store_id FK, fuel_type FK, timestamps, soft_deletes)
store_car_pictures (id, picture, car_id FK, timestamps, soft_deletes)
store_car_components (id, store_car_id FK, component_id FK, part_number, description, price, stock_quantity, warranty_months, timestamps, soft_deletes)  # NEW - replaces old car_components
```

### Customer Cars
```
customer_cars (id, manufacturing_year, vehicle_plat_number, car_name_id FK, color_id FK, customer_id FK, fuel_type FK, timestamps, soft_deletes)
customer_car_pictures (id, picture, car_id FK, timestamps, soft_deletes)
```

### Orders & Payments
```
orders (id, order_type[enum], quantity, customer_image, status[enum], offered_price, notes, customer_id FK, store_car_component_id FK(nullable), model_id FK(nullable), timestamps, soft_deletes)
order_offers (id, order_id FK, store_id FK, price, notes, timestamps, soft_deletes)
payments (id, order_id FK, amount, payment_method[enum], payment_status[enum], timestamps, soft_deletes)
```

### Communication
```
conversations (id, customer_id FK, provider_id FK, timestamps, soft_deletes)
messages (id, content, is_read[boolean], conversation_id FK, sender_id FK, timestamps, soft_deletes)
notifications (uuid PK, type, notifiable_type, notifiable_id, data[text], read_at[nullable], timestamps)  # Laravel built-in
```

### Other
```
service_provider_requests (id, request_time, request_status[enum], user_id FK, store_id FK, timestamps, soft_deletes)
ratings (id, customer_id FK, store_id FK, order_id FK, rating[tinyint], comment, timestamps, soft_deletes)  # NEW
platform_settings (id, key[unique], value, timestamps)  # NEW
cms (id, arabic_text, english_text, timestamps, soft_deletes)
```

---

## Coding Conventions

### Response Format
Every endpoint returns this envelope:
```json
// Success
{
    "success": true,
    "message": "Store created.",
    "data": { "id": 1, "name": "AlFaris" }
}

// Success with pagination
{
    "success": true,
    "message": "OK",
    "data": [...],
    "meta": { "current_page": 1, "last_page": 5, "per_page": 20, "total": 95 }
}

// Error
{
    "success": false,
    "message": "Validation failed",
    "errors": { "name": ["The name field is required."] }
}
```

### Base Classes
- **`Controller`** — Uses `ApiResponse` trait. Every controller extends this.
  - `$this->success($data, $message, $code)` — 200
  - `$this->created($data, $message)` — 201
  - `$this->error($message, $code, $errors)` — 400
  - `$this->notFound($message)` — 404
  - `$this->forbidden($message)` — 403
  - `$this->unauthorized($message)` — 401
  - `$this->paginated($paginator, $message)` — 200 with meta
- **`BaseRequest`** — Every Form Request extends this. Overrides failedValidation to return JSON 422 (no redirects).

### Models
- Use `$fillable` for mass-assignable fields (NEVER `*`)
- Use `$hidden` for sensitive/internal fields (deleted_at, etc.)
- Use `casts()` method with PHP Enums for enum columns
- Always type-hint relationships: `BelongsTo`, `HasMany`, `MorphToMany`, etc.
- Table name = plural snake_case (Laravel convention)
- Foreign key = `singular_id` (e.g., `city_id`, `user_id`)
- One model file per class (PascalCase filename)

### Enums (PHP 8.2)
- One enum class per database enum column
- Always backed by string values matching DB enum values
- Named PascalCase, values are snake_case
- Use in Form Requests for validation: `Rule::enum(OrderStatus::class)`
- Use in Model `$casts` for automatic casting

### Controllers
- Organized by feature domain (Auth/, Customer/, Provider/, Admin/, Chat/)
- Thin controllers — delegate business logic to Services
- Use Form Request classes for validation (NOT inline rules)
- Use API Resources for responses (NOT raw model/array)
- Use `$this->authorize()` or Policy for authorization
- Always return JSON with consistent envelope

### Form Requests
- One class per operation (CreateXxxRequest, UpdateXxxRequest)
- Place in `Http/Requests/{Domain}/` matching the controller domain
- Define `rules()` and `authorize()` methods
- Use PHP Enums in validation rules: `Rule::enum(OrderStatus::class)`

### API Resources
- One Resource class per model
- Return `{ "data": { ... } }` for single items
- Return `{ "data": [...], "meta": { "current_page": 1, "last_page": 5, "per_page": 15 } }` for collections
- Use `$this->whenLoaded('relation')` for conditional nested data
- Use `$this->whenCounted('relation')` for counts

### Policies
- One Policy per model that needs authorization
- Register in AppServiceProvider::boot() or use auto-discovery
- Methods: viewAny, view, create, update, delete, restore, forceDelete
- Check ownership: `$model->user_id === $user->id`
- Use `$this->authorize()` in controllers

### Services
- Only create when business logic is complex (multi-step, validation across models)
- No Service for simple CRUD (use Eloquent directly in controller)
- Inject via constructor or use `app()` helper
- Services: OtpService, OrderService, PaymentService, ChatService, RegistrationService, ImageService

### Migrations
- Filename format: `YYYY_MM_DD_HHMMSS_create_{table}_table.php`
- Always use `$table->id()` as primary key
- Always add `$table->timestamps()` and `$table->softDeletes()` unless intentional
- Foreign keys: `$table->foreignId('x_id')->constrained('x_table')->onDelete('cascade')`
- Spell everything correctly in column names

### Testing
- Feature tests in `tests/Feature/{Domain}/`
- Unit tests in `tests/Unit/Services/`
- Use `actingAs()` for authenticated tests
- Use factories for all test data
- Test both success and failure paths
- Test authorization (unauthorized access returns 403)

---

## Production Best Practices

These are the rules applied to every endpoint in this project. Review before building any feature.

### 1. Validation
Always validate every request. Never trust the frontend.
- Required fields, email format, password length, unique values
- Use Form Request classes (NOT inline `$request->validate()` in controllers)
```php
// WRONG — inline validation clutters the controller
$request->validate(['name' => 'required|string|max:255']);

// RIGHT — dedicated Form Request class
class CreateStoreRequest extends FormRequest {
    public function rules(): array {
        return ['name' => 'required|string|max:255'];
    }
}
```

### 2. Authorization
Never trust the frontend. Check:
- Is the user logged in?
- Does the user own this resource?
- Does the user have permission?
- Use Policies + `$this->authorize()` in controllers

### 3. Transactions
Use `DB::transaction()` when multiple queries must all succeed:
```php
DB::transaction(function () use ($data) {
    $order = Order::create($data);
    StoreCarComponent::where('id', $data['component_id'])
        ->decrement('stock_quantity', $data['quantity']);
    Payment::create(['order_id' => $order->id, ...]);
});
// If anything fails → automatic rollback
```
Use for: Orders, Payments, Wallets, Stock updates

### 4. Logging
Log unexpected events. Don't log everything.
- Good: Payment failed, external API error, unexpected exception
- Bad: Passwords, tokens, routine operations
```php
Log::error('Payment failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
```

### 5. Caching
Use cache for data that changes rarely: settings, countries, categories.
```php
Cache::remember('countries', 3600, fn() => Country::all());
```
Don't cache frequently changing data unless needed.

### 6. Pagination
Never return thousands of rows.
```php
// WRONG — loads entire table into memory
User::all();

// RIGHT — returns 20 per page
User::paginate(20);
```

### 7. API Resources
Never return raw models. Always use API Resource transformers.
```php
// WRONG
return User::find(1);

// RIGHT
return new UserResource($user);
```

### 8. Consistent API Response
Every endpoint must return the same format:
```json
{
    "success": true,
    "message": "Store created.",
    "data": { "id": 1, "name": "AlFaris" }
}
```

### 9. Exception Handling
Don't wrap everything in try/catch. Use Laravel's global exception handler. Only catch when you need to transform the error message or take recovery action.

### 10. Service Layer
For complex business logic, use Services:
```
Controller → Service → Model
```
Controller stays thin. No Services for simple CRUD.

### 11. Dependency Injection
```php
// WRONG
$userService = new UserService();

// RIGHT
public function __construct(UserService $service) {
    $this->service = $service;
}
```

### 12. Eager Loading (Avoid N+1)
```php
// WRONG — N+1 query problem (1 query for orders + N queries for each customer)
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->full_name; // triggers a query each time!
}

// RIGHT — eager loads all customers in one query
$orders = Order::with('customer')->get();
```

### 13. Database Indexes
Index columns used in: WHERE, JOIN, ORDER BY, search queries.

### 14. Soft Deletes
Use `SoftDeletes` when data shouldn't disappear permanently. Allows recovery and audit trails.

### 15. Queue Jobs
For slow tasks (Send email, Send SMS, Generate PDF, Upload image), use Queues instead of making the user wait.

### 16. Events & Listeners
Break side effects into separate listeners:
```
User registered → CreateProfile listener
                → SendWelcomeEmail listener
                → GiveBonus listener
```

### 17. Rate Limiting
Protect APIs from abuse:
```php
Route::middleware('throttle:60,1')->group(function () {
    // 60 requests per minute
});
```

### 18. Secure Passwords
Never store plain passwords. Always use `Hash::make()`. Model casts `'password' => 'hashed'` handles this automatically.

### 19. Environment Variables
Never hardcode API keys, passwords, or database credentials. Always use `.env`.

### 20. File Storage
Never store uploads inside `public/`. Use Laravel's `Storage` facade (S3, local disk, etc.).

### 21. Database Seeders
Use seeders for: Countries, Roles, Permissions, Default settings, Reference data.

### 22. Factories
Generate fake data for testing. One factory per model.

### 23. Database Constraints
Use foreign keys, unique constraints, cascade rules. Don't rely only on validation — the database is the last line of defense.

### 24. Logging
Structured, purposeful logging. Never log sensitive data (passwords, tokens, OTPs, full card numbers).

**Channels:**
- `daily` (default) — Application errors, exceptions, debug info. Auto-rotated, 14-day retention.
- `operations` — Third-party API calls, payment transactions, SMS delivery status. 30-day retention. Use via `Log::channel('operations')`.

**When to log:**
| Log Level | When | Example |
|---|---|---|
| `error` | Unhandled exceptions, system failures | Payment gateway timeout, DB connection lost |
| `warning` | Recoverable issues, degraded state | Cache miss, retry succeeded, rate limit hit |
| `info` | Business events, audit trail | Order created, user registered, OTP sent (production-safe) |
| `debug` | Development debugging only | Variable dumps, query logs (NEVER in production) |

**Production rules:**
- Default stack is `daily` (auto-rotated, 14-day retention)
- All logs are **JSON-formatted** via `LoggingServiceProvider` (structured, parseable by log aggregators)
- `LOG_LEVEL=info` in production (no debug noise)
- OTP codes are **NEVER** logged in production (`Log::info` only in `local` environment)
- Use `Log::channel('operations')` for financial/transactional logs

**Code examples:**
```php
// Application error — always log with context
Log::error('Payment failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);

// Business event — use info level
Log::info('Order created', ['order_id' => $order->id, 'customer_id' => $user->id]);

// Third-party API / financial — use operations channel
Log::channel('operations')->info('SMS sent', ['mobile' => $mobile, 'provider' => 'unifonic', 'status' => 'delivered']);
Log::channel('operations')->info('Payment processed', ['order_id' => $order->id, 'amount' => $amount, 'method' => 'credit_card']);

// NEVER do this
Log::info("OTP for {$mobile}: {$otp}");           // leaks secrets
Log::debug($user->toArray());                      // debug in production
Log::info($request->all());                        // may contain passwords
```

**Log file structure:**
```
storage/logs/
├── laravel-YYYY-MM-DD.log    # daily channel (14 days retention)
├── operations-YYYY-MM-DD.log # operations channel (30 days retention)
└── laravel.log               # emergency fallback
```

---

## Professional Request Flow

Every request in this API follows this flow:

```
Request
  ↓
Middleware (auth, block check, rate limit)
  ↓
Form Request Validation
  ↓
Policy Authorization
  ↓
Controller (thin — delegates to Service)
  ↓
Service (business logic)
  ↓
DB::transaction (if multi-step)
  ↓
Model (Eloquent)
  ↓
Cache (read/write if needed)
  ↓
Event / Queue (if async work needed)
  ↓
API Resource (transform to JSON)
  ↓
JSON Response (consistent format)
```

---

## API Route Groups

All routes use `locale` middleware globally (Accept-Language header → ar/en).

```
api.php
├── Public routes (no auth)
│   ├── GET /test
│   ├── POST /auth/otp/send
│   ├── POST /auth/otp/verify
│   ├── POST /auth/admin/login
│   ├── GET /countries, /cities, /fuel-types, /colors
│   ├── GET /car-companies, /car-names, /car-models
│   ├── GET /car-sections, /components
│   └── GET /cms
│
├── Customer routes (auth:sanctum + user.active + customer)
│   ├── GET/PUT /customer/profile
│   ├── GET/POST/DELETE /customer/customer-cars
│   ├── POST /customer/orders
│   ├── GET /customer/orders, /customer/orders/{order}
│   ├── POST /customer/orders/{order}/accept-offer, /cancel
│   ├── GET /customer/stores, /customer/stores/{store}
│   ├── GET /customer/stores/{store}/components
│   ├── GET/POST /customer/conversations
│   ├── GET/POST /customer/conversations/{conversation}/messages
│   ├── POST /customer/ratings
│   └── GET /customer/notifications, PUT .../read
│
├── Provider routes (auth:sanctum + user.active + provider)
│   ├── GET/PUT /provider/profile
│   ├── GET/POST/PUT /provider/store
│   ├── POST/DELETE /provider/store/pictures, /provider/store/picture-car
│   ├── GET/POST/DELETE /provider/store-cars
│   ├── GET/POST /provider/store-cars/{storeCar}/components
│   ├── PUT/DELETE /provider/store-cars/{storeCar}/components/{component}
│   ├── GET /provider/orders, /provider/orders/{order}
│   ├── POST /provider/orders/{order}/offer
│   ├── PUT/DELETE /provider/orders/{order}/offer/{offer}
│   ├── POST /provider/orders/{order}/reject
│   ├── GET /provider/store-requests
│   ├── POST /provider/store-requests/{request}/accept, /reject
│   ├── GET /provider/conversations
│   ├── GET/POST /provider/conversations/{conversation}/messages
│   ├── GET /provider/ratings
│   └── GET /provider/notifications, PUT .../read
│
└── Admin routes (auth:admin + admin.active)
    ├── GET/PUT /admin/profile
    ├── GET /admin/dashboard
    ├── GET /admin/users, /admin/users/{user}
    ├── PUT /admin/users/{user}/block, /unblock
    ├── GET /admin/stores, /admin/stores/{store}
    ├── PUT /admin/stores/{store}/activate, /deactivate
    ├── GET/POST /admin/admins
    ├── PUT/DELETE /admin/admins/{admin}
    ├── GET /admin/service-provider-requests
    ├── PUT /admin/service-provider-requests/{request}/approve, /reject
    ├── GET /admin/orders, /admin/orders/{order}
    ├── GET/PUT /admin/cms, /admin/cms/{cms}
    ├── GET/PUT /admin/platform-settings, /admin/platform-settings/{setting}
    ├── GET /admin/ratings
    └── GET /admin/notifications
```

---

## Changes Made (Changelog)

### Date: 2026-08-18
| Change | File | Reason |
|---|---|---|
| Installed `laravel/sanctum` v4.3.3 | `composer.json`, `composer.lock` | Token-based auth for mobile API |
| Fixed User model | `app/Models/User.php` | Added HasApiTokens, type in fillable, casts, missing relationships, helper methods |
| Deleted dead model | `app/Models/users.php` | Unused duplicate of User model |
| Added `password` to admin migration | `2026_05_21_084201_create_admin_table.php` | Admin needs email+password login |
| Fixed spelling in stores migration | `2026_05_19_025132_create_stores_table.php` | emploee_name→employee_name, commerical→commercial (2 cols) |
| Fixed spelling in stores_cars migration | `2026_05_19_030316_create_stores_cars_table.php` | manufuctionary_year→manufacturing_year |
| Fixed spelling in customer_cars migration | `2026_05_19_031119_create_customer_cars_table.php` | manufuctionary_year→manufacturing_year |
| Deleted car_components migration | (removed) | Replaced by 3-level hierarchy (car_sections→components→store_car_components) |
| Updated orders migration | `2026_08_18_073600_create_orders_table.php` | Replaced component_id FK with store_car_component_id FK |
| Moved order_offers migration | `2026_08_18_073650_create_order_offers_table.php` | FK dependency: must run after orders |
| Moved payments migration | `2026_08_18_073800_create_payments_table.php` | FK dependency: must run after orders |
| Moved ratings migration | `2026_08_18_073900_create_ratings_table.php` | FK dependency: must run after orders |
| Created car_sections migration | `2026_08_18_073134_create_car_sections_table.php` | New: main sections (front, rear, engine...) |
| Created components migration | `2026_08_18_073254_create_components_table.php` | New: universal parts catalog |
| Created store_car_components migration | `2026_08_18_073340_create_store_car_components_table.php` | New: store-specific pricing + stock + warranty |
| Created store_requests migration | `2026_08_18_073432_create_store_requests_table.php` | New: store approval workflow |
| Created device_tokens migration | `2026_08_18_073540_create_device_tokens_table.php` | New: push notification tokens |
| Created platform_settings migration | `2026_08_18_073756_create_platform_settings_table.php` | New: platform config (fees, etc.) |
| Created ratings migration | `2026_08_18_073900_create_ratings_table.php` | New: customer ratings for stores |
| Created notifications migration | `2026_08_18_075055_create_notifications_table.php` | Laravel built-in: in-app notification storage (polymorphic, read_at tracking) |
| Created 11 PHP 8.2 Enums | `app/Enums/*.php` | Type safety for all enum DB columns (UserType, UserStatus, StoreStatus, AdminStatus, AdminRole, OrderType, OrderStatus, PaymentMethod, PaymentStatus, RequestStatus, DevicePlatform) |
| Created 31 Eloquent Models | `app/Models/*.php` | All models with $fillable, $hidden, casts(), typed relationships, SoftDeletes, helper methods. Admin extends Authenticatable with employee_id PK. |
| Removed store_car_id from orders | `2026_08_18_073600_create_orders_table.php` | Redundant — store reachable via store_car_component → storeCar → store |
| Updated Order model | `app/Models/Order.php` | Removed store_car_id from $fillable, removed storeCar() relationship, added getStoreAttribute() convenience accessor |
| Removed broken orders() from StoresCar | `app/Models/StoresCar.php` | FK store_car_id no longer exists on orders table |
| Removed broken orders() from Store | `app/Models/Store.php` | Store→Order was wrong (no store_id FK on orders). Indirect access via storeCarComponent |
| Added getTable() to CarName | `app/Models/CarName.php` | Table is `cars_names` but Laravel guesses `car_names` — would crash on every query |
| Added getTable() to CarCompany | `app/Models/CarCompany.php` | Table is `cars_companies` but Laravel guesses `car_companies` — would crash on every query |
| Added getStoreAttribute() to StoreCarComponent | `app/Models/StoreCarComponent.php` | Convenience accessor: chain through storeCar → store |
| Added Production Best Practices | `CODE.md` | 30 best practices + professional request flow for reference during development |
| Added getTable() to Admin | `app/Models/Admin.php` | Table is `admin` (singular) but Laravel guesses `admins` — would crash every query |
| Removed remember_token from Admin $hidden | `app/Models/Admin.php` | Migration has no remember_token column — misleading |
| Added deleted_at cast to User | `app/Models/User.php` | Missing datetime cast for soft delete timestamp — inconsistent with all other models |
| Fixed Store $hidden doc comment | `app/Models/Store.php` | Comment said status should be hidden but it wasn't in array — corrected comment |
| Removed redundant getTable() from PlatformSetting | `app/Models/PlatformSetting.php` | Laravel already guesses platform_settings correctly — no override needed |
| Made country_id nullable on cars_companies | `2026_05_18_162656_create_cars_companies_table.php` | Not every brand maps to a single country |
| Fixed UserFactory | `database/factories/UserFactory.php` | Was using wrong fields (name, email) — fixed to match User model (full_name, mobile, type) |
| Created 12 model factories | `database/factories/*.php` | Admin, Store, StoresCar, StoreCarComponent, CustomerCar, Order, OrderOffer, Payment, Rating, Conversation, Message, (User fixed) |
| Created ReferenceDataSeeder | `database/seeders/ReferenceDataSeeder.php` | 92 countries (EN/AR), 46 Saudi cities, 250+ international cities, 5 fuel types, 12 colors |
| Created CarDataSeeder | `database/seeders/CarDataSeeder.php` | 54 car companies, 276 car names, 2005 car models (year variants) |
| Created ComponentSeeder | `database/seeders/ComponentSeeder.php` | 7 car sections, 130 car components (all major parts) |
| Created PlatformDataSeeder | `database/seeders/PlatformDataSeeder.php` | 12 platform settings, 4 CMS pages, default admin account |
| Updated DatabaseSeeder | `database/seeders/DatabaseSeeder.php` | Calls all seeders in dependency order |
| Optimized all 4 seeders to bulk inserts | `database/seeders/*.php` | ~2,650 queries → ~13 queries (15x faster) |
| Added seed_test_data config | `config/database.php` | Controls TestDataSeeder execution via env var |
| Created Authenticate middleware | `app/Http/Middleware/Authenticate.php` | Overrides default auth — returns JSON 401 instead of redirect |
| Created SetLocale middleware | `app/Http/Middleware/SetLocale.php` | Accept-Language header → ar/en locale (defaults to ar) |
| Created EnsureUserIsActive middleware | `app/Http/Middleware/EnsureUserIsActive.php` | Blocks blocked customers/providers with 403 |
| Created EnsureAdminIsActive middleware | `app/Http/Middleware/EnsureAdminIsActive.php` | Blocks blocked admins with 403 |
| Created EnsureIsCustomer middleware | `app/Http/Middleware/EnsureIsCustomer.php` | Checks user type === Customer, 403 if not |
| Created EnsureIsProvider middleware | `app/Http/Middleware/EnsureIsProvider.php` | Checks user type === ServiceProvider, 403 if not |
| Added admin guard to auth config | `config/auth.php` | Separate guard for admin table (email+password, employee_id PK) |
| Registered all middleware aliases | `bootstrap/app.php` | auth, locale, user.active, admin.active, customer, provider |
| Handled unauthenticated JSON responses | `bootstrap/app.php` | AuthenticationException → JSON 401 (no redirect attempts) |
| Created full route skeleton | `routes/api.php` | 91 routes across 4 groups (public, customer, provider, admin) |
| Created ApiResponse trait | `app/Http/Traits/ApiResponse.php` | Consistent JSON envelope: success, message, data, meta, errors |
| Updated Base Controller | `app/Http/Controllers/Controller.php` | Uses ApiResponse trait — all controllers inherit response helpers |
| Created BaseRequest | `app/Http/Requests/BaseRequest.php` | JSON 422 on validation failure (no redirect) |
| Cleaned api.php | `routes/api.php` | Removed closure routes — clean slate for controller-based routing |
| Created SendOtpRequest | `app/Http/Requests/Auth/SendOtpRequest.php` | Validates Saudi mobile (05XXXXXXXX) |
| Created VerifyOtpRequest | `app/Http/Requests/Auth/VerifyOtpRequest.php` | Validates mobile + 4-digit OTP |
| Created AdminLoginRequest | `app/Http/Requests/Auth/AdminLoginRequest.php` | Validates email + password (min 6) |
| Created RegisterRequest | `app/Http/Requests/Auth/RegisterRequest.php` | Validates temp_token + full_name + city_id |
| Created OtpService | `app/Services/OtpService.php` | sendOtp, verifyOtp, findByMobile, createPendingRegistration, consumePendingRegistration, createUser, createToken |
| Created AuthController | `app/Http/Controllers/Auth/AuthController.php` | sendOtp, verifyOtp (new vs existing), register, adminLogin |
| Wired auth routes | `routes/api.php` | otp/send, otp/verify, register (public), admin/login |
| Made users.city_id nullable | `database/migrations/2026_08_22_195000_make_users_city_id_nullable.php` | Users register with phone first, set city in profile later |
| Switched to cache-based registration | `app/Services/OtpService.php` | No skeleton users — temp token via Cache, one-time use, 30min expiry |
| Added OTP brute-force protection | `app/Services/OtpService.php` | Max 5 attempts per mobile, locked 5 minutes via Cache |
| Made OTP logging environment-aware | `app/Services/OtpService.php` | Only logs OTP in local env, never in production |
| Added Cache::lock() to registration | `app/Services/OtpService.php` | Prevents race condition on temp token consumption |
| Created cache + cache_locks tables | `database/migrations/2026_08_22_182024_create_cache_table.php` | Required for database cache driver + Cache::lock() |
