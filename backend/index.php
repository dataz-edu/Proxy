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
    return DB::fetchAll('SELECT * FROM mod_dataz_proxy_services WHERE status != "deleted"');
}

function allocate_ip()
{
    $ranges = DB::fetchAll('SELECT * FROM mod_dataz_proxy_ip_pool ORDER BY id ASC');
    foreach ($ranges as $range) {
        $start = (int)$range['start_int'];
        $end = (int)$range['end_int'];
        $current = $range['current_int'] !== null ? (int)$range['current_int'] : null;
        $next = $current === null ? $start : $current + 1;
        if ($next > $end) {
            continue;
        }
        DB::execute(
            'UPDATE mod_dataz_proxy_ip_pool SET current_int = :curr, updated_at = NOW() WHERE id = :id',
            [
                ':curr' => $next,
                ':id' => $range['id'],
            ]
        );
        return Utils::intToIp($next);
    }
    throw new RuntimeException('No free IP available');
}

function allocate_port()
{
    $range = DB::fetch('SELECT * FROM mod_dataz_proxy_port_pool ORDER BY id ASC LIMIT 1');
    if (!$range) {
        throw new RuntimeException('No port ranges configured');
    }
    $min = (int)$range['min_port'];
    $max = (int)$range['max_port'];
    $current = $range['current_port'] !== null ? (int)$range['current_port'] : null;
    $next = $current === null ? $min : $current + 1;
    if ($next > $max) {
        throw new RuntimeException('No free port available');
    }
    DB::execute(
        'UPDATE mod_dataz_proxy_port_pool SET current_port = :curr, updated_at = NOW() WHERE id = :id',
        [
            ':curr' => $next,
            ':id' => $range['id'],
        ]
    );
    return $next;
}

$router->add('POST', '/proxy/create', function () use ($config) {
    require_auth();
    $data = Utils::jsonInput();
    $quantity = isset($data['quantity']) ? (int)$data['quantity'] : 1;
    $quantity = $quantity > 0 ? $quantity : 1;
    $serviceId = (int)$data['service_id'];
    $userId = (int)$data['user_id'];
    $proxyType = $data['proxy_type'];
    $autoAssign = !empty($data['auto_assign_ip']);
    $virtUrl = $data['virt_api_url'] ?? $config['virtualizor']['api_url'];
    $virtKey = $data['virt_api_key'] ?? $config['virtualizor']['api_key'];
    $virtPass = $data['virt_api_pass'] ?? $config['virtualizor']['api_pass'];
    $virtVpsId = $data['virt_vps_id'] ?? null;

    $created = [];
    try {
        for ($i = 0; $i < $quantity; $i++) {
            $ipAddress = allocate_ip();
            $portValue = allocate_port();

            if ($autoAssign && $virtUrl && $virtKey && $virtPass && $virtVpsId) {
                Virtualizor::addIp($virtUrl, $virtKey, $virtPass, $virtVpsId, $ipAddress);
            }

            $username = Utils::randomString(10);
            $password = Utils::randomString(16);
            $status = 'active';

            $proxyId = DB::insert(
                'INSERT INTO mod_dataz_proxy_services (service_id, user_id, proxy_ip, proxy_port, proxy_username, proxy_password, proxy_type, status, created_at, updated_at)
                 VALUES (:sid, :uid, :ip, :port, :user, :pass, :type, :status, NOW(), NOW())',
                [
                    ':sid' => $serviceId,
                    ':uid' => $userId,
                    ':ip' => $ipAddress,
                    ':port' => $portValue,
                    ':user' => $username,
                    ':pass' => $password,
                    ':type' => $proxyType,
                    ':status' => $status,
                ]
            );

            $created[] = [
                'id' => $proxyId,
                'service_id' => $serviceId,
                'user_id' => $userId,
                'proxy_ip' => $ipAddress,
                'proxy_port' => $portValue,
                'proxy_username' => $username,
                'proxy_password' => $password,
                'proxy_type' => $proxyType,
                'status' => $status,
            ];
        }

        $proxies = get_active_proxies();
        SquidManager::regenerate($proxies, $config['squid_conf'], $config['squid_passwd']);
        DanteManager::regenerate($proxies, $config['dante_conf_dir'], $config['dante_systemd_dir']);

        Response::json(['message' => 'created', 'data' => ['proxies' => $created]]);
    } catch (Exception $e) {
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
        $newUser = Utils::randomString(10);
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
    foreach ($proxies as $proxy) {
        if (!empty($data['virt_api_url']) && !empty($data['virt_api_key']) && !empty($data['virt_api_pass']) && !empty($data['virt_vps_id'])) {
            try {
                Virtualizor::removeIp($data['virt_api_url'], $data['virt_api_key'], $data['virt_api_pass'], $data['virt_vps_id'], $proxy['proxy_ip']);
            } catch (Exception $e) {
                Utils::logMessage('warning', 'detach ip failed', ['error' => $e->getMessage(), 'ip' => $proxy['proxy_ip']]);
            }
        }
    }
    DB::execute('DELETE FROM mod_dataz_proxy_services WHERE service_id = :sid', [':sid' => $serviceId]);
    $proxies = get_active_proxies();
    SquidManager::regenerate($proxies, $config['squid_conf'], $config['squid_passwd']);
    DanteManager::regenerate($proxies, $config['dante_conf_dir'], $config['dante_systemd_dir']);
    Response::json(['message' => 'deleted']);
});

$router->add('GET', '/proxy/list', function () {
    require_auth();
    $list = DB::fetchAll('SELECT * FROM mod_dataz_proxy_services WHERE status != "deleted"');
    Response::json(['data' => $list]);
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
