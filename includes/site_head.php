<?php
/**
 * Cabeçalho comum: ticker + navegação + menu mobile.
 * Antes de incluir, defina $pageTitle. Defina $fullBleed = true para páginas
 * que cuidam do próprio layout (home, categoria); senão o conteúdo vai dentro de <main>.
 */
require_once __DIR__ . '/ui.php';
$pageTitle = $pageTitle ?? '';
$fullBleed = $fullBleed ?? false;
$cartCount = cartItemCount();
$navCats = mmsCategories();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pageTitle ? mmsH($pageTitle) . ' — ' : '' ?>MM'S Boutique</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,450;9..144,600;9..144,700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/site.css?v=2">
</head>
<body>

<div class="ticker-band">
  <div class="ticker-track">
    <?php for ($i = 0; $i < 2; $i++): ?>
      <span>Novidades toda semana</span>
      <span>Entrega toda Natal e região</span>
      <span>Loja 01 · Santa Luzia, 1550 — Igapó</span>
      <span>Loja 02 · SuperFácil Atacado SGA</span>
      <span>Atendimento via WhatsApp</span>
    <?php endfor; ?>
  </div>
</div>

<nav class="site-nav">
  <div class="nav-inner">
    <a href="index.php" class="brand">
      <img src="assets/logo.png" alt="MM'S Boutique" class="brand-img">
      <span class="logo">MM<em>'</em>S <span class="logo-sub">boutique</span></span>
    </a>
    <div class="nav-links">
      <?php foreach ($navCats as $name => $c): ?>
        <a href="categoria.php?cat=<?= urlencode($name) ?>"<?= (($_GET['cat'] ?? '') === $name && basename($_SERVER['SCRIPT_NAME']) === 'categoria.php') ? ' class="active"' : '' ?>><?= mmsH($c['label']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="nav-icons">
      <a href="catalog.php" class="nav-search">Buscar</a>
      <a href="cart.php" class="bag-icon" aria-label="Carrinho">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
        <?php if ($cartCount > 0): ?><em class="bag-count"><?= (int)$cartCount ?></em><?php endif; ?>
      </a>
      <button class="hamburger-btn" id="hamburgerBtn" aria-label="Abrir menu"><span></span><span></span><span></span></button>
    </div>
  </div>
</nav>

<div class="mobile-drawer" id="mobileDrawer">
  <div class="mobile-drawer-panel">
    <button class="close-drawer" id="closeDrawerBtn" aria-label="Fechar menu">×</button>
    <?php foreach ($navCats as $name => $c): ?>
      <a href="categoria.php?cat=<?= urlencode($name) ?>"><?= mmsH($c['label']) ?></a>
    <?php endforeach; ?>
    <a href="cart.php">Meu carrinho<?= $cartCount > 0 ? ' (' . (int)$cartCount . ')' : '' ?></a>
  </div>
</div>

<?php if (!$fullBleed): ?><main class="page-main"><?php endif; ?>
