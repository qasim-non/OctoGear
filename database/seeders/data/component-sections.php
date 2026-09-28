<?php

// Generic part taxonomy; vehicle-specific fitment must be supplied by stores.
return [
    'Brakes' => ['الفرامل', [
        ['Brake Disc', 'قرص الفرامل'], ['Brake Pads', 'فحمات الفرامل'],
        ['Brake Caliper', 'كليبر الفرامل'], ['Brake Drum', 'طنبورة الفرامل'],
        ['Brake Shoes', 'أحذية الفرامل'], ['Brake Master Cylinder', 'أسطوانة الفرامل الرئيسية'],
        ['Brake Booster', 'معزز الفرامل'], ['Brake Hose', 'خرطوم الفرامل'],
        ['Parking Brake Cable', 'سلك فرامل الوقوف'], ['ABS Pump', 'مضخة نظام منع انغلاق المكابح'],
        ['Brake Fluid', 'سائل الفرامل'],
    ]],
    'Suspension and Steering' => ['التعليق والتوجيه', [
        ['Shock Absorber', 'ممتص الصدمات'], ['Strut Assembly', 'مجموعة المساعد'],
        ['Coil Spring', 'نابض حلزوني'], ['Control Arm', 'ذراع التعليق'],
        ['Ball Joint', 'مفصل كروي'], ['Tie Rod End', 'طرف ذراع التوجيه'],
        ['Steering Rack', 'علبة التوجيه'], ['Stabilizer Link', 'وصلة عمود التوازن'],
        ['Suspension Bushing', 'جلبة التعليق'], ['Wheel Bearing', 'محمل العجلة'],
        ['Power Steering Pump', 'مضخة التوجيه'],
    ]],
    'Transmission' => ['ناقل الحركة', [
        ['Automatic Transmission', 'ناقل حركة أوتوماتيكي'], ['Manual Transmission', 'ناقل حركة يدوي'],
        ['CVT Transmission', 'ناقل حركة متغير باستمرار'], ['Clutch Disc', 'قرص القابض'],
        ['Clutch Pressure Plate', 'صحن ضغط القابض'], ['Flywheel', 'دولاب الموازنة'],
        ['Torque Converter', 'محول العزم'], ['Drive Shaft', 'عمود نقل الحركة'],
        ['CV Joint', 'مفصل سرعة ثابتة'], ['Differential', 'الترس التفاضلي'],
        ['Transmission Mount', 'قاعدة ناقل الحركة'], ['Transmission Fluid', 'سائل ناقل الحركة'],
    ]],
    'Wheels and Tires' => ['العجلات والإطارات', [
        ['Alloy Wheel', 'جنط ألمنيوم'], ['Steel Wheel', 'جنط فولاذي'],
        ['Tire', 'إطار'], ['Spare Wheel', 'عجلة احتياطية'],
        ['Wheel Hub', 'صرة العجلة'], ['Wheel Stud', 'مسمار العجلة'],
        ['Wheel Nut', 'صامولة العجلة'], ['Valve Stem', 'صمام الإطار'],
    ]],
    'Climate Control' => ['التكييف والتدفئة', [
        ['AC Compressor', 'ضاغط المكيف'], ['AC Evaporator', 'مبخر المكيف'],
        ['Blower Motor', 'محرك مروحة التكييف'], ['Heater Core', 'مشع التدفئة'],
        ['AC Expansion Valve', 'صمام تمدد المكيف'], ['AC Receiver Drier', 'مجفف المكيف'],
        ['Blower Resistor', 'مقاومة مروحة التكييف'], ['Cabin Air Filter', 'فلتر هواء المقصورة'],
    ]],
];
