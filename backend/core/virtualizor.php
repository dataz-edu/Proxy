<?php
class Virtualizor
{
    public static function request($baseUrl, $apiKey, $apiPass, $endpoint, array $params)
    {
        $url = rtrim($baseUrl, '/') . '/index.php?act=' . $endpoint . '&api=json';
        $payload = array_merge($params, [
            'apikey' => $apiKey,
            'apipass' => $apiPass,
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Virtualizor error: ' . $error);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($result, true);
        if ($httpCode >= 400) {
            throw new RuntimeException('Virtualizor HTTP error ' . $httpCode . ' response: ' . $result);
        }
        if (!is_array($decoded)) {
            throw new RuntimeException('Invalid Virtualizor response: ' . $result);
        }
        return $decoded;
    }

    public static function addIp($baseUrl, $apiKey, $apiPass, $vpsId, $ip)
    {
        return self::request($baseUrl, $apiKey, $apiPass, 'addips', [
            'vpsid' => $vpsId,
            'ips' => [$ip],
        ]);
    }

    public static function removeIp($baseUrl, $apiKey, $apiPass, $vpsId, $ip)
    {
        return self::request($baseUrl, $apiKey, $apiPass, 'removeips', [
            'vpsid' => $vpsId,
            'ips' => [$ip],
        ]);
    }
}
