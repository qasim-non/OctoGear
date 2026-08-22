<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PlatformDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->seedPlatformSettings();
        $this->seedCms();
        $this->seedDefaultAdmin();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedPlatformSettings(): void
    {
        $now = Carbon::now();

        $settings = [
            ['key' => 'platform_fee_percentage', 'value' => '5'],
            ['key' => 'min_order_amount', 'value' => '50'],
            ['key' => 'max_order_amount', 'value' => '50000'],
            ['key' => 'default_currency', 'value' => 'SAR'],
            ['key' => 'platform_name', 'value' => 'YARDY'],
            ['key' => 'platform_name_ar', 'value' => 'ياردي'],
            ['key' => 'support_email', 'value' => 'support@yardy.com'],
            ['key' => 'support_phone', 'value' => '+966500000000'],
            ['key' => 'terms_url', 'value' => 'https://yardy.com/terms'],
            ['key' => 'privacy_url', 'value' => 'https://yardy.com/privacy'],
            ['key' => 'min_store_distance_km', 'value' => '0'],
            ['key' => 'max_delivery_distance_km', 'value' => '100'],
        ];

        $rows = array_map(fn (array $setting): array => [
            ...$setting,
            'created_at' => $now,
            'updated_at' => $now,
        ], $settings);

        // Single INSERT ... ON DUPLICATE KEY UPDATE ('key' has a unique index).
        DB::table('platform_settings')->upsert($rows, ['key'], ['value', 'updated_at']);

        $this->command->info('Seeded ' . count($settings) . ' platform settings');
    }

    private function seedCms(): void
    {
        $now = Carbon::now();

        $pages = [
            [
                'english_text' => 'Welcome to YARDY — the car parts marketplace that connects you with trusted service providers. Find the right parts for your car at the best prices.',
                'arabic_text' => 'مرحباً بكم في ياردي — سوق قطع الغيار الذي يربطكم بمزودي الخدمات الموثوقين. اعثر على القطع المناسبة لسيارتك بأفضل الأسعار.',
            ],
            [
                'english_text' => 'About Us: YARDY is a leading car parts marketplace in Saudi Arabia. We connect customers with verified service providers, ensuring quality parts and competitive prices.',
                'arabic_text' => 'من نحن: ياردي هو السوق الرائد لقطع الغيار في المملكة العربية السعودية. نربط العملاء بمزودي الخدمات المعتمدين، لضمان قطع عالية الأسعار التنافسية.',
            ],
            [
                'english_text' => 'Terms and Conditions: By using the YARDY platform, you agree to our terms of service. All transactions are subject to our policies.',
                'arabic_text' => 'الشروط والأحكام: باستخدامك لمنصة ياردي، فإنك توافق على شروط الخدمة. جميع المعاملات خاضعة لسياساتنا.',
            ],
            [
                'english_text' => 'Privacy Policy: We value your privacy. Your personal information is protected and will never be shared with third parties without your consent.',
                'arabic_text' => 'سياسة الخصوصية: نحن نقدر خصوصيتك. معلوماتك الشخصية محمية ولن تتم مشاركتها مع أطراف ثالثة بدون موافقتك.',
            ],
        ];

        $rows = array_map(fn (array $page): array => [
            ...$page,
            'created_at' => $now,
            'updated_at' => $now,
            'deleted_at' => null,
        ], $pages);

        DB::table('cms')->insert($rows);

        $this->command->info('Seeded ' . count($pages) . ' CMS pages');
    }

    private function seedDefaultAdmin(): void
    {
        Admin::create([
            'name' => 'Admin',
            'assigned_role' => AdminRole::Admin,
            'mobile' => '+966500000000',
            'email' => 'admin@yardy.com',
            'password' => Hash::make('password'),
            'status' => AdminStatus::Active,
        ]);

        $this->command->info('Seeded default admin (admin@yardy.com / password)');
    }
}
