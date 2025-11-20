<?php
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/core/database.php';
require_once __DIR__ . '/../backend/core/squid.php';
require_once __DIR__ . '/../backend/core/dante.php';

$config = require __DIR__ . '/../backend/config.php';
DB::init($config['db']);

$proxies = DB::fetchAll('SELECT * FROM mod_dataz_proxy_services WHERE status != "deleted"');
SquidManager::regenerate($proxies, $config['squid_conf'], $config['squid_passwd']);
DanteManager::regenerate($proxies, $config['dante_conf_dir'], $config['dante_systemd_dir']);
