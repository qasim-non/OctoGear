<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->seedCountries($now);
        $this->seedSaudiCities($now);
        $this->seedFuelTypes($now);
        $this->seedColors($now);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedCountries(Carbon $now): void
    {
        $rows = array_map(fn ($c) => [
            'name_en' => $c[0],
            'name_ar' => $c[1],
            'created_at' => $now,
            'updated_at' => $now,
        ], [
            ['Saudi Arabia', 'المملكة العربية السعودية'],
            ['United Arab Emirates', 'الإمارات العربية المتحدة'],
            ['Kuwait', 'الكويت'],
            ['Qatar', 'قطر'],
            ['Bahrain', 'البحرين'],
            ['Oman', 'عُمان'],
            ['Jordan', 'الأردن'],
            ['Lebanon', 'لبنان'],
            ['Iraq', 'العراق'],
            ['Syria', 'سوريا'],
            ['Palestine', 'فلسطين'],
            ['Yemen', 'اليمن'],
            ['Egypt', 'مصر'],
            ['Sudan', 'السودان'],
            ['Libya', 'ليبيا'],
            ['Tunisia', 'تونس'],
            ['Algeria', 'الجزائر'],
            ['Morocco', 'المغرب'],
            ['Mauritania', 'موريتانيا'],
            ['Iran', 'إيران'],
            ['China', 'الصين'],
            ['Japan', 'اليابان'],
            ['South Korea', 'كوريا الجنوبية'],
            ['India', 'الهند'],
            ['Pakistan', 'باكستان'],
            ['Turkey', 'تركيا'],
            ['Thailand', 'تايلاند'],
            ['Indonesia', 'إندونيسيا'],
            ['Malaysia', 'ماليزيا'],
            ['Philippines', 'الفلبين'],
            ['Vietnam', 'فيتنام'],
            ['Bangladesh', 'بنغلاديش'],
            ['Sri Lanka', 'سريلانكا'],
            ['Myanmar', 'ميانمار'],
            ['Cambodia', 'كمبوديا'],
            ['Singapore', 'سنغافورة'],
            ['Germany', 'ألمانيا'],
            ['France', 'فرنسا'],
            ['United Kingdom', 'المملكة المتحدة'],
            ['Italy', 'إيطاليا'],
            ['Spain', 'إسبانيا'],
            ['Netherlands', 'هولندا'],
            ['Belgium', 'بلجيكا'],
            ['Switzerland', 'سويسرا'],
            ['Sweden', 'السويد'],
            ['Norway', 'النرويج'],
            ['Denmark', 'الدنمارك'],
            ['Finland', 'فنلندا'],
            ['Poland', 'بولندا'],
            ['Portugal', 'البرتغال'],
            ['Austria', 'النمسا'],
            ['Ireland', 'أيرلندا'],
            ['Greece', 'اليونان'],
            ['Czech Republic', 'التشيك'],
            ['Romania', 'رومانيا'],
            ['Hungary', 'المجر'],
            ['Ukraine', 'أوكرانيا'],
            ['Russia', 'روسيا'],
            ['Croatia', 'كرواتيا'],
            ['Serbia', 'صربيا'],
            ['Bulgaria', 'بلغاريا'],
            ['Slovakia', 'سلوفاكيا'],
            ['Slovenia', 'سلوفينيا'],
            ['Lithuania', 'ليتوانيا'],
            ['Latvia', 'لاتفيا'],
            ['Estonia', 'إستونيا'],
            ['United States', 'الولايات المتحدة'],
            ['Canada', 'كندا'],
            ['Mexico', 'المكسيك'],
            ['Brazil', 'البرازيل'],
            ['Argentina', 'الأرجنتين'],
            ['Colombia', 'كولومبيا'],
            ['Chile', 'تشيلي'],
            ['Peru', 'بيرو'],
            ['Venezuela', 'فنزويلا'],
            ['Ecuador', 'الإكوادور'],
            ['South Africa', 'جنوب أفريقيا'],
            ['Nigeria', 'نيجيريا'],
            ['Kenya', 'كينيا'],
            ['Ethiopia', 'إثيوبيا'],
            ['Ghana', 'غانا'],
            ['Tanzania', 'تنزانيا'],
            ['Senegal', 'السنغال'],
            ['Cameroon', 'الكاميرون'],
            ['Ivory Coast', 'ساحل العاج'],
            ['Uganda', 'أوغندا'],
            ['Rwanda', 'رواندا'],
            ['Djibouti', 'جيبوتي'],
            ['Somalia', 'الصومال'],
            ['Comoros', 'جزر القمر'],
            ['Australia', 'أستراليا'],
            ['New Zealand', 'نيوزيلندا'],
        ]);

        DB::table('countries')->insert($rows);

        $this->command->info('Seeded ' . count($rows) . ' countries (1 query)');
    }

    private function seedSaudiCities(Carbon $now): void
    {
        $countryId = DB::table('countries')->where('name_en', 'Saudi Arabia')->value('id');

        $cities = [
            ['Riyadh', 'الرياض'], ['Al Kharj', 'الخرج'], ['Al Majmaah', 'المجمعة'],
            ['Al Zulfi', 'الزلفي'], ['Ad Dilam', 'الدلم'], ['Jeddah', 'جدة'],
            ['Makkah', 'مكة المكرمة'], ['At Taif', 'الطائف'], ['Rabigh', 'رابغ'],
            ['Al Qunfudhah', 'القنفذة'], ['Al Madinah', 'المدينة المنورة'],
            ['Yanbu', 'ينبع'], ['Al Ula', 'العلا'], ['Dammam', 'الدمام'],
            ['Al Dhahran', 'ظهران'], ['Al Khobar', 'الخبر'], ['Al Jubail', 'الجبيل'],
            ['Al Hofuf', 'الهفوف'], ['Qatif', 'القطيف'], ['Ras Tanura', 'رأس تنورة'],
            ['Abha', 'أبها'], ['Khamis Mushait', 'خميس مشيط'], ['Bishah', 'بيشة'],
            ['Muhayil', 'محايل'], ['Tabuk', 'تبوك'], ['Al Wajh', 'الوجه'],
            ['Haql', 'حقل'], ['Hail', 'حائل'], ['Baqaa', 'البكاة'],
            ['Najran', 'نجران'], ['Sharorah', 'شرورة'], ['Jazan', 'جازان'],
            ['Samtah', 'صامطة'], ['Sabya', 'صبيا'], ['Abu Arish', 'أبو عريش'],
            ['Buraidah', 'بريدة'], ['Unaizah', 'عنيزة'], ['Al Rass', 'الرس'],
            ['Sakaka', 'سكاكا'], ['Arar', 'عرعر'], ['Domat Al Jandal', 'دومة الجندل'],
            ['Al Baha', 'الباحة'], ['Al Mandaq', 'المندق'], ['Rafha', 'رفحة'],
            ['Turaif', 'طريف'], ['Jubbah', 'جبة'],
        ];

        $rows = array_map(fn ($c) => [
            'name_en' => $c[0],
            'name_ar' => $c[1],
            'country_id' => $countryId,
            'created_at' => $now,
            'updated_at' => $now,
        ], $cities);

        DB::table('cities')->insert($rows);

        $this->command->info('Seeded ' . count($rows) . ' Saudi cities (1 query)');
    }

    private function seedFuelTypes(Carbon $now): void
    {
        DB::table('fuel_types')->insert([
            ['type_en' => 'Gasoline', 'type_ar' => 'بنزين', 'created_at' => $now, 'updated_at' => $now],
            ['type_en' => 'Diesel', 'type_ar' => 'ديزل', 'created_at' => $now, 'updated_at' => $now],
            ['type_en' => 'Electric', 'type_ar' => 'كهرباء', 'created_at' => $now, 'updated_at' => $now],
            ['type_en' => 'Hybrid', 'type_ar' => 'هجين', 'created_at' => $now, 'updated_at' => $now],
            ['type_en' => 'Plug-in Hybrid', 'type_ar' => 'هجين قابل للشحن', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->command->info('Seeded 5 fuel types (1 query)');
    }

    private function seedColors(Carbon $now): void
    {
        DB::table('colors')->insert([
            ['name_en' => 'Black', 'name_ar' => 'أسود', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'White', 'name_ar' => 'أبيض', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Silver', 'name_ar' => 'فضي', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Gray', 'name_ar' => 'رمادي', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Red', 'name_ar' => 'أحمر', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Blue', 'name_ar' => 'أزرق', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Green', 'name_ar' => 'أخضر', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Gold', 'name_ar' => 'ذهبي', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Brown', 'name_ar' => 'بني', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Beige', 'name_ar' => 'بيج', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Yellow', 'name_ar' => 'أصفر', 'created_at' => $now, 'updated_at' => $now],
            ['name_en' => 'Orange', 'name_ar' => 'برتقالي', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->command->info('Seeded 12 colors (1 query)');
    }
}
