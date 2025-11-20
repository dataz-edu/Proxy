# Installation Guide

This guide covers deploying the WHMCS proxy automation module, backend API, and supporting services on Ubuntu 22.04 with PHP 7.4 and MariaDB 10.6.x.

## Requirements
- PHP 7.4 (CLI and web SAPI) with extensions: `curl`, `pdo_mysql`, `json`, `mbstring`
- MariaDB 10.6.x
- WHMCS with module upload access
- Ubuntu 22.04 proxy server with systemd
- Squid (HTTP) and Dante (SOCKS5) installed on the proxy server
- Virtualizor API URL, API key, API pass, and target VPS ID for attaching/detaching IPs
- Git, zip, and bash for build/release scripts

## Database Initialization
1. Create the schema and tables:
```bash
mysql -u root -p < sql/schema.sql
```
2. Populate IP and port pools with available values for your proxy server:
```sql
INSERT INTO mod_dataz_proxy_ip_pool (ip_address) VALUES ('203.0.113.10'),('203.0.113.11');
INSERT INTO mod_dataz_proxy_port_pool (port) VALUES (30000),(30001),(30002);
```

## Backend Deployment
1. Copy the `backend/` directory to the proxy server (e.g., `/opt/dataz-proxy/backend`).
2. Update `backend/config.php` with database credentials, API token, Squid/Dante paths, and Virtualizor settings.
3. Set secure permissions (example):
```bash
chown -R www-data:www-data /opt/dataz-proxy/backend
find /opt/dataz-proxy/backend -type f -exec chmod 640 {} \;
find /opt/dataz-proxy/backend -type d -exec chmod 750 {} \;
```
4. Serve the backend via your web server (Apache/Nginx with PHP 7.4) or for testing:
```bash
php -S 0.0.0.0:8080 -t /opt/dataz-proxy/backend
```
5. Ensure HTTPS and firewall rules restrict access. All API requests must include `Authorization: Bearer <API_TOKEN>` matching `config.php`.

## WHMCS Module Installation
1. Copy `modules/servers/dataz_proxy/` into your WHMCS installation’s `modules/servers/` directory.
2. In WHMCS admin, create or edit a product to use the `DATAZ Proxy Provisioning` module.
3. Configure module settings:
   - `API_ENDPOINT`: Backend base URL (e.g., `https://proxy.example.com/backend`).
   - `API_TOKEN`: Must match backend `api_token`.
   - `PROXY_TYPE`: `http`, `socks5`, or `both`.
   - `AUTO_ASSIGN_IP`: `yes`/`no` (whether to attach IPs via Virtualizor).
   - `VIRT_API_URL`, `VIRT_API_KEY`, `VIRT_API_PASS`, `VIRT_VPS_ID`: Virtualizor connection details.
4. Add a custom product field named `Proxy List` (textarea) to store generated proxy credentials in `ip:port:user:pass` format.
5. Place the backend URL and token in the product’s module settings and save.

## Service Notes
- Squid configuration is generated at `/etc/squid/conf.d/dataz_proxies.conf` and reloads via `systemctl reload squid`.
- Dante per-proxy configs are generated as `/etc/danted-<id>.conf` with systemd units `/etc/systemd/system/danted-<id>.service`.
- Ensure `/usr/local/sbin/sockd` exists; adjust paths in `backend/core/dante.php` if needed.
- Confirm Squid main config includes `include /etc/squid/conf.d/*.conf` and that firewall rules allow allocated ports.

## Running Tests
Execute repository checks from the project root:
```bash
php tests/run_all_tests.php
```
The script validates required folders and files exist. It exits non-zero if any check fails.

## Building the WHMCS Module ZIP
Package the module for distribution:
```bash
./scripts/build_module.sh
```
The script reads `VERSION` (creating one if missing) and outputs `dist/dataz-proxy-module-<VERSION>.zip` containing only the WHMCS module.

## Releasing
To tag, push, and optionally publish a GitHub release:
```bash
./scripts/release_module.sh            # use current VERSION
./scripts/release_module.sh 1.0.1      # set and release a new version
```
The release script runs the build, commits `dist/` and `VERSION`, creates a tag, pushes to origin, and uses `gh release create` when GitHub CLI is available.

## Troubleshooting
- **Backend returns 401**: Verify the `Authorization: Bearer` token matches `config.php`.
- **No free IP/port**: Ensure `mod_dataz_proxy_ip_pool` and `mod_dataz_proxy_port_pool` contain unused entries and `is_used` is `0`.
- **Squid/Dante not applying changes**: Check systemctl status and logs, and confirm the backend PHP process has permission to write `/etc/squid/conf.d/` and `/etc/systemd/system/`.
- **Virtualizor attach/detach fails**: Validate API URL/key/pass and that the target VPS ID is correct and has available IP slots.
- **Build/release script errors**: Confirm `zip`, `git`, and optionally `gh` are installed and accessible in PATH.
