<?php
/**
 * Adaptador PagSeguro (PagBank).
 * Mesmo contrato do adaptador do Mercado Pago. Diferença crítica:
 * PagSeguro trabalha com valores em CENTAVOS INTEIROS, não decimal.
 */

/** Monta o payload de criação de pedido. Note a conversão pra centavos. */
function pagseguro_build_order_payload(array $order): array
{
    $items = [];
    foreach ($order['items'] as $item) {
        $items[] = [
            'name' => $item['name'],
            'quantity' => (int)$item['quantity'],
            'unit_amount' => (int)round(((float)$item['unit_price']) * 100), // reais -> centavos
        ];
    }

    return [
        'reference_id' => (string)$order['id'],
        'customer' => ['email' => $order['customer_email']],
        'items' => $items,
        'notification_urls' => [$order['base_url'] . '/webhooks/pagseguro.php'],
    ];
}

/** Normaliza o status do PagSeguro para o vocabulário interno da loja. */
function pagseguro_map_status(string $psStatus): string
{
    return match ($psStatus) {
        'PAID' => 'paid',
        'WAITING', 'AUTHORIZED' => 'pending',
        'DECLINED' => 'failed',
        'CANCELED' => 'refunded',
        default => 'pending',
    };
}

/** Faz a chamada HTTP de verdade. Isolada pra poder ser testada/mocada. */
function pagseguro_api_request(string $method, string $url, string $accessToken, ?array $body = null): array
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
        throw new RuntimeException('Falha de rede ao chamar o PagSeguro: ' . $error);
    }

    $decoded = json_decode($response, true);
    if ($httpCode >= 400) {
        throw new RuntimeException('PagSeguro retornou erro HTTP ' . $httpCode . ': ' . $response);
    }

    return $decoded ?? [];
}

/** Cria o pedido/cobrança e retorna a URL de pagamento. */
function pagseguro_create_charge(array $order, string $accessToken): array
{
    $payload = pagseguro_build_order_payload($order);
    $data = pagseguro_api_request('POST', 'https://api.pagseguro.com/orders', $accessToken, $payload);

    $checkoutUrl = null;
    foreach ($data['links'] ?? [] as $link) {
        if (($link['rel'] ?? '') === 'PAY') {
            $checkoutUrl = $link['href'];
            break;
        }
    }

    return [
        'charge_id' => $data['id'] ?? null,
        'checkout_url' => $checkoutUrl,
        'status' => 'pending',
    ];
}

/** Consulta o status real de um pedido (nunca confiar só no webhook). */
function pagseguro_get_charge_status(string $chargeId, string $accessToken): array
{
    $data = pagseguro_api_request('GET', 'https://api.pagseguro.com/orders/' . $chargeId, $accessToken);
    $status = $data['charges'][0]['status'] ?? '';
    return ['status' => pagseguro_map_status($status)];
}

/** Processa a notificação de webhook do PagSeguro. */
function pagseguro_handle_webhook(array $requestBody, string $accessToken): array
{
    $orderId = $requestBody['reference_id'] ?? null;
    $chargeId = $requestBody['id'] ?? null;

    if (!$orderId || !$chargeId) {
        throw new InvalidArgumentException('Webhook do PagSeguro sem reference_id ou id.');
    }

    // Reconfirma direto na API — nunca confia cegamente no corpo do webhook
    $data = pagseguro_api_request('GET', 'https://api.pagseguro.com/orders/' . $chargeId, $accessToken);
    $status = $data['charges'][0]['status'] ?? '';

    return [
        'order_id' => $orderId,
        'status' => pagseguro_map_status($status),
    ];
}
