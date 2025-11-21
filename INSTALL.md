# Hướng dẫn Cài đặt & Vận hành (Tiếng Việt)

## 1. Tổng quan kiến trúc
Hệ thống quản lý proxy gồm 3 thành phần:
- **WHMCS module** (modules/servers/dataz_proxy): nhận thao tác tạo/tạm dừng/hủy dịch vụ từ WHMCS và gọi Backend.
- **Backend PHP 7.4** (backend/): xử lý API `/proxy/*`, chọn IP + 2 cổng (HTTP/SOCKS5), gọi Virtualizor để gán/gỡ IP, sinh cấu hình Squid & Dante rồi reload service.
- **Hạ tầng proxy** trên VPS Ubuntu 22.04: Squid cho HTTP và Dante cho SOCKS5, mỗi proxy là 1 IP + 1 cổng HTTP + 1 cổng SOCKS5 + user/pass.

Luồng chung: WHMCS → Backend → (Virtualizor gán IP) → Sinh config Squid/Dante → Hoàn tất trả về WHMCS.

## 2. Yêu cầu hệ thống
- VPS proxy: Ubuntu 22.04, quyền root, đã cài **PHP 7.4**, **MariaDB 10.6.x**, **Squid**, **Dante**, **systemd**.
- Máy chủ WHMCS riêng (server billing) có quyền cài module server.
- Thông tin Virtualizor: API URL, API key, API pass, VPS ID (nếu cần auto gán/gỡ IP).
- Công cụ: `git`, `zip`, `bash` để build/release; webserver (Nginx/Apache + PHP 7.4) hoặc `php -S` cho thử nghiệm.

## 3. Khởi tạo database backend
1. Tạo schema và bảng:
```bash
mysql -u root -p < sql/schema.sql
```
2. Seed ngắn minh họa (không phải danh sách sản xuất):
```sql
INSERT INTO mod_dataz_proxy_ip_pool (ip_address, is_used) VALUES
  ('160.30.136.10', 0),
  ('160.250.132.20', 0);

INSERT INTO mod_dataz_proxy_port_pool (port, is_used) VALUES
  (1080, 0),
  (1081, 0),
  (1082, 0);
```
> Khuyến nghị: admin import đầy đủ IP/port thực tế từ file riêng (ví dụ `sql/ip_port_seed.sql`) thay vì ghi trực tiếp vào tài liệu.

### Giải thích bảng chính
- `mod_dataz_proxy_ip_pool`: mỗi dòng 1 IP dành cho proxy, cờ `is_used` cho biết đã cấp phát, `attached_to_vps_id` lưu VPS Virtualizor (nếu gán).
- `mod_dataz_proxy_port_pool`: mỗi dòng 1 cổng, `is_used` đánh dấu đã cấp phát.
- `mod_dataz_proxy_services`: mỗi dòng 1 proxy cặp (IP + HTTP port + SOCKS5 port + user/pass + trạng thái).
- `mod_dataz_proxy_logs`: log backend.

## 4. Triển khai backend trên VPS proxy
1. Sao chép thư mục `backend/` lên VPS (ví dụ `/opt/dataz-proxy/backend`).
2. Chỉnh `backend/config.php` với thông tin DB, `api_token`, đường dẫn Squid/Dante, Virtualizor.
3. Phân quyền an toàn:
```bash
chown -R www-data:www-data /opt/dataz-proxy/backend
find /opt/dataz-proxy/backend -type f -exec chmod 640 {} \;
find /opt/dataz-proxy/backend -type d -exec chmod 750 {} \;
```
4. Triển khai web (Nginx/Apache + PHP 7.4) hoặc thử nhanh:
```bash
php -S 0.0.0.0:8080 -t /opt/dataz-proxy/backend
```
5. Bảo vệ HTTPS/firewall; mọi request phải có header `Authorization: Bearer <API_TOKEN>` khớp `config.php`.

## 5. Cài module WHMCS
1. Sao chép `modules/servers/dataz_proxy/` vào `modules/servers/` của WHMCS.
2. Trong WHMCS Admin, tạo sản phẩm và chọn module **DATAZ Proxy Provisioning**.
3. Cấu hình:
   - `API_ENDPOINT`: URL backend (ví dụ `https://proxy.example.com/backend`).
   - `API_TOKEN`: trùng `api_token` backend.
   - `AUTO_ASSIGN_IP`: yes/no (tự gán IP qua Virtualizor).
   - `VIRT_API_URL`, `VIRT_API_KEY`, `VIRT_API_PASS`, `VIRT_VPS_ID`: thông tin Virtualizor (nếu dùng).
   - `Quantity`: số proxy mặc định.
4. (Tùy chọn) Custom field textarea để lưu danh sách proxy/ghi chú.

## 6. Cấu hình IP/Port và luồng hoạt động
- **Quản lý pool**: thêm IP/port bằng `INSERT` giống ví dụ hoặc import file seed. Chỉ nạp IP dành riêng cho proxy để tránh gán nhầm.
- **Luồng tạo proxy**:
  1) Backend chọn 1 IP is_used=0 + 2 port is_used=0 (trong transaction, khóa hàng).
  2) Kiểm tra Virtualizor xem IP còn khả dụng; nếu đạt, gán IP cho VPS (nếu bật auto_assign_ip).
  3) Đánh dấu is_used=1 cho IP/port, tạo bản ghi `mod_dataz_proxy_services` với user/pass.
  4) Sinh cấu hình Squid/Dante và reload/restart dịch vụ.
- **Luồng hủy proxy**:
  1) Gỡ cấu hình Squid/Dante tương ứng.
  2) Tháo IP khỏi VPS qua Virtualizor (nếu đã gán).
  3) Cập nhật `mod_dataz_proxy_services.status='deleted'`, đặt IP/port về `is_used=0`.

## 7. Gỡ lỗi & vận hành
- Kiểm tra header Bearer khi gặp 401 từ backend.
- Nếu hết IP/port: bổ sung dữ liệu pool và đảm bảo `is_used=0` cho dòng chưa dùng.
- Nếu Squid/Dante không áp dụng: kiểm tra `systemctl status`, log, quyền ghi `/etc/squid/conf.d/` và `/etc/systemd/system/`.
- Virtualizor lỗi gán/gỡ: kiểm tra URL/key/pass và VPS ID, cùng giới hạn IP của VPS.
- Khi cần reset trạng thái IP/port: đặt `is_used=0` trong pool và đảm bảo cấu hình backend được tái sinh.
