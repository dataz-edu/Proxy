<?php
class Utils
{
    public static function authenticate($config)
    {
        $headers = getallheaders();
        $authHeader = isset($headers['Authorization']) ? $headers['Authorization'] : (isset($headers['authorization']) ? $headers['authorization'] : '');
        if (strpos($authHeader, 'Bearer ') !== 0) {
            return false;
        }
        $token = substr($authHeader, 7);
        return $token === $config['api_token'];
    }

    public static function randomString($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $str;
    }

    public static function intToIp($int)
    {
        $value = (int) $int;
        if ($value < 0) {
            $value = $value + 4294967296;
        }
        return long2ip($value);
    }

    public static function logMessage($level, $message, array $context = [])
    {
        $config = $GLOBALS['config'];
        $line = sprintf('[%s] %s: %s %s', date('Y-m-d H:i:s'), strtoupper($level), $message, json_encode($context));
        if (!empty($config['log_file'])) {
            $dir = dirname($config['log_file']);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($config['log_file'], $line . "\n", FILE_APPEND);
        }
        DB::insert(
            'INSERT INTO mod_dataz_proxy_logs (level, message, context, created_at) VALUES (:level, :message, :context, NOW())',
            [
                ':level' => $level,
                ':message' => $message,
                ':context' => json_encode($context),
            ]
        );
    }

    public static function jsonInput()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        return is_array($data) ? $data : [];
    }
}
