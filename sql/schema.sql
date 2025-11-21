CREATE TABLE IF NOT EXISTS `mod_dataz_proxy_services` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `service_id` int unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `proxy_ip` varchar(64) NOT NULL,
  `http_port` int unsigned NOT NULL,
  `socks5_port` int unsigned NOT NULL,
  `proxy_username` varchar(64) NOT NULL,
  `proxy_password` varchar(128) NOT NULL,
  `status` enum('creating','active','disabled','deleted') NOT NULL DEFAULT 'creating',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mod_dataz_proxy_ip_pool` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(64) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `attached_to_vps_id` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_unique` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mod_dataz_proxy_port_pool` (
  `id` int NOT NULL AUTO_INCREMENT,
  `port` int NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `port_unique` (`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mod_dataz_proxy_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `level` varchar(16) NOT NULL,
  `message` text NOT NULL,
  `context` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ví dụ seed ngắn (tùy chọn, chỉ minh họa; import dữ liệu thực tế từ file riêng nếu có)
-- INSERT INTO mod_dataz_proxy_ip_pool (ip_address, is_used) VALUES ('160.30.136.10', 0);
-- INSERT INTO mod_dataz_proxy_port_pool (port, is_used) VALUES (1080, 0);
