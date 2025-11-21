#!/usr/bin/env php
<?php
// Basic repository validation checks for PHP 7.4 CLI
$root = dirname(__DIR__);

$tests = [
    'Backend directory exists' => function () use ($root) {
        return is_dir($root . '/backend');
    },
    'Backend config.php exists' => function () use ($root) {
        return is_file($root . '/backend/config.php');
    },
    'WHMCS module directory exists' => function () use ($root) {
        return is_dir($root . '/modules/servers/dataz_proxy');
    },
    'SQL schema file exists' => function () use ($root) {
        return is_file($root . '/sql/schema.sql');
    },
];

$failed = 0;
foreach ($tests as $name => $callback) {
    $result = false;
    try {
        $result = (bool) $callback();
    } catch (Throwable $e) {
        $result = false;
    }

    if ($result) {
        echo "[PASS] {$name}\n";
    } else {
        echo "[FAIL] {$name}\n";
        $failed++;
    }
}

if ($failed > 0) {
    echo "\n{$failed} test(s) failed.\n";
    exit(1);
}

echo "\nAll tests passed.\n";
exit(0);
