<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class DatazProxyDatabase
{
    public static function storeProxies(array $proxies)
    {
        foreach ($proxies as $proxy) {
            Capsule::table('mod_dataz_proxy_services')->insert([
                'service_id' => (int)$proxy['service_id'],
                'user_id' => (int)$proxy['user_id'],
                'proxy_ip' => $proxy['proxy_ip'],
                'proxy_port' => (int)$proxy['proxy_port'],
                'proxy_username' => $proxy['proxy_username'],
                'proxy_password' => $proxy['proxy_password'],
                'proxy_type' => $proxy['proxy_type'],
                'status' => $proxy['status'] ?? 'creating',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public static function getByService($serviceId)
    {
        return Capsule::table('mod_dataz_proxy_services')
            ->where('service_id', (int)$serviceId)
            ->whereIn('status', ['creating', 'active', 'disabled'])
            ->orderBy('id')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })->toArray();
    }

    public static function setStatusByService($serviceId, $status)
    {
        Capsule::table('mod_dataz_proxy_services')
            ->where('service_id', (int)$serviceId)
            ->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    public static function deleteByService($serviceId)
    {
        Capsule::table('mod_dataz_proxy_services')
            ->where('service_id', (int)$serviceId)
            ->update([
                'status' => 'deleted',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
