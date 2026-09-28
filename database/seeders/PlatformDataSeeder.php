<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedRecords;
use Illuminate\Database\Seeder;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class PlatformDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $settings = [
                'platform_fee_percentage' => (string) config('payments.commission_rate', 5),
                'min_order_amount' => '50',
                'max_order_amount' => '50000',
                'default_currency' => 'SAR',
                'platform_name' => (string) config('app.name', 'OctoGear'),
                'platform_name_ar' => 'أوكتوجير',
                'support_email' => '',
                'support_phone' => '',
                'terms_url' => '',
                'privacy_url' => '',
                'min_store_distance_km' => '0',
                'max_delivery_distance_km' => '100',
            ];
            foreach ($settings as $key => $value) {
                SeedRecords::once('platform_settings', ['key' => $key], ['value' => $value]);
            }

            $pages = [
                'welcome' => ['Welcome. Find car parts and contact stores through the platform.', 'مرحباً بك. ابحث عن قطع غيار السيارات وتواصل مع المتاجر عبر المنصة.'],
                'about' => ['This platform connects customers with car parts stores.', 'تربط هذه المنصة العملاء بمتاجر قطع غيار السيارات.'],
                'terms' => ['Terms have not been published by the platform operator yet.', 'لم ينشر مشغل المنصة الشروط والأحكام بعد.'],
                'privacy' => ['The privacy notice has not been published by the platform operator yet.', 'لم ينشر مشغل المنصة إشعار الخصوصية بعد.'],
            ];
            foreach ($pages as $type => [$english, $arabic]) {
                SeedRecords::once('cms', ['type' => $type], ['english_text' => $english, 'arabic_text' => $arabic]);
            }

            $this->bootstrapAdmin();
        });
    }

    private function bootstrapAdmin(): void
    {
        // Optional one-time bootstrap; export these variables when config is cached.
        $email = Env::get('SEED_ADMIN_EMAIL');
        $password = Env::get('SEED_ADMIN_PASSWORD');
        $mobile = Env::get('SEED_ADMIN_MOBILE');
        if ($email === null && $password === null && $mobile === null) {
            return;
        }

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! is_string($password) || strlen($password) < 12
            || ! is_string($mobile) || ! preg_match('/^\\+9665[0-9]{8}$/', $mobile)) {
            throw new InvalidArgumentException('Set a valid SEED_ADMIN_EMAIL, SEED_ADMIN_MOBILE (+9665xxxxxxxx), and SEED_ADMIN_PASSWORD (at least 12 characters).');
        }

        if (! DB::table('admin')->where('email', $email)->exists()) {
            SeedRecords::once('admin', ['email' => $email], [
                'name' => (string) Env::get('SEED_ADMIN_NAME', 'Platform administrator'),
                'mobile' => $mobile,
                'password' => Hash::make($password),
                'assigned_role' => 'admin',
                'status' => 'active',
            ], 'employee_id');
        }
    }
}
