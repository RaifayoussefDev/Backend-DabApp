<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AdminMenu;
use Illuminate\Support\Facades\DB;

class AdminMenuV2Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks to truncate safely
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        AdminMenu::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $menus = [
            [
                "key" => "dashboard",
                "path" => "/",
                "label_en" => "Dashboard",
                "label_ar" => "لوحة التحكم",
                "icon" => "LayoutDashboard",
                "permission" => "dashboard.view",
                "order" => 1
            ],
            [
                "key" => "users",
                "path" => "/users",
                "label_en" => "Users",
                "label_ar" => "المستخدمين",
                "icon" => "Users",
                "permission" => "users.view",
                "order" => 2
            ],
            [
                "key" => "listings",
                "path" => "/listings",
                "label_en" => "Listings",
                "label_ar" => "الإعلانات",
                "icon" => "List",
                "permission" => "listings.view",
                "order" => 3
            ],
            [
                "key" => "events",
                "path" => "/events",
                "label_en" => "Events",
                "label_ar" => "الفعاليات",
                "icon" => "Calendar",
                "permission" => "events.view",
                "order" => 4,
                "sub_items" => [
                    ["key" => "events-all", "path" => "/events", "label_en" => "All Events", "label_ar" => "كل الفعاليات", "permission" => "events.view", "order" => 1],
                    ["key" => "events-categories", "path" => "/events/categories", "label_en" => "Categories", "label_ar" => "التصنيفات", "permission" => "events.categories", "order" => 2],
                    ["key" => "events-sponsors", "path" => "/events/sponsors", "label_en" => "Sponsors", "label_ar" => "الرعاة", "permission" => "events.view", "order" => 3],
                    ["key" => "events-organizers", "path" => "/events/organizers", "label_en" => "Organizers", "label_ar" => "المنظمون", "permission" => "events.view", "order" => 4],
                    ["key" => "events-reviews", "path" => "/events/reviews", "label_en" => "Reviews", "label_ar" => "التقييمات", "permission" => "events.view", "order" => 5]
                ]
            ],
            [
                "key" => "guides",
                "path" => "/guides",
                "label_en" => "Guides",
                "label_ar" => "الأدلة",
                "icon" => "BookOpen",
                "permission" => "guides.view",
                "order" => 5,
                "sub_items" => [
                    ["key" => "guides-all", "path" => "/guides", "label_en" => "All Guides", "label_ar" => "كل الأدلة", "permission" => "guides.view", "order" => 1],
                    ["key" => "guides-categories", "path" => "/guides/categories", "label_en" => "Categories", "label_ar" => "التصنيفات", "permission" => "guides.manage", "order" => 2],
                    ["key" => "guides-tags", "path" => "/guides/tags", "label_en" => "Tags", "label_ar" => "الوسوم", "permission" => "guides.manage", "order" => 3],
                    ["key" => "guides-create", "path" => "/guides/create", "label_en" => "Add New", "label_ar" => "أضف جديد", "permission" => "guides.manage", "order" => 4]
                ]
            ],
            [
                "key" => "services",
                "path" => "/services",
                "label_en" => "Services",
                "label_ar" => "الخدمات",
                "icon" => "Wrench",
                "permission" => "services.view",
                "order" => 6,
                "sub_items" => [
                    ["key" => "services-all", "path" => "/services", "label_en" => "All Services", "label_ar" => "كل الخدمات", "permission" => "services.view", "order" => 1],
                    ["key" => "services-categories", "path" => "/services/categories", "label_en" => "Categories", "label_ar" => "التصنيفات", "permission" => "services.manage", "order" => 2],
                    ["key" => "services-bookings", "path" => "/services/bookings", "label_en" => "Bookings", "label_ar" => "الحجوزات", "permission" => "services.view", "order" => 3],
                    ["key" => "services-reviews", "path" => "/services/reviews", "label_en" => "Reviews", "label_ar" => "التقييمات", "permission" => "services.manage", "order" => 4],
                    ["key" => "services-chat-sessions", "path" => "/services/chat-sessions", "label_en" => "Chat Sessions", "label_ar" => "جلسات المحادثة", "permission" => "services.view", "order" => 5]
                ]
            ],
            [
                "key" => "promo-codes",
                "path" => "/promo-codes",
                "label_en" => "Promo Codes",
                "label_ar" => "أكواد الخصم",
                "icon" => "Tag",
                "permission" => "promo_codes.view",
                "order" => 7,
                "sub_items" => [
                    ["key" => "promo-codes-all", "path" => "/promo-codes", "label_en" => "All Codes", "label_ar" => "كل الأكواد", "permission" => "promo_codes.view", "order" => 1],
                    ["key" => "promo-codes-usages", "path" => "/promo-codes/usages", "label_en" => "Usages", "label_ar" => "الاستخدامات", "permission" => "promo_codes.manage", "order" => 2]
                ]
            ],
            [
                "key" => "banners",
                "path" => "/banners",
                "label_en" => "Banners",
                "label_ar" => "البانرات",
                "icon" => "Image",
                "permission" => "banners.manage",
                "order" => 8
            ],
            [
                "key" => "reports",
                "path" => "/reports",
                "label_en" => "Reports",
                "label_ar" => "التقارير",
                "icon" => "Flag",
                "permission" => "reports.view",
                "order" => 9,
                "sub_items" => [
                    ["key" => "reports-all", "path" => "/reports", "label_en" => "All Reports", "label_ar" => "كل التقارير", "permission" => "reports.view", "order" => 1],
                    ["key" => "reports-types", "path" => "/reports/types", "label_en" => "Report Types", "label_ar" => "أنواع التقارير", "permission" => "reports.resolve", "order" => 2],
                    ["key" => "reports-reasons", "path" => "/reports/reasons", "label_en" => "Report Reasons", "label_ar" => "أسباب التقارير", "permission" => "reports.resolve", "order" => 3]
                ]
            ],
            [
                "key" => "notifications",
                "path" => "/notifications",
                "label_en" => "Notifications",
                "label_ar" => "الإشعارات",
                "icon" => "Bell",
                "permission" => "notifications.send",
                "order" => 10,
                "sub_items" => [
                    ["key" => "notifications-logs", "path" => "/notifications", "label_en" => "Logs", "label_ar" => "السجلات", "permission" => "notifications.send", "order" => 1],
                    ["key" => "notifications-settings", "path" => "/notifications/settings", "label_en" => "Settings", "label_ar" => "الإعدادات", "permission" => "notifications.settings", "order" => 2]
                ]
            ],
            [
                "key" => "pricing",
                "path" => "/pricing",
                "label_en" => "Pricing Rules",
                "label_ar" => "قواعد التسعير",
                "icon" => "DollarSign",
                "permission" => "pricing.manage",
                "order" => 11
            ],
            [
                "key" => "routes",
                "path" => "/routes",
                "label_en" => "Routes",
                "label_ar" => "المسارات",
                "icon" => "Navigation",
                "permission" => "routes.view",
                "order" => 12,
                "sub_items" => [
                    ["key" => "routes-all", "path" => "/routes", "label_en" => "All Routes", "label_ar" => "كل المسارات", "permission" => "routes.view", "order" => 1],
                    ["key" => "route-categories", "path" => "/route-categories", "label_en" => "Categories", "label_ar" => "التصنيفات", "permission" => "routes.manage", "order" => 2],
                    ["key" => "route-reviews", "path" => "/route-reviews", "label_en" => "Reviews", "label_ar" => "التقييمات", "permission" => "routes.manage", "order" => 3],
                    ["key" => "route-completions", "path" => "/route-completions", "label_en" => "Completions", "label_ar" => "الإتمامات", "permission" => "routes.view", "order" => 4]
                ]
            ],
            [
                "key" => "pois",
                "path" => "/pois",
                "label_en" => "Points of Interest",
                "label_ar" => "نقاط الاهتمام",
                "icon" => "MapPin",
                "permission" => "pois.view",
                "order" => 13,
                "sub_items" => [
                    ["key" => "poi-list", "path" => "/pois", "label_en" => "All Locations", "label_ar" => "كل المواقع", "permission" => "pois.view", "order" => 1],
                    ["key" => "poi-types", "path" => "/poi-types", "label_en" => "Categories", "label_ar" => "التصنيفات", "permission" => "pois.manage", "order" => 2],
                    ["key" => "poi-tags", "path" => "/poi-tags", "label_en" => "Tags", "label_ar" => "الوسوم", "permission" => "pois.manage", "order" => 3],
                    ["key" => "poi-services", "path" => "/poi-services", "label_en" => "Services", "label_ar" => "الخدمات", "permission" => "pois.manage", "order" => 4]
                ]
            ],
            [
                "key" => "poi-management",
                "path" => "/poi-management",
                "label_en" => "POI Management",
                "label_ar" => "إدارة المواقع",
                "icon" => "ShieldAlert",
                "permission" => "pois.manage",
                "order" => 14,
                "sub_items" => [
                    ["key" => "poi-reviews", "path" => "/poi-reviews", "label_en" => "Reviews", "label_ar" => "التقييمات", "permission" => "pois.manage", "order" => 1],
                    ["key" => "poi-reports", "path" => "/poi-reports", "label_en" => "Reports", "label_ar" => "التقارير", "permission" => "pois.manage", "order" => 2]
                ]
            ],
            [
                "key" => "motorcycle",
                "path" => "/motorcycle",
                "label_en" => "Motorcycle",
                "label_ar" => "الدراجات النارية",
                "icon" => "Bike",
                "permission" => "catalog.motorcycle",
                "order" => 15,
                "sub_items" => [
                    ["key" => "motorcycle-brands", "path" => "/motorcycle/brands", "label_en" => "Brands", "label_ar" => "العلامات التجارية", "permission" => "catalog.motorcycle", "order" => 1],
                    ["key" => "motorcycle-models", "path" => "/motorcycle/models", "label_en" => "Models", "label_ar" => "الموديلات", "permission" => "catalog.motorcycle", "order" => 2],
                    ["key" => "motorcycle-types", "path" => "/motorcycle/types", "label_en" => "Types", "label_ar" => "الأنواع", "permission" => "catalog.motorcycle", "order" => 3],
                    ["key" => "motorcycle-years", "path" => "/motorcycle/years", "label_en" => "Years", "label_ar" => "السنوات", "permission" => "catalog.motorcycle", "order" => 4]
                ]
            ],
            [
                "key" => "spare-parts",
                "path" => "/spare-parts",
                "label_en" => "Spare Parts",
                "label_ar" => "قطع الغيار",
                "icon" => "Wrench",
                "permission" => "catalog.spare_parts",
                "order" => 16,
                "sub_items" => [
                    ["key" => "spare-parts-brands", "path" => "/spare-parts/brands", "label_en" => "Brands", "label_ar" => "العلامات التجارية", "permission" => "catalog.spare_parts", "order" => 1],
                    ["key" => "spare-parts-categories", "path" => "/spare-parts/categories", "label_en" => "Categories", "label_ar" => "التصنيفات", "permission" => "catalog.spare_parts", "order" => 2]
                ]
            ],
            [
                "key" => "settings",
                "path" => "/settings",
                "label_en" => "Settings",
                "label_ar" => "الإعدادات",
                "icon" => "Settings",
                "permission" => "settings.view",
                "order" => 17,
                "sub_items" => [
                    ["key" => "settings-general", "path" => "/settings", "label_en" => "General", "label_ar" => "عام", "permission" => "settings.view", "order" => 1],
                    ["key" => "settings-app-version", "path" => "/settings/app-version", "label_en" => "App Version", "label_ar" => "إصدار التطبيق", "permission" => "settings.view", "order" => 2],
                    ["key" => "settings-roles", "path" => "/settings/roles", "label_en" => "Roles & Permissions", "label_ar" => "الأدوار والصلاحيات", "permission" => "roles.view", "order" => 3],
                    ["key" => "settings-menus", "path" => "/settings/menus", "label_en" => "Menu Management", "label_ar" => "إدارة القوائم", "permission" => "settings.view", "order" => 4],
                    ["key" => "settings-permissions", "path" => "/settings/permissions", "label_en" => "System Permissions", "label_ar" => "صلاحيات النظام", "permission" => "roles.view", "order" => 5]
                ]
            ]
        ];

        foreach ($menus as $menu) {
            $parent = AdminMenu::create([
                'name' => $menu['key'],
                'title' => $menu['label_en'],
                'translate' => $menu['label_ar'],
                'icon' => $menu['icon'] ?? null,
                'path' => $menu['path'],
                'order' => $menu['order'],
                'permission' => $menu['permission'] ?? null,
                'type' => isset($menu['sub_items']) ? 'collapse' : 'item',
                'roles' => ($menu['key'] === 'dashboard') ? ['admin', 'Manager', 'dashboard'] : (in_array($menu['key'], ['users', 'listings', 'events', 'guides', 'services', 'promo-codes', 'reports', 'pois', 'routes', 'banners', 'motorcycle', 'spare-parts']) ? ['admin', 'Manager'] : ['admin']),
                'is_main_parent' => true,
                'is_active' => true,
            ]);

            if (isset($menu['sub_items'])) {
                foreach ($menu['sub_items'] as $sub) {
                    AdminMenu::create([
                        'parent_id' => $parent->id,
                        'name' => $sub['key'],
                        'title' => $sub['label_en'],
                        'translate' => $sub['label_ar'],
                        'path' => $sub['path'],
                        'order' => $sub['order'],
                        'permission' => $sub['permission'] ?? null,
                        'type' => 'item',
                        'roles' => ($parent->name === 'dashboard') ? ['admin', 'Manager', 'dashboard'] : (in_array($parent->name, ['users', 'listings', 'events', 'guides', 'services', 'promo-codes', 'reports', 'pois', 'routes', 'banners', 'motorcycle', 'spare-parts']) ? ['admin', 'Manager'] : ['admin']),
                        'is_main_parent' => false,
                        'is_active' => true,
                    ]);
                }
            }
        }

        $this->command->info('✅ New Admin Menu seeded successfully!');
    }
}
