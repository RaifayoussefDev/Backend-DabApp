<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\CommissionSetting;
use App\Models\Marketplace\Address;
use App\Models\Marketplace\Brand;
use App\Models\Marketplace\Cart;
use App\Models\Marketplace\CartItem;
use App\Models\Marketplace\Category;
use App\Models\Marketplace\Order;
use App\Models\Marketplace\OrderItem;
use App\Models\Marketplace\OrderPromoCode;
use App\Models\Marketplace\Payment;
use App\Models\Marketplace\Product;
use App\Models\Marketplace\ProductAttribute;
use App\Models\Marketplace\ProductComment;
use App\Models\Marketplace\ProductImage;
use App\Models\Marketplace\ProductLike;
use App\Models\Marketplace\ProductMotorcycle;
use App\Models\Marketplace\ProductQuestion;
use App\Models\Marketplace\ProductReview;
use App\Models\Marketplace\ShippingProvider;
use App\Models\Marketplace\Vendor;
use App\Models\Marketplace\VendorPayout;
use App\Models\Marketplace\VendorShippingMethod;
use App\Models\Marketplace\Wishlist;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Test data for the marketplace (3 vendors, ~25 products, 2 buyers, 4 orders in different states,
 * reviews, Q&A, comments, likes, wishlist, carts, chat, a payout...).
 *
 *   php artisan db:seed --class=MarketplaceDemoSeeder
 *
 * Demo accounts (password for all: Password123!):
 *   vendor1@marketplace-demo.dabapp.test  vendor2@...  vendor3@...   (shop owners)
 *   buyer1@marketplace-demo.dabapp.test   buyer2@...                  (customers)
 *
 * Not idempotent by design: it skips if the demo data is already there. To reseed, roll back and
 * re-run the marketplace migrations. Do NOT run it on production.
 */
class MarketplaceDemoSeeder extends Seeder
{
    private const DOMAIN = 'marketplace-demo.dabapp.test';
    private const PASSWORD = 'Password123!';

    private float $globalRate = 15.0;

    /** @var array<int,float> category id => commission % */
    private array $categoryRates = [];

    public function run(): void
    {
        // Hard guard, not just a docblock: this creates fake vendors/orders/reviews with known passwords.
        // Never let it run against production even if someone calls `db:seed --class=...` directly.
        if (app()->isProduction()) {
            $this->command?->error('MarketplaceDemoSeeder refused: APP_ENV=production. Use MarketplaceReferenceSeeder there instead.');

            throw new \RuntimeException('MarketplaceDemoSeeder must never run in production.');
        }

        $this->call(MarketplaceReferenceSeeder::class);

        if (Vendor::withTrashed()->where('slug', 'demo-moto-parts-riyadh')->exists()) {
            $this->command?->warn('Marketplace demo data already present, nothing done.');

            return;
        }

        $adminId = (int) (DB::table('users')->where('role_id', 1)->value('id') ?? DB::table('users')->value('id'));

        $this->globalRate = (float) (CommissionSetting::global()->active()
            ->orderByDesc('effective_from')->orderByDesc('id')->value('commission_percentage') ?? 15);

        DB::transaction(function () use ($adminId) {
            $users = $this->seedUsers();
            $vendors = $this->seedVendors($users);
            $this->seedShippingMethods($vendors);
            $brands = $this->seedBrands();
            $this->seedCommissionSettings($adminId);
            $products = $this->seedProducts($vendors, $brands);
            $promos = $this->seedPromoCodes($vendors);
            $addresses = $this->seedAddresses($users);
            $orders = $this->seedOrders($users, $addresses, $products, $promos);
            $this->seedPayout($vendors['v1'], $orders['delivered'], $adminId);
            $this->seedSocial($users, $products, $orders['delivered']);
            $this->seedCarts($users, $products);
            $this->seedChat($users, $vendors['v1'], $products[0]);
            $this->refreshCounters();
        });

        $this->command?->info('Marketplace demo data created. Login: buyer1@' . self::DOMAIN . ' / ' . self::PASSWORD);
    }

    // ------------------------------------------------------------------ users / vendors

    private function seedUsers(): array
    {
        $defs = [
            'vendor1' => ['Khalid', 'Al-Otaibi', '01'],
            'vendor2' => ['Sara', 'Al-Ghamdi', '02'],
            'vendor3' => ['Omar', 'Al-Harbi', '03'],
            'buyer1'  => ['Ahmed', 'Ali', '04'],
            'buyer2'  => ['Layla', 'Hassan', '05'],
        ];

        $users = [];
        foreach ($defs as $key => [$first, $last, $suffix]) {
            $users[$key] = User::firstOrCreate(
                ['email' => $key . '@' . self::DOMAIN],
                [
                    'first_name'                => $first,
                    'last_name'                 => $last,
                    'phone'                     => '+96655500' . $suffix . '00',
                    'password'                  => Hash::make(self::PASSWORD),
                    'role_id'                   => 2,
                    'is_active'                 => true,
                    'verified'                  => true,
                    'is_registration_completed' => true,
                    'country_id'                => 1,
                    'language'                  => 'en',
                ]
            );
        }

        return $users;
    }

    private function seedVendors(array $users): array
    {
        $defs = [
            'v1' => ['vendor1', 'Moto Parts Riyadh', 'مركز قطع الغيار الرياض', 'demo-moto-parts-riyadh', '#E63946', 1, 'active', null,
                'Tyres, brakes and engine parts for sport and touring bikes.'],
            'v2' => ['vendor2', "Rider's Gear Jeddah", 'معدات الراكب جدة', 'demo-riders-gear-jeddah', '#1D3557', 2, 'active', 12.5,
                'Helmets, riding gear and accessories from the top brands.'],
            'v3' => ['vendor3', 'Desert Moto Accessories', 'إكسسوارات الصحراء', 'demo-desert-moto-accessories', '#F4A261', 1, 'pending', null,
                'Adventure accessories. Waiting for approval.'],
        ];

        $vendors = [];
        foreach ($defs as $key => [$userKey, $name, $nameAr, $slug, $color, $cityId, $status, $override, $description]) {
            $vendors[$key] = Vendor::create([
                'user_id'             => $users[$userKey]->id,
                'shop_name'           => $name,
                'shop_name_ar'        => $nameAr,
                'slug'                => $slug,
                'description'         => $description,
                'logo_path'           => $this->placeholder("marketplace/demo/vendors/{$slug}-logo.png", 400, 400, $color, $name),
                'cover_image_path'    => $this->placeholder("marketplace/demo/vendors/{$slug}-cover.png", 1200, 400, $color, $name),
                'brand_color'         => $color,
                'phone'               => $users[$userKey]->phone,
                'email'               => $users[$userKey]->email,
                'country_id'          => 1,
                'city_id'             => $cityId,
                'status'              => $status,
                'approved_at'         => $status === 'active' ? now()->subDays(30) : null,
                'commission_override' => $override,
                'bank_name'           => 'Al Rajhi Bank',
                'iban'                => 'SA0380000000608010167519',
            ]);
        }

        return $vendors;
    }

    private function seedShippingMethods(array $vendors): void
    {
        $providers = ShippingProvider::pluck('id', 'code');

        $methods = [
            'v1' => [['dhl', 35, true, 2, 4], ['dabapp', 25, false, 3, 6]],
            'v2' => [['aramex', 30, true, 2, 5], ['smsa', 20, true, 1, 3], ['manual', 15, false, 3, 7]],
            'v3' => [['manual', 15, false, 3, 7]],
        ];

        foreach ($methods as $key => $rows) {
            foreach ($rows as [$code, $rate, $ownApi, $min, $max]) {
                VendorShippingMethod::create([
                    'vendor_id'          => $vendors[$key]->id,
                    'provider_id'        => $providers[$code],
                    'uses_own_api'       => $ownApi,
                    'credentials'        => $ownApi ? ['api_key' => 'demo-key-' . $code, 'account' => 'DEMO'] : null,
                    'flat_rate'          => $rate,
                    'estimated_days_min' => $min,
                    'estimated_days_max' => $max,
                    'is_active'          => true,
                ]);
            }
        }
    }

    private function seedBrands(): Collection
    {
        $names = [
            'Michelin', 'Pirelli', 'Dunlop', 'Brembo', 'Akrapovic', 'Motul',
            'K&N', 'DID', 'Yuasa', 'Shoei', 'Arai', 'Alpinestars', 'Dainese', 'TCX', 'Givi', 'Quad Lock', 'Abus', 'Oxford',
        ];

        $brands = collect();
        foreach ($names as $i => $name) {
            $slug = Str::slug($name);
            $brands[$name] = Brand::create([
                'name'        => $name,
                'slug'        => $slug,
                'logo_path'   => $this->placeholder("marketplace/demo/brands/{$slug}.png", 300, 300, '#3A5468', $name),
                'is_featured' => $i < 6,
                'is_active'   => true,
            ]);
        }

        return $brands;
    }

    private function seedCommissionSettings(int $adminId): void
    {
        $tyres = Category::where('slug', 'tyres')->first();

        // Category rate (priority: vendor override > category > global)
        CommissionSetting::create([
            'entity_type'           => 'category',
            'entity_id'             => $tyres->id,
            'commission_percentage' => 8,
            'is_active'             => true,
            'effective_from'        => now()->toDateString(),
            'notes'                 => 'Demo: tyres category rate',
            'created_by'            => $adminId,
        ]);

        foreach ($tyres->descendantIds() as $id) {
            $this->categoryRates[$id] = 8.0;
        }
    }

    // ------------------------------------------------------------------ products

    /** @return Product[] indexed 0..n in template order */
    private function seedProducts(array $vendors, Collection $brands): array
    {
        $cats = Category::pluck('id', 'slug');

        $bikes = DB::table('motorcycle_years')
            ->join('motorcycle_models', 'motorcycle_models.id', '=', 'motorcycle_years.model_id')
            ->select('motorcycle_years.id as year_id', 'motorcycle_models.id as model_id', 'motorcycle_models.brand_id')
            ->orderBy('motorcycle_years.id')->limit(4)->get();

        $tyreAttrs = fn (string $w, string $a, string $pos) => [['Width', "{$w} mm"], ['Aspect ratio', $a], ['Diameter', '17 in'], ['Position', $pos]];

        // vendor, category, brand, name, name_ar, price, stock, condition, status, attributes, fits ('bike'|'universal')
        $templates = [
            ['v1', 'tyres-front', 'Michelin', 'Michelin Pilot Road 5 120/70 ZR17', 'ميشلان بايلوت رود 5 أمامي', 649, 14, 'new', 'active', $tyreAttrs('120', '70', 'Front'), 'bike'],
            ['v1', 'tyres-rear', 'Michelin', 'Michelin Pilot Road 5 180/55 ZR17', 'ميشلان بايلوت رود 5 خلفي', 729, 9, 'new', 'active', $tyreAttrs('180', '55', 'Rear'), 'bike'],
            ['v1', 'tyres-front', 'Pirelli', 'Pirelli Diablo Rosso IV 120/70 ZR17', 'بيريلي ديابلو روسو 4 أمامي', 599, 20, 'new', 'active', $tyreAttrs('120', '70', 'Front'), 'bike'],
            ['v1', 'tyres-rear', 'Pirelli', 'Pirelli Diablo Rosso IV 190/55 ZR17', 'بيريلي ديابلو روسو 4 خلفي', 689, 0, 'new', 'active', $tyreAttrs('190', '55', 'Rear'), 'bike'],
            ['v1', 'tyres-rear', 'Dunlop', 'Dunlop Sportmax Roadsport 2 180/55 ZR17', 'دنلوب سبورت ماكس خلفي', 559, 12, 'new', 'active', $tyreAttrs('180', '55', 'Rear'), 'bike'],
            ['v1', 'brake-pads', 'Brembo', 'Brembo Sintered Front Brake Pads', 'بطانات فرامل برمبو أمامية', 219, 30, 'new', 'active', [['Material', 'Sintered'], ['Position', 'Front']], 'bike'],
            ['v1', 'brake-discs', 'Brembo', 'Brembo Serie Oro Brake Disc 320 mm', 'قرص فرامل برمبو 320 ملم', 1190, 5, 'new', 'active', [['Diameter', '320 mm'], ['Type', 'Floating']], 'bike'],
            ['v1', 'air-filters', 'K&N', 'K&N High-Flow Air Filter', 'فلتر هواء كي اند ان', 289, 18, 'new', 'active', [['Type', 'Washable']], 'bike'],
            ['v1', 'exhausts', 'Akrapovic', 'Akrapovic Slip-On Line Titanium', 'شكمان أكرابوفيتش تيتانيوم', 3450, 3, 'new', 'active', [['Material', 'Titanium'], ['Type', 'Slip-on']], 'bike'],
            ['v1', 'chain-sprockets', 'DID', 'DID 525 ZVM-X Chain 118L', 'سلسلة دي آي دي 525', 420, 25, 'new', 'active', [['Pitch', '525'], ['Links', '118']], 'bike'],
            ['v1', 'batteries', 'Yuasa', 'Yuasa YTZ10S Battery', 'بطارية يواسا YTZ10S', 310, 16, 'new', 'active', [['Voltage', '12 V'], ['Capacity', '8.6 Ah']], 'bike'],
            ['v1', 'engine-oil', 'Motul', 'Motul 7100 10W-40 4T 1L', 'زيت موتول 7100', 89, 120, 'new', 'active', [['Viscosity', '10W-40'], ['Volume', '1 L']], 'universal'],
            ['v1', 'engine-oil', 'Motul', 'Motul 300V Factory Line 15W-50 1L', 'زيت موتول 300V', 129, 60, 'new', 'active', [['Viscosity', '15W-50'], ['Volume', '1 L']], 'universal'],
            ['v2', 'helmets', 'Shoei', 'Shoei NXR2 Full-Face Helmet', 'خوذة شوي NXR2', 2150, 8, 'new', 'active', [['Type', 'Full-face'], ['Shell', 'AIM+ fibre']], 'universal'],
            ['v2', 'helmets', 'Arai', 'Arai RX-7V Evo', 'خوذة أراي RX-7V', 3890, 4, 'new', 'active', [['Type', 'Full-face'], ['Shell', 'Super Fibre']], 'universal'],
            ['v2', 'jackets', 'Alpinestars', 'Alpinestars T-GP Plus R v3 Jacket', 'جاكيت ألبينستارز', 1490, 10, 'new', 'active', [['Material', 'Textile'], ['Protection', 'CE Level 1']], 'universal'],
            ['v2', 'gloves', 'Dainese', 'Dainese Carbon 4 Short Gloves', 'قفازات داينيز', 780, 15, 'new', 'active', [['Material', 'Leather'], ['Cuff', 'Short']], 'universal'],
            ['v2', 'boots', 'TCX', 'TCX RT-Race Pro Air Boots', 'حذاء TCX', 1350, 7, 'new', 'active', [['Type', 'Racing'], ['Ventilation', 'Air']], 'universal'],
            ['v2', 'luggage', 'Givi', 'Givi E370 Monokey Top Case 39L', 'صندوق خلفي جيفي', 690, 11, 'new', 'active', [['Capacity', '39 L']], 'bike'],
            ['v2', 'phone-mounts', 'Quad Lock', 'Quad Lock Motorcycle Phone Mount', 'حامل هاتف كواد لوك', 259, 40, 'new', 'active', [['Mount', 'Handlebar']], 'universal'],
            ['v2', 'security', 'Abus', 'Abus Granit 37/60 Disc Lock', 'قفل قرص أبوس', 349, 22, 'new', 'active', [['Pin', '10 mm']], 'universal'],
            ['v2', 'covers', 'Oxford', 'Oxford Aquatex Motorcycle Cover', 'غطاء دراجة أكسفورد', 149, 35, 'new', 'active', [['Material', 'Waterproof']], 'universal'],
            ['v2', 'boots', 'Alpinestars', 'Alpinestars Tech 7 Boots (used)', 'حذاء ألبينستارز مستعمل', 450, 1, 'used', 'active', [['Size', '43'], ['Wear', 'Light']], 'universal'],
            ['v3', 'luggage', null, 'Adventure Tank Bag 15L', 'حقيبة خزان 15 لتر', 310, 6, 'new', 'draft', [['Capacity', '15 L']], 'universal'],
            ['v3', 'helmets', null, 'Desert Sand Riding Goggles', 'نظارات الصحراء', 120, 20, 'new', 'pending_review', [['Lens', 'Anti-fog']], 'universal'],
        ];

        $products = [];
        foreach ($templates as $i => [$vKey, $catSlug, $brandName, $name, $nameAr, $price, $stock, $condition, $status, $attrs, $fits]) {
            $slug = Str::slug($name);
            $categoryId = $cats[$catSlug];
            $color = $this->rootColor($categoryId);

            $product = Product::create([
                'vendor_id'      => $vendors[$vKey]->id,
                'category_id'    => $categoryId,
                'brand_id'       => $brandName ? $brands[$brandName]->id : null,
                'reference'      => 'DEMO-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'slug'           => $slug,
                'name'           => $name,
                'name_ar'        => $nameAr,
                'description'    => $name . ' by ' . ($brandName ?? 'a trusted maker') . '. Sold and shipped by ' . $vendors[$vKey]->shop_name . '.',
                'price'          => $price,
                'stock_quantity' => $stock,
                'condition'      => $condition,
                'status'         => $status,
                'is_featured'    => $i % 5 === 0 && $status === 'active',
                'published_at'   => $status === 'active' ? now()->subDays($i + 1) : null,
            ]);

            foreach ([0, 1] as $n) {
                ProductImage::create([
                    'product_id'     => $product->id,
                    'image_path'     => $this->placeholder("marketplace/demo/products/{$slug}-{$n}.png", 800, 800, $n === 0 ? $color : '#6B6560', $name),
                    'is_cover'       => $n === 0,
                    'order_position' => $n,
                ]);
            }

            foreach ($attrs as $pos => [$label, $value]) {
                ProductAttribute::create([
                    'product_id' => $product->id, 'label' => $label, 'value' => $value, 'order_position' => $pos,
                ]);
            }

            if ($fits === 'bike' && $bikes->isNotEmpty()) {
                foreach ($bikes->slice($i % $bikes->count(), 2) as $bike) {
                    ProductMotorcycle::create([
                        'product_id'    => $product->id,
                        'moto_brand_id' => $bike->brand_id,
                        'moto_model_id' => $bike->model_id,
                        'moto_year_id'  => $bike->year_id,
                        'is_universal'  => false,
                    ]);
                }
            } else {
                ProductMotorcycle::create(['product_id' => $product->id, 'is_universal' => true]);
            }

            $products[] = $product->load('vendor');
        }

        return $products;
    }

    // ------------------------------------------------------------------ promo / addresses

    private function seedPromoCodes(array $vendors): array
    {
        $base = [
            'discount_type'     => 'percentage',
            'max_discount'      => null,
            'min_listing_price' => 0,
            'usage_limit'       => null,
            'per_user_limit'    => 5,
            'valid_from'        => now()->subDay(),
            'valid_until'       => now()->addMonths(3),
            'is_active'         => true,
            'display'           => false,
            'applies_to'        => 'marketplace',
        ];

        return [
            'platform' => PromoCode::updateOrCreate(['code' => 'MP5'], $base + [
                'description' => 'Marketplace: 5% off the whole order (DabApp)',
                'discount_value' => 5, 'vendor_id' => null, 'funded_by' => 'platform',
            ]),
            'vendor' => PromoCode::updateOrCreate(['code' => 'SHOPA10'], $base + [
                'description' => 'Marketplace: 10% off Moto Parts Riyadh items',
                'discount_value' => 10, 'vendor_id' => $vendors['v1']->id, 'funded_by' => 'vendor',
            ]),
        ];
    }

    private function seedAddresses(array $users): array
    {
        return [
            'buyer1' => Address::create([
                'user_id' => $users['buyer1']->id, 'full_name' => 'Ahmed Ali', 'phone' => $users['buyer1']->phone,
                'country_id' => 1, 'city_id' => 1, 'address_line' => 'Olaya St, building 12', 'postal_code' => '12211', 'is_default' => true,
            ]),
            'buyer2' => Address::create([
                'user_id' => $users['buyer2']->id, 'full_name' => 'Layla Hassan', 'phone' => $users['buyer2']->phone,
                'country_id' => 1, 'city_id' => 2, 'address_line' => 'Tahlia St, apartment 4', 'postal_code' => '21452', 'is_default' => true,
            ]),
        ];
    }

    // ------------------------------------------------------------------ orders

    private function seedOrders(array $users, array $addresses, array $products, array $promos): array
    {
        // Product indexes follow the template order above.
        $delivered = $this->makeOrder($users['buyer1'], $addresses['buyer1'],
            [[$products[0], 2], [$products[13], 1]],
            ['status' => 'delivered', 'item' => 'delivered', 'days_ago' => 14, 'payment' => 'completed'],
            [$promos['vendor'], $promos['platform']]);

        $processing = $this->makeOrder($users['buyer2'], $addresses['buyer2'],
            [[$products[15], 1], [$products[16], 1]],
            ['status' => 'processing', 'item' => 'processing', 'days_ago' => 3, 'payment' => 'completed'],
            [$promos['platform']]);

        // Two vendors, two parcels: one already shipped, the other not yet.
        $multi = $this->makeOrder($users['buyer2'], $addresses['buyer2'],
            [[$products[11], 2], [$products[18], 1]],
            ['status' => 'processing', 'item' => 'pending', 'days_ago' => 2, 'payment' => 'completed'],
            []);
        $first = $multi->items->firstWhere('vendor_id', $products[11]->vendor_id);
        $first->update(['fulfillment_status' => 'shipped', 'tracking_number' => '1234567890', 'shipped_at' => now()->subDay()]);

        $pending = $this->makeOrder($users['buyer1'], $addresses['buyer1'],
            [[$products[5], 1]],
            ['status' => 'pending_payment', 'item' => 'pending', 'days_ago' => 0, 'payment' => 'pending'],
            []);

        return compact('delivered', 'processing', 'multi', 'pending');
    }

    /**
     * @param  array<int,array{0:Product,1:int}>  $lines
     * @param  PromoCode[]  $promos
     */
    private function makeOrder(User $buyer, Address $address, array $lines, array $state, array $promos): Order
    {
        $createdAt = now()->subDays($state['days_ago']);
        $order = Order::create([
            'order_number'     => Order::generateOrderNumber(),
            'user_id'          => $buyer->id,
            'address_id'       => $address->id,
            'shipping_address' => $address->only(['full_name', 'phone', 'country_id', 'city_id', 'address_line', 'postal_code']),
            'currency'         => 'SAR',
            'status'           => $state['status'],
            'expires_at'       => $state['status'] === 'pending_payment' ? now()->addMinutes(30) : null,
            'paid_at'          => $state['payment'] === 'completed' ? $createdAt : null,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        $subtotal = $shipping = $discount = 0.0;
        $shippedVendors = [];
        $promoTotals = [];

        foreach ($lines as [$product, $qty]) {
            $total = round($product->price * $qty, 2);

            $method = VendorShippingMethod::where('vendor_id', $product->vendor_id)->orderBy('id')->first();
            $fee = 0.0;
            if ($method && ! isset($shippedVendors[$product->vendor_id])) {
                $fee = (float) $method->flat_rate; // carried by the vendor's first line
                $shippedVendors[$product->vendor_id] = true;
            }

            $vendorDiscount = $platformDiscount = 0.0;
            foreach ($promos as $promo) {
                $amount = round($total * $promo->discount_value / 100, 2);
                if ($promo->vendor_id === null) {
                    $platformDiscount += $amount;
                    $promoTotals[$promo->id] = ($promoTotals[$promo->id] ?? 0) + $amount;
                } elseif ($promo->vendor_id === $product->vendor_id) {
                    $vendorDiscount += $amount;
                    $promoTotals[$promo->id] = ($promoTotals[$promo->id] ?? 0) + $amount;
                }
            }

            // Commission on what the vendor actually sells for. Vendor-funded promo comes out of the vendor's
            // share, platform-funded promo out of DabApp's margin.
            $rate = $this->commissionRate($product);
            $base = $total - $vendorDiscount;
            $commission = round($base * $rate / 100, 2);

            OrderItem::create([
                'order_id'           => $order->id,
                'product_id'         => $product->id,
                'vendor_id'          => $product->vendor_id,
                'shipping_method_id' => $method?->id,
                'product_name'       => $product->name,
                'product_reference'  => $product->reference,
                'quantity'           => $qty,
                'unit_price'         => $product->price,
                'total_price'        => $total,
                'shipping_fee'       => $fee,
                'discount_amount'    => $vendorDiscount + $platformDiscount,
                'commission_rate'    => $rate,
                'commission_amount'  => $commission,
                'vendor_net'         => round($base - $commission + $fee, 2),
                'fulfillment_status' => $state['item'],
                'delivered_at'       => $state['item'] === 'delivered' ? $createdAt->copy()->addDays(4) : null,
            ]);

            $subtotal += $total;
            $shipping += $fee;
            $discount += $vendorDiscount + $platformDiscount;

            // Stock is reserved as soon as the order exists; sales only count once paid.
            $product->decrement('stock_quantity', min($qty, $product->stock_quantity));
            if ($state['payment'] === 'completed') {
                $product->increment('sales_count', $qty);
            }
        }

        foreach ($promos as $promo) {
            if (isset($promoTotals[$promo->id])) {
                OrderPromoCode::create([
                    'order_id'        => $order->id,
                    'promo_code_id'   => $promo->id,
                    'vendor_id'       => $promo->vendor_id,
                    'code'            => $promo->code,
                    'discount_amount' => $promoTotals[$promo->id],
                ]);
            }
        }

        $order->update([
            'subtotal'     => $subtotal,
            'shipping_fee' => $shipping,
            'discount'     => $discount,
            'total'        => round($subtotal + $shipping - $discount, 2),
        ]);

        Payment::create([
            'order_id'       => $order->id,
            'gateway'        => 'paytabs',
            'amount'         => $order->total,
            'currency'       => 'SAR',
            'payment_status' => $state['payment'],
            'cart_id'        => 'cart_demo_' . $order->id,
            'tran_ref'       => $state['payment'] === 'completed' ? 'TST-DEMO-' . str_pad((string) $order->id, 6, '0', STR_PAD_LEFT) : null,
            'payment_url'    => $state['payment'] === 'pending' ? 'https://secure.paytabs.sa/payment/page/DEMO' . $order->id : null,
            'expires_at'     => $state['payment'] === 'pending' ? now()->addMinutes(30) : null,
            'paid_at'        => $state['payment'] === 'completed' ? $createdAt : null,
        ]);

        return $order->load('items');
    }

    private function commissionRate(Product $product): float
    {
        return (float) ($product->vendor->commission_override
            ?? $this->categoryRates[$product->category_id]
            ?? $this->globalRate);
    }

    private function seedPayout(Vendor $vendor, Order $order, int $adminId): void
    {
        $items = $order->items->where('vendor_id', $vendor->id);

        $payout = VendorPayout::create([
            'vendor_id'      => $vendor->id,
            'period_start'   => now()->subDays(20)->toDateString(),
            'period_end'     => now()->subDays(7)->toDateString(),
            'gross_sales'    => $items->sum('total_price'),
            'commission'     => $items->sum('commission_amount'),
            'net_amount'     => $items->sum('vendor_net'),
            'currency'       => 'SAR',
            'status'         => 'approved',
            'approved_by'    => $adminId,
            'approved_at'    => now()->subDays(5),
            'bank_name'      => 'Al Rajhi Bank',
            'iban'           => 'SA0380000000608010167519',
            'items_snapshot' => $items->map(fn ($i) => ['order_item_id' => $i->id, 'net' => (float) $i->vendor_net])->values()->all(),
            'notes'          => 'Demo payout',
        ]);

        OrderItem::whereIn('id', $items->pluck('id'))->update(['payout_id' => $payout->id]);
    }

    // ------------------------------------------------------------------ social

    private function seedSocial(array $users, array $products, Order $delivered): void
    {
        $b1 = $users['buyer1'];
        $b2 = $users['buyer2'];
        $tyreItem = $delivered->items->firstWhere('product_id', $products[0]->id);
        $helmetItem = $delivered->items->firstWhere('product_id', $products[13]->id);

        // Reviews: two verified (delivered order), one from someone who did not buy it
        ProductReview::create(['product_id' => $products[0]->id, 'user_id' => $b1->id, 'order_item_id' => $tyreItem->id,
            'rating' => 5, 'title' => 'Great grip', 'comment' => 'Superb in the wet, worth every riyal.', 'verified_purchase' => true]);
        ProductReview::create(['product_id' => $products[13]->id, 'user_id' => $b1->id, 'order_item_id' => $helmetItem->id,
            'rating' => 4, 'comment' => 'Quiet and comfortable. The visor could seal better.', 'verified_purchase' => true]);
        ProductReview::create(['product_id' => $products[0]->id, 'user_id' => $b2->id,
            'rating' => 4, 'comment' => 'Good tyre, bought the same one elsewhere first.', 'verified_purchase' => false]);

        // Q&A
        ProductQuestion::create(['product_id' => $products[0]->id, 'user_id' => $b1->id,
            'question' => 'Does this front tyre fit a 2021 MT-07?',
            'answer' => 'Yes, it fits the stock 17-inch front rim.', 'answered_at' => now()->subDays(10),
            'answered_by' => $products[0]->vendor->user_id]);
        ProductQuestion::create(['product_id' => $products[13]->id, 'user_id' => $b2->id,
            'question' => 'Is the Pinlock insert included?']);

        // Comments (with a reply)
        $comment = ProductComment::create(['product_id' => $products[0]->id, 'user_id' => $b1->id,
            'content' => 'Excellent tyre, very stable at speed.']);
        ProductComment::create(['product_id' => $products[0]->id, 'user_id' => $b2->id, 'parent_id' => $comment->id,
            'content' => 'Agree, mine is at 8,000 km and still fine.']);
        ProductComment::create(['product_id' => $products[13]->id, 'user_id' => $b2->id,
            'content' => 'Looks great in matte black.']);

        // Likes
        foreach ([[$b1, [0, 8, 13]], [$b2, [0, 14]]] as [$user, $indexes]) {
            foreach ($indexes as $i) {
                ProductLike::create(['product_id' => $products[$i]->id, 'user_id' => $user->id]);
            }
        }

        // Wishlist
        foreach ([[$b1, [2, 8, 15]], [$b2, [0]]] as [$user, $indexes]) {
            foreach ($indexes as $i) {
                Wishlist::create(['product_id' => $products[$i]->id, 'user_id' => $user->id]);
            }
        }
    }

    private function seedCarts(array $users, array $products): void
    {
        $cart = Cart::create(['user_id' => $users['buyer2']->id, 'status' => 'active', 'last_activity_at' => now()]);
        foreach ([[$products[4], 1], [$products[10], 2]] as [$product, $qty]) {
            CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'vendor_id' => $product->vendor_id,
                'quantity' => $qty, 'unit_price_snapshot' => $product->price]);
        }

        // A guest cart: no user, identified by the device's X-Cart-Session
        $guest = Cart::create(['session_id' => 'demo-guest-session-0001', 'status' => 'active', 'last_activity_at' => now()]);
        CartItem::create(['cart_id' => $guest->id, 'product_id' => $products[2]->id, 'vendor_id' => $products[2]->vendor_id,
            'quantity' => 1, 'unit_price_snapshot' => $products[2]->price]);
    }

    private function seedChat(array $users, Vendor $vendor, Product $product): void
    {
        $session = ChatSession::create([
            'user_id'        => $users['buyer1']->id,
            'vendor_id'      => $vendor->id,
            'product_id'     => $product->id,
            'session_status' => 'active',
            'started_at'     => now()->subDay(),
        ]);

        // sender_type is a 2-value enum (user | provider): the vendor side uses 'provider'.
        $messages = [
            [$users['buyer1']->id, 'user', 'Hi, is the 120/70 in stock for delivery to Riyadh this week?', true],
            [$users['vendor1']->id, 'provider', 'Hello! Yes, we ship with DHL within 2 to 4 days.', false],
        ];
        foreach ($messages as $n => [$senderId, $type, $text, $read]) {
            ChatMessage::create([
                'session_id' => $session->id, 'sender_id' => $senderId, 'sender_type' => $type,
                'message' => $text, 'message_type' => 'text', 'is_read' => $read,
            ]);
        }
    }

    /** Recompute the denormalised counters from the seeded rows. */
    private function refreshCounters(): void
    {
        Product::withCount(['likes'])->get()->each(function (Product $p) {
            $reviews = ProductReview::where('product_id', $p->id)->approved();
            $p->update([
                'likes_count'   => $p->likes_count,
                'reviews_count' => $reviews->count(),
                'rating_avg'    => round((float) ($reviews->avg('rating') ?? 0), 2),
            ]);
        });

        Vendor::all()->each(function (Vendor $v) {
            $reviews = ProductReview::whereIn('product_id', Product::where('vendor_id', $v->id)->pluck('id'))->approved();
            $v->update([
                'reviews_count' => $reviews->count(),
                'rating_avg'    => round((float) ($reviews->avg('rating') ?? 0), 2),
            ]);
        });
    }

    // ------------------------------------------------------------------ helpers

    private function rootColor(int $categoryId): string
    {
        $category = Category::find($categoryId);
        while ($category && $category->parent_id) {
            $category = Category::find($category->parent_id);
        }

        return match ($category?->slug) {
            'parts'       => '#3A5468',
            'oils-care'   => '#B8291A',
            'accessories' => '#0B7A61',
            'riding-gear' => '#F03D24',
            default       => '#6B6560',
        };
    }

    /** Draws a flat-colour PNG with the label centred, stores it on the public disk, returns its path. */
    private function placeholder(string $path, int $w, int $h, string $hex, string $label): string
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        $img = imagecreatetruecolor($w, $h);
        imagefilledrectangle($img, 0, 0, $w, $h, imagecolorallocate($img, $r, $g, $b));

        $font = 5;
        $cw = imagefontwidth($font);
        $ch = imagefontheight($font);
        $lines = explode("\n", wordwrap(preg_replace('/[^\x20-\x7E]/', '', $label), max(1, intdiv($w - 40, $cw)), "\n", true));
        $y = intdiv($h - count($lines) * ($ch + 4), 2);
        $white = imagecolorallocate($img, 255, 255, 255);

        foreach ($lines as $line) {
            imagestring($img, $font, intdiv($w - strlen($line) * $cw, 2), $y, $line, $white);
            $y += $ch + 4;
        }

        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        imagedestroy($img);

        Storage::disk('public')->put($path, $png);

        return $path;
    }
}
