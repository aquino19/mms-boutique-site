<?php
// ============================================================
// Recebe do n8n o estoque atualizado de UMA unidade, casa cada SKU
// com a variação correspondente, e sobrescreve o estoque daquela
// unidade (o Firebird é sempre a fonte da verdade, não somamos).
//
// Autenticação simples por segredo compartilhado — troque o valor
// abaixo por algo único antes de ir pra produção, e configure o
// mesmo valor no header "X-Sync-Secret" do nó HTTP Request do n8n.
// ============================================================

define('STOCK_SYNC_SECRET', 'TROCAR_AQUI');

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

        $upsertStock->execute([
            'variant_id' => $variant['id'],
            'location_id' => $locationId,
            'quantity' => (int)$quantity,
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
]);
