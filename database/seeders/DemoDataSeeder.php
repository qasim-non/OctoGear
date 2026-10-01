<?php

namespace Database\Seeders;

use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Notifications\NewOrderNotification;
use Database\Seeders\Support\DemoImages;
use Database\Seeders\Support\SeedRecords;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

/**
 * Deterministic, local-only business scenarios. Inserts directly so fixtures
 * never send OTPs/push messages, dispatch jobs, or call payment gateways.
 */
class DemoDataSeeder extends Seeder
{
    private DemoImages $images;

    private array $assets;

    private array $parts = [
        ['Front Headlight - Left', 'Front', 'part-headlight.png', 42000],
        ['Front Bumper', 'Front', 'part-bumper.png', 65000],
        ['Passenger Side Mirror', 'Passenger Side', 'part-mirror.png', 18000],
        ['Radiator', 'Front', 'part-radiator.png', 38000],
        ['Alternator', 'Engine', 'part-alternator.png', 52000],
        ['Starter Motor', 'Engine', 'part-starter.png', 29000],
        ['Brake Disc', 'Brakes', 'part-brake-disc.png', 16000],
        ['Air Filter', 'Engine', 'part-air-filter.png', 4500],
        ['Steering Wheel', 'Interior', 'part-steering-wheel.png', 24000],
        ['Rear Taillight - Left', 'Rear', 'part-tail-light.png', 21000],
    ];

    public function run(DemoImages $images): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DemoDataSeeder is allowed only in local/testing environments.');
        }

        $this->images = $images;
        $images->verify();
        $this->assets = $images->manifest();
        $this->call(ProductionDataSeeder::class);

        try {
            DB::transaction(function (): void {
                $adminId = $this->admins();
                [$customers, $providers] = $this->users();
                $stores = $this->stores($providers, $adminId);
                $this->customerCars($customers);
                $this->pendingRequests($customers, $adminId);
                $this->ordersAndConversations($customers, $stores);
            });
            $images->committed();
        } catch (Throwable $exception) {
            $images->rollback();
            throw $exception;
        }

        $this->command?->info('Demo data ready: 30 users, 15 stores, 30 customer cars, 45 store cars, 450 stock records, and 60 orders. Images use the shared private storage service.');
    }

    private function admins(): int
    {
        $first = null;
        foreach (['admin', 'manager', 'employee', 'hr', 'developer'] as $index => $role) {
            $email = "demo-{$role}@example.test";
            $existing = DB::table('admin')->where('email', $email)->value('employee_id');
            $id = $existing ?? SeedRecords::once('admin', ['email' => $email], [
                'name' => 'Demo '.ucfirst($role),
                'mobile' => $this->mobile(900 + $index),
                'assigned_role' => $role,
                'password' => Hash::make('DemoOnly-ChangeMe!'),
                'status' => $index === 3 ? 'inactive' : 'active',
            ], 'employee_id');
            $first ??= (int) $id;
        }

        return $first;
    }

    private function users(): array
    {
        $cities = ['Riyadh', 'Jeddah', 'Dammam', 'Makkah', 'Al Madinah'];
        $names = ['أحمد', 'خالد', 'محمد', 'عبدالله', 'عمر', 'سعد', 'يوسف', 'فهد', 'ناصر', 'علي', 'سلمان', 'حسن', 'بدر', 'ماجد', 'وليد'];
        $customers = [];
        $providers = [];
        foreach (range(0, 14) as $index) {
            $city = $cities[$index % count($cities)];
            $cityId = $this->id('cities', ['name_en' => $city, 'country_id' => $this->id('countries', ['name_en' => 'Saudi Arabia'])]);
            foreach (['customer', 'service provider'] as $type) {
                $customer = $type === 'customer';
                $id = SeedRecords::once('users', ['mobile' => $this->mobile(($customer ? 100 : 200) + $index)], [
                    'full_name' => $names[$index].($customer ? ' - عميل تجريبي' : ' - مزود تجريبي'),
                    'type' => $type,
                    'status' => 'unblocked',
                    'city_id' => $cityId,
                ]);
                $record = ['id' => $id, 'city_id' => $cityId, 'city' => $city, 'name' => $names[$index]];
                if ($customer) {
                    $customers[] = $record;
                } else {
                    $providers[] = $record;
                }
            }
        }

        return [$customers, $providers];
    }

    private function stores(array $providers, int $adminId): array
    {
        $labels = ['الرياض', 'جدة', 'الدمام', 'مكة', 'المدينة'];
        $stores = [];
        $shopImages = array_values(array_filter($this->assets, fn ($asset) => $asset['kind'] === 'store'));
        $galleryIndex = 0;
        foreach ($providers as $index => $provider) {
            $fields = [
                'user_id' => $provider['id'],
                'name' => 'متجر قطع '.($index + 1).' - '.$labels[$index % 5].' (تجريبي)',
                'mobile' => $this->mobile(300 + $index),
                'nick_name' => 'Demo Parts '.($index + 1),
                'employee_name' => $provider['name'].' - تجريبي',
                'url_location' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($provider['city'].', Saudi Arabia'),
                'commercial_registration_number' => sprintf('DEMO-CR-%03d', $index + 1),
                'commercial_registration_path' => '',
                'city_id' => $provider['city_id'],
            ];
            $id = SeedRecords::once('stores', ['commercial_registration_number' => $fields['commercial_registration_number']], [
                ...$fields, 'status' => $index < 13 ? 'active' : 'inactive',
            ]);
            $document = $index % 2 === 0 ? 'document-demo-01.png' : 'document-demo-02.png';
            $this->images->attachment('stores', $id, 'commercial_registration_', $document);

            if ($index % 4 !== 0) {
                $this->images->picture('store_pictures', 'store_id', $id, $shopImages[$galleryIndex++ % count($shopImages)]['file'], 0);
                if ($index % 2 === 0) {
                    $this->images->picture('store_pictures', 'store_id', $id, $shopImages[$galleryIndex++ % count($shopImages)]['file'], 1);
                }
            }

            $requestId = SeedRecords::once('store_requests', [
                'user_id' => $provider['id'],
                'commercial_registration_number' => $fields['commercial_registration_number'],
            ], [...$fields, 'request_status' => 'accepted', 'processed_by' => $adminId]);
            // Independent private copies prevent deletion of one record breaking another.
            $this->images->attachment('store_requests', $requestId, 'commercial_registration_', $document);
            SeedRecords::once('service_provider_requests', ['user_id' => $provider['id'], 'store_id' => $id], [
                'request_time' => now()->subDays(30), 'request_status' => 'accepted',
            ]);

            $stores[] = [
                'id' => $id,
                'provider_id' => $provider['id'],
                'city_id' => $provider['city_id'],
                'active' => $index < 13,
                'cars' => $this->storeCars($id, $index),
            ];
        }

        return $stores;
    }

    private function storeCars(int $storeId, int $storeIndex): array
    {
        $intact = $this->carAssets(false);
        $damaged = $this->carAssets(true);
        $cars = [];
        foreach (range(0, 2) as $position) {
            $broken = $position === 0 || ($position === 2 && $storeIndex % 2 === 0);
            $pool = $broken ? $damaged : $intact;
            $asset = $pool[($storeIndex + $position) % count($pool)];
            $profile = $this->profile($asset);
            $plate = $asset['plate'];
            $id = SeedRecords::once('stores_cars', ['store_id' => $storeId, 'vehicle_plat_number' => $plate], [
                'manufacturing_year' => 2024,
                'car_name_id' => $profile['car_name_id'],
                'color_id' => $profile['color_id'],
                'fuel_type' => $profile['fuel_type'],
            ]);
            SeedRecords::once('store_companies', ['store_id' => $storeId, 'company_id' => $profile['company_id']]);
            $damagedSection = match ($asset['damaged_section']) {
                'Right Side' => 'Passenger Side',
                'Left Side' => 'Driver Side',
                default => $asset['damaged_section'],
            };
            foreach (DB::table('car_sections')->whereNull('deleted_at')->get() as $section) {
                SeedRecords::once('store_car_sections', ['store_car_id' => $id, 'section_id' => $section->id], [
                    'condition' => $section->name_en === $damagedSection ? 'damaged' : 'okay',
                ]);
            }

            if (($storeIndex * 3 + $position) % 5 !== 0) {
                $this->images->picture('store_car_pictures', 'car_id', $id, $asset['file'], 0);
                if (! $broken && $asset['model'] === 'Camry') {
                    $this->images->picture('store_car_pictures', 'car_id', $id, 'car-camry-white-rear.png', 1);
                }
            }

            $components = [];
            foreach ($this->parts as $partIndex => [$name, $section, $image, $price]) {
                $componentId = $this->id('components', [
                    'name_en' => $name,
                    'section_id' => $this->id('car_sections', ['name_en' => $section]),
                ]);
                $unavailable = $section === $damagedSection;
                $stockId = SeedRecords::once('store_car_components', ['store_car_id' => $id, 'component_id' => $componentId], [
                    'part_number' => sprintf('DEMO-S%02d-C%d-P%02d', $storeIndex + 1, $position + 1, $partIndex + 1),
                    'description' => $unavailable
                        ? 'قطعة تجريبية من جهة متضررة، غير متاحة للبيع حتى الفحص.'
                        : 'قطعة غيار تجريبية. يلزم التحقق من رقم القطعة وتوافقها مع السيارة قبل الشراء.',
                    'price' => $price,
                    'stock_quantity' => $unavailable ? 0 : 8,
                    'warranty_months' => $unavailable ? 0 : 3,
                ]);
                $components[] = ['id' => $stockId, 'component_id' => $componentId, 'price' => $price, 'image' => $image, 'name' => $name];
            }
            $cars[] = ['id' => $id, 'components' => $components];
        }

        return $cars;
    }

    private function customerCars(array $customers): void
    {
        $assets = $this->carAssets(false);
        foreach ($customers as $index => $customer) {
            foreach (range(0, 1) as $position) {
                $asset = $assets[($index + $position) % count($assets)];
                $profile = $this->profile($asset);
                $id = SeedRecords::once('customer_cars', [
                    'customer_id' => $customer['id'],
                    'vehicle_plat_number' => $asset['plate'],
                ], [
                    'manufacturing_year' => 2024, 'car_name_id' => $profile['car_name_id'],
                    'color_id' => $profile['color_id'], 'fuel_type' => $profile['fuel_type'],
                ]);
                if (($index + $position) % 4 !== 0) {
                    $this->images->picture('customer_car_pictures', 'car_id', $id, $asset['file'], 0);
                    if ($asset['model'] === 'Camry') {
                        $this->images->picture('customer_car_pictures', 'car_id', $id, 'car-camry-white-rear.png', 1);
                    }
                }
            }
        }
    }

    private function pendingRequests(array $customers, int $adminId): void
    {
        foreach (array_slice($customers, 0, 10) as $index => $customer) {
            $rejected = $index >= 5;
            $id = SeedRecords::once('store_requests', [
                'user_id' => $customer['id'],
                'commercial_registration_number' => sprintf('DEMO-APPLICATION-%02d', $index + 1),
            ], [
                'name' => 'طلب متجر تجريبي '.($index + 1),
                'mobile' => $this->mobile(400 + $index),
                'nick_name' => 'Demo Application '.($index + 1),
                'employee_name' => $customer['name'],
                'url_location' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($customer['city'].', Saudi Arabia'),
                'commercial_registration_path' => '',
                'city_id' => $customer['city_id'],
                'request_status' => $rejected ? 'rejected' : 'pending',
                'processed_by' => $rejected ? $adminId : null,
                'rejection_reason' => $rejected ? 'طلب تجريبي مرفوض: المستند المرفق عينة غير صالحة.' : null,
            ]);
            $this->images->attachment('store_requests', $id, 'commercial_registration_', 'document-demo-02.png');
        }
    }

    private function ordersAndConversations(array $customers, array $stores): void
    {
        foreach ($customers as $index => $customer) {
            $localStores = array_values(array_filter($stores, fn ($store) => $store['active'] && $store['city_id'] === $customer['city_id']));
            $seller = $localStores[0];
            $otherSeller = $localStores[1];
            $car = $seller['cars'][1]; // Intact donor; selected parts have usable stock.
            $part = $car['components'][$index % count($this->parts)];
            $specificStatus = ['pending', 'negotiating', 'paid', 'rejected', 'cancelled'][$index % 5];
            $generalStatus = ['pending', 'negotiating', 'paid', 'cancelled', 'pending'][$index % 5];
            $scenarios = [
                ['general', $generalStatus, $seller, 'general-open'],
                ['specific', $specificStatus, $seller, 'specific-open'],
                ['general', 'completed', $otherSeller, 'general-completed'],
                ['specific', 'completed', $seller, 'specific-completed'],
            ];

            foreach ($scenarios as $offset => [$type, $status, $chosenStore, $label]) {
                $notes = sprintf('[DEMO:%02d:%s] %s', $index + 1, $label, $part['name']);
                $accepted = in_array($status, ['negotiating', 'paid', 'completed'], true);
                $identity = ['customer_id' => $customer['id'], 'notes' => $notes];
                $isNew = ! DB::table('orders')->where($identity)->exists();
                $price = $type === 'specific' || $accepted ? $part['price'] : null;
                $orderId = SeedRecords::once('orders', $identity, [
                    'order_type' => $type,
                    'status' => $status,
                    'quantity' => 1,
                    'offered_price' => $price,
                    'component_id' => $type === 'general' && $offset === 0 ? $part['component_id'] : null,
                    'component_name' => $type === 'general' && $offset !== 0 ? $part['name'] : null,
                    'store_car_component_id' => $type === 'specific' ? $part['id'] : null,
                    'accepted_store_id' => $type === 'general' && $accepted ? $chosenStore['id'] : null,
                    'created_at' => now()->subDays(20 - $offset),
                    'updated_at' => now()->subDays(10 - $offset),
                ]);
                if ($type === 'general') {
                    $vehicle = DB::table('stores_cars')->where('id', $car['id'])->first();
                    $name = DB::table('cars_names')->where('id', $vehicle->car_name_id)->first();
                    $company = DB::table('cars_companies')->where('id', $name->car_company_id)->first();
                    $color = DB::table('colors')->where('id', $vehicle->color_id)->first();
                    $fuel = DB::table('fuel_types')->where('id', $vehicle->fuel_type)->first();
                    SeedRecords::once('order_vehicle_details', ['order_id' => $orderId], [
                        'car_name_id' => $name->id, 'car_company_id' => $company->id,
                        'car_name_en' => $name->name_en, 'car_name_ar' => $name->name_ar,
                        'company_name_en' => $company->name_en, 'company_name_ar' => $company->name_ar,
                        'manufacturing_year' => $vehicle->manufacturing_year, 'transmission_type' => 'unknown',
                        'color_id' => $color->id, 'color_name_en' => $color->name_en, 'color_name_ar' => $color->name_ar,
                        'fuel_type' => $fuel->id, 'fuel_type_en' => $fuel->type_en, 'fuel_type_ar' => $fuel->type_ar,
                    ]);
                }
                if (($index + $offset) % 4 !== 0) {
                    $this->images->attachment('orders', $orderId, 'customer_image_', $part['image']);
                }

                // Keep changes made by a tester on reruns: transactions are fixtures only once.
                if ($isNew) {
                    if ($type === 'general' && $status !== 'cancelled') {
                        foreach (array_slice($localStores, 0, 2) as $candidate) {
                            $winner = $accepted && $candidate['id'] === $chosenStore['id'];
                            SeedRecords::once('order_offers', ['order_id' => $orderId, 'store_id' => $candidate['id']], [
                                'price' => $part['price'] + ($winner || ! $accepted ? 0 : 2500),
                                'notes' => 'عرض تجريبي لقطعة مطابقة بعد التحقق من رقمها.',
                                'status' => ! $accepted ? 'pending' : ($winner ? 'accepted' : 'rejected'),
                                'rejection_reason' => $accepted && ! $winner ? 'تم اختيار عرض آخر في السيناريو التجريبي.' : null,
                            ]);
                        }
                    }
                    if (in_array($status, ['paid', 'completed'], true)) {
                        SeedRecords::once('payments', ['order_id' => $orderId], [
                            'amount' => $price, 'payment_method' => 'credit_card', 'payment_status' => 'paid',
                        ]);
                        if ($type === 'specific') {
                            $deducted = DB::table('store_car_components')->where('id', $part['id'])->where('stock_quantity', '>=', 1)->decrement('stock_quantity');
                            if ($deducted !== 1) {
                                throw new RuntimeException('Insufficient demo stock; transaction rolled back.');
                            }
                        }
                    }
                    if ($status === 'completed') {
                        SeedRecords::once('ratings', ['customer_id' => $customer['id'], 'store_id' => $chosenStore['id']], [
                            'order_id' => $orderId,
                            'rating' => 3 + $index % 3,
                            'comment' => ['تقييم تجريبي: القطعة مناسبة والتعامل واضح.', 'تقييم تجريبي: تم الاستلام كما هو متفق عليه.', 'تقييم تجريبي: خدمة جيدة ويمكن تحسين سرعة التسليم.'][$index % 3],
                        ]);
                    }
                }

                if ($type === 'specific' && $offset === 1) {
                    $this->notification("order-{$orderId}", $seller['provider_id'], NewOrderNotification::class, [
                        'type' => 'new_order', 'order_id' => $orderId, 'order_type' => $type,
                        'message' => 'طلب تجريبي جديد رقم '.$orderId,
                    ], $index % 2 === 0);
                }
            }

            foreach (array_slice($localStores, 0, 2) as $store) {
                $this->conversation($customer, $store, $index);
            }
        }
    }

    private function conversation(array $customer, array $store, int $index): void
    {
        $conversationId = SeedRecords::once('conversations', ['customer_id' => $customer['id'], 'provider_id' => $store['provider_id']]);
        $messages = [
            [$customer['id'], 'السلام عليكم، هل يمكن التأكد من توافق القطعة مع سيارتي؟'],
            [$store['provider_id'], 'وعليكم السلام، أرسل اسم السيارة وسنة الصنع ورقم القطعة إن توفر.'],
            [$customer['id'], 'شكراً، سأراجع البيانات قبل تأكيد الطلب.'],
            [$store['provider_id'], 'حياك الله، يمكننا توضيح حالة القطعة وموعد الاستلام.'],
        ];
        foreach ($messages as $position => [$sender, $content]) {
            $messageId = SeedRecords::once('messages', [
                'conversation_id' => $conversationId, 'sender_id' => $sender, 'content' => $content,
            ], [
                'is_read' => $position < 3 || $index % 2 === 0,
                'created_at' => now()->subDays(2)->addMinutes($position * 5),
                'updated_at' => now()->subDays(2)->addMinutes($position * 5),
            ]);
            if ($position === 3) {
                $this->notification("message-{$messageId}", $customer['id'], NewMessageNotification::class, [
                    'type' => 'new_message', 'conversation_id' => $conversationId,
                    'message_id' => $messageId, 'sender_id' => $sender,
                    'message' => 'لديك رسالة تجريبية جديدة من المتجر.',
                ], $index % 2 === 0);
            }
        }
    }

    private function notification(string $key, int $userId, string $type, array $data, bool $read): void
    {
        $hash = md5('octogear-demo:'.$key.':'.$userId);
        $uuid = substr($hash, 0, 8).'-'.substr($hash, 8, 4).'-4'.substr($hash, 13, 3).'-a'.substr($hash, 17, 3).'-'.substr($hash, 20, 12);
        if (! DB::table('notifications')->where('id', $uuid)->exists()) {
            DB::table('notifications')->insert([
                'id' => $uuid, 'type' => $type,
                'notifiable_type' => (new User)->getMorphClass(), 'notifiable_id' => $userId,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                'read_at' => $read ? now()->subDay() : null,
                'created_at' => now()->subDays(2), 'updated_at' => now()->subDay(),
            ]);
        }
    }

    private function carAssets(bool $damaged): array
    {
        // The rear Camry photo is the second angle of the same vehicle.
        return array_values(array_filter($this->assets, fn ($asset) => $asset['kind'] === 'car'
            && $asset['damaged'] === $damaged && $asset['id'] !== 'car-camry-white-rear'));
    }

    private function profile(array $asset): array
    {
        $companyId = $this->id('cars_companies', ['name_en' => $asset['make']]);
        $carNameId = $this->id('cars_names', ['car_company_id' => $companyId, 'name_en' => $asset['model']]);

        return [
            'company_id' => $companyId, 'car_name_id' => $carNameId,
            'model_id' => $this->id('models', ['car_name_id' => $carNameId, 'name_en' => $asset['model'].' 2024']),
            'color_id' => $this->id('colors', ['name_en' => $asset['color']]),
            'fuel_type' => $this->id('fuel_types', ['type_en' => 'Gasoline']),
        ];
    }

    private function id(string $table, array $identity): int
    {
        $id = DB::table($table)->where($identity)->whereNull('deleted_at')->orderBy('id')->value('id');
        if ($id === null) {
            throw new RuntimeException('Missing active reference in '.$table.': '.json_encode($identity));
        }

        return (int) $id;
    }

    private function mobile(int $suffix): string
    {
        return '+966500'.sprintf('%06d', $suffix);
    }
}
