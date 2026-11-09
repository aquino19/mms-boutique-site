<?php
/**
 * Adaptador Melhor Envio — cotação de frete.
 * Mesmo princípio dos adaptadores de pagamento: lógica de montar payload e
 * interpretar resposta fica separada da chamada de rede, pra dar pra testar
 * sem precisar de internet nem de credencial real.
 */

/** Monta o payload de cotação a partir da origem, destino e itens. */
function me_build_calculate_payload(string $originCep, string $destCep, array $items): array
{
    $products = [];
    foreach ($items as $item) {
        $products[] = [
            'id' => (string)$item['product_id'],
            'width' => (float)$item['width_cm'],
            'height' => (float)$item['height_cm'],
            'length' => (float)$item['length_cm'],
            'weight' => (float)$item['weight_kg'],
            'insurance_value' => (float)$item['unit_price'],
            'quantity' => (int)$item['quantity'],
        ];
    }

    return [
        'from' => ['postal_code' => me_clean_cep($originCep)],
        'to' => ['postal_code' => me_clean_cep($destCep)],
        'products' => $products,
    ];
}

/** Remove qualquer caractere que não seja número do CEP. */
function me_clean_cep(string $cep): string
{
    return preg_replace('/\D/', '', $cep);
}

/** Transforma a resposta bruta da API numa lista simples de opções, já sem erros. */
function me_map_response(array $response): array
{
    $options = [];
    foreach ($response as $service) {
        // Serviços com erro (ex: transportadora não atende aquele CEP) vêm com "error" preenchido
        if (!empty($service['error'])) {
            continue;
        }
        $options[] = [
            'service_id' => $service['id'] ?? null,
            'service_name' => $service['name'] ?? 'Frete',
            'company_name' => $service['company']['name'] ?? '',
            'price' => isset($service['price']) ? (float)$service['price'] : null,
            'delivery_time' => $service['delivery_time'] ?? null,
        ];
    }
    return $options;
}

/** Faz a chamada HTTP de verdade. Isolada pra poder ser testada/mocada. */
function me_api_request(string $url, string $accessToken, array $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($body),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $accessToken,
            'User-Agent: MMS Boutique (contato@mmsboutique.com.br)',
        ],
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $response === false) {
        throw new RuntimeException('Falha de rede ao chamar o Melhor Envio: ' . $error);
    }

    $decoded = json_decode($response, true);
    if ($httpCode >= 400) {
        throw new RuntimeException('Melhor Envio retornou erro HTTP ' . $httpCode . ': ' . $response);
    }

    return $decoded ?? [];
}

/**
 * Cotação completa: monta o payload, chama a API, e devolve as opções já
 * limpas (sem os serviços que deram erro pra aquele CEP).
 */
function me_calculate_shipping(string $originCep, string $destCep, array $items, string $accessToken): array
{
    $payload = me_build_calculate_payload($originCep, $destCep, $items);
    $response = me_api_request('https://api.melhorenvio.com.br/api/v2/me/shipment/calculate', $accessToken, $payload);
    return me_map_response($response);
}
