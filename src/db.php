<?php
declare(strict_types=1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

function getPdo(): PDO {
    $host = getenv('DB_HOST') ?: 'db';
    $db   = getenv('DB_NAME') ?: 'php_Slepko';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: 'lab2';
    $port = (int)(getenv('DB_PORT') ?: 3306);

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    return new PDO($dsn, $user, $pass, $options);
}
?>