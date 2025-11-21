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

function lock_free_ip($virtUrl, $virtKey, $virtPass, $virtVpsId)
{
    $pdo = DB::pdo();
    $attempts = 0;
    while (true) {
        $stmt = $pdo->prepare('SELECT * FROM mod_dataz_proxy_ip_pool WHERE is_used = 0 ORDER BY id ASC LIMIT 1 FOR UPDATE');
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new RuntimeException('No free IP available');
        }

        $attempts++;
        if ($attempts > 50) {
            throw new RuntimeException('No available IP passed validation');
        }

        if ($virtUrl && $virtKey && $virtPass && $virtVpsId) {
            if (!Virtualizor::isIpAvailable($virtUrl, $virtKey, $virtPass, $virtVpsId, $row['ip_address'])) {
                Utils::logMessage('warning', 'Virtualizor reports IP unavailable', ['ip' => $row['ip_address']]);
                DB::execute('UPDATE mod_dataz_proxy_ip_pool SET is_used = 1, updated_at = NOW() WHERE id = :id', [':id' => $row['id']]);
                continue;
            }
        }

        return $row;
    }
}

function lock_free_ports($count = 2)
{
    $pdo = DB::pdo();
    $stmt = $pdo->prepare('SELECT * FROM mod_dataz_proxy_port_pool WHERE is_used = 0 ORDER BY id ASC LIMIT ' . (int)$count . ' FOR UPDATE');
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($rows) < $count) {
        throw new RuntimeException('No free port available');
    }
    return $rows;
}

function mark_ip_used($ipId, $vpsId = null)
{
    DB::execute('UPDATE mod_dataz_proxy_ip_pool SET is_used = 1, attached_to_vps_id = :vps, updated_at = NOW() WHERE id = :id', [
        ':id' => $ipId,
        ':vps' => $vpsId,
    ]);
}

function mark_port_used(array $portRows)
{
    foreach ($portRows as $row) {
        DB::execute('UPDATE mod_dataz_proxy_port_pool SET is_used = 1, updated_at = NOW() WHERE id = :id', [':id' => $row['id']]);
    }
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
    $attachedIps = [];
    try {
        DB::beginTransaction();
        for ($i = 0; $i < $quantity; $i++) {
            $ipRow = lock_free_ip($virtUrl, $virtKey, $virtPass, $virtVpsId);
            $ports = lock_free_ports(2);

            if ($autoAssign && $virtUrl && $virtKey && $virtPass && $virtVpsId) {
                if (!Virtualizor::isIpAvailable($virtUrl, $virtKey, $virtPass, $virtVpsId, $ipRow['ip_address'])) {
                    Utils::logMessage('warning', 'IP unavailable during attach', ['ip' => $ipRow['ip_address']]);
                    DB::execute('UPDATE mod_dataz_proxy_ip_pool SET is_used = 1, updated_at = NOW() WHERE id = :id', [':id' => $ipRow['id']]);
                    continue;
                }
                Virtualizor::addIp($virtUrl, $virtKey, $virtPass, $virtVpsId, $ipRow['ip_address']);
                $attachedIps[] = $ipRow['ip_address'];
                mark_ip_used($ipRow['id'], $virtVpsId);
            } else {
                mark_ip_used($ipRow['id'], null);
            }

            mark_port_used($ports);

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
        if (count($created) < $quantity) {
            throw new RuntimeException('Unable to allocate requested number of proxies');
        }
        DB::commit();

        $proxies = get_active_proxies();
        SquidManager::regenerate($proxies, $config['squid_conf'], $config['squid_passwd']);
        DanteManager::regenerate($proxies, $config['dante_conf_dir'], $config['dante_systemd_dir']);

        Response::json(['message' => 'created', 'data' => ['proxies' => $created]]);
    } catch (Exception $e) {
        DB::rollBack();
        foreach ($attachedIps as $ip) {
            try {
                if ($virtUrl && $virtKey && $virtPass && $virtVpsId) {
                    Virtualizor::removeIp($virtUrl, $virtKey, $virtPass, $virtVpsId, $ip);
                }
            } catch (Exception $ex) {
                Utils::logMessage('warning', 'rollback detach failed', ['ip' => $ip, 'error' => $ex->getMessage()]);
            }
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

    try {
        DB::beginTransaction();
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
        DB::commit();
    } catch (Exception $e) {
        DB::rollBack();
        Response::json(['message' => $e->getMessage()], 400);
    }
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
