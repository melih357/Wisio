<?php
function loadEnv(string $path): array {
    $env=[];
    if (!file_exists($path)) return $env;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line=trim($line);
        if ($line==='' || str_starts_with($line,'#') || !str_contains($line,'=')) continue;
        [$k,$v]=explode('=', $line, 2);
        $env[trim($k)]=trim($v, " \t\n\r\0\x0B\"'");
    }
    return $env;
}
function db(): PDO {
    static $pdo=null;
    if ($pdo) return $pdo;
    $env=loadEnv(dirname(__DIR__,2).'/.env');
    $host=$env['DB_HOST'] ?? '127.0.0.1';
    $port=$env['DB_PORT'] ?? '3306';
    $name=$env['DB_NAME'] ?? 'wisio';
    $user=$env['DB_USER'] ?? 'root';
    $pass=$env['DB_PASS'] ?? '';
    $dsn="mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    $pdo=new PDO($dsn,$user,$pass,[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,
    ]);
    return $pdo;
}
