<?php
declare(strict_types=1);

return (static function (): PDO {
    $host = getenv('SALEVISION_DB_HOST') ?: 'sql303.infinityfree.com';
    $port = getenv('SALEVISION_DB_PORT') ?: '3307';
    $database = getenv('SALEVISION_DB_NAME') ?: 'if0_43296541_salevision_demo_sales';
    $username = getenv('SALEVISION_DB_USER') ?: 'if0_43296541';
    $password = getenv('SALEVISION_DB_PASSWORD') ?: 'Eisendichosa717';

    if ($password === false) {
        $password = '';
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
})();
