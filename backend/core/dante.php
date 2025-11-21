<?php
class DanteManager
{
    public static function regenerate(array $proxies, $confDir, $systemdDir)
    {
        $socksProxies = array_filter($proxies, function ($proxy) {
            return isset($proxy['status']) ? $proxy['status'] === 'active' : true;
        });

        foreach ($socksProxies as $proxy) {
            self::writeConfig($proxy, $confDir);
            self::writeService($proxy, $systemdDir);
        }

        self::cleanupRemoved($socksProxies, $confDir, $systemdDir);
        self::reload();
    }

    private static function writeConfig(array $proxy, $confDir)
    {
        $id = $proxy['id'];
        if (!is_dir($confDir)) {
            mkdir($confDir, 0755, true);
        }
        $config = "logoutput: /var/log/danted/danted-{$id}.log\n" .
            "internal: {$proxy['proxy_ip']} port = {$proxy['socks5_port']}\n" .
            "external: {$proxy['proxy_ip']}\n\n" .
            "method: username\n" .
            "user.privileged: root\n" .
            "user.notprivileged: nobody\n\n" .
            "client pass {\n    from: 0.0.0.0/0 to: 0.0.0.0/0\n    log: connect disconnect error\n}\n\n" .
            "socks pass {\n    from: 0.0.0.0/0 to: 0.0.0.0/0\n    command: connect\n    log: connect disconnect error\n}\n";

        $path = rtrim($confDir, '/') . "/danted-{$id}.conf";
        file_put_contents($path, $config);
    }

    private static function writeService(array $proxy, $systemdDir)
    {
        $id = $proxy['id'];
        if (!is_dir($systemdDir)) {
            mkdir($systemdDir, 0755, true);
        }
        $service = "[Unit]\nDescription=Danted SOCKS5 Proxy #{$id} ({$proxy['proxy_ip']}:{$proxy['socks5_port']})\nAfter=network.target\n\n" .
            "[Service]\nExecStart=/usr/local/sbin/sockd -f /etc/danted-{$id}.conf\nRestart=always\nLimitNOFILE=65535\n\n" .
            "[Install]\nWantedBy=multi-user.target\n";

        $path = rtrim($systemdDir, '/') . "/danted-{$id}.service";
        file_put_contents($path, $service);
    }

    private static function cleanupRemoved(array $currentProxies, $confDir, $systemdDir)
    {
        $existingConfigs = glob(rtrim($confDir, '/') . '/danted-*.conf');
        $ids = array_map(function ($p) {
            return (int)preg_replace('/[^0-9]/', '', basename($p));
        }, $existingConfigs ?: []);

        $activeIds = array_map(function ($p) {
            return (int)$p['id'];
        }, $currentProxies);

        foreach ($ids as $id) {
            if (!in_array($id, $activeIds)) {
                @unlink(rtrim($confDir, '/') . "/danted-{$id}.conf");
                @unlink(rtrim($systemdDir, '/') . "/danted-{$id}.service");
                @exec('systemctl disable danted-' . $id . ' --now');
            }
        }
    }

    private static function reload()
    {
        @exec('systemctl daemon-reload');
        $services = glob('/etc/systemd/system/danted-*.service') ?: [];
        foreach ($services as $service) {
            $name = basename($service, '.service');
            @exec('systemctl enable ' . $name . '');
            @exec('systemctl restart ' . $name);
        }
    }
}
