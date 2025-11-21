<?php
class SquidManager
{
    public static function regenerate(array $proxies, $configPath, $passwdPath)
    {
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

        $global = [
            'auth_param basic program /usr/lib/squid/basic_ncsa_auth /etc/squid/passwd',
            'auth_param basic realm DATAZ Proxy Service',
            'auth_param basic credentialsttl 2 hours',
            'auth_param basic children 10',
            'acl authenticated proxy_auth REQUIRED',
            'acl SSL_ports port 443',
            'acl Safe_ports port 80',
            'acl Safe_ports port 443',
            'acl Safe_ports port 1025-65535',
            'acl CONNECT method CONNECT',
            'http_access deny !Safe_ports',
            'http_access deny CONNECT !SSL_ports',
            'http_access allow authenticated',
            'http_access deny all',
            'cache deny all',
            'dns_v4_first on',
            'via off',
            'forwarded_for off',
            'visible_hostname dataz-squid',
        ];

        $content = implode("\n", $global) . "\n" . implode("\n", $lines) . "\n";
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
