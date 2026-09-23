<?php
function jsonResponse($data, int $status=200): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function input(): array {
    $raw=file_get_contents('php://input');
    $json=json_decode($raw,true);
    return is_array($json)?$json:array_merge($_POST,$_GET);
}
function requireAuth(): array {
    if (empty($_SESSION['user'])) jsonResponse(['ok'=>false,'message'=>'Oturum açmanız gerekiyor.'],401);
    return $_SESSION['user'];
}
function clean(string $v): string { return trim(strip_tags($v)); }
function userPublic(array $u): array {
    unset($u['password_hash']); return $u;
}
