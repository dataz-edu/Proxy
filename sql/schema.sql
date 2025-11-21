CREATE TABLE IF NOT EXISTS `mod_dataz_proxy_services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `user_id` int NOT NULL,
  `proxy_ip` varchar(64) NOT NULL,
  `proxy_port` int NOT NULL,
  `proxy_username` varchar(64) NOT NULL,
  `proxy_password` varchar(128) NOT NULL,
  `proxy_type` enum('http','socks5','both') NOT NULL DEFAULT 'http',
  `status` enum('creating','active','disabled','deleted') NOT NULL DEFAULT 'creating',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `service_id` (`service_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mod_dataz_proxy_ip_pool` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cidr` varchar(64) NOT NULL,
  `start_int` bigint NOT NULL,
  `end_int` bigint NOT NULL,
  `current_int` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cidr_unique` (`cidr`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mod_dataz_proxy_port_pool` (
  `id` int NOT NULL AUTO_INCREMENT,
  `min_port` int NOT NULL,
  `max_port` int NOT NULL,
  `current_port` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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
