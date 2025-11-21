<?php
class SquidManager
{
    public static function regenerate(array $proxies, $configPath, $passwdPath)
    {
        // Only active proxies contribute to the include file. Main squid.conf must include this path.
        $httpProxies = array_filter($proxies, function ($proxy) {
            return isset($proxy['status']) ? $proxy['status'] === 'active' : true;
        });

        $lines = [];
        $authUsers = [];
        foreach ($httpProxies as $proxy) {
            $portName = 'p_' . $proxy['http_port'];
            $lines[] = sprintf('http_port %s:%d name=%s', $proxy['proxy_ip'], $proxy['http_port'], $portName);
            $lines[] = sprintf('acl %s myportname %s', $portName, $portName);
            $lines[] = sprintf('tcp_outgoing_address %s %s', $proxy['proxy_ip'], $portName);
            $authUsers[$proxy['proxy_username']] = $proxy['proxy_password'];
        }

        $content = implode("\n", $lines) . "\n";
        if (!is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }
        file_put_contents($configPath, $content);

        self::generatePasswd($authUsers, $passwdPath);
        self::reload();
    }

    private static function generatePasswd(array $users, $passwdPath)
    {
        $passwdLines = [];
        foreach ($users as $user => $pass) {
            $hash = crypt($pass, '$6$' . substr(hash('sha256', uniqid('', true)), 0, 8));
            $passwdLines[] = $user . ':' . $hash;
        }
        if (!is_dir(dirname($passwdPath))) {
            mkdir(dirname($passwdPath), 0755, true);
        }
        file_put_contents($passwdPath, implode("\n", $passwdLines) . "\n");
    }

    private static function reload()
    {
        @exec('systemctl reload squid');
    }
}
