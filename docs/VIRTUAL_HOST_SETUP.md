# Hướng dẫn tạo Virtual Host cho dự án Laravel với WampServer (Windows)

Tài liệu này hướng dẫn tạo một virtual host dạng `ten-du-an.local` để chạy dự án Laravel
bằng WampServer trên Windows, thay cho việc gõ `php artisan serve` mỗi lần. Ví dụ cụ thể áp
dụng cho domain `qlibanhang.local` của dự án này đã được thực hiện sẵn — xem mục 5.

## Yêu cầu

- Đã cài [WampServer](https://www.wampserver.com/) (ví dụ máy này cài ở `D:\Tool\wamp64`).
- Có quyền Administrator trên máy (để sửa file `hosts` và khởi động lại dịch vụ Apache).
- Module `fcgid` của Apache đã bật nếu muốn chỉ định version PHP riêng cho từng vhost (WAMP
  bật sẵn theo mặc định).

## Bước 1 — Thêm domain vào file `hosts`

File `hosts` map một domain giả (không có DNS thật) về địa chỉ máy local `127.0.0.1`, để
trình duyệt hiểu `ten-du-an.local` chính là máy của bạn.

1. Mở **Notepad với quyền Administrator** (chuột phải Notepad → "Run as administrator").
2. Mở file: `C:\Windows\System32\drivers\etc\hosts`
3. Thêm 2 dòng vào cuối file (theo đúng format các domain đã có sẵn trong file):

   ```
   127.0.0.1	ten-du-an.local
   ::1	ten-du-an.local
   ```

4. Lưu file (Notepad chạy quyền Admin mới lưu được vào thư mục `System32`).

## Bước 2 — Thêm VirtualHost vào Apache

1. Mở file cấu hình vhost của WAMP:
   `D:\Tool\wamp64\bin\apache\apache2.4.62.1\conf\extra\httpd-vhosts.conf`

2. Thêm một block `<VirtualHost>` mới vào cuối file:

   ```apache
   <VirtualHost *:80>
       ServerName ten-du-an.local
       DocumentRoot "d:/duong-dan/toi/du-an/public"
       <Directory "d:/duong-dan/toi/du-an/public/">
           Options +Indexes +Includes +FollowSymLinks +MultiViews
           AllowOverride All
           Require local
       </Directory>

       <!-- Cho phép truy cập file/ảnh upload qua storage:link (symlink) -->
       <Directory "d:/duong-dan/toi/du-an/public/storage/">
           Options +FollowSymLinks
           AllowOverride None
           Require local
       </Directory>

       <!-- Chỉ cần nếu đang chạy `npm run dev` (Vite) và muốn hot-reload qua domain này -->
       ProxyPreserveHost On
       ProxyPass /@vite http://127.0.0.1:5173/@vite
       ProxyPassReverse /@vite http://127.0.0.1:5173/@vite
       ProxyPass /resources http://127.0.0.1:5173/resources
       ProxyPassReverse /resources http://127.0.0.1:5173/resources

       <!-- Chỉ định đúng version PHP cho vhost này (WAMP hỗ trợ nhiều PHP song song) -->
       <IfModule fcgid_module>
           Define FCGIPHPVERSION "8.3.14"
           FcgidInitialEnv PHPRC ${PHPROOT}${FCGIPHPVERSION}
           <Files ~ "\.php$">
               Options +Indexes +Includes +FollowSymLinks +MultiViews +ExecCGI
               AddHandler fcgid-script .php
               FcgidWrapper "${PHPROOT}${FCGIPHPVERSION}/php-cgi.exe" .php
           </Files>
       </IfModule>
   </VirtualHost>
   ```

   **Lưu ý:**
   - `DocumentRoot` của Laravel luôn phải là thư mục `public/`, không phải gốc dự án.
   - `ServerName` phải khớp đúng domain đã thêm ở Bước 1.
   - `FCGIPHPVERSION` phải là version PHP đã cài trong `D:\Tool\wamp64\bin\php\` (ví dụ
     `php8.3.14`) — trùng với version dự án yêu cầu (xem `composer.json` → `"php": "^..."`).
   - Bỏ khối `ProxyPass /@vite ...` nếu chỉ chạy bằng asset đã build (`npm run build`), không
     cần `npm run dev`.

## Bước 3 — Khởi động lại Apache

Chọn 1 trong 2 cách:

- **Qua icon WAMP** ở system tray: click icon → "Restart All Services".
- **Qua Services** (`services.msc`), hoặc PowerShell chạy với quyền Admin:

  ```powershell
  Restart-Service wampapache64
  ```

Nếu Apache không start được, mở `D:\Tool\wamp64\logs\apache_error.log` để xem lỗi cụ thể —
thường gặp nhất là sai cú pháp trong `httpd-vhosts.conf`, hoặc trùng `DocumentRoot`/`ServerName`
với vhost khác.

## Bước 4 — Kiểm tra

Mở trình duyệt vào `http://ten-du-an.local`. Nếu thấy đúng trang của dự án (không phải trang
mặc định của WAMP hay lỗi "Not Found") là đã cấu hình đúng.

Có thể kiểm tra nhanh domain đã resolve về `127.0.0.1` chưa (không cần đợi Apache) bằng:

```powershell
ping ten-du-an.local
```

## Mục 5 — Ví dụ đã áp dụng: `qlibanhang.local`

Cho dự án này (`d:\HCMUTE\TKPMHDT\Doan\qlibanhang_be`), virtual host đã được tạo sẵn với:

- **Domain:** `qlibanhang.local`
- **DocumentRoot:** `d:/HCMUTE/TKPMHDT/Doan/qlibanhang_be/public`
- **PHP version:** 8.3.14 (khớp `composer.json`)
- **Proxy Vite:** có sẵn — chạy `npm run dev` để có hot-reload qua `http://qlibanhang.local`,
  hoặc `npm run build` rồi truy cập bình thường (không cần `npm run dev`).

Sau khi Apache khởi động lại, truy cập `http://qlibanhang.local` sẽ vào đúng trang đăng nhập
của SPA Vue (route `/login`).

## Sự cố thường gặp

| Hiện tượng | Nguyên nhân | Cách xử lý |
|---|---|---|
| Trình duyệt báo "không tìm thấy máy chủ" | Chưa thêm domain vào `hosts`, hoặc lưu file `hosts` thất bại (thiếu quyền Admin) | Mở lại Notepad với quyền Admin, kiểm tra file `hosts` đã có domain chưa |
| Apache không start | Sai cú pháp trong `httpd-vhosts.conf` | Xem `apache_error.log`, hoặc dùng nút "Test Apache Setting" trong menu WAMP |
| Vào domain nhưng ra trang WAMP mặc định | Domain chưa map đúng `ServerName`, hoặc `NameVirtualHost`/thứ tự vhost bị đè bởi `_default_` | Kiểm tra `ServerName` khớp chính xác domain đã gõ trên trình duyệt |
| Trang load được nhưng lỗi 500 / trắng trang | Version PHP trong `FCGIPHPVERSION` không khớp version dự án cần, hoặc thiếu extension PHP | Đổi đúng version, kiểm tra `php.ini` của version đó có đủ extension (pdo_mysql, gd...) |
| Ảnh/file trong `storage` bị 403/404 | Chưa chạy `php artisan storage:link`, hoặc thiếu block `<Directory ".../public/storage/">` | Chạy `storage:link`, thêm đúng block như Bước 2 |
