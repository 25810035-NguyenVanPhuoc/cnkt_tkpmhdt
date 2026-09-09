# Tài liệu nghiệp vụ - Hệ thống Quản lý Bán hàng (QLBanHang)

> Tài liệu này mô tả toàn bộ nghiệp vụ của hệ thống dựa trên thiết kế cơ sở dữ liệu (migrations) và mã nguồn hiện có (`app/`).
> **Lưu ý quan trọng:** tại thời điểm viết tài liệu này, project mới ở giai đoạn khởi tạo — schema database đã được thiết kế đầy đủ cho toàn bộ nghiệp vụ, nhưng phần lớn logic xử lý (model, controller, service) **chưa được lập trình**. Mỗi mục nghiệp vụ bên dưới đều có dòng trạng thái để phân biệt rõ **Đã cài đặt** và **Mới thiết kế ở DB (chưa code)**.

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
| Phân quyền RBAC (Spatie Permission) | ✅ Cấu trúc + seed quyền đã có, ⚠️ chưa áp dụng vào route nào |
| Eloquent Models (Product, Order, Warehouse...) | ❌ Chưa có (chỉ có `User`) |
| Controllers nghiệp vụ (sản phẩm, đơn hàng, kho, nhập hàng...) | ❌ Chưa có (chỉ có `AuthController`) |
| Routes API cho nghiệp vụ | ❌ Chưa có (chỉ có `/login`, `/logout`, `/me`) |
| Service/luồng chuyển trạng thái đơn hàng, nhập hàng | ❌ Chưa có |
| Ghi nhật ký thao tác (`audit_logs`) | ❌ Chưa có code ghi |

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

⚠️ Hiện tại **chưa có vai trò nào khác ngoài `admin`** (ví dụ: nhân viên bán hàng, thủ kho...), và **chưa route nào áp dụng middleware `role:`/`permission:`** để thực sự giới hạn quyền truy cập — cần bổ sung khi xây controller cho từng module.

---

## 3. Quản lý danh mục & sản phẩm

**Trạng thái: ❌ Chỉ có DB schema, chưa có Model/Controller.**

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

**Trạng thái: ❌ Chỉ có DB schema, chưa có Model/Controller/logic tính tồn kho.**

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

**Trạng thái: ❌ Chỉ có DB schema, chưa có Model/Controller/logic chuyển trạng thái.**

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

**Trạng thái: ❌ Chỉ có DB schema, chưa có Model/Controller/logic chuyển trạng thái.**

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

**Trạng thái: ❌ Chỉ có DB schema, chưa có Model/Controller/logic tính giảm giá.**

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

**Trạng thái: ❌ Chỉ có DB schema, chưa có code ghi/đọc.**

### Bảng `audit_logs`
- `user_id`, `action`, `auditable_type` + `auditable_id` (đa hình — bản ghi nào bị tác động), `old_values`/`new_values` (JSON), `ip_address`, `user_agent`
- Dùng để ghi lại lịch sử thay đổi dữ liệu quan trọng (ai sửa gì, khi nào).

### Bảng `settings`
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

1. Tạo Eloquent Model cho 19 bảng còn thiếu, khai báo đầy đủ quan hệ (`belongsTo`/`hasMany`/`belongsToMany`).
2. Viết Controller + FormRequest cho từng module (CRUD danh mục, sản phẩm, kho, nhập hàng, bán hàng, khuyến mãi, khách hàng, nhà cung cấp, nhân viên, cấu hình).
3. Viết Service xử lý nghiệp vụ có trạng thái phức tạp: `OrderService` (đổi trạng thái đơn + trừ/hoàn kho), `PurchaseOrderService` (nhận hàng + nhập kho), `PromotionService` (tính giảm giá).
4. Áp dụng middleware `permission:module.action` vào route theo đúng 40 quyền đã seed.
5. Bổ sung vai trò khác ngoài `admin` (vd: `staff`, `warehouse_keeper`) với tập quyền phù hợp.
6. Ghi `audit_logs` tự động khi có thao tác tạo/sửa/xoá trên các bảng quan trọng.
7. Bổ sung Job/Notification cho các sự kiện: cảnh báo tồn kho thấp, xác nhận đơn hàng, nhắc hạn khuyến mãi.
