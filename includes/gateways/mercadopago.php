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
    $hasNegative = false;
    $total = 0.0;
    foreach ($order['items'] as $item) {
        $price = round((float)$item['unit_price'], 2);
        $qty = (int)$item['quantity'];
        $hasNegative = $hasNegative || $price <= 0;
        $total += $price * $qty;
        $items[] = [
            'title' => mb_substr((string)$item['name'], 0, 250),
            'quantity' => $qty,
            'unit_price' => $price,
            'currency_id' => 'BRL',
        ];
    }
    // O Mercado Pago não aceita itens com valor negativo (ex.: linha de desconto).
    // Nesse caso manda uma linha única com o total do pedido.
    if ($hasNegative) {
        $items = [[
            'title' => "Pedido #" . $order['id'] . " - MM'S Boutique",
            'quantity' => 1,
            'unit_price' => round($total, 2),
            'currency_id' => 'BRL',
        ]];
    }

    $base = rtrim($order['base_url'], '/');
    $successUrl = $base . '/checkout_success.php?order_id=' . $order['id'];
    $payload = [
        'items' => $items,
        'payer' => ['email' => $order['customer_email']],
        'external_reference' => (string)$order['id'],
        'statement_descriptor' => 'MMS BOUTIQUE',
        'back_urls' => [
            'success' => $successUrl,
            'pending' => $successUrl,
            'failure' => $successUrl,
        ],
        'notification_url' => $base . '/webhooks/mercadopago.php',
    ];
    // Volta sozinho para o site depois do pagamento aprovado (exige https)
    if (strncmp($base, 'https://', 8) === 0) {
        $payload['auto_return'] = 'approved';
    }
    return $payload;
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

/** Dados completos de um pagamento (status, external_reference...). */
function mp_get_payment(string $paymentId, string $accessToken): array
{
    return mp_api_request('GET', 'https://api.mercadopago.com/v1/payments/' . rawurlencode($paymentId), $accessToken);
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
    // Formato novo (corpo JSON com data.id) ou antigo (IPN: ?topic=payment&id=...)
    $type = $requestBody['type'] ?? ($_GET['type'] ?? ($_GET['topic'] ?? 'payment'));
    if ($type !== 'payment') {
        return ['order_id' => null, 'status' => 'ignored'];
    }
    $paymentId = $requestBody['data']['id'] ?? ($_GET['data_id'] ?? ($_GET['id'] ?? null));
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
