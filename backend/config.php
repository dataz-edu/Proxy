<?php
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'whmcs',
        'user' => 'whmcs',
        'pass' => 'changeme',
        'charset' => 'utf8mb4',
    ],
    'api_token' => 'changeme_token',
    'log_file' => __DIR__ . '/logs/backend.log',
    'squid_conf' => '/etc/squid/conf.d/dataz_proxies.conf',
    'squid_passwd' => '/etc/squid/passwd',
    'dante_conf_dir' => '/etc',
    'dante_systemd_dir' => '/etc/systemd/system',
    'virtualizor' => [
        'api_url' => '',
        'api_key' => '',
        'api_pass' => '',
    ],
];
