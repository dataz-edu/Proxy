<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class DatazProxyHelpers
{
    public static function formatProxyList(array $proxies)
    {
        $lines = [];
        foreach ($proxies as $proxy) {
            $lines[] = sprintf('%s:%s:%s:%s', $proxy['proxy_ip'], $proxy['proxy_port'], $proxy['proxy_username'], $proxy['proxy_password']);
        }
        return implode("\n", $lines);
    }

    public static function updateCustomField($serviceId, $productId, $fieldName, $value)
    {
        $field = Capsule::table('tblcustomfields')
            ->where('type', 'product')
            ->where('relid', (int)$productId)
            ->where(function ($query) use ($fieldName) {
                $query->where('fieldname', $fieldName)
                    ->orWhere('fieldname', 'like', $fieldName . '|%');
            })
            ->first();

        if (!$field) {
            return;
        }

        $existing = Capsule::table('tblcustomfieldsvalues')
            ->where('fieldid', $field->id)
            ->where('relid', (int)$serviceId)
            ->first();

        if ($existing) {
            Capsule::table('tblcustomfieldsvalues')
                ->where('id', $existing->id)
                ->update(['value' => $value]);
        } else {
            Capsule::table('tblcustomfieldsvalues')->insert([
                'fieldid' => $field->id,
                'relid' => (int)$serviceId,
                'value' => $value,
            ]);
        }
    }
}
