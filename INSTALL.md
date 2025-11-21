# Hướng dẫn Cài đặt & Cấu hình

## Giới thiệu
Module WHMCS và backend PHP 7.4 này tự động tạo proxy HTTP (Squid) và SOCKS5 (Dante) trên VPS proxy. Mỗi proxy là một cặp gồm 1 địa chỉ IP, 1 cổng HTTP, 1 cổng SOCKS5 và bộ username/password riêng.

## Yêu cầu hệ thống
- VPS proxy Ubuntu 22.04 có quyền root, đã cài PHP 7.4, MariaDB 10.6.x, Squid, Dante và systemd
- Máy chủ WHMCS riêng (server billing) có quyền tải module server
- Thông tin Virtualizor API URL, API key, API pass và VPS ID (nếu cần gán/gỡ IP tự động)
- Công cụ: git, zip, bash để build/release

## Khởi tạo database
1. Tạo schema và bảng:
```bash
mysql -u root -p < sql/schema.sql
```
2. Thêm vài dòng seed minh họa cho IP/port (backend sẽ tự duyệt IP/port khả dụng):
```sql
INSERT INTO mod_dataz_proxy_ip_pool (ip_address, is_used) VALUES
  ('160.30.136.10', 0),
  ('160.250.132.20', 0);

INSERT INTO mod_dataz_proxy_port_pool (port, is_used) VALUES
  (1080, 0),
  (1081, 0),
  (1082, 0);
```
> Ghi chú: Admin nên import danh sách IP/port thực tế từ file riêng (ví dụ: `sql/ip_port_seed.sql`) để phục vụ môi trường sản xuất.

## Cài backend
1. Sao chép thư mục `backend/` lên VPS proxy (ví dụ: `/opt/dataz-proxy/backend`).
2. Chỉnh `backend/config.php` với thông tin database, `api_token`, đường dẫn Squid/Dante và thông số Virtualizor.
3. Thiết lập phân quyền an toàn:
```bash
chown -R www-data:www-data /opt/dataz-proxy/backend
find /opt/dataz-proxy/backend -type f -exec chmod 640 {} \;
find /opt/dataz-proxy/backend -type d -exec chmod 750 {} \;
```
4. Triển khai qua Nginx/Apache (PHP 7.4) hoặc kiểm thử nhanh:
```bash
php -S 0.0.0.0:8080 -t /opt/dataz-proxy/backend
```
5. Bảo vệ HTTPS, firewall; mọi request phải kèm header `Authorization: Bearer <API_TOKEN>` trùng với `config.php`.

## Cài module WHMCS
1. Sao chép `modules/servers/dataz_proxy/` vào thư mục `modules/servers/` của WHMCS.
2. Trong WHMCS Admin, tạo sản phẩm và chọn module `DATAZ Proxy Provisioning`.
3. Cấu hình module:
   - `API_ENDPOINT`: URL backend (ví dụ `https://proxy.example.com/backend`).
   - `API_TOKEN`: Khớp `api_token` của backend.
   - `AUTO_ASSIGN_IP`: yes/no (tự gán IP qua Virtualizor).
   - `VIRT_API_URL`, `VIRT_API_KEY`, `VIRT_API_PASS`, `VIRT_VPS_ID`: thông tin Virtualizor (nếu dùng).
   - `Quantity`: số lượng proxy mặc định cho sản phẩm.
4. Thêm custom field (textarea) nếu cần lưu ghi chú danh sách proxy.
5. Lưu cấu hình sau khi nhập URL và token backend.

## Cấu hình IP/port
- Bảng `mod_dataz_proxy_ip_pool` quản lý từng IP khả dụng (một IP mỗi dòng) với cờ `is_used`.
- Bảng `mod_dataz_proxy_port_pool` quản lý từng port (một port mỗi dòng) với cờ `is_used`.
- Có thể thêm dải IP/port bằng lệnh `INSERT` tương tự ví dụ hoặc import file seed riêng.

## Gỡ lỗi cơ bản
- Backend trả 401: kiểm tra header `Authorization: Bearer` khớp `config.php`.
- Hết IP/port: đảm bảo bảng pool còn dòng `is_used = 0`.
- Squid/Dante không áp dụng thay đổi: kiểm tra trạng thái service, log và quyền ghi `/etc/squid/conf.d/` cùng `/etc/systemd/system/`.
- Virtualizor gán/gỡ IP thất bại: xác thực URL/key/pass và VPS ID; đảm bảo VPS còn slot IP.
- Lỗi build/release: kiểm tra `zip`, `git` và (nếu dùng) `gh` có trong PATH.
