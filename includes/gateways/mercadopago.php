<?php
/**
 * Adaptador Mercado Pago.
 * Segue o contrato comum descrito em spec-adaptadores-gateway-pagamento.md:
 *   createCharge(order, storeConfig) -> { charge_id, checkout_url, status }
 *   getChargeStatus(charge_id, storeConfig) -> { status }
 *   handleWebhook(requestBody, storeConfig) -> { order_id, status }
 *
 * As funções de "montar payload" e "mapear status" ficam separadas da
 * chamada de rede de propósito — permite testar a lógica sem precisar
 * de internet nem de credencial real.
 */

/** Monta o payload de criação de preferência a partir do pedido interno. */
function mp_build_preference_payload(array $order): array
{
    $items = [];
    foreach ($order['items'] as $item) {
        $items[] = [
            'title' => $item['name'],
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
        ];
    }

    return [
        'items' => $items,
        'payer' => ['email' => $order['customer_email']],
        'external_reference' => (string)$order['id'],
        'back_urls' => [
            'success' => $order['base_url'] . '/checkout_success.php?order_id=' . $order['id'],
            'failure' => $order['base_url'] . '/checkout.php',
        ],
        'notification_url' => $order['base_url'] . '/webhooks/mercadopago.php',
    ];
}

/** Normaliza o status do Mercado Pago para o vocabulário interno da loja. */
function mp_map_status(string $mpStatus): string
{
    return match ($mpStatus) {
        'approved' => 'paid',
        'pending', 'in_process' => 'pending',
        'rejected' => 'failed',
        'refunded', 'charged_back' => 'refunded',
        default => 'pending',
    };
}

/** Faz a chamada HTTP de verdade. Isolada pra poder ser testada/mocada. */
function mp_api_request(string $method, string $url, string $accessToken, ?array $body = null): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken,
        ],
        CURLOPT_TIMEOUT => 15,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $response === false) {
        throw new RuntimeException('Falha de rede ao chamar o Mercado Pago: ' . $error);
    }

    $decoded = json_decode($response, true);
    if ($httpCode >= 400) {
        throw new RuntimeException('Mercado Pago retornou erro HTTP ' . $httpCode . ': ' . $response);
    }

    return $decoded ?? [];
}

/** Cria a cobrança (preferência de checkout) e retorna a URL pra redirecionar o cliente. */
function mp_create_charge(array $order, string $accessToken): array
{
    $payload = mp_build_preference_payload($order);
    $data = mp_api_request('POST', 'https://api.mercadopago.com/checkout/preferences', $accessToken, $payload);

    // Credenciais de teste (TEST-...) só funcionam no checkout de sandbox.
    $isTestToken = strncmp($accessToken, 'TEST-', 5) === 0;
    $checkoutUrl = $isTestToken
        ? ($data['sandbox_init_point'] ?? $data['init_point'] ?? null)
        : ($data['init_point'] ?? null);

    return [
        'charge_id' => $data['id'] ?? null,
        'checkout_url' => $checkoutUrl,
        'status' => 'pending',
    ];
}

/** Consulta o status real de um pagamento (nunca confiar só no webhook). */
function mp_get_charge_status(string $chargeId, string $accessToken): array
{
    $data = mp_api_request('GET', 'https://api.mercadopago.com/v1/payments/' . $chargeId, $accessToken);
    return ['status' => mp_map_status($data['status'] ?? '')];
}

/** Processa a notificação de webhook do Mercado Pago. */
function mp_handle_webhook(array $requestBody, string $accessToken): array
{
    $paymentId = $requestBody['data']['id'] ?? null;
    if (!$paymentId) {
        throw new InvalidArgumentException('Webhook do Mercado Pago sem id de pagamento.');
    }

    // Reconfirma direto na API — nunca confia cegamente no corpo do webhook
    $data = mp_api_request('GET', 'https://api.mercadopago.com/v1/payments/' . $paymentId, $accessToken);

    return [
        'order_id' => $data['external_reference'] ?? null,
        'status' => mp_map_status($data['status'] ?? ''),
    ];
}
