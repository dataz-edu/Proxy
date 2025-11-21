<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/core/database.php';
require_once __DIR__ . '/core/response.php';
require_once __DIR__ . '/core/router.php';
require_once __DIR__ . '/core/utils.php';
require_once __DIR__ . '/core/squid.php';
require_once __DIR__ . '/core/dante.php';
require_once __DIR__ . '/core/virtualizor.php';

$config = require __DIR__ . '/config.php';
$GLOBALS['config'] = $config;
DB::init($config['db']);

$router = new Router();

function require_auth()
{
    global $config;
    if (!Utils::authenticate($config)) {
        Response::json(['message' => 'Unauthorized'], 401);
    }
}

function get_active_proxies()
{
    return DB::fetchAll('SELECT * FROM mod_dataz_proxy_services WHERE status = "active"');
}

function allocate_ip()
{
    $row = DB::fetch('SELECT * FROM mod_dataz_proxy_ip_pool WHERE is_used = 0 ORDER BY id ASC LIMIT 1');
    if (!$row) {
        throw new RuntimeException('No free IP available');
    }
    DB::execute(
        'UPDATE mod_dataz_proxy_ip_pool SET is_used = 1, updated_at = NOW() WHERE id = :id',
        [':id' => $row['id']]
    );
    return $row;
}

function allocate_ports($count = 2)
{
    $rows = DB::fetchAll('SELECT * FROM mod_dataz_proxy_port_pool WHERE is_used = 0 ORDER BY id ASC LIMIT ' . (int)$count);
    if (count($rows) < $count) {
        throw new RuntimeException('No free port available');
    }
    $ports = [];
    foreach ($rows as $row) {
        DB::execute(
            'UPDATE mod_dataz_proxy_port_pool SET is_used = 1, updated_at = NOW() WHERE id = :id',
            [':id' => $row['id']]
        );
        $ports[] = $row;
    }
    return $ports;
}

function free_ip($ipAddress)
{
    DB::execute('UPDATE mod_dataz_proxy_ip_pool SET is_used = 0, attached_to_vps_id = NULL, updated_at = NOW() WHERE ip_address = :ip', [':ip' => $ipAddress]);
}

function free_port($port)
{
    DB::execute('UPDATE mod_dataz_proxy_port_pool SET is_used = 0, updated_at = NOW() WHERE port = :p', [':p' => $port]);
}

$router->add('POST', '/proxy/create', function () use ($config) {
    require_auth();
    $data = Utils::jsonInput();
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
    $quantity = $quantity > 0 ? $quantity : 1;
    $serviceId = (int)$data['service_id'];
    $userId = (int)$data['user_id'];
    $autoAssign = !empty($data['auto_assign_ip']);
    $virtUrl = $data['virt_api_url'] ?? $config['virtualizor']['api_url'];
    $virtKey = $data['virt_api_key'] ?? $config['virtualizor']['api_key'];
    $virtPass = $data['virt_api_pass'] ?? $config['virtualizor']['api_pass'];
    $virtVpsId = $data['virt_vps_id'] ?? null;

    $created = [];
    $allocated = ['ips' => [], 'ports' => []];
    try {
        for ($i = 0; $i < $quantity; $i++) {
            $ipRow = allocate_ip();
            $ports = allocate_ports(2);
            $allocated['ips'][] = $ipRow['ip_address'];
            $allocated['ports'][] = $ports[0]['port'];
            $allocated['ports'][] = $ports[1]['port'];

            if ($autoAssign && $virtUrl && $virtKey && $virtPass && $virtVpsId) {
                Virtualizor::addIp($virtUrl, $virtKey, $virtPass, $virtVpsId, $ipRow['ip_address']);
                DB::execute('UPDATE mod_dataz_proxy_ip_pool SET attached_to_vps_id = :vps, updated_at = NOW() WHERE id = :id', [
                    ':vps' => $virtVpsId,
                    ':id' => $ipRow['id'],
                ]);
            }

            $username = 'proxy_' . Utils::randomString(8);
            $password = Utils::randomString(16);
            $status = 'active';

            $proxyId = DB::insert(
                'INSERT INTO mod_dataz_proxy_services (service_id, user_id, proxy_ip, http_port, socks5_port, proxy_username, proxy_password, status, created_at, updated_at)
                 VALUES (:sid, :uid, :ip, :http_port, :socks_port, :user, :pass, :status, NOW(), NOW())',
                [
                    ':sid' => $serviceId,
                    ':uid' => $userId,
                    ':ip' => $ipRow['ip_address'],
                    ':http_port' => $ports[0]['port'],
                    ':socks_port' => $ports[1]['port'],
                    ':user' => $username,
                    ':pass' => $password,
                    ':status' => $status,
                ]
            );

            $created[] = [
                'id' => $proxyId,
                'service_id' => $serviceId,
                'user_id' => $userId,
                'ip' => $ipRow['ip_address'],
                'proxy_ip' => $ipRow['ip_address'],
                'http_port' => (int)$ports[0]['port'],
                'socks5_port' => (int)$ports[1]['port'],
                'username' => $username,
                'password' => $password,
                'proxy_username' => $username,
                'proxy_password' => $password,
                'status' => $status,
            ];
        }

        $proxies = get_active_proxies();
        SquidManager::regenerate($proxies, $config['squid_conf'], $config['squid_passwd']);
        DanteManager::regenerate($proxies, $config['dante_conf_dir'], $config['dante_systemd_dir']);

        Response::json(['message' => 'created', 'data' => ['proxies' => $created]]);
    } catch (Exception $e) {
        foreach ($allocated['ports'] as $p) {
            free_port($p);
        }
        foreach ($allocated['ips'] as $ip) {
            free_ip($ip);
        }
        Utils::logMessage('error', 'create failed', ['error' => $e->getMessage()]);
        Response::json(['message' => $e->getMessage()], 400);
    }
});

$router->add('POST', '/proxy/reset_pass', function () use ($config) {
    require_auth();
    $data = Utils::jsonInput();
    $serviceId = (int)$data['service_id'];
    $proxies = DB::fetchAll('SELECT * FROM mod_dataz_proxy_services WHERE service_id = :sid AND status != "deleted"', [':sid' => $serviceId]);
    if (!$proxies) {
        Response::json(['message' => 'Service not found'], 404);
    }
    $updated = [];
    foreach ($proxies as $proxy) {
        $newUser = 'proxy_' . Utils::randomString(8);
        $newPass = Utils::randomString(16);
        DB::execute('UPDATE mod_dataz_proxy_services SET proxy_username = :user, proxy_password = :pass, updated_at = NOW() WHERE id = :id', [
            ':user' => $newUser,
            ':pass' => $newPass,
            ':id' => $proxy['id'],
        ]);
        $proxy['proxy_username'] = $newUser;
        $proxy['proxy_password'] = $newPass;
        $updated[] = $proxy;
    }
    $proxiesAll = get_active_proxies();
    SquidManager::regenerate($proxiesAll, $config['squid_conf'], $config['squid_passwd']);
    DanteManager::regenerate($proxiesAll, $config['dante_conf_dir'], $config['dante_systemd_dir']);

    Response::json(['message' => 'reset', 'data' => ['proxies' => $updated]]);
});

$router->add('POST', '/proxy/enable', function () use ($config) {
    require_auth();
    $data = Utils::jsonInput();
    $serviceId = (int)$data['service_id'];
    DB::execute('UPDATE mod_dataz_proxy_services SET status = "active", updated_at = NOW() WHERE service_id = :sid', [':sid' => $serviceId]);
    $proxies = get_active_proxies();
    SquidManager::regenerate($proxies, $config['squid_conf'], $config['squid_passwd']);
    DanteManager::regenerate($proxies, $config['dante_conf_dir'], $config['dante_systemd_dir']);
    Response::json(['message' => 'enabled']);
});

$router->add('POST', '/proxy/disable', function () use ($config) {
    require_auth();
    $data = Utils::jsonInput();
    $serviceId = (int)$data['service_id'];
    DB::execute('UPDATE mod_dataz_proxy_services SET status = "disabled", updated_at = NOW() WHERE service_id = :sid', [':sid' => $serviceId]);
    $proxies = get_active_proxies();
    SquidManager::regenerate($proxies, $config['squid_conf'], $config['squid_passwd']);
    DanteManager::regenerate($proxies, $config['dante_conf_dir'], $config['dante_systemd_dir']);
    Response::json(['message' => 'disabled']);
});

$router->add('DELETE', '/proxy/delete', function () use ($config) {
    require_auth();
    $data = Utils::jsonInput();
    $serviceId = (int)$data['service_id'];
    $proxies = DB::fetchAll('SELECT * FROM mod_dataz_proxy_services WHERE service_id = :sid', [':sid' => $serviceId]);
    $virtUrl = $data['virt_api_url'] ?? $config['virtualizor']['api_url'];
    $virtKey = $data['virt_api_key'] ?? $config['virtualizor']['api_key'];
    $virtPass = $data['virt_api_pass'] ?? $config['virtualizor']['api_pass'];
    $virtVpsId = $data['virt_vps_id'] ?? null;

    foreach ($proxies as $proxy) {
        if ($virtUrl && $virtKey && $virtPass && $virtVpsId) {
            try {
                Virtualizor::removeIp($virtUrl, $virtKey, $virtPass, $virtVpsId, $proxy['proxy_ip']);
            } catch (Exception $e) {
                Utils::logMessage('warning', 'detach ip failed', ['error' => $e->getMessage(), 'ip' => $proxy['proxy_ip']]);
            }
        }
        free_ip($proxy['proxy_ip']);
        free_port($proxy['http_port']);
        free_port($proxy['socks5_port']);
    }
    DB::execute('UPDATE mod_dataz_proxy_services SET status = "deleted", updated_at = NOW() WHERE service_id = :sid', [':sid' => $serviceId]);
    $proxies = get_active_proxies();
    SquidManager::regenerate($proxies, $config['squid_conf'], $config['squid_passwd']);
    DanteManager::regenerate($proxies, $config['dante_conf_dir'], $config['dante_systemd_dir']);
    Response::json(['message' => 'deleted']);
});

$router->add('GET', '/proxy/list', function () {
    require_auth();
    $serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : null;
    $params = [];
    $sql = 'SELECT * FROM mod_dataz_proxy_services WHERE status != "deleted"';
    if ($serviceId) {
        $sql .= ' AND service_id = :sid';
        $params[':sid'] = $serviceId;
    }
    $sql .= ' ORDER BY id ASC';
    $list = DB::fetchAll($sql, $params);
    Response::json(['data' => $list]);
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
