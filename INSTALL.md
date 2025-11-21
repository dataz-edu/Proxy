# Hướng dẫn Cài đặt & Cấu hình

## Giới thiệu
Tài liệu này hướng dẫn triển khai và cấu hình hệ thống tự động hóa proxy cho WHMCS, gồm backend PHP 7.4, cơ sở dữ liệu MariaDB 10.6.x và dịch vụ Squid/Dante trên Ubuntu 22.04.

## Yêu cầu hệ thống
- PHP 7.4 (CLI và web SAPI) với các extension: `curl`, `pdo_mysql`, `json`, `mbstring`
- MariaDB 10.6.x
- WHMCS có quyền tải lên module server
- Máy chủ proxy Ubuntu 22.04 sử dụng systemd
- Squid (HTTP) và Dante (SOCKS5) đã cài đặt trên máy chủ proxy
- Thông tin Virtualizor API URL, API key, API pass và VPS ID mục tiêu để gán/gỡ IP
- Git, zip và bash cho các script build/release

## Khởi tạo database
1. Tạo schema và bảng:
```bash
mysql -u root -p < sql/schema.sql
```
2. Khai báo dải IP và dải port (ví dụ minh họa, chỉ vài dòng). Backend sẽ tự động duyệt từng IP/port trong dải:
```sql
INSERT INTO mod_dataz_proxy_ip_pool (cidr, start_int, end_int, current_int) VALUES
  ('160.30.136.0/23', INET_ATON('160.30.136.0'), INET_ATON('160.30.137.255'), NULL),
  ('160.250.132.0/23', INET_ATON('160.250.132.0'), INET_ATON('160.250.133.255'), NULL);

INSERT INTO mod_dataz_proxy_port_pool (min_port, max_port, current_port) VALUES
  (1080, 1200, NULL);
```
> Ghi chú: Admin nên import dữ liệu thực tế (đủ dải IP/port sản xuất) từ file riêng như `sql/ip_port_seed.sql` nếu có.

## Cài backend
1. Sao chép thư mục `backend/` lên máy chủ proxy (ví dụ: `/opt/dataz-proxy/backend`).
2. Cập nhật `backend/config.php` với thông tin database, `api_token`, đường dẫn Squid/Dante và thông số Virtualizor.
3. Thiết lập phân quyền an toàn (ví dụ):
```bash
chown -R www-data:www-data /opt/dataz-proxy/backend
find /opt/dataz-proxy/backend -type f -exec chmod 640 {} \;
find /opt/dataz-proxy/backend -type d -exec chmod 750 {} \;
```
4. Triển khai qua web server (Apache/Nginx + PHP 7.4) hoặc kiểm thử nhanh:
```bash
php -S 0.0.0.0:8080 -t /opt/dataz-proxy/backend
```
5. Bảo vệ HTTPS và firewall; mọi request phải kèm header `Authorization: Bearer <API_TOKEN>` khớp với `config.php`.

## Cài module WHMCS
1. Sao chép `modules/servers/dataz_proxy/` vào thư mục `modules/servers/` của WHMCS.
2. Trong WHMCS Admin, tạo/sửa sản phẩm và chọn module `DATAZ Proxy Provisioning`.
3. Cấu hình module:
   - `API_ENDPOINT`: URL backend (ví dụ `https://proxy.example.com/backend`).
   - `API_TOKEN`: Trùng `api_token` của backend.
   - `PROXY_TYPE`: `http`, `socks5` hoặc `both`.
   - `AUTO_ASSIGN_IP`: `yes`/`no` (tự gán IP qua Virtualizor).
   - `VIRT_API_URL`, `VIRT_API_KEY`, `VIRT_API_PASS`, `VIRT_VPS_ID`: thông tin Virtualizor.
4. Thêm custom field sản phẩm tên `Proxy List` (textarea) để lưu danh sách proxy `ip:port:user:pass`.
5. Lưu cấu hình sau khi nhập URL và token backend.

## Cấu hình IP/port
- Dải IP quản lý trong `mod_dataz_proxy_ip_pool` theo CIDR với con trỏ `current_int`; backend sẽ tự tăng từng IP.
- Dải port quản lý trong `mod_dataz_proxy_port_pool` với `min_port`, `max_port`, `current_port`; backend sẽ tự cấp phát tuần tự.
- Có thể cập nhật dải mới bằng lệnh `INSERT` tương tự ví dụ trên hoặc import file seed riêng.

## Gỡ lỗi cơ bản
- **Backend trả 401**: Kiểm tra header `Authorization: Bearer` khớp `config.php`.
- **Hết IP/port**: Đảm bảo bảng pool có dải hợp lệ và `current_int/current_port` chưa vượt giới hạn.
- **Squid/Dante không áp dụng thay đổi**: Kiểm tra trạng thái service, log và quyền ghi `/etc/squid/conf.d/` cùng `/etc/systemd/system/`.
- **Virtualizor gán/gỡ IP thất bại**: Xác thực API URL/key/pass và VPS ID; đảm bảo VPS còn slot IP khả dụng.
- **Lỗi build/release**: Kiểm tra `zip`, `git` và (nếu dùng) `gh` có trong PATH.
