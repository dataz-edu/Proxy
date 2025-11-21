# Hướng dẫn Cài đặt

Tài liệu này hướng dẫn triển khai module tự động hóa proxy cho WHMCS, backend API và các dịch vụ đi kèm trên Ubuntu 22.04 với PHP 7.4 và MariaDB 10.6.x.

## Yêu cầu
- PHP 7.4 (CLI và web SAPI) kèm các extension: `curl`, `pdo_mysql`, `json`, `mbstring`
- MariaDB 10.6.x
- WHMCS có quyền tải lên module
- Máy chủ proxy Ubuntu 22.04 sử dụng systemd
- Squid (HTTP) và Dante (SOCKS5) đã cài trên máy chủ proxy
- Thông tin Virtualizor API URL, API key, API pass, VPS ID mục tiêu để gán/gỡ IP
- Git, zip và bash để chạy các script build/release

## Khởi tạo cơ sở dữ liệu
1. Tạo schema và bảng:
```bash
mysql -u root -p < sql/schema.sql
```
2. Khai báo pool IP và port theo dải. Backend sẽ tự động duyệt từng IP và port trong các dải này:
```sql
INSERT INTO mod_dataz_proxy_ip_pool (cidr, start_int, end_int, current_int) VALUES
  ('160.30.136.0/23', INET_ATON('160.30.136.0'), INET_ATON('160.30.137.255'), NULL),
  ('160.250.132.0/23', INET_ATON('160.250.132.0'), INET_ATON('160.250.133.255'), NULL),
  ('163.223.210.0/23', INET_ATON('163.223.210.0'), INET_ATON('163.223.211.255'), NULL);

INSERT INTO mod_dataz_proxy_port_pool (min_port, max_port, current_port) VALUES (1080, 65535, NULL);
```

## Triển khai Backend
1. Sao chép thư mục `backend/` lên máy chủ proxy (ví dụ: `/opt/dataz-proxy/backend`).
2. Cập nhật `backend/config.php` với thông tin kết nối database, API token, đường dẫn Squid/Dante và thông số Virtualizor.
3. Thiết lập phân quyền an toàn (ví dụ):
```bash
chown -R www-data:www-data /opt/dataz-proxy/backend
find /opt/dataz-proxy/backend -type f -exec chmod 640 {} \;
find /opt/dataz-proxy/backend -type d -exec chmod 750 {} \;
```
4. Triển khai backend qua web server (Apache/Nginx + PHP 7.4) hoặc kiểm thử nhanh:
```bash
php -S 0.0.0.0:8080 -t /opt/dataz-proxy/backend
```
5. Bảo vệ HTTPS và firewall, mọi request phải gửi header `Authorization: Bearer <API_TOKEN>` trùng với `config.php`.

## Cài đặt module WHMCS
1. Sao chép `modules/servers/dataz_proxy/` vào thư mục `modules/servers/` của WHMCS.
2. Trong WHMCS Admin, tạo/sửa sản phẩm và chọn module `DATAZ Proxy Provisioning`.
3. Cấu hình các trường module:
   - `API_ENDPOINT`: URL backend (ví dụ `https://proxy.example.com/backend`).
   - `API_TOKEN`: Trùng với `api_token` của backend.
   - `PROXY_TYPE`: `http`, `socks5` hoặc `both`.
   - `AUTO_ASSIGN_IP`: `yes`/`no` (tự gán IP qua Virtualizor hay không).
   - `VIRT_API_URL`, `VIRT_API_KEY`, `VIRT_API_PASS`, `VIRT_VPS_ID`: thông tin Virtualizor.
4. Thêm custom field sản phẩm tên `Proxy List` (textarea) để lưu danh sách proxy dạng `ip:port:user:pass`.
5. Lưu cấu hình module sau khi nhập URL và token backend.

## Ghi chú dịch vụ
- Squid sinh cấu hình tại `/etc/squid/conf.d/dataz_proxies.conf` và reload bằng `systemctl reload squid`.
- Dante sinh cấu hình `/etc/danted-<id>.conf` cùng unit `/etc/systemd/system/danted-<id>.service`.
- Đảm bảo `/usr/local/sbin/sockd` tồn tại; cần thiết thì chỉnh đường dẫn trong `backend/core/dante.php`.
- Kiểm tra Squid main config có `include /etc/squid/conf.d/*.conf` và firewall mở các port đã cấp phát.

## Chạy kiểm thử
Thực thi kiểm thử từ thư mục gốc dự án:
```bash
php tests/run_all_tests.php
```
Script sẽ kiểm tra sự tồn tại của các thư mục/file bắt buộc và trả về mã lỗi khác 0 nếu có lỗi.

## Đóng gói WHMCS module
Đóng gói module để phân phối:
```bash
./scripts/build_module.sh
```
Script đọc `VERSION` (tự tạo nếu thiếu) và xuất `dist/dataz-proxy-module-<VERSION>.zip` chứa duy nhất mã nguồn module WHMCS.

## Phát hành
Để tag, push và (nếu có) phát hành GitHub:
```bash
./scripts/release_module.sh            # dùng VERSION hiện tại
./scripts/release_module.sh 1.0.1      # đặt VERSION mới và phát hành
```
Script sẽ build, commit `dist/` và `VERSION`, tạo tag, push lên origin, và dùng `gh release create` nếu GitHub CLI có sẵn.

## Khắc phục sự cố
- **Backend trả 401**: Kiểm tra token `Authorization: Bearer` trùng với `config.php`.
- **Hết IP/port**: Đảm bảo các bảng pool có dải giá trị hợp lệ và `current_int/current_port` chưa vượt quá giới hạn.
- **Squid/Dante không áp dụng thay đổi**: Kiểm tra trạng thái service, log, và quyền ghi vào `/etc/squid/conf.d/` cùng `/etc/systemd/system/`.
- **Virtualizor gán/gỡ IP thất bại**: Xác thực API URL/key/pass và VPS ID, đảm bảo VPS còn slot IP khả dụng.
- **Lỗi build/release**: Kiểm tra `zip`, `git`, và (nếu dùng) `gh` có trong PATH.
