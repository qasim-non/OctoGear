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
│   │   │   ├── EnsureUserIsCustomer.php
│   │   │   ├── EnsureUserIsProvider.php
│   │   │   ├── EnsureUserIsAdmin.php
│   │   │   └── EnsureUserIsUnblocked.php
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
orders (id, order_type[enum], quantity, customer_image, status[enum], offered_price, notes, customer_id FK, store_car_component_id FK(nullable), store_car_id FK(nullable), model_id FK(nullable), timestamps, soft_deletes)
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

## API Route Groups

```
api.php
├── Public routes (no auth)
│   ├── POST /auth/send-otp
│   ├── POST /auth/verify-otp
│   └── GET /cms
│
├── Customer routes (auth:sanctum + EnsureUserIsCustomer + EnsureUserIsUnblocked)
│   ├── POST /auth/register
│   ├── GET/PUT /auth/me, /auth/profile
│   ├── CRUD /customer/cars
│   ├── POST /orders
│   ├── GET /orders, /orders/{id}
│   ├── PUT /orders/{id}/cancel
│   ├── GET /orders/{id}/offers
│   ├── POST /orders/{id}/offers/{offer_id}/accept
│   ├── POST /orders/{id}/pay
│   ├── GET /conversations
│   ├── GET/POST /conversations/{id}/messages
│   └── Search: GET /stores, /stores/{id}, /components/search
│
├── Provider routes (auth:sanctum + EnsureUserIsProvider + EnsureUserIsUnblocked)
│   ├── POST /provider/register
│   ├── CRUD /provider/store
│   ├── CRUD /provider/cars
│   ├── CRUD /provider/cars/{id}/components
│   ├── GET /provider/orders
│   ├── PUT /provider/orders/{id}/accept, /reject
│   ├── POST /provider/orders/{id}/offers
│   ├── GET/POST /conversations
│   └── GET/POST /conversations/{id}/messages
│
└── Admin routes (auth:sanctum + EnsureUserIsAdmin)
    ├── POST /admin/auth/login, /logout, GET /me
    ├── GET /admin/dashboard
    ├── CRUD /admin/customers
    ├── CRUD /admin/service-providers
    ├── CRUD /admin/provider-requests
    ├── CRUD /admin/store-requests
    ├── CRUD /admin/stores
    ├── CRUD /admin/orders
    ├── CRUD /admin/payments
    ├── CRUD /admin/admins
    ├── CRUD /admin/cars-companies, /cars-names, /models, /fuel-types, /colors, /countries, /cities, /car-sections, /components
    └── GET/PUT /admin/cms
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
