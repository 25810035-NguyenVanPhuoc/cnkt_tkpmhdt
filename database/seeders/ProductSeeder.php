<?php

namespace Database\Seeders;

use App\Enums\ProductUnitStatus;
use App\Models\Category;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\ProductUnit;
use App\Models\Warehouse;
use App\Services\Media\ImageOptimizer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Dữ liệu mẫu chuyển đổi từ `database/coza_seed_reference.sql` (schema + data gốc của
 * CozaStore — cửa hàng thiết bị Apple). Chỉ lấy 2 bảng liên quan tới module Sản phẩm hiện có
 * (`Categories`, `Products`) — các bảng Orders/Accounts/Reviews... của CozaStore không map
 * sang schema hiện tại nên bỏ qua.
 *
 * Ảnh sản phẩm: file SQL gốc chỉ lưu TÊN file (cột `image`, `image1..4`), ảnh thật nằm ở
 * `src/main/webapp/images/` của project CozaStore (Nhom6...FinalProject) — đã copy đúng
 * 127 file ảnh thật sự được dùng vào `database/seeders/assets/product-images/` để seeder này
 * tự chứa, không phụ thuộc project khác trên máy. Ảnh được import qua đúng Media Library
 * (`ImageOptimizer`) như khi upload thật qua UI — có resize + sinh thumbnail.
 *
 * Các trường KHÔNG có trong file gốc, phải tự suy ra khi chuyển đổi:
 * - `sku`, `slug`: sinh mới (CozaStore không có SKU, chỉ có `id` số).
 * - `cost_price`: file gốc chỉ có `price` (giá bán) — cost_price lấy tạm 80% giá bán.
 * - `is_serialized`: file gốc không phân biệt — tự gán serial/IMEI cho nhóm thiết bị chính
 *   (iPhone, MacBook, iPad, Watch), còn phụ kiện/âm thanh quản lý theo số lượng.
 */
class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Nhóm sản phẩm quản lý theo serial/IMEI (điện thoại, laptop, tablet, đồng hồ). */
    private const SERIALIZED_CATEGORIES = ['iPhone', 'MacBook', 'iPad', 'Watch'];

    /** Số lượng unit demo tạo cho mỗi sản phẩm serial — file gốc ghi đồng loạt quantity=100
     *  cho mọi sản phẩm (rõ ràng là placeholder), sinh 100 IMEI/sản phẩm là quá nặng cho
     *  seeder demo nên rút xuống 5. */
    private const SERIALIZED_UNITS_PER_PRODUCT = 5;

    /** Số lượng tồn kho cho sản phẩm quản lý theo số lượng — giữ đúng giá trị 100 trong file gốc. */
    private const BULK_STOCK_QUANTITY = 100;

    private const CATEGORIES = ['iPhone', 'MacBook', 'iPad', 'Watch', 'Âm Thanh', 'Phụ Kiện'];

    /** [name, price, category, is_active, images] — trích từ bảng Products trong file gốc.
     *  images[0] = ảnh đại diện (cột `image`), images[1..4] = ảnh thumbnail (cột `image1..4`). */
    private const PRODUCTS = [
        ['iPhone 15 Pro Max', 34690000, 'iPhone', true, ['9a98155b.webp', '15promax11.jpeg', '15promax111.jpeg', '15promax1111.jpeg', '15promax11111.jpeg']],
        ['iPhone 15 Pro', 28790000, 'iPhone', true, ['f61683f7.webp', '26865658.webp', '486ae7f7.webp', '309760eb.webp', 'd4aaf33.webp']],
        ['iPhone 15 Plus', 25990000, 'iPhone', true, ['1e38f55b.webp', '8d36302.webp', '553a24fe.webp', '3ca7a42a.webp', 'b55fdf06.webp']],
        ['iPhone 15', 22990000, 'iPhone', true, ['876a9634.webp', '65f272d6.webp', '8774c576.webp', '3225e728.webp', 'd9666e42.webp']],
        ['iPhone 14 Pro Max', 29490000, 'iPhone', true, ['edc08299.webp', '341d0316.webp', '61610e0c.webp', '24460bf7.webp', '16dc3080.webp']],
        ['iPhone 14 Plus', 21590000, 'iPhone', true, ['6ec9b368.webp', '6e505897.webp', 'f27e9e91.webp', 'b9c4beaf.webp', '7f5dffaa.webp']],
        ['iPhone 14 Pro', 24590000, 'iPhone', true, ['8ed50f0b.webp', '683e33ed.webp', '8c63ab91.webp', '856bea25.webp', 'd7a2a8a9.webp']],
        ['iPhone 14', 21990000, 'iPhone', false, ['b08f72c8.webp', 'cc81f08f.webp', '2427b98f.webp', '108e23a9.webp', '939c24e1.webp']],

        ['MacBook Pro M3 Max 2023', 79990000, 'MacBook', true, ['3d00b4f1.webp', '4f09e4f4.webp', 'ab73b975.webp', '5249b944.webp', '992a49d2.webp']],
        ['MacBook Air M2 2023', 36490000, 'MacBook', true, ['9253138a.webp', '75d632e3.webp', '9c751ff2.webp', '72b74021.webp', '87e11bab.webp']],
        ['MacBook Pro 2023 M2 Pro', 62490000, 'MacBook', true, ['bd79c7e6.webp', '683c5594.webp', '64769149.webp', 'a884b5db.webp', 'b9ed3548.webp']],
        ['MacBook Air M2 2022', 33690000, 'MacBook', true, ['3a92c1ea.webp', 'd6095f74.webp', 'fb45f6d1.webp', 'd93d809d.webp', '81f5654d.webp']],
        ['MacBook Pro 13 M2 2022', 41690000, 'MacBook', true, ['b393bdd5.webp', 'e87da20.webp', 'a1d4758b.webp', '5450d5a6.webp', '576d25ea.webp']],

        ['iPad Pro M2 12.9', 32090000, 'iPad', true, ['9a189b33.webp', '8e152d38.webp', 'db3f7daf.webp', '5fa43dca.webp', '6bbe30c9.webp']],
        ['iPad Pro M2 11', 23990000, 'iPad', true, ['57a294e1.webp', 'd4934b7.webp', 'd695820c.webp', '926aaad.webp', 'e586f882.webp']],
        ['iPad 10 WiFi', 14290000, 'iPad', true, ['f0de8293.webp', '3c3c6ccf.webp', 'ddc9ef8c.webp', '554cc8c6.webp', '5b6890e4.webp']],
        ['iPad Air 5 WiFi', 20090000, 'iPad', true, ['9b6297e8.webp', '19556d37.webp', '9b693ec2.webp', 'f4da6a88.webp', 'eb5e6db8.webp']],
        ['iPad Air 5', 15490000, 'iPad', true, ['ipad5.jpeg', 'ccc74c16.webp', '12dbed59.webp', '386ebeb4.webp', '9763abf1.webp']],

        ['Apple Watch SE 2023', 6390000, 'Watch', true, ['watch1.jpeg', 'b78732c.webp', '1bf921e5.webp', 'ec627a67.webp', 'a912f208.webp']],
        ['Apple Watch Series 9', 11290000, 'Watch', true, ['watch2.jpeg', '9242589.webp', '334fc2d2.webp', 'db8115b8.webp', 'bdd6bfb.webp']],
        ['Apple Watch Ultra 2', 21990000, 'Watch', true, ['watch3.jpeg', '9242589.webp', '334fc2d2.webp', 'db8115b8.webp', 'bdd6bfb.webp']],
        ['Apple Watch Series 8', 17490000, 'Watch', true, ['watch4.jpeg', '9242589.webp', '334fc2d2.webp', 'db8115b8.webp', 'bdd6bfb.webp']],
        ['Apple Watch SE 2022', 7990000, 'Watch', true, ['3deb2dac.webp', '9242589.webp', '334fc2d2.webp', 'db8115b8.webp', 'bdd6bfb.webp']],

        ['Loa Bluetooth JBL', 11030000, 'Âm Thanh', true, ['9d67effe.webp', 'a.webp', 'b.webp', 'c.webp', 'd.webp']],
        ['Micro Shure MV7', 7790000, 'Âm Thanh', true, ['ce7eb207.webp', 'a.webp', 'b.webp', 'c.webp', 'd.webp']],
        ['Loa Bluetooth Harman', 8990000, 'Âm Thanh', true, ['6961a5a7.webp', 'a.webp', 'b.webp', 'c.webp', 'd.webp']],
        ['Tai Nghe Apple MDR', 520000, 'Âm Thanh', true, ['1b5cf22.webp', 'a.webp', 'b.webp', 'c.webp', 'd.webp']],
        ['Tai Nghe Beats Fit Pro', 4390000, 'Âm Thanh', true, ['5fbb9436.webp', 'a.webp', 'b.webp', 'c.webp', 'd.webp']],

        ['Apple Pencil', 2310000, 'Phụ Kiện', true, ['c258d7aa.webp', '637cfe6c.webp', 'b5a2154e.webp', 'a8c1940.webp', 'f3346663.webp']],
        ['Magic Mouse', 1990000, 'Phụ Kiện', true, ['c8ec77d2.webp', 'c9427aaf.webp', '70e35491.webp', '75dc2654.webp', '3472f1e9.webp']],
        ['Cáp sạc Magnetic Type C', 820000, 'Phụ Kiện', true, ['2396d65f.webp', '637cfe6c.webp', 'b5a2154e.webp', 'a8c1940.webp', 'f3346663.webp']],
        ['AirTag', 790000, 'Phụ Kiện', true, ['7b095ed7.webp', '637cfe6c.webp', 'b5a2154e.webp', 'a8c1940.webp', 'f3346663.webp']],
        ['Magic Keyboard', 3190000, 'Phụ Kiện', true, ['a91d9255.webp', 'bc75524f.webp', '6dbf479f.webp', 'bc75524f.webp', '6dbf479f.webp']],
    ];

    /** @var array<string, MediaFile> cache theo tên file gốc — 1 file vật lý chỉ import 1 lần dù nhiều sản phẩm dùng chung (vd a.webp, 9242589.webp...). */
    private array $importedMedia = [];

    public function run(): void
    {
        $categoryIds = collect(self::CATEGORIES)->mapWithKeys(function (string $name) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true],
            );

            return [$name => $category->id];
        });

        $warehouse = Warehouse::where('is_default', true)->first() ?? Warehouse::first();

        if (! $warehouse) {
            return;
        }

        $optimizer = app(ImageOptimizer::class);

        foreach (self::PRODUCTS as $sequence => [$name, $price, $categoryName, $isActive, $images]) {
            $sku = sprintf('CZ%03d', $sequence + 1);
            $isSerialized = in_array($categoryName, self::SERIALIZED_CATEGORIES, true);

            $product = Product::firstOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'slug' => Str::slug($name).'-'.strtolower($sku),
                    'category_id' => $categoryIds[$categoryName],
                    'cost_price' => (int) floor($price * 0.8 / 1000) * 1000,
                    'sale_price' => $price,
                    'description' => null,
                    'is_serialized' => $isSerialized,
                    'is_active' => $isActive,
                ]
            );

            if ($isSerialized) {
                if ($product->productUnits()->where('warehouse_id', $warehouse->id)->doesntExist()) {
                    for ($i = 1; $i <= self::SERIALIZED_UNITS_PER_PRODUCT; $i++) {
                        ProductUnit::create([
                            'product_id' => $product->id,
                            'warehouse_id' => $warehouse->id,
                            'imei_serial' => sprintf('%s-%03d', $sku, $i),
                            'status' => ProductUnitStatus::InStock,
                        ]);
                    }
                }
            } else {
                ProductStock::firstOrCreate(
                    ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
                    ['quantity' => self::BULK_STOCK_QUANTITY],
                );
            }

            if ($product->images()->doesntExist()) {
                foreach ($images as $index => $filename) {
                    $media = $this->importImage($optimizer, $filename);

                    if (! $media) {
                        continue;
                    }

                    $product->images()->create([
                        'path' => $media->path,
                        'sort_order' => $index,
                        'is_primary' => $index === 0,
                    ]);
                }
            }
        }
    }

    private function importImage(ImageOptimizer $optimizer, string $filename): ?MediaFile
    {
        if (isset($this->importedMedia[$filename])) {
            return $this->importedMedia[$filename];
        }

        $sourcePath = database_path('seeders/assets/product-images/'.$filename);

        if (! is_file($sourcePath)) {
            return null;
        }

        $uploadedFile = new UploadedFile($sourcePath, $filename, null, null, true);
        $meta = $optimizer->storeWithMetadata($uploadedFile, 'media');

        $media = MediaFile::create($meta + [
            'name' => pathinfo($filename, PATHINFO_FILENAME),
        ]);

        return $this->importedMedia[$filename] = $media;
    }
}
