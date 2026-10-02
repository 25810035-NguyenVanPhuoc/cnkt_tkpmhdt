# Tài liệu nghiệp vụ - Hệ thống Quản lý Bán hàng (QLBanHang)

> Tài liệu này mô tả toàn bộ nghiệp vụ của hệ thống dựa trên thiết kế cơ sở dữ liệu (migrations) và mã nguồn hiện có (`app/`).
> **Cập nhật 2026-10-02:** đã rà lại toàn bộ Model/Controller/route/trang Vue so với bản thiết kế DB ban đầu. 3 nhóm nghiệp vụ lõi (Danh mục & Sản phẩm, Kho hàng, Bán hàng) cùng Nhân viên, Phân quyền, Cài đặt hệ thống đã lập trình xong. Còn **3 module chưa làm**: Nhập hàng (NCC/Purchase Order), Khuyến mãi, Khách hàng — xem chi tiết trạng thái ở từng mục và bảng tổng hợp bên dưới. Mỗi mục nghiệp vụ có dòng trạng thái riêng để phân biệt **✅ Đã cài đặt** / **⚠️ Có nhưng còn thiếu** / **❌ Chưa code**.

---

## 1. Tổng quan hệ thống

QLBanHang là hệ thống quản lý bán hàng/kho cho cửa hàng, bao gồm các nhóm nghiệp vụ chính:

1. Quản lý người dùng & phân quyền (nhân viên, vai trò, quyền hạn)
2. Quản lý danh mục & sản phẩm
3. Quản lý kho hàng (tồn kho theo số lượng và theo từng serial/IMEI)
4. Quản lý nhập hàng (nhà cung cấp, đơn nhập hàng)
5. Quản lý bán hàng (khách hàng, đơn hàng)
6. Khuyến mãi
7. Nhật ký thao tác & cấu hình hệ thống

**Trạng thái triển khai chung:**

| Thành phần | Trạng thái |
|---|---|
| Database schema (migrations) | ✅ Đầy đủ cho toàn bộ 20 bảng nghiệp vụ |
| Xác thực (đăng nhập/đăng xuất, Sanctum token) | ✅ Đã cài đặt |
| Phân quyền RBAC (Spatie Permission) | ✅ Đã áp dụng middleware `permission:` vào toàn bộ route nghiệp vụ; có 3 role (`admin`, `sales_staff`, `warehouse_staff`) |
| Danh mục & Sản phẩm | ✅ Model/Controller/CRUD + Media Library, có trang Vue |
| Kho hàng (tồn kho SL + serial/IMEI, nhập/điều chỉnh/chuyển kho) | ✅ Model/Controller/Service + trang Vue |
| Bán hàng (đơn hàng, đổi trạng thái, thanh toán) | ✅ `OrderService` + `OrderController` + trang Vue (POS) |
| Nhân viên & Phân quyền (role/permission CRUD) | ✅ Model/Controller/CRUD + trang Vue |
| Cài đặt hệ thống (`settings`) | ✅ `SettingsManager` (Singleton) + `SettingsController` + form Vue |
| **Nhập hàng** (`suppliers`, `purchase_orders`, `purchase_order_items`) | ❌ Chỉ có DB schema — chưa có Model/Controller/route/trang Vue (trang `purchasing/orders/list.vue` đang là placeholder) |
| **Khuyến mãi** (`promotions`, `promotion_customer`, `order_promotion`) | ❌ Chỉ có DB schema — chưa có Model/Controller/route/trang Vue (trang `promotions/list.vue` đang là placeholder); `OrderController` cũng chưa áp khuyến mãi khi tạo đơn |
| **Khách hàng** (`customers`) | ✅ `CustomerController` (CRUD) + `/api/customers` + trang Vue; `OrderService` đã tự gắn `customer_id` theo SĐT từ trước |
| Ghi nhật ký thao tác (`audit_logs`) | ❌ Chỉ có DB schema, chưa có code ghi |

---

## 2. Xác thực & Phân quyền

### 2.1 Xác thực (Đã cài đặt)

Dùng Laravel Sanctum, xử lý ở `app/Http/Controllers/Api/AuthController.php`.

| Endpoint | Method | Middleware | Mô tả |
|---|---|---|---|
| `/api/login` | POST | - | Đăng nhập bằng `email` + `password` |
| `/api/logout` | POST | `auth:sanctum` | Xoá access token hiện tại |
| `/api/me` | GET | `auth:sanctum` | Lấy thông tin user hiện tại (kèm roles, permissions) |

Quy tắc nghiệp vụ khi đăng nhập:
- Kiểm tra email tồn tại và mật khẩu đúng.
- Nếu tài khoản bị khoá (`users.is_active = false`) → từ chối đăng nhập với thông báo "Thông tin đăng nhập không đúng hoặc tài khoản đã bị khoá."
- Token được tạo với tên `spa` (`createToken('spa')`).

`GET /api/me` trả về: `id, name, email, roles, permissions`.

### 2.2 Phân quyền RBAC (Cấu trúc đã có, chưa áp dụng vào route)

Dùng package `spatie/laravel-permission`. Dữ liệu được seed sẵn trong `database/seeders/DatabaseSeeder.php`:

- **10 module nghiệp vụ:** `sales, products, categories, warehouse, purchasing, customers, employees, promotions, roles, settings`
- **4 hành động mỗi module:** `view, create, update, delete`
- → Sinh ra **40 quyền** dạng `module.action` (vd: `products.create`, `sales.view`).
- **1 vai trò duy nhất `admin`** được gán toàn bộ 40 quyền.
- **Tài khoản admin mặc định:** email `admin@qlibanhang.local`, mật khẩu `password`.

✅ Đã có thêm 2 role ngoài `admin`: `sales_staff` (`sales.view/create/update`, `products.view`, `categories.view`, `warehouse.view`) và `warehouse_staff` (toàn quyền `warehouse.*` + `products.*`, `categories.view`). Mọi route nghiệp vụ đã áp middleware `permission:module.action` tương ứng (xem từng `*Controller::middleware()` dùng `HasMiddleware`).

---

## 3. Quản lý danh mục & sản phẩm

**Trạng thái: ✅ Đã cài đặt đầy đủ** — `CategoryController`/`ProductController` (CRUD + FormRequest + Resource), quản lý ảnh qua `ProductImageController` + Media Library (`MediaController`, `MediaFile`, `ImageOptimizer`), trang Vue `catalog/categories` và `catalog/products`.

### Bảng `categories`
- `name`, `slug` (unique), `is_active`

### Bảng `products`
- `sku` (unique), `name`, `slug` (unique), `category_id` (FK → categories, restrict), `cost_price`, `sale_price`, `description`, `is_serialized`, `is_active`
- `is_serialized`: phân biệt 2 kiểu quản lý tồn kho:
  - `true` → sản phẩm quản lý theo từng đơn vị/serial/IMEI (dùng bảng `product_units`), ví dụ điện thoại, laptop.
  - `false` → sản phẩm quản lý theo số lượng (dùng bảng `product_stock`), ví dụ phụ kiện, hàng tiêu hao.

### Bảng `product_images`
- `product_id` (FK cascade), `path`, `sort_order`, `is_primary` — nhiều ảnh cho 1 sản phẩm, đánh dấu ảnh đại diện.

**Nghiệp vụ dự kiến:** CRUD danh mục/sản phẩm, quản lý ảnh sản phẩm, bật/tắt hiển thị (`is_active`), tính giá bán dựa trên `cost_price`.

---

## 4. Quản lý kho hàng

**Trạng thái: ✅ Đã cài đặt đầy đủ** — `WarehouseController`, `StockController` (nhập/điều chỉnh/chuyển kho), `ProductUnitController`, service `InventoryService` + `InventoryStrategy` (Bulk/Serialized) xử lý đúng 2 kiểu tồn kho (số lượng vs serial/IMEI), trang Vue `warehouse/stock`.

### Bảng `warehouses`
- `name`, `address`, `is_default` — hệ thống có thể có nhiều kho, 1 kho mặc định.

### Bảng `product_stock` (tồn kho theo số lượng)
- `product_id`, `warehouse_id`, `quantity` — unique theo cặp (product_id, warehouse_id).
- Dùng cho sản phẩm `is_serialized = false`.

### Bảng `product_units` (tồn kho theo từng đơn vị/serial)
- `product_id`, `warehouse_id`, `purchase_order_item_id` (nullable — đơn nhập hàng nào tạo ra unit này), `order_item_id` (nullable — đã bán trong đơn hàng nào), `imei_serial` (unique)
- `status`: enum `in_stock → reserved → sold → returned / damaged`
  - `in_stock`: còn trong kho, chưa bán
  - `reserved`: đã giữ chỗ cho 1 đơn hàng
  - `sold`: đã bán
  - `returned`: khách trả hàng
  - `damaged`: hỏng, không bán được

### Bảng `stock_movements` (lịch sử biến động kho)
- `product_id`, `warehouse_id`, `product_unit_id` (nullable), `type`: enum `in / out / adjustment / transfer`, `quantity`, `reference_type` + `reference_id` (liên kết đa hình tới đơn nhập hàng/đơn bán hàng gây ra biến động), `note`, `created_by`.

**Nghiệp vụ dự kiến:**
- Nhập kho (từ đơn nhập hàng) → tăng `product_stock.quantity` hoặc tạo `product_units` mới với `status = in_stock`, ghi `stock_movements` loại `in`.
- Xuất kho (từ đơn bán hàng) → giảm `product_stock.quantity` hoặc chuyển `product_units.status = sold`, ghi `stock_movements` loại `out`.
- Điều chỉnh kho thủ công (kiểm kê) → loại `adjustment`.
- Chuyển kho giữa các warehouse → loại `transfer`.

---

## 5. Quản lý nhập hàng

**Trạng thái: ❌ Chưa làm — chỉ có DB schema.** Không có Model (`Supplier`, `PurchaseOrder`, `PurchaseOrderItem`), không có Controller, không có route `/api/suppliers`/`/api/purchase-orders`. Trang Vue `purchasing/orders/list.vue` đang là `PlaceholderPage`. Đây là 1 trong 3 module còn thiếu của hệ thống.

### Bảng `suppliers`
- `name`, `contact_name`, `phone`, `email`, `address`, `tax_code`, `is_active`

### Bảng `purchase_orders`
- `code` (unique), `supplier_id`, `warehouse_id` (kho nhận hàng), `status`, `order_date`, `expected_date`, `created_by`, `total_amount`
- `status`: enum `draft → ordered → partially_received → received`, hoặc huỷ `cancelled`

### Bảng `purchase_order_items`
- `purchase_order_id` (FK cascade), `product_id`, `quantity_ordered`, `quantity_received` (mặc định 0), `unit_cost`

**Luồng nghiệp vụ dự kiến (theo thiết kế schema, chưa có code enforce):**

```
draft ──(gửi đơn cho NCC)──> ordered ──(nhận 1 phần hàng)──> partially_received ──(nhận đủ)──> received
  │                              │
  └──────────(huỷ đơn)───────────┴────────────────────> cancelled
```

- Khi nhận hàng, cập nhật `quantity_received` trên từng `purchase_order_item`.
- Khi `quantity_received = quantity_ordered` cho tất cả item → tự động chuyển `status = received`.
- Khi nhận một phần → `status = partially_received`.
- Mỗi lần nhận hàng cần tạo `stock_movements` loại `in` và cập nhật tồn kho tương ứng (`product_stock` hoặc tạo mới `product_units`).

---

## 6. Quản lý bán hàng

**Trạng thái: ⚠️ Một nửa đã làm.** Phần **đơn hàng** (`orders`, `order_items`, `order_status_histories`) ✅ đã cài đặt đầy đủ: `OrderService` (đổi trạng thái + trừ/hoàn kho qua `InventoryService`), `OrderController` (CRUD, đổi trạng thái, huỷ, thanh toán, in hoá đơn), trang Vue `sales/list.vue` (POS). Đơn khách đặt từ storefront (`/api/storefront/orders`) cũng đi qua cùng `OrderService` này.

Phần **khách hàng** (`customers`) ✅ đã cài đặt: `CustomerController` (CRUD + tìm theo tên/SĐT) + `/api/customers`, trang Vue `customers/list.vue`. `OrderService::resolveCustomer()` đã tự `firstOrCreate` khách hàng theo `customer_phone` ngay từ khi `OrderService` được viết — cả đơn tạo trong admin lẫn đơn từ storefront đều tự động gắn `customer_id` (đính chính: bản cập nhật tài liệu trước đó ghi nhầm là chưa gắn — thực tế đã có sẵn và hoạt động đúng, xác nhận qua dữ liệu thật: 5 khách hàng đã tồn tại trong DB từ các đơn trước khi module này được xây).

### Bảng `customers`
- `name`, `phone` (unique), `email`, `address`, `loyalty_points` (điểm tích luỹ)

### Bảng `orders`
- `code` (unique), `customer_id` (nullable — cho phép khách vãng lai), `user_id` (nhân viên lập đơn), `warehouse_id`, `status`, `subtotal`, `discount_total`, `shipping_fee`, `grand_total`, `payment_method`, `paid_amount`, `order_date`, `note`
- `status`: enum `pending → confirmed → delivering → completed`, hoặc huỷ `cancelled`

### Bảng `order_items`
- `order_id` (FK cascade), `product_id`, `product_unit_id` (nullable — nếu sản phẩm bán theo serial), `quantity`, `unit_price`, `discount_amount`, `line_total`

### Bảng `order_status_histories`
- `order_id`, `from_status`, `to_status`, `changed_by`, `note` — nhật ký lịch sử đổi trạng thái đơn hàng.

**Luồng nghiệp vụ dự kiến:**

```
pending ──(xác nhận)──> confirmed ──(giao hàng)──> delivering ──(hoàn tất)──> completed
   │            │              │
   └────────────┴──────────────┴────────(huỷ)────> cancelled
```

- Mỗi lần đổi `status` cần ghi 1 dòng vào `order_status_histories`.
- Khi đơn chuyển sang `confirmed`/`completed`: trừ tồn kho (`product_stock` hoặc set `product_units.status = sold`), ghi `stock_movements` loại `out`.
- Khi đơn bị `cancelled` sau khi đã trừ kho: cần hoàn kho.
- `grand_total = subtotal - discount_total + shipping_fee`, `paid_amount` theo dõi số tiền khách đã thanh toán (hỗ trợ thanh toán một phần/công nợ).

---

## 7. Khuyến mãi

**Trạng thái: ❌ Chưa làm — chỉ có DB schema.** Không có Model (`Promotion`, `PromotionCustomer`, `OrderPromotion`), không có Controller, không có route `/api/promotions`. Trang Vue `promotions/list.vue` đang là `PlaceholderPage`. `OrderController`/`OrderService` hiện tại chỉ nhận `discount_amount` nhập tay theo từng dòng sản phẩm (nhân viên tự gõ số tiền giảm), chưa tự động áp khuyến mãi nào. Đây là 1 trong 3 module còn thiếu của hệ thống.

### Bảng `promotions`
- `name`, `code` (nullable, unique), `type`: enum `percentage / fixed_amount / buy_x_get_y`
- `value` (mức giảm — % hoặc số tiền tuỳ `type`), `buy_quantity` + `get_quantity` (dùng cho `buy_x_get_y`)
- `min_order_amount` (đơn tối thiểu để áp dụng), `applies_to`: enum `all / category / product` + `target_id` (id danh mục hoặc sản phẩm áp dụng, tuỳ `applies_to`)
- `starts_at`, `ends_at` (thời hạn), `usage_limit` + `usage_count` (giới hạn số lần dùng), `is_active`

### Bảng `promotion_customer`
- Gán khuyến mãi riêng cho từng khách hàng cụ thể (`promotion_id`, `customer_id`), theo dõi `assigned_at`/`used_at`.

### Bảng `order_promotion`
- Ghi nhận khuyến mãi đã áp dụng vào 1 đơn hàng cụ thể và số tiền đã giảm (`discount_amount`).

**Nghiệp vụ dự kiến:** khi tạo đơn hàng, hệ thống kiểm tra các khuyến mãi đang `is_active` và còn hiệu lực (`starts_at`/`ends_at`, `usage_limit`), tính `discount_amount` theo `type`, cộng dồn vào `orders.discount_total`, và tăng `usage_count`.

---

## 8. Nhật ký & Cấu hình hệ thống

### Bảng `audit_logs`
**Trạng thái: ❌ Chưa làm** — chỉ có DB schema, chưa có code ghi/đọc ở đâu trong `app/`.
- `user_id`, `action`, `auditable_type` + `auditable_id` (đa hình — bản ghi nào bị tác động), `old_values`/`new_values` (JSON), `ip_address`, `user_agent`
- Dùng để ghi lại lịch sử thay đổi dữ liệu quan trọng (ai sửa gì, khi nào).

### Bảng `settings`
**Trạng thái: ✅ Đã cài đặt** — `Setting` model, service `SettingsManager` (Singleton — 1 instance đọc/ghi bảng `settings` dùng chung, xem `docs/DESIGN_PATTERNS.docx`), `SettingsController` (`GET`/`PUT /api/settings`, gate bằng `settings.view`/`settings.update`), form Vue `settings/general/form.vue` (thông tin cửa hàng: tên, SĐT, email, địa chỉ, thuế VAT).
- `key` (unique), `value`, `type`, `group`, `updated_by`
- Cấu hình hệ thống dạng key-value (vd: thông tin cửa hàng, thuế suất mặc định...), có thể nhóm theo `group`.

---

## 9. Sơ đồ quan hệ tổng quát

```
users ──< orders ──< order_items >── products ── categories
  │          │                          │
  │          ├──< order_status_histories│
  │          └──< order_promotion >── promotions ──< promotion_customer >── customers
  │                                     │
  ├──< purchase_orders ──< purchase_order_items >── products
  │          │
  │      suppliers                warehouses ──< product_stock >── products
  │                                     │
  │                                product_units >── products
  │                                     │
  ├──< stock_movements ──> product_units / product_stock
  ├──< audit_logs
  └──< settings
```

---

## 10. Việc cần làm tiếp (TODO)

**Còn đúng 3 module nghiệp vụ chưa code (ngoài `audit_logs`):**

1. ~~Khách hàng~~ ✅ Đã làm (`feat/module-customers`).
2. **Nhập hàng** — tạo Model `Supplier`/`PurchaseOrder`/`PurchaseOrderItem`, `SupplierController` + `PurchaseOrderController`, service `PurchaseOrderService` xử lý luồng `draft → ordered → partially_received → received`/`cancelled` (nhận hàng cập nhật `quantity_received`, ghi `stock_movements` loại `in`, cộng `product_stock`/tạo `product_units` mới), trang Vue thay `purchasing/orders/list.vue`.
3. **Khuyến mãi** — tạo Model `Promotion`/`PromotionCustomer`/`OrderPromotion`, `PromotionController`, service `PromotionService` tính `discount_amount` theo `type` (`percentage`/`fixed_amount`/`buy_x_get_y`) và nối vào `OrderService::createOrder` để tự áp khi tạo đơn, trang Vue thay `promotions/list.vue`.

**Việc phụ, không gấp:**

4. Ghi `audit_logs` tự động khi có thao tác tạo/sửa/xoá trên các bảng quan trọng (chưa có observer/listener nào).
5. Bổ sung Job/Notification cho các sự kiện: cảnh báo tồn kho thấp, xác nhận đơn hàng, nhắc hạn khuyến mãi.
