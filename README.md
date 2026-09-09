# QLBanHang – Hệ thống Quản lý Bán hàng (Backend)

Backend API cho hệ thống quản lý bán hàng/kho cho cửa hàng, xây dựng trên Laravel.

> Xem chi tiết đầy đủ nghiệp vụ tại [`docs/NGHIEP_VU.md`](docs/NGHIEP_VU.md).

## Giới thiệu

QLBanHang là hệ thống quản lý bán hàng và kho hàng dành cho cửa hàng, bao gồm các nhóm nghiệp vụ chính:

1. Quản lý người dùng & phân quyền (nhân viên, vai trò, quyền hạn)
2. Quản lý danh mục & sản phẩm
3. Quản lý kho hàng (tồn kho theo số lượng và theo từng serial/IMEI)
4. Quản lý nhập hàng (nhà cung cấp, đơn nhập hàng)
5. Quản lý bán hàng (khách hàng, đơn hàng)
6. Khuyến mãi
7. Nhật ký thao tác & cấu hình hệ thống

## Trạng thái triển khai

| Thành phần | Trạng thái |
|---|---|
| Database schema (migrations) | ✅ Đầy đủ cho toàn bộ 20 bảng nghiệp vụ |
| Xác thực (đăng nhập/đăng xuất, Sanctum token) | ✅ Đã cài đặt |
| Phân quyền RBAC (Spatie Permission) | ✅ Cấu trúc + seed quyền đã có, ⚠️ chưa áp dụng vào route nào |
| Eloquent Models nghiệp vụ (Product, Order, Warehouse...) | ❌ Chưa có (chỉ có `User`) |
| Controllers nghiệp vụ | ❌ Chưa có (chỉ có `AuthController`) |
| Routes API cho nghiệp vụ | ❌ Chưa có (chỉ có `/login`, `/logout`, `/me`) |

## Công nghệ sử dụng

- [Laravel](https://laravel.com) (PHP)
- [Laravel Sanctum](https://laravel.com/docs/sanctum) – xác thực API bằng token
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) – phân quyền RBAC
- MySQL (xem `schema.sql`)

## Cài đặt

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Tài khoản admin mặc định sau khi seed: `admin@qlibanhang.local` / `password`.

## Tài liệu

- [`docs/NGHIEP_VU.md`](docs/NGHIEP_VU.md) – tài liệu nghiệp vụ chi tiết (các module, luồng trạng thái, sơ đồ quan hệ, TODO).
- [`AGENTS.md`](AGENTS.md) – hướng dẫn dành cho AI coding agent làm việc trên dự án này.

## License

Dự án sử dụng framework Laravel, được cấp phép theo [MIT license](https://opensource.org/licenses/MIT).
