<?php

/**
 * Run: php database/seeders/verify.php
 * Uses an isolated in-memory SQLite connection and temporary private disk.
 * Does not migrate, seed, or clear the configured application database.
 */
use App\Services\ImageStorageService;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\Support\DemoImages;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
foreach (['SEED_ADMIN_EMAIL', 'SEED_ADMIN_PASSWORD', 'SEED_ADMIN_MOBILE', 'SEED_ADMIN_NAME'] as $key) {
    unset($_ENV[$key], $_SERVER[$key]);
    putenv($key);
}
$app['env'] = 'testing';
$scratch = sys_get_temp_dir().DIRECTORY_SEPARATOR.'octogear-seed-'.bin2hex(random_bytes(8));
config([
    'app.env' => 'testing',
    'database.default' => 'seed_verification',
    'database.connections.seed_verification' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        'foreign_key_constraints' => true, 'url' => null,
    ],
    'database.seed_test_data' => false,
    'images.disk' => 'seed_verification',
    'customer_car_media.disk' => 'seed_verification',
    'filesystems.disks.seed_verification' => ['driver' => 'local', 'root' => $scratch, 'throw' => true],
    'cache.default' => 'array',
    'queue.default' => 'sync',
    'mail.default' => 'array',
    'hashing.bcrypt.rounds' => 4,
]);

$seed = static function (string $class) use ($assert): void {
    $exit = Artisan::call('db:seed', ['--class' => $class, '--force' => true]);
    $assert($exit === 0, Artisan::output());
};
$counts = static function (): array {
    $result = [];
    foreach (DB::connection()->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
        $result[$table] = DB::table($table)->count();
    }
    ksort($result);

    return $result;
};
$state = static function (): string {
    $all = [];
    foreach (DB::connection()->getSchemaBuilder()->getTableListing(schemaQualified: false) as $table) {
        $rows = DB::table($table)->get()->map(fn ($row) => json_encode($row))->all();
        sort($rows);
        $all[$table] = $rows;
    }
    ksort($all);

    return hash('sha256', json_encode($all));
};

$failure = null;
try {
    $assert(Artisan::call('migrate', ['--database' => 'seed_verification', '--force' => true]) === 0, Artisan::output());
    // Non-sequential existing IDs exercise the original off-by-one failure.
    DB::table('countries')->insert(['id' => 91, 'name_en' => 'Saudi Arabia', 'name_ar' => 'السعودية', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cars_companies')->insert(['id' => 77, 'name_en' => 'Toyota', 'name_ar' => 'تويوتا', 'country_id' => 91, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cars_names')->insert(['id' => 123, 'name_en' => 'Camry', 'name_ar' => 'كامري', 'car_company_id' => 77, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('platform_settings')->insert(['key' => 'support_email', 'value' => 'operator@example.test', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('cms')->insert(['type' => 'privacy', 'english_text' => 'Operator-managed published text', 'arabic_text' => 'نص يديره المشغل', 'created_at' => now(), 'updated_at' => now()]);
    $seed('Database\\Seeders\\ProductionDataSeeder');
    $referenceState = $state();
    $referenceCounts = $counts();
    $assert(DB::table('cars_companies')->count() >= 100, 'Expected expanded Saudi make coverage.');
    $assert(DB::table('cars_names')->count() >= 1800, 'Expected current and historical nameplates.');
    $cities = json_decode(file_get_contents(__DIR__.'/data/saudi-cities.json'), true, flags: JSON_THROW_ON_ERROR);
    $assert(count($cities['cities']) === 4581 && count(array_unique(array_column($cities['cities'], 'region_id'))) === 13, 'Saudi locality snapshot is incomplete.');
    $assert(DB::table('cities')->where('country_id', 91)->count() === 4581, 'Saudi localities were lost through duplicate names.');
    $cityNames = DB::table('cities')->where('country_id', 91)->pluck('name_ar', 'name_en')->all();
    foreach ($cities['cities'] as $city) {
        $assert(($cityNames[$city['name_en']] ?? null) === $city['name_ar'], 'Missing bilingual locality: '.$city['name_en']);
    }
    foreach (['Toyota' => 'Prado', 'Ford' => 'Crown Victoria', 'Chevrolet' => 'Caprice', 'Chery' => 'Tiggo 9', 'KGM' => 'Torres', 'Hongqi' => 'H9', 'Changan' => 'Alsvin', 'Geely' => 'Emgrand'] as $make => $model) {
        $assert(DB::table('cars_names')->join('cars_companies', 'cars_companies.id', '=', 'cars_names.car_company_id')
            ->where('cars_companies.name_en', $make)->where('cars_names.name_en', $model)->exists(), "Missing {$make} {$model}.");
    }
    $assert(DB::table('cars_names')->selectRaw('car_company_id, LOWER(name_en) as normalized_name, COUNT(*) as total')
        ->groupBy('car_company_id')->groupByRaw('LOWER(name_en)')->havingRaw('COUNT(*) > 1')->get()->isEmpty(), 'Overlapping sources created duplicate names.');
    $hondaId = DB::table('cars_companies')->where('name_en', 'Honda')->value('id');
    $assert(! DB::table('cars_names')->where('car_company_id', $hondaId)->whereIn('name_en', ['Gold Wing', 'Grom', 'CBR1000RR'])->exists(), 'Motorcycles leaked into the car catalogue.');
    $assert(DB::table('users')->count() === 0 && DB::table('admin')->count() === 0, 'Production seeding created fixture accounts.');
    $assert(DB::table('platform_settings')->where('key', 'support_email')->value('value') === 'operator@example.test', 'Operator setting overwritten.');
    $assert(DB::table('cms')->where('type', 'privacy')->value('english_text') === 'Operator-managed published text', 'Published CMS content overwritten.');
    $assert(DB::table('cars_names')->where('id', 123)->value('car_company_id') === 77, 'Existing catalogue IDs changed.');
    $seed('Database\\Seeders\\ProductionDataSeeder');
    $assert($state() === $referenceState, 'Production rerun changed records.');

    $failingStorage = new class(app(Factory::class)) extends ImageStorageService
    {
        private int $writes = 0;

        public function store(UploadedFile $file, string $directory, array &$storedFiles, ?string $disk = null): array
        {
            $result = parent::store($file, $directory, $storedFiles, $disk);
            if (++$this->writes === 2) {
                throw new RuntimeException('Simulated demo write failure');
            }

            return $result;
        }
    };
    try {
        app(DemoDataSeeder::class)->setContainer($app)->run(new DemoImages($failingStorage));
        throw new RuntimeException('Expected simulated storage failure.');
    } catch (RuntimeException $error) {
        $assert($error->getMessage() === 'Simulated demo write failure', $error->getMessage());
    }
    $assert($state() === $referenceState, 'Failed demo seed left database rows behind.');
    $assert(Storage::disk('seed_verification')->allFiles() === [], 'Failed demo seed left uploaded files behind.');

    $seed('Database\\Seeders\\DemoDataSeeder');
    $firstCounts = $counts();
    foreach (['users' => 40, 'admin' => 5, 'stores' => 15, 'customer_cars' => 39, 'stores_cars' => 45, 'store_car_components' => 450, 'orders' => 100, 'ratings' => 50, 'conversations' => 50, 'messages' => 200, 'store_requests' => 25] as $table => $expected) {
        $assert($firstCounts[$table] === $expected, "{$table}: expected {$expected}, found {$firstCounts[$table]}");
    }
    $assert(DB::select('PRAGMA foreign_key_check') === [], 'Foreign-key integrity failed.');
    foreach (['otp_codes', 'personal_access_tokens', 'device_tokens', 'pending_image_deletions'] as $table) {
        $assert(DB::table($table)->count() === 0, "Unexpected operational data: {$table}");
    }
    $assert(DB::table('store_car_components')->where('stock_quantity', '<', 0)->count() === 0, 'Negative inventory.');
    foreach (DB::table('users')->where('type', 'customer')->get() as $customer) {
        $orders = DB::table('orders')->where('customer_id', $customer->id)->pluck('id');
        $assert($orders->count() === 4, 'A demo customer is missing orders.');
        $assert(DB::table('order_offers')->whereIn('order_id', $orders)->count() >= 2, 'A demo customer has no competing offers.');
    }
    $assert(DB::table('users')->where('type', 'customer')->whereNotIn('id', DB::table('customer_cars')->select('customer_id'))->count() === 4, 'Missing customers without saved cars.');
    $assert(DB::table('order_offers')->whereNotIn('id', DB::table('offer_images')->select('order_offer_id'))->count() === 0, 'A demo offer is missing its picture.');
    foreach (DB::table('orders')->get() as $order) {
        $offers = DB::table('order_offers')->where('order_id', $order->id)->get();
        if ($order->order_type === 'specific') {
            $assert($offers->isEmpty() && $order->store_car_component_id !== null, 'Specific order has bids or lacks inventory.');
        } elseif (in_array($order->status, ['awaiting_payment', 'paid', 'completed'], true)) {
            $accepted = $offers->where('status', 'accepted');
            $notSelected = $offers->where('status', 'not_selected');
            $assert($accepted->count() === 1
                && $accepted->first()->id === $order->accepted_offer_id
                && $accepted->first()->store_id === DB::table('order_offers')->where('id', $order->accepted_offer_id)->value('store_id')
                && $accepted->first()->price === $order->offered_price
                && $notSelected->count() === $offers->count() - 1, 'General order accepted offer mismatch.');
        } else {
            $assert($order->accepted_offer_id === null, 'Unaccepted general order has a winning offer.');
        }
        $payment = DB::table('payments')->where('order_id', $order->id)->first();
        $assert(($payment !== null) === in_array($order->status, ['paid', 'completed'], true), 'Order/payment state mismatch.');
        if ($payment) {
            $expectedAmount = $order->order_type === 'general'
                ? DB::table('order_offers')->where('id', $order->accepted_offer_id)->value('price')
                : $order->offered_price * $order->quantity;
            $assert($payment->amount === $expectedAmount && $payment->payment_status === 'paid', 'Payment amount/status mismatch.');
        }
    }
    foreach (DB::table('ratings')->get() as $rating) {
        $order = DB::table('orders')->where('id', $rating->order_id)->first();
        $owner = DB::table('order_offers')->where('id', $order->accepted_offer_id)->value('store_id');
        if ($order->order_type === 'specific') {
            $owner = DB::table('store_car_components')->join('stores_cars', 'stores_cars.id', '=', 'store_car_components.store_car_id')->where('store_car_components.id', $order->store_car_component_id)->value('stores_cars.store_id');
        }
        $assert($order->status === 'completed' && $order->customer_id === $rating->customer_id && $owner === $rating->store_id, 'Rating does not belong to the completed purchase.');
    }

    $mediaCount = 0;
    foreach (['customer_car_pictures' => '', 'store_car_pictures' => '', 'store_pictures' => '', 'stores' => 'commercial_registration_', 'store_requests' => 'commercial_registration_', 'order_images' => '', 'offer_images' => ''] as $table => $prefix) {
        $rows = DB::table($table)->whereNotNull($prefix.'disk')->get();
        $assert($rows->isNotEmpty(), "No images seeded for {$table}.");
        foreach ($rows as $row) {
            $disk = $row->{$prefix.'disk'};
            $path = $row->{$prefix.'path'};
            $assert($disk === 'seed_verification' && Storage::disk($disk)->exists($path), "Missing private image: {$table}/{$row->id}");
            $assert(Storage::disk($disk)->size($path) === $row->{$prefix.'size_bytes'}, 'Image metadata size mismatch.');
            $assert(Storage::disk($disk)->mimeType($path) === $row->{$prefix.'mime_type'}, 'Image metadata MIME mismatch.');
            $mediaCount++;
        }
    }
    foreach (['customer_cars' => ['customer_car_pictures', 'car_id'], 'stores_cars' => ['store_car_pictures', 'car_id'], 'stores' => ['store_pictures', 'store_id']] as $table => [$pictures, $foreignKey]) {
        $without = DB::table($table)->whereNotIn('id', DB::table($pictures)->select($foreignKey))->count();
        $assert($without > 0 && $without < DB::table($table)->count(), "Expected a mixture of records with and without pictures: {$table}");
    }
    $assert(DB::table('store_car_sections')->where('condition', 'damaged')->count() >= 15, 'Missing damaged donor scenarios.');
    $snapshot = $state();
    $files = Storage::disk('seed_verification')->allFiles();
    sort($files);
    $assert(count($files) === $mediaCount, 'Private files are shared between records or orphaned.');
    $usedHashes = array_map(fn ($file) => hash('sha256', Storage::disk('seed_verification')->get($file)), $files);
    $assert(count(array_unique($usedHashes)) === 36, 'Demo must reuse the existing 36 assets without generating new pictures.');
    foreach (app(DemoImages::class)->manifest() as $asset) {
        $assert(in_array(hash_file('sha256', __DIR__.'/assets/images/'.$asset['file']), $usedHashes, true), 'Unused seed image: '.$asset['file']);
    }
    $seed('Database\\Seeders\\DemoDataSeeder');
    $rerunFiles = Storage::disk('seed_verification')->allFiles();
    sort($rerunFiles);
    $assert($state() === $snapshot && $files === $rerunFiles, 'Demo rerun duplicated or changed records/files.');

    // A missing private copy is repaired from the committed seed asset.
    $picture = DB::table('customer_car_pictures')->first();
    Storage::disk($picture->disk)->delete($picture->path);
    $offerPicture = DB::table('offer_images')->first();
    Storage::disk($offerPicture->disk)->delete($offerPicture->path);
    // Simulate a pre-gallery demo offer: rerunning must backfill the missing row.
    DB::table('offer_images')->where('id', $offerPicture->id)->delete();
    $seed('Database\\Seeders\\DemoDataSeeder');
    $repaired = DB::table('customer_car_pictures')->where('id', $picture->id)->first();
    $assert($repaired->path !== $picture->path && Storage::disk($repaired->disk)->exists($repaired->path), 'Missing private image was not repaired.');
    $repairedOffer = DB::table('offer_images')->where('order_offer_id', $offerPicture->order_offer_id)->first();
    $assert($repairedOffer !== null && Storage::disk($repairedOffer->disk)->exists($repairedOffer->path), 'Existing demo offer gallery was not backfilled.');
    $assert(count(Storage::disk('seed_verification')->allFiles()) === count($files), 'Repair leaked private files.');

    $guardState = $state();
    $app['env'] = 'production';
    foreach (['Database\\Seeders\\DemoDataSeeder', 'Database\\Seeders\\TestDataSeeder', 'Database\\Seeders\\DatabaseSeeder'] as $class) {
        config(['database.seed_test_data' => true]);
        try {
            $seed($class);
            throw new RuntimeException('Expected production demo guard.');
        } catch (RuntimeException $error) {
            $assert(str_contains($error->getMessage(), 'local/testing'), $error->getMessage());
        }
    }
    $assert($state() === $guardState, 'Production guard allowed writes.');

    // Optional production admin bootstrap must hash credentials and never reset them.
    $environment = Env::getRepository();
    foreach (['SEED_ADMIN_EMAIL' => 'bootstrap@example.test', 'SEED_ADMIN_PASSWORD' => 'FixtureBootstrap-Only!', 'SEED_ADMIN_MOBILE' => '+966500009999'] as $key => $value) {
        $environment->set($key, $value);
    }
    $seed('Database\\Seeders\\PlatformDataSeeder');
    $admin = DB::table('admin')->where('email', 'bootstrap@example.test')->first();
    $assert($admin !== null && Hash::check('FixtureBootstrap-Only!', $admin->password), 'Bootstrap credentials were not hashed.');
    $environment->set('SEED_ADMIN_PASSWORD', 'DifferentFixture-Only!');
    $seed('Database\\Seeders\\PlatformDataSeeder');
    $assert(DB::table('admin')->where('employee_id', $admin->employee_id)->value('password') === $admin->password, 'Bootstrap reset an existing admin password.');
    $environment->clear('SEED_ADMIN_PASSWORD');
    $beforeInvalidBootstrap = $state();
    try {
        $seed('Database\\Seeders\\PlatformDataSeeder');
        throw new RuntimeException('Incomplete bootstrap credentials were accepted.');
    } catch (InvalidArgumentException $error) {
        $assert(str_contains($error->getMessage(), 'SEED_ADMIN_PASSWORD'), 'Unexpected bootstrap validation failure.');
    }
    $assert($state() === $beforeInvalidBootstrap, 'Invalid bootstrap changed data.');
    foreach (['SEED_ADMIN_EMAIL', 'SEED_ADMIN_PASSWORD', 'SEED_ADMIN_MOBILE'] as $key) {
        $environment->clear($key);
    }

    $app['env'] = 'testing';
    config(['database.seed_test_data' => false]);
    DB::table('users')->where('mobile', '+966500000100')->update(['full_name' => 'Edited demo customer']);
    $pending = DB::table('orders')->where('order_type', 'specific')->where('status', 'pending')->first();
    DB::table('orders')->where('id', $pending->id)->update(['status' => 'awaiting_payment']);
    $editedState = $state();
    $seed('Database\\Seeders\\DemoDataSeeder');
    $assert($state() === $editedState, 'Rerun reset tester edits.');
    echo json_encode(['result' => 'PASS', 'reference_counts' => array_intersect_key($referenceCounts, array_flip(['countries', 'cities', 'cars_companies', 'cars_names', 'models', 'car_sections', 'components'])), 'demo_counts' => $firstCounts, 'private_images' => $mediaCount], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;
} catch (Throwable $error) {
    $failure = $error;
} finally {
    DB::disconnect('seed_verification');
    Storage::forgetDisk('seed_verification');
    $resolved = realpath($scratch);
    $allowedPrefix = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'octogear-seed-';
    if ($resolved !== false && str_starts_with($resolved, $allowedPrefix)) {
        (new Filesystem)->deleteDirectory($resolved);
    }
}

if ($failure !== null) {
    fwrite(STDERR, $failure->getMessage().PHP_EOL.$failure->getTraceAsString().PHP_EOL);
    exit(1);
}
