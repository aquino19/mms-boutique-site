<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDbConnection();
$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    $stmt = $pdo->prepare('UPDATE products SET active = NOT active WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

header('Location: products.php');
exit;
