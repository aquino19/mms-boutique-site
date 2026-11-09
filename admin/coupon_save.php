<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: coupons.php');
    exit;
}

$pdo = getDbConnection();

$id = trim($_POST['id'] ?? '');
$code = strtoupper(trim($_POST['code'] ?? ''));
$discountPercent = $_POST['discount_percent'] ?? '';
$appliesTo = $_POST['applies_to'] ?? 'all';
$validFrom = trim($_POST['valid_from'] ?? '');
$validUntil = trim($_POST['valid_until'] ?? '');
$active = isset($_POST['active']) ? 1 : 0;
$productIds = $_POST['product_ids'] ?? [];

if ($code === '' || !is_numeric($discountPercent) || (float)$discountPercent <= 0 || (float)$discountPercent > 100) {
    die('Dados inválidos. Confira o código e o percentual de desconto (1 a 100).');
}
if (!in_array($appliesTo, ['all', 'specific'], true)) {
    die('Valor inválido em "Válido para".');
}
if ($appliesTo === 'specific' && empty($productIds)) {
    die('Selecione ao menos um produto, ou escolha "Todos os produtos".');
}

$pdo->beginTransaction();

try {
    if ($id !== '') {
        $couponId = (int)$id;
        $stmt = $pdo->prepare(
            'UPDATE coupons
             SET code = :code, discount_percent = :discount_percent, applies_to = :applies_to,
                 valid_from = :valid_from, valid_until = :valid_until, active = :active
             WHERE id = :id'
        );
        $stmt->execute([
            'code' => $code,
            'discount_percent' => $discountPercent,
            'applies_to' => $appliesTo,
            'valid_from' => $validFrom !== '' ? $validFrom : null,
            'valid_until' => $validUntil !== '' ? $validUntil : null,
            'active' => $active,
            'id' => $couponId,
        ]);
        // Remove associações antigas antes de gravar as novas
        $pdo->prepare('DELETE FROM coupon_products WHERE coupon_id = :id')->execute(['id' => $couponId]);
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO coupons (code, discount_percent, applies_to, valid_from, valid_until, active)
             VALUES (:code, :discount_percent, :applies_to, :valid_from, :valid_until, :active)'
        );
        $stmt->execute([
            'code' => $code,
            'discount_percent' => $discountPercent,
            'applies_to' => $appliesTo,
            'valid_from' => $validFrom !== '' ? $validFrom : null,
            'valid_until' => $validUntil !== '' ? $validUntil : null,
            'active' => $active,
        ]);
        $couponId = (int)$pdo->lastInsertId();
    }

    if ($appliesTo === 'specific') {
        $insertProduct = $pdo->prepare('INSERT INTO coupon_products (coupon_id, product_id) VALUES (:coupon_id, :product_id)');
        foreach ($productIds as $productId) {
            $insertProduct->execute(['coupon_id' => $couponId, 'product_id' => (int)$productId]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Erro ao salvar cupom: ' . $e->getMessage());
    die('Não foi possível salvar o cupom. Verifique se o código já não está em uso.');
}

header('Location: coupons.php?saved=1');
exit;
