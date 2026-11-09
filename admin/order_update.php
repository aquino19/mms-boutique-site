<?php
/**
 * Avança o andamento (separação/envio/entrega) de um pedido pago.
 *
 * Regra da loja: o pedido só sai de "novo" depois que a vendedora confirma que
 * lançou a venda no sistema da loja (Sgbr) e deu baixa no estoque físico.
 * Enquanto isso não acontece, api/stock_sync.php desconta as peças do pedido.
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

const FULFILLMENT_FLOW = [
    'pickup'   => ['novo', 'separado', 'entregue'],
    'delivery' => ['novo', 'separado', 'enviado', 'entregue'],
];

function backToOrders(string $msg, bool $error = false): void
{
    $_SESSION['orders_flash'] = ['msg' => $msg, 'error' => $error];
    $anchor = isset($_POST['order_id']) ? '#pedido-' . (int)$_POST['order_id'] : '';
    header('Location: orders.php' . $anchor);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit;
}

if (!hash_equals($_SESSION['orders_csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
    backToOrders('Sessão expirada. Recarregue a página e tente de novo.', true);
}

$orderId = (int)($_POST['order_id'] ?? 0);
$newStatus = (string)($_POST['fulfillment_status'] ?? '');
$erpConfirmed = ($_POST['erp_confirm'] ?? '') === '1';

$pdo = getDbConnection();
$stmt = $pdo->prepare('SELECT id, status, fulfillment_method, fulfillment_status, erp_registered_at FROM orders WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    backToOrders('Pedido não encontrado.', true);
}
if ($order['status'] !== 'paid') {
    backToOrders('O pedido #' . $orderId . ' ainda não está pago. Só pedidos pagos podem ser separados.', true);
}

$flow = FULFILLMENT_FLOW[$order['fulfillment_method']] ?? FULFILLMENT_FLOW['delivery'];
$current = array_search($order['fulfillment_status'], $flow, true);
$target = array_search($newStatus, $flow, true);
if ($target === false || $current === false || $target <= $current) {
    backToOrders('Andamento inválido para o pedido #' . $orderId . '.', true);
}

$needsErp = $order['erp_registered_at'] === null;
if ($needsErp && !$erpConfirmed) {
    backToOrders('Pedido #' . $orderId . ': marque "Lancei a venda no sistema da loja" antes de mudar o andamento.', true);
}

$update = $pdo->prepare(
    'UPDATE orders
     SET fulfillment_status = :fs,
         fulfillment_updated_at = NOW(),
         erp_registered_at = COALESCE(erp_registered_at, IF(:erp = 1, NOW(), NULL)),
         erp_registered_by = COALESCE(erp_registered_by, IF(:erp2 = 1, :uid, NULL))
     WHERE id = :id'
);
$update->execute([
    'fs' => $newStatus,
    'erp' => $needsErp ? 1 : 0,
    'erp2' => $needsErp ? 1 : 0,
    'uid' => (int)$_SESSION['admin_user_id'],
    'id' => $orderId,
]);

backToOrders('Pedido #' . $orderId . ' atualizado.');
