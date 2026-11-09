<?php
require_once __DIR__ . '/gateways/mercadopago.php';
require_once __DIR__ . '/gateways/pagseguro.php';

/**
 * Interface única usada pelo checkout e pelos webhooks. Decide qual
 * adaptador chamar com base na configuração da loja.
 */
function gatewayCreateCharge(array $order, array $storeConfig): array
{
    return match ($storeConfig['gateway_provider']) {
        'mercadopago' => mp_create_charge($order, $storeConfig['gateway_access_token']),
        'pagseguro' => pagseguro_create_charge($order, $storeConfig['gateway_access_token']),
        default => throw new InvalidArgumentException('Gateway não configurado ou desconhecido.'),
    };
}

function gatewayGetChargeStatus(string $chargeId, array $storeConfig): array
{
    return match ($storeConfig['gateway_provider']) {
        'mercadopago' => mp_get_charge_status($chargeId, $storeConfig['gateway_access_token']),
        'pagseguro' => pagseguro_get_charge_status($chargeId, $storeConfig['gateway_access_token']),
        default => throw new InvalidArgumentException('Gateway não configurado ou desconhecido.'),
    };
}

function gatewayHandleWebhook(string $provider, array $requestBody, array $storeConfig): array
{
    return match ($provider) {
        'mercadopago' => mp_handle_webhook($requestBody, $storeConfig['gateway_access_token']),
        'pagseguro' => pagseguro_handle_webhook($requestBody, $storeConfig['gateway_access_token']),
        default => throw new InvalidArgumentException('Gateway desconhecido: ' . $provider),
    };
}

/**
 * Processa um webhook recebido de ponta a ponta: carrega a config da loja,
 * chama o adaptador certo, e atualiza o pedido no banco.
 * Usada pelos dois arquivos em /webhooks/.
 */
function processIncomingWebhook(PDO $pdo, string $provider, array $requestBody): void
{
    $settings = $pdo->query('SELECT * FROM store_settings WHERE id = 1')->fetch();

    if (!$settings || $settings['gateway_provider'] !== $provider || !$settings['gateway_access_token']) {
        throw new RuntimeException('Loja não está configurada para o gateway ' . $provider);
    }

    $result = gatewayHandleWebhook($provider, $requestBody, $settings);

    if (!$result['order_id']) {
        throw new RuntimeException('Webhook não trouxe um order_id válido.');
    }

    $stmt = $pdo->prepare('UPDATE orders SET status = :status WHERE id = :id');
    $stmt->execute([
        'status' => $result['status'],
        'id' => (int)$result['order_id'],
    ]);
}
