<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\City;
use App\Models\FuelType;
use App\Models\Color;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        $this->seedCountries();
        $this->seedCities();
        $this->seedFuelTypes();
        $this->seedColors();

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedCountries(): void
    {
        $countries = [
            // Middle East
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

            // Asia
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

            // Europe
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

            // Americas
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

            // Africa
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

            // Oceania
            ['Australia', 'أستراليا'],
            ['New Zealand', 'نيوزيلندا'],
        ];

        foreach ($countries as [$en, $ar]) {
            Country::create(['name_en' => $en, 'name_ar' => $ar]);
        }

        $this->command->info("Seeded " . count($countries) . " countries");
    }

    private function seedCities(): void
    {
        $data = [
            'Saudi Arabia' => [
                // Riyadh Region
                ['Riyadh', 'الرياض'],
                ['Al Kharj', 'الخرج'],
                ['Al Majmaah', 'المجمعة'],
                ['Al Zulfi', 'الزلفي'],
                ['Ad Dilam', 'الدلم'],
                // Makkah Region
                ['Jeddah', 'جدة'],
                ['Makkah', 'مكة المكرمة'],
                ['At Taif', 'الطائف'],
                ['Rabigh', 'رابغ'],
                ['Al Qunfudhah', 'القنفذة'],
                // Madinah Region
                ['Al Madinah', 'المدينة المنورة'],
                ['Yanbu', 'ينبع'],
                ['Al Ula', 'العلا'],
                // Eastern Province
                ['Dammam', 'الدمام'],
                ['Al Dhahran', 'ظهران'],
                ['Al Khobar', 'الخبر'],
                ['Al Jubail', 'الجبيل'],
                ['Al Hofuf', 'الهفوف'],
                ['Qatif', 'القطيف'],
                ['Ras Tanura', 'رأس تنورة'],
                // Asir Region
                ['Abha', 'أبها'],
                ['Khamis Mushait', 'خميس مشيط'],
                ['Bishah', 'بيشة'],
                ['Muhayil', 'محايل'],
                // Tabuk Region
                ['Tabuk', 'تبوك'],
                ['Al Wajh', 'الوجه'],
                ['Haql', 'حقل'],
                // Ha'il Region
                ['Ha\'il', 'حائل'],
                ['Baqaa', 'البكاة'],
                // Najran Region
                ['Najran', 'نجران'],
                ['Sharorah', 'شرورة'],
                // Jazan Region
                ['Jazan', 'جازان'],
                ['Samtah', 'صامطة'],
                ['Sabya', 'صبيا'],
                ['Abu Arish', 'أبو عريش'],
                // Al Qassim Region
                ['Buraidah', 'بريدة'],
                ['Unaizah', 'عنيزة'],
                ['Al Rass', 'الرس'],
                // Al Jouf Region
                ['Sakaka', 'سكاكا'],
                ['Arar', 'عرعر'],
                ['Domat Al Jandal', 'دومة الجندل'],
                // Al Baha Region
                ['Al Baha', 'الباحة'],
                ['Al Mandaq', 'المندق'],
                // Northern Borders
                ['Rafha', 'رفحة'],
                ['Turaif', 'طريف'],
                ['Jubbah', 'جبة'],
            ],

            'United States' => [
                ['New York', 'نيويورك'],
                ['Los Angeles', 'لوس أنجلوس'],
                ['Chicago', 'شيكاغو'],
                ['Houston', 'هيوستن'],
                ['Miami', 'ميامي'],
                ['Dallas', 'دالاس'],
            ],

            'Canada' => [
                ['Toronto', 'تورنتو'],
                ['Montreal', 'مونتريال'],
                ['Vancouver', 'فانكوفر'],
                ['Calgary', 'كالغاري'],
            ],

            'United Kingdom' => [
                ['London', 'لندن'],
                ['Manchester', 'مانشستر'],
                ['Birmingham', 'برمنغهام'],
                ['Liverpool', 'ليفربول'],
            ],

            'Germany' => [
                ['Berlin', 'برلين'],
                ['Munich', 'ميونخ'],
                ['Frankfurt', 'فرانكفورت'],
                ['Hamburg', 'هامبورغ'],
            ],

            'France' => [
                ['Paris', 'باريس'],
                ['Lyon', 'ليون'],
                ['Marseille', 'مارسيليا'],
                ['Nice', 'نيس'],
            ],

            'Japan' => [
                ['Tokyo', 'طوكيو'],
                ['Osaka', 'أوساكا'],
                ['Yokohama', 'يوكوهاما'],
                ['Nagoya', 'ناغويا'],
            ],

            'China' => [
                ['Beijing', 'بكين'],
                ['Shanghai', 'شانغهاي'],
                ['Guangzhou', 'غوانغتشو'],
                ['Shenzhen', 'شنتشن'],
            ],

            'India' => [
                ['Mumbai', 'مومباي'],
                ['Delhi', 'دهلي'],
                ['Bangalore', 'بنغالور'],
                ['Chennai', 'تشيناي'],
            ],

            'Turkey' => [
                ['Istanbul', 'إسطنبول'],
                ['Ankara', 'أنقرة'],
                ['Izmir', 'إزمير'],
                ['Bursa', 'بورصة'],
            ],

            'United Arab Emirates' => [
                ['Dubai', 'دبي'],
                ['Abu Dhabi', 'أبو ظبي'],
                ['Sharjah', 'الشارقة'],
                ['Ajman', 'عجمان'],
            ],

            'Kuwait' => [
                ['Kuwait City', 'مدينة الكويت'],
                ['Hawalli', 'حولي'],
                ['Salmiya', 'السالمية'],
            ],

            'Qatar' => [
                ['Doha', 'الدوحة'],
                ['Al Wakrah', 'الوكرة'],
            ],

            'Bahrain' => [
                ['Manama', 'المنامة'],
                ['Riffa', 'الرفاع'],
            ],

            'Oman' => [
                ['Muscat', 'مسقط'],
                ['Salalah', 'صلالة'],
                ['Sohar', 'صحار'],
            ],

            'Egypt' => [
                ['Cairo', 'القاهرة'],
                ['Alexandria', 'الإسكندرية'],
                ['Giza', 'الجيزة'],
            ],

            'South Korea' => [
                ['Seoul', 'سيول'],
                ['Busan', 'بوسان'],
                ['Incheon', 'إنتشون'],
            ],

            'Brazil' => [
                ['São Paulo', 'ساو باولو'],
                ['Rio de Janeiro', 'ريو دي جانيرو'],
            ],

            'Australia' => [
                ['Sydney', 'سيدني'],
                ['Melbourne', 'ملبورن'],
                ['Brisbane', 'بريزبين'],
            ],

            'Russia' => [
                ['Moscow', 'موسكو'],
                ['Saint Petersburg', 'سانت بطرسبرغ'],
            ],

            'Jordan' => [
                ['Amman', 'عمّان'],
                ['Zarqa', 'الزرقاء'],
                ['Irbid', 'إربد'],
            ],

            'Lebanon' => [
                ['Beirut', 'بيروت'],
                ['Tripoli', 'طرابلس'],
                ['Sidon', 'صيدا'],
            ],

            'Iraq' => [
                ['Baghdad', 'بغداد'],
                ['Basra', 'البصرة'],
                ['Erbil', 'أربيل'],
                ['Mosul', 'الموصل'],
            ],

            'Syria' => [
                ['Damascus', 'دمشق'],
                ['Aleppo', 'حلب'],
                ['Homs', 'حمص'],
            ],

            'Palestine' => [
                ['Ramallah', 'رام الله'],
                ['Gaza', 'غزة'],
                ['Hebron', 'الخليل'],
            ],

            'Yemen' => [
                ['Sana\'a', 'صنعاء'],
                ['Aden', 'عدن'],
                ['Taiz', 'تعز'],
            ],

            'Sudan' => [
                ['Khartoum', 'الخرطوم'],
                ['Omdurman', 'أم درمان'],
            ],

            'Libya' => [
                ['Tripoli', 'طرابلس'],
                ['Benghazi', 'بنغازي'],
            ],

            'Tunisia' => [
                ['Tunis', 'تونس'],
                ['Sfax', 'صفاقس'],
                ['Sousse', 'السوسة'],
            ],

            'Algeria' => [
                ['Algiers', 'الجزائر'],
                ['Oran', 'وهران'],
                ['Constantine', 'قسنطينة'],
            ],

            'Morocco' => [
                ['Casablanca', 'الدار البيضاء'],
                ['Rabat', 'الرباط'],
                ['Marrakech', 'مراكش'],
                ['Fez', 'فاس'],
            ],

            'Mauritania' => [
                ['Nouakchott', 'نواكشوط'],
                ['Nouadhibou', 'نواذيبو'],
            ],

            'Iran' => [
                ['Tehran', 'طهران'],
                ['Isfahan', 'أصفهان'],
                ['Shiraz', 'شيراز'],
                ['Tabriz', 'تبريز'],
            ],

            'South Africa' => [
                ['Johannesburg', 'جوهانسبورغ'],
                ['Cape Town', 'كيب تاون'],
                ['Durban', 'ديربان'],
            ],

            'Nigeria' => [
                ['Lagos', 'لاغوس'],
                ['Abuja', 'أبوجا'],
                ['Kano', 'كانو'],
            ],

            'Kenya' => [
                ['Nairobi', 'نيروبي'],
                ['Mombasa', 'مومباسا'],
            ],

            'Ethiopia' => [
                ['Addis Ababa', 'أديس أبابا'],
                ['Dire Dawa', 'دير داوا'],
            ],

            'Ghana' => [
                ['Accra', 'أكرا'],
                ['Kumasi', 'كوماسي'],
            ],

            'Tanzania' => [
                ['Dar es Salaam', 'دار السلام'],
                ['Dodoma', 'دودوما'],
            ],

            'Senegal' => [
                ['Dakar', 'داكار'],
                ['Saint-Louis', 'سان لويس'],
            ],

            'Cameroon' => [
                ['Yaoundé', 'ياوندي'],
                ['Douala', 'دوالا'],
            ],

            'Ivory Coast' => [
                ['Abidjan', 'أبيدجان'],
                ['Yamoussoukro', 'ياموسوكرو'],
            ],

            'Uganda' => [
                ['Kampala', 'كامبالا'],
                ['Entebbe', 'إنتيبي'],
            ],

            'Rwanda' => [
                ['Kigali', 'كيغالي'],
                ['Butare', 'بوتاري'],
            ],

            'Djibouti' => [
                ['Djibouti City', 'مدينة جيبوتي'],
                ['Ali Sabieh', 'علي صبيح'],
            ],

            'Somalia' => [
                ['Mogadishu', 'مقديشو'],
                ['Hargeisa', 'هرجيسا'],
            ],

            'Comoros' => [
                ['Moroni', 'موروني'],
                ['Mutsamudu', 'موتسامودو'],
            ],

            'Philippines' => [
                ['Manila', 'مانيلا'],
                ['Quezon City', 'كيزون سيتي'],
                ['Cebu', 'سيبو'],
            ],

            'Vietnam' => [
                ['Hanoi', 'هانوي'],
                ['Ho Chi Minh City', 'مدينة هو تشي منه'],
                ['Da Nang', 'دانانغ'],
            ],

            'Thailand' => [
                ['Bangkok', 'بانكوك'],
                ['Chiang Mai', 'تشيانغ ماي'],
                ['Pattaya', 'باتايا'],
            ],

            'Indonesia' => [
                ['Jakarta', 'جاكرتا'],
                ['Surabaya', 'سورابايا'],
                ['Bandung', 'باندونغ'],
            ],

            'Malaysia' => [
                ['Kuala Lumpur', 'كوالالمبور'],
                ['George Town', 'جورج تاون'],
                ['Johor Bahru', 'جوهور بهرو'],
            ],

            'Pakistan' => [
                ['Karachi', 'كراتشي'],
                ['Lahore', 'لاهور'],
                ['Islamabad', 'إسلام آباد'],
            ],

            'Bangladesh' => [
                ['Dhaka', 'دكا'],
                ['Chittagong', 'شيتاغونغ'],
            ],

            'Sri Lanka' => [
                ['Colombo', 'كولومبو'],
                ['Kandy', 'كاندي'],
            ],

            'Myanmar' => [
                ['Yangon', 'يانغون'],
                ['Naypyidaw', 'نايبيدو'],
            ],

            'Cambodia' => [
                ['Phnom Penh', 'بنوم بنه'],
                ['Siem Reap', 'سيم ريب'],
            ],

            'Singapore' => [
                ['Singapore', 'سنغافورة'],
            ],

            'Italy' => [
                ['Rome', 'روما'],
                ['Milan', 'ميلانو'],
                ['Naples', 'نابولي'],
                ['Turin', 'تورينو'],
            ],

            'Spain' => [
                ['Madrid', 'مدريد'],
                ['Barcelona', 'برشلونة'],
                ['Valencia', 'فالنسيا'],
                ['Seville', 'إشبيلية'],
            ],

            'Netherlands' => [
                ['Amsterdam', 'أمستردام'],
                ['Rotterdam', 'روتردام'],
                ['The Hague', 'لاهاي'],
            ],

            'Belgium' => [
                ['Brussels', 'بروكسل'],
                ['Antwerp', 'أنتويرب'],
                ['Ghent', 'غنت'],
            ],

            'Switzerland' => [
                ['Zurich', 'زيوريخ'],
                ['Geneva', 'جنيف'],
                ['Bern', 'برن'],
            ],

            'Sweden' => [
                ['Stockholm', 'ستوكهولم'],
                ['Gothenburg', 'غوتيبورغ'],
                ['Malmö', 'مالمو'],
            ],

            'Norway' => [
                ['Oslo', 'أوسلو'],
                ['Bergen', 'برغن'],
                ['Trondheim', 'روندهييم'],
            ],

            'Denmark' => [
                ['Copenhagen', 'كوبنهاغن'],
                ['Aarhus', 'آرهوس'],
                ['Odense', 'أودنسه'],
            ],

            'Finland' => [
                ['Helsinki', 'هلسنكي'],
                ['Tampere', 'تمبري'],
                ['Turku', 'توركو'],
            ],

            'Poland' => [
                ['Warsaw', 'وارسو'],
                ['Krakow', 'كركوف'],
                ['Gdansk', 'دانسك'],
            ],

            'Portugal' => [
                ['Lisbon', 'لشبونة'],
                ['Porto', 'بورتو'],
                ['Faro', 'فارو'],
            ],

            'Austria' => [
                ['Vienna', 'فيينا'],
                ['Salzburg', 'سالزبورغ'],
                ['Graz', 'غراتس'],
            ],

            'Ireland' => [
                ['Dublin', 'دبلن'],
                ['Cork', 'كورك'],
                ['Galway', 'غالوي'],
            ],

            'Greece' => [
                ['Athens', 'أثينا'],
                ['Thessaloniki', 'سالونيكا'],
                ['Patras', 'باتراس'],
            ],

            'Czech Republic' => [
                ['Prague', 'براغ'],
                ['Brno', 'برنو'],
                ['Ostrava', 'أوسترافا'],
            ],

            'Romania' => [
                ['Bucharest', 'بوخارست'],
                ['Cluj-Napoca', 'كلوج نابوكا'],
                ['Timișoara', 'تيمي쇼ارا'],
            ],

            'Hungary' => [
                ['Budapest', 'بودابست'],
                ['Debrecen', 'ديبريسن'],
                ['Szeged', 'سيغد'],
            ],

            'Ukraine' => [
                ['Kyiv', 'كييف'],
                ['Kharkiv', 'خاركوف'],
                ['Odesa', 'أوديسا'],
            ],

            'Croatia' => [
                ['Zagreb', 'زغرب'],
                ['Split', 'سبليت'],
                ['Dubrovnik', 'دوبروفنيك'],
            ],

            'Serbia' => [
                ['Belgrade', 'بلغراد'],
                ['Niš', 'نيش'],
            ],

            'Bulgaria' => [
                ['Sofia', 'صوفيا'],
                ['Plovdiv', 'بلوفديف'],
            ],

            'Slovakia' => [
                ['Bratislava', 'براتيسلافا'],
                ['Košice', 'كوشيتسه'],
            ],

            'Slovenia' => [
                ['Ljubljana', 'ليوبليانا'],
                ['Maribor', 'ماريبور'],
            ],

            'Lithuania' => [
                ['Vilnius', 'فيلنيوس'],
                ['Kaunas', 'كاوناس'],
            ],

            'Latvia' => [
                ['Riga', 'ريغا'],
                ['Daugavpils', 'داوغافبيلس'],
            ],

            'Estonia' => [
                ['Tallinn', 'تالين'],
                ['Tartu', 'تارتو'],
            ],

            'Argentina' => [
                ['Buenos Aires', 'بوينس آيرس'],
                ['Córdoba', 'كوردوفا'],
                ['Rosario', 'روزاريو'],
            ],

            'Colombia' => [
                ['Bogotá', 'بوغوتا'],
                ['Medellín', 'ميدلين'],
                ['Cali', 'كالي'],
            ],

            'Chile' => [
                ['Santiago', 'سانتياغو'],
                ['Valparaíso', 'فالبارايسو'],
            ],

            'Peru' => [
                ['Lima', 'ليما'],
                ['Arequipa', 'أريكيبا'],
                ['Cusco', 'كوسبو'],
            ],

            'Venezuela' => [
                ['Caracas', 'كاراكاس'],
                ['Maracaibo', 'ماراكايبو'],
            ],

            'Ecuador' => [
                ['Quito', 'كيتو'],
                ['Guayaquil', 'غواياكيل'],
            ],

            'Mexico' => [
                ['Mexico City', 'مدينة مكسيكو'],
                ['Guadalajara', 'غوادالاخارا'],
                ['Monterrey', 'مونتيري'],
                ['Cancún', 'كانكون'],
            ],

            'New Zealand' => [
                ['Auckland', 'أوكلاند'],
                ['Wellington', 'ولينغتون'],
                ['Christchurch', 'كرايستشرش'],
            ],
        ];

        foreach ($data as $countryName => $cities) {
            $country = Country::where('name_en', $countryName)->first();

            if (!$country) {
                $this->command->warn("Country not found: {$countryName}, skipping cities.");
                continue;
            }

            foreach ($cities as [$en, $ar]) {
                City::create([
                    'name_en' => $en,
                    'name_ar' => $ar,
                    'country_id' => $country->id,
                ]);
            }

            $this->command->info("Seeded " . count($cities) . " cities for {$countryName}");
        }
    }

    private function seedFuelTypes(): void
    {
        $types = [
            ['Gasoline', 'بنزين'],
            ['Diesel', 'ديزل'],
            ['Electric', 'كهرباء'],
            ['Hybrid', 'هجين'],
            ['Plug-in Hybrid', 'هجين قابل للشحن'],
        ];

        foreach ($types as [$en, $ar]) {
            FuelType::create(['type_en' => $en, 'type_ar' => $ar]);
        }

        $this->command->info("Seeded " . count($types) . " fuel types");
    }

    private function seedColors(): void
    {
        $colors = [
            ['Black', 'أسود'],
            ['White', 'أبيض'],
            ['Silver', 'فضي'],
            ['Gray', 'رمادي'],
            ['Red', 'أحمر'],
            ['Blue', 'أزرق'],
            ['Green', 'أخضر'],
            ['Gold', 'ذهبي'],
            ['Brown', 'بني'],
            ['Beige', 'بيج'],
            ['Yellow', 'أصفر'],
            ['Orange', 'برتقالي'],
        ];

        foreach ($colors as [$en, $ar]) {
            Color::create(['name_en' => $en, 'name_ar' => $ar]);
        }

        $this->command->info("Seeded " . count($colors) . " colors");
    }
}
