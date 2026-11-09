<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/shipping/melhorenvio.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getDbConnection();
$destCep = trim($_POST['cep'] ?? '');
$cart = getCart();

if ($destCep === '' || strlen(me_clean_cep($destCep)) !== 8) {
    http_response_code(400);
    echo json_encode(['error' => 'CEP inválido.']);
    exit;
}

if (empty($cart)) {
    http_response_code(400);
    echo json_encode(['error' => 'Carrinho vazio.']);
    exit;
}

$settings = $pdo->query('SELECT melhor_envio_token FROM store_settings WHERE id = 1')->fetch();
if (!$settings || !$settings['melhor_envio_token']) {
    echo json_encode(['error' => 'Frete ainda não configurado nesta loja.', 'groups' => []]);
    exit;
}

// Reaproveita a mesma lógica de alocação por loja usada no checkout de retirada
$allocation = allocateFulfillment($pdo, $cart);
if (!$allocation['success']) {
    echo json_encode(['error' => $allocation['error'], 'groups' => []]);
    exit;
}

// Agrupa os itens alocados por loja de origem
$byLocation = [];
foreach ($allocation['allocations'] as $a) {
    $byLocation[$a['location_id']]['location_name'] = $a['location_name'];
    $byLocation[$a['location_id']]['items'][] = $a;
}

$stmt = $pdo->prepare('SELECT weight_kg, height_cm, width_cm, length_cm FROM products WHERE id = :id');
$locationStmt = $pdo->prepare('SELECT cep FROM store_locations WHERE id = :id');

$groups = [];
foreach ($byLocation as $locationId => $group) {
    $locationStmt->execute(['id' => $locationId]);
    $originCep = $locationStmt->fetchColumn();

    if (!$originCep) {
        $groups[] = [
            'location_name' => $group['location_name'],
            'error' => 'CEP de origem não cadastrado pra essa unidade.',
            'options' => [],
        ];
        continue;
    }

    $items = [];
    foreach ($group['items'] as $a) {
        $stmt->execute(['id' => $a['product_id']]);
        $dims = $stmt->fetch();
        $items[] = [
            'product_id' => $a['product_id'],
            'weight_kg' => $dims['weight_kg'] ?? 0.3,
            'height_cm' => $dims['height_cm'] ?? 15,
            'width_cm' => $dims['width_cm'] ?? 20,
            'length_cm' => $dims['length_cm'] ?? 5,
            'unit_price' => $a['unit_price'],
            'quantity' => $a['quantity'],
        ];
    }

    try {
        $options = me_calculate_shipping($originCep, $destCep, $items, $settings['melhor_envio_token']);
        $groups[] = [
            'location_id' => $locationId,
            'location_name' => $group['location_name'],
            'error' => empty($options) ? 'Nenhuma opção de frete disponível pra esse CEP.' : null,
            'options' => $options,
        ];
    } catch (Throwable $e) {
        error_log('Erro ao cotar frete (Melhor Envio): ' . $e->getMessage());
        $groups[] = [
            'location_id' => $locationId,
            'location_name' => $group['location_name'],
            'error' => 'Não foi possível calcular o frete agora. Tente novamente em instantes.',
            'options' => [],
        ];
    }
}

echo json_encode(['error' => null, 'groups' => $groups]);
