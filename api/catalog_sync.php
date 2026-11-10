<?php
// ============================================================
// Recebe do programa do PC da loja (mms-sync.ps1), direto ou pelo n8n,
// a lista COMPLETA de produtos marcados "Disponibilizar online" no
// SG Master e atualiza o catálogo do site. Regras em includes/erp_sync.php.
//
// POST JSON:
// { "location_code": "sao_goncalo",
//   "items": [ { "code": "011188", "name": "BABY FEM M", "reference": "BABY-FEM",
//                "size": "M", "price": 49.9, "quantity": 3, "weight_kg": 0.2,
//                "web_description": "<p>...</p>" } ] }
// Cabeçalho: X-Sync-Secret (valor em api/sync_secret.php)
// ============================================================

require_once __DIR__ . '/sync_secret.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/erp_sync.php';

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
if (STOCK_SYNC_SECRET === 'TROCAR_AQUI' || !hash_equals(STOCK_SYNC_SECRET, $_SERVER['HTTP_X_SYNC_SECRET'] ?? '')) {
    respond(401, ['error' => 'Segredo inválido.']);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input) || empty($input['location_code']) || !isset($input['items']) || !is_array($input['items'])) {
    respond(400, ['error' => 'Corpo inválido. Esperado: {location_code, items: [...]}']);
}
// Proteção: uma lista vazia esconderia todas as peças do site.
if (count($input['items']) === 0 && empty($input['allow_empty'])) {
    respond(400, ['error' => 'Lista vazia recebida; nada foi alterado. Envie allow_empty=true para esconder tudo de propósito.']);
}

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT id FROM store_locations WHERE code = :code AND active = 1');
$stmt->execute(['code' => $input['location_code']]);
$locationId = (int)$stmt->fetchColumn();
if (!$locationId) {
    respond(404, ['error' => 'Unidade "' . $input['location_code'] . '" não encontrada ou inativa.']);
}

try {
    $pdo->beginTransaction();
    $stats = erpSyncCatalog($pdo, $locationId, $input['items']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('catalog_sync: ' . $e->getMessage());
    respond(500, ['error' => 'Falha ao sincronizar; nada foi alterado.', 'detail' => $e->getMessage()]);
}

try {
    erpLogSync($pdo, (string)($input['source'] ?? 'pc-loja'), $stats);
} catch (Throwable $e) {
    error_log('catalog_sync log: ' . $e->getMessage());
}

respond(200, [
    'ok' => true,
    'items_received' => $stats['items_received'],
    'products_created' => $stats['products_created'],
    'variants_created' => $stats['variants_created'],
    'variants_updated' => $stats['variants_updated'],
    'variants_hidden' => $stats['variants_hidden'],
    'waiting_photo' => $stats['waiting_photo'],
    'reserved_by_online_orders' => $stats['reserved'],
    'warnings' => $stats['warnings'],
]);
