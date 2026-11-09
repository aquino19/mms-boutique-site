<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/gateway.php';

$pdo = getDbConnection();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    processIncomingWebhook($pdo, 'pagseguro', $body);
    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    error_log('Erro no webhook PagSeguro: ' . $e->getMessage());
    http_response_code(200);
    echo 'erro registrado';
}
