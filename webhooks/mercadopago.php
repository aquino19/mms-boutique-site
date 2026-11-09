<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/gateway.php';

$pdo = getDbConnection();
$body = json_decode(file_get_contents('php://input'), true) ?? [];

try {
    processIncomingWebhook($pdo, 'mercadopago', $body);
    http_response_code(200);
    echo 'ok';
} catch (Throwable $e) {
    error_log('Erro no webhook Mercado Pago: ' . $e->getMessage());
    // Responde 200 mesmo em erro interno para o Mercado Pago não ficar reenviando
    // indefinidamente; o erro já foi registrado no log para investigação.
    http_response_code(200);
    echo 'erro registrado';
}
