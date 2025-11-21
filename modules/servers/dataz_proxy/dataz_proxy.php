<?php
if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/api.php';
require_once __DIR__ . '/lib/helpers.php';
require_once __DIR__ . '/lib/database.php';

use WHMCS\Module\Server as ServerModule;
use WHMCS\Module\Server\Setting; // ensure autoload

function dataz_proxy_MetaData()
{
    return [
        'DisplayName' => 'DATAZ Proxy Provisioning',
        'APIVersion' => '1.1',
        'RequiresServer' => false,
    ];
}

function dataz_proxy_ConfigOptions()
{
    return [
        'API_ENDPOINT' => [
            'FriendlyName' => 'API Endpoint',
            'Type' => 'text',
            'Size' => '50',
            'Default' => 'https://proxy-backend.example.com',
        ],
        'API_TOKEN' => [
            'FriendlyName' => 'API Token',
            'Type' => 'password',
            'Size' => '50',
        ],
        'PROXY_TYPE' => [
            'FriendlyName' => 'Proxy Type',
            'Type' => 'dropdown',
            'Options' => 'http,socks5,both',
            'Default' => 'http',
        ],
        'AUTO_ASSIGN_IP' => [
            'FriendlyName' => 'Auto Assign IP',
            'Type' => 'yesno',
            'Description' => 'Automatically attach IPs in Virtualizor',
        ],
        'VIRT_API_URL' => [
            'FriendlyName' => 'Virtualizor API URL',
            'Type' => 'text',
            'Size' => '50',
        ],
        'VIRT_API_KEY' => [
            'FriendlyName' => 'Virtualizor API Key',
            'Type' => 'text',
            'Size' => '50',
        ],
        'VIRT_API_PASS' => [
            'FriendlyName' => 'Virtualizor API Password',
            'Type' => 'password',
            'Size' => '50',
        ],
        'VIRT_VPS_ID' => [
            'FriendlyName' => 'Virtualizor VPS ID',
            'Type' => 'text',
            'Size' => '20',
        ],
        'Quantity' => [
            'FriendlyName' => 'Default Proxy Quantity',
            'Type' => 'text',
            'Size' => '5',
            'Default' => '1',
        ],
    ];
}

function dataz_proxy_CreateAccount(array $params)
{
    try {
        $quantity = (int)($params['configoptions']['Quantity'] ?? $params['customfields']['Quantity'] ?? $params['customfields']['quantity'] ?? $params['customfields']['proxies'] ?? $params['configoptions']['proxies'] ?? $params['configoptions']['Proxies'] ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }

        $api = new DatazProxyApi($params['configoption1'], $params['configoption2']);
        $payload = [
            'service_id' => (int)$params['serviceid'],
            'user_id' => (int)$params['clientsdetails']['userid'],
            'quantity' => $quantity,
            'proxy_type' => $params['configoption3'],
            'auto_assign_ip' => (bool)$params['configoption4'],
            'virt_api_url' => $params['configoption5'],
            'virt_api_key' => $params['configoption6'],
            'virt_api_pass' => $params['configoption7'],
            'virt_vps_id' => $params['configoption8'],
        ];

        $response = $api->post('/proxy/create', $payload);
        if (!$response['success']) {
            return 'API error: ' . $response['message'];
        }

        $proxies = $response['data']['proxies'] ?? [];
        DatazProxyDatabase::storeProxies($proxies);
        $formatted = DatazProxyHelpers::formatProxyList($proxies);
        DatazProxyHelpers::updateCustomField($params['serviceid'], $params['pid'], 'Proxy List', $formatted);

        return 'success';
    } catch (Exception $e) {
        return 'Create failed: ' . $e->getMessage();
    }
}

function dataz_proxy_SuspendAccount(array $params)
{
    try {
        $api = new DatazProxyApi($params['configoption1'], $params['configoption2']);
        $payload = [
            'service_id' => (int)$params['serviceid'],
        ];
        $response = $api->post('/proxy/disable', $payload);
        if (!$response['success']) {
            return 'API error: ' . $response['message'];
        }
        DatazProxyDatabase::setStatusByService($params['serviceid'], 'disabled');
        return 'success';
    } catch (Exception $e) {
        return 'Suspend failed: ' . $e->getMessage();
    }
}

function dataz_proxy_UnsuspendAccount(array $params)
{
    try {
        $api = new DatazProxyApi($params['configoption1'], $params['configoption2']);
        $payload = [
            'service_id' => (int)$params['serviceid'],
        ];
        $response = $api->post('/proxy/enable', $payload);
        if (!$response['success']) {
            return 'API error: ' . $response['message'];
        }
        DatazProxyDatabase::setStatusByService($params['serviceid'], 'active');
        return 'success';
    } catch (Exception $e) {
        return 'Unsuspend failed: ' . $e->getMessage();
    }
}

function dataz_proxy_TerminateAccount(array $params)
{
    try {
        $api = new DatazProxyApi($params['configoption1'], $params['configoption2']);
        $payload = [
            'service_id' => (int)$params['serviceid'],
        ];
        $response = $api->delete('/proxy/delete', $payload);
        if (!$response['success']) {
            return 'API error: ' . $response['message'];
        }
        DatazProxyDatabase::deleteByService($params['serviceid']);
        DatazProxyHelpers::updateCustomField($params['serviceid'], $params['pid'], 'Proxy List', '');
        return 'success';
    } catch (Exception $e) {
        return 'Terminate failed: ' . $e->getMessage();
    }
}

function dataz_proxy_AdminServicesTabFields(array $params)
{
    $proxies = DatazProxyDatabase::getByService($params['serviceid']);
    $count = count($proxies);
    $status = $proxies ? $proxies[0]['status'] : 'none';
    $output = [
        'Proxy Count' => $count,
        'Status' => $status,
    ];
    return $output;
}

function dataz_proxy_AdminServicesTabFieldsSave(array $params)
{
    // No editable fields; handled automatically
}

function dataz_proxy_ClientArea(array $params)
{
    $api = new DatazProxyApi($params['configoption1'], $params['configoption2']);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modaction']) && $_POST['modaction'] === 'resetpass') {
        try {
            $resp = $api->post('/proxy/reset_pass', ['service_id' => (int)$params['serviceid']]);
            if ($resp['success'] && isset($resp['data']['proxies'])) {
                DatazProxyDatabase::deleteByService($params['serviceid']);
                DatazProxyDatabase::storeProxies($resp['data']['proxies']);
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    $proxies = DatazProxyDatabase::getByService($params['serviceid']);
    $status = $proxies ? $proxies[0]['status'] : 'creating';

    if ($params['templatefile']) {
        $templateFile = $params['templatefile'];
    } else {
        $templateFile = 'clientarea';
    }

    return [
        'templatefile' => $templateFile,
        'breadcrumb' => ['clientarea.php?action=productdetails' => 'Proxy Service'],
        'vars' => [
            'status' => $status,
            'proxies' => $proxies,
            'refresh_url' => $_SERVER['REQUEST_URI'],
            'serviceid' => $params['serviceid'],
            'api_endpoint' => $params['configoption1'],
            'api_token' => $params['configoption2'],
        ],
    ];
}
