<?php

namespace Database\Seeders;

use Database\Seeders\Support\SeedRecords;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComponentSeeder extends Seeder
{
    public function run(): void
    {

        $sections = [
            'Front' => [
                'المقدمة',
                [
                    ['Front Bumper', 'الصدام الأمامي'],
                    ['Front Headlight - Left', 'المصباح الأمامي - يسار'],
                    ['Front Headlight - Right', 'المصباح الأمامي - يمين'],
                    ['Front Grille', 'الشبكة الأمامية'],
                    ['Hood', 'غطاء المحرك'],
                    ['Front Fender - Left', 'الجناح الأمامي - يسار'],
                    ['Front Fender - Right', 'الجناح الأمامي - يمين'],
                    ['Front Windshield', 'الزجاج الأمامي'],
                    ['Front Wiper Blades', 'مساحات الزجاج الأمامية'],
                    ['Front Fog Light - Left', 'إضاءة الضباب الأمامية - يسار'],
                    ['Front Fog Light - Right', 'إضاءة الضباب الأمامية - يمين'],
                    ['Radiator', 'الرادياتور / المبرد'],
                    ['AC Condenser', 'مكثف المكيف'],
                    ['Front Turn Signal - Left', 'الإشارة الأمامية - يسار'],
                    ['Front Turn Signal - Right', 'الإشارة الأمامية - يمين'],
                    ['Front License Plate Holder', 'حامل اللوحة الأمامية'],
                    ['Hood Insulation', 'عازل غطاء المحرك'],
                ],
            ],
            'Rear' => [
                'الخلفية',
                [
                    ['Rear Bumper', 'الصدام الخلفي'],
                    ['Rear Taillight - Left', 'المصباح الخلفي - يسار'],
                    ['Rear Taillight - Right', 'المصباح الخلفي - يمين'],
                    ['Trunk Lid', 'غطاء الصندوق الخلفي'],
                    ['Rear Windshield', 'الزجاج الخلفي'],
                    ['Rear Wiper Blade', 'مساح الزجاج الخلفي'],
                    ['Rear Fog Light - Left', 'إضاءة الضباب الخلفية - يسار'],
                    ['Rear Fog Light - Right', 'إضاءة الضباب الخلفية - يمين'],
                    ['Rear Turn Signal - Left', 'الإشارة الخلفية - يسار'],
                    ['Rear Turn Signal - Right', 'الإشارة الخلفية - يمين'],
                    ['Exhaust Pipe', 'أنبوب العادم'],
                    ['Muffler', 'الكاتم'],
                    ['Rear Diffuser', 'الديفيسر الخلفي'],
                    ['Rear License Plate Holder', 'حامل اللوحة الخلفية'],
                    ['Trunk Floor Mat', 'سجادة الصندوق'],
                    ['Spoiler', 'السبويلر'],
                ],
            ],
            'Driver Side' => [
                'جانب السائق',
                [
                    ['Driver Door', 'باب السائق'],
                    ['Driver Side Mirror', 'مرآة السائق'],
                    ['Driver Side Mirror Glass', 'زجاج مرآة السائق'],
                    ['Driver Side Fender', 'جناح جانب السائق'],
                    ['Driver Side Window', 'نافذة السائق'],
                    ['Driver Side Window Regulator', 'ناقل نافذة السائق'],
                    ['Driver Door Handle', 'مقبض باب السائق'],
                    ['Driver Side Panel', 'لوحة جانب السائق'],
                    ['Driver Side Skirt', 'الجوانب السفلية - يسار'],
                    ['Driver Side Bumper Corner', 'زاوية الصدام - يسار'],
                    ['Driver Seat Belt', 'حزام أمان السائق'],
                    ['Driver Side Mirror Cover', 'غطاء مرآة السائق'],
                ],
            ],
            'Passenger Side' => [
                'جانب الراكب',
                [
                    ['Passenger Door', 'باب الراكب'],
                    ['Passenger Side Mirror', 'مرآة الراكب'],
                    ['Passenger Side Mirror Glass', 'زجاج مرآة الراكب'],
                    ['Passenger Side Fender', 'جناح جانب الراكب'],
                    ['Passenger Side Window', 'نافذة الراكب'],
                    ['Passenger Side Window Regulator', 'ناقل نافذة الراكب'],
                    ['Passenger Door Handle', 'مقبض باب الراكب'],
                    ['Passenger Side Panel', 'لوحة جانب الراكب'],
                    ['Passenger Side Skirt', 'الجوانب السفلية - يمين'],
                    ['Passenger Side Bumper Corner', 'زاوية الصدام - يمين'],
                    ['Passenger Seat Belt', 'حزام أمان الراكب'],
                    ['Passenger Side Mirror Cover', 'غطاء مرآة الراكب'],
                ],
            ],
            'Engine' => [
                'المحرك',
                [
                    ['Air Filter', 'فلتر الهواء'],
                    ['Oil Filter', 'فلتر الزيت'],
                    ['Fuel Filter', 'فلتر الوقود'],
                    ['Spark Plugs', 'شمعات الإشعال'],
                    ['Timing Belt', 'حزام التوقيت'],
                    ['Timing Chain', 'سلسلة التوقيت'],
                    ['Water Pump', 'مضخة الماء'],
                    ['Alternator', 'الدينمو / مولد الكهرباء'],
                    ['Starter Motor', 'بادئ الحركة'],
                    ['Battery', 'البطارية'],
                    ['Radiator Hoses Upper', 'أنابيب المبرد العلوية'],
                    ['Radiator Hoses Lower', 'أنابيب المبرد السفلية'],
                    ['Engine Mount - Front', 'حامل المحرك - أمامي'],
                    ['Engine Mount - Rear', 'حامل المحرك - خلفي'],
                    ['Valve Cover', 'غطاء الصمامات'],
                    ['Oil Pan', 'وعاء الزيت'],
                    ['Intake Manifold', 'مانيفولد السحب'],
                    ['Exhaust Manifold', 'مانيفولد العادم'],
                    ['Turbocharger', 'التوربو'],
                    ['Intercooler', 'الإنتركوولر'],
                    ['Serpentine Belt', 'الحزام المتعرج'],
                    ['Pulleys', 'البكرات'],
                    ['Coolant Reservoir', 'خزان التبريد'],
                    ['Engine Oil', 'زيت المحرك'],
                    ['Coolant', 'سائل التبريد'],
                ],
            ],
            'Interior' => [
                'الداخلية',
                [
                    ['Dashboard', 'لوحة القيادة'],
                    ['Steering Wheel', 'عجلة القيادة'],
                    ['Steering Wheel Cover', 'غطاء عجلة القيادة'],
                    ['Gear Shift Knob', 'مقبض ناقل الحركة'],
                    ['Center Console', 'الكونسول الوسطي'],
                    ['Front Seat - Left', 'المقعد الأمامي - يسار'],
                    ['Front Seat - Right', 'المقعد الأمامي - يمين'],
                    ['Rear Seat', 'المقعد الخلفي'],
                    ['Head Rest', 'مسند الرأس'],
                    ['Floor Mats', 'سجاد الأرضية'],
                    ['Door Panel - Driver', 'لوحة باب السائق'],
                    ['Door Panel - Passenger', 'لوحة باب الراكب'],
                    ['Rear Door Panel - Left', 'لوحة الباب الخلفي - يسار'],
                    ['Rear Door Panel - Right', 'لوحة الباب الخلفي - يمين'],
                    ['Sun Visor - Driver', 'حاجب الشمس - السائق'],
                    ['Sun Visor - Passenger', 'حاجب الشمس - الراكب'],
                    ['Rearview Mirror', 'المرآة الداخلية'],
                    ['Instrument Cluster', 'لوحة العدادات'],
                    ['Infotainment Screen', 'شاشة الترفيه'],
                    ['AC Vents', 'فتحات التكييف'],
                    ['Glove Box', 'صندوق القفازات'],
                    ['Seatbelt Buckle', 'مقبض حزام الأمان'],
                    ['Handbrake', 'يد الفرامل'],
                ],
            ],
            'Electrical' => [
                'الكهرباء',
                [
                    ['Headlight Bulb', 'لمبة المصباح الأمامي'],
                    ['Taillight Bulb', 'لمبة المصباح الخلفي'],
                    ['Fog Light Bulb', 'لمبة الضباب'],
                    ['Turn Signal Bulb', 'لمبة الإشارة'],
                    ['Interior Light Bulb', 'لمبة الإضاءة الداخلية'],
                    ['Ignition Coil', 'ملف الإشعال'],
                    ['Distributor Cap', 'غطاء الموزع'],
                    ['Window Motor', 'محرك النافذة'],
                    ['Door Lock Actuator', 'محرك القفل'],
                    ['Horn', 'بوق السيارة'],
                    ['Fuse Box', 'علبة المصاهر'],
                    ['Wiring Harness', 'حزمة الأسلاك'],
                    ['ECU / Engine Control Unit', 'وحدة التحكم الإلكترونية'],
                    ['Sensor - Oxygen', 'الحساس - الأكسجين'],
                    ['Sensor - Mass Airflow', 'حساس تدفق الهواء'],
                    ['Sensor - ABS', 'حساس ABS'],
                    ['Sensor - TPMS', 'حساس ضغط الإطارات'],
                    ['Parking Sensors', 'حساسات الركن'],
                    ['Reverse Camera', 'كاميرا الرجوع'],
                    ['Dashcam', 'كاميرا القيادة'],
                    ['Battery Terminal', 'قطب البطارية'],
                ],
            ],
        ];

        $sections += require __DIR__.'/data/component-sections.php';

        DB::transaction(function () use ($sections): void {
            foreach ($sections as $sectionEn => [$sectionAr, $components]) {
                $sectionId = SeedRecords::reference('car_sections', ['name_en' => $sectionEn], ['name_ar' => $sectionAr]);
                foreach ($components as [$componentEn, $componentAr]) {
                    SeedRecords::reference('components', ['section_id' => $sectionId, 'name_en' => $componentEn], ['name_ar' => $componentAr]);
                }
            }
        });

        $this->command?->info('Seeded '.count($sections).' sections with their component catalogue.');
    }
}
