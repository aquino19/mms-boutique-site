<?php
// ============================================================
// Recebe do n8n o estoque atualizado de UMA unidade, casa cada SKU
// com a variação correspondente, e sobrescreve o estoque daquela
// unidade (o Firebird é sempre a fonte da verdade, não somamos).
//
// Vendas do site ainda NÃO lançadas no sistema da loja são descontadas
// do número recebido (reserva), para o site não "devolver" estoque já
// vendido. Contam como reserva os pedidos pagos sem erp_registered_at e
// os pendentes de pagamento criados há menos de RESERVE_PENDING_DAYS dias.
//
// Autenticação por segredo compartilhado (cabeçalho X-Sync-Secret).
// O valor fica em api/sync_secret.php.
// ============================================================

require_once __DIR__ . '/sync_secret.php';
define('RESERVE_PENDING_DAYS', 3);

require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $body): void
{
    http_response_code($code);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'Método não permitido.']);
}

$receivedSecret = $_SERVER['HTTP_X_SYNC_SECRET'] ?? '';
if (!hash_equals(STOCK_SYNC_SECRET, $receivedSecret)) {
    respond(401, ['error' => 'Segredo inválido.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['location_code']) || !isset($input['items']) || !is_array($input['items'])) {
    respond(400, ['error' => 'Corpo da requisição inválido. Esperado: {location_code, items: [{sku, quantity}]}']);
}

$pdo = getDbConnection();

$stmt = $pdo->prepare('SELECT id FROM store_locations WHERE code = :code AND active = 1');
$stmt->execute(['code' => $input['location_code']]);
$location = $stmt->fetch();

if (!$location) {
    respond(404, ['error' => 'Nenhuma unidade ativa encontrada com o código "' . $input['location_code'] . '".']);
}
$locationId = (int)$location['id'];

$findVariant = $pdo->prepare('SELECT id, product_id FROM product_variants WHERE sku = :sku AND active = 1');
$upsertStock = $pdo->prepare(
    'INSERT INTO stock_by_store (variant_id, location_id, quantity)
     VALUES (:variant_id, :location_id, :quantity)
     ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
);

$reservedStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(oi.quantity), 0)
     FROM order_items oi
     JOIN orders o ON o.id = oi.order_id
     WHERE oi.variant_id = :variant_id
       AND oi.fulfillment_location_id = :location_id
       AND o.erp_registered_at IS NULL
       AND (o.status = \'paid\'
            OR (o.status = \'pending\' AND o.created_at >= NOW() - INTERVAL ' . (int)RESERVE_PENDING_DAYS . ' DAY))'
);
$reservedSkus = [];
$updated = 0;
$notFoundSkus = [];
$touchedProducts = [];

$pdo->beginTransaction();
try {
    foreach ($input['items'] as $item) {
        $sku = trim((string)($item['sku'] ?? ''));
        $quantity = $item['quantity'] ?? null;

        if ($sku === '' || !is_numeric($quantity) || (int)$quantity < 0) {
            continue; // item malformado, ignorado silenciosamente
        }

        $findVariant->execute(['sku' => $sku]);
        $variant = $findVariant->fetch();

        if (!$variant) {
            $notFoundSkus[] = $sku;
            continue;
        }

        $reservedStmt->execute(['variant_id' => $variant['id'], 'location_id' => $locationId]);
        $reserved = (int)$reservedStmt->fetchColumn();
        if ($reserved > 0) {
            $reservedSkus[$sku] = $reserved;
        }
        $upsertStock->execute([
            'variant_id' => $variant['id'],
            'location_id' => $locationId,
            'quantity' => max(0, (int)$quantity - $reserved),
        ]);
        $touchedProducts[$variant['product_id']] = true;
        $updated++;
    }

    foreach (array_keys($touchedProducts) as $productId) {
        recalcProductStock($pdo, (int)$productId);
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('Erro na sincronização de estoque: ' . $e->getMessage());
    respond(500, ['error' => 'Erro interno ao processar a sincronização.']);
}

respond(200, [
    'success' => true,
    'location_code' => $input['location_code'],
    'variants_updated' => $updated,
    'skus_not_found' => $notFoundSkus,
    'reserved_by_online_orders' => $reservedSkus,
]);
