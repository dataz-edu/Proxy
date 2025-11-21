<?php
class DatazProxyApi
{
    private $endpoint;
    private $token;

    public function __construct($endpoint, $token)
    {
        $this->endpoint = rtrim($endpoint, '/');
        $this->token = $token;
    }

    private function request($method, $path, array $data = [])
    {
        $url = $this->endpoint . $path;
        if ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
        }

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->token,
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            curl_close($ch);
            return ['success' => false, 'message' => 'Curl error: ' . $error];
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($result, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'message' => 'Invalid response from API: ' . $result];
        }
        if ($httpCode >= 200 && $httpCode < 300) {
            return $decoded + ['success' => true];
        }
        return ['success' => false, 'message' => $decoded['message'] ?? 'HTTP error ' . $httpCode];
    }

    public function post($path, array $data = [])
    {
        return $this->request('POST', $path, $data);
    }

    public function delete($path, array $data = [])
    {
        return $this->request('DELETE', $path, $data);
    }

    public function get($path, array $data = [])
    {
        return $this->request('GET', $path, $data);
    }
}
