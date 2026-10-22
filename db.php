<?php
declare(strict_types=1);

return (static function (): PDO {
    $host = getenv('SALEVISION_DB_HOST') ?: '127.0.0.1';
    $port = getenv('SALEVISION_DB_PORT') ?: '3307';
    $database = getenv('SALEVISION_DB_NAME') ?: 'salevision';
    $username = getenv('SALEVISION_DB_USER') ?: 'root';
    $password = getenv('SALEVISION_DB_PASSWORD');

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
