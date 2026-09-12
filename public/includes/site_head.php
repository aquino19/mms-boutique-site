<?php
/** @var string $pageTitle definido antes de incluir este arquivo */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'MM\'S Boutique') ?> — MM'S Boutique</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,450;9..144,600;9..144,700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --emerald-deep:#0E5C33;
    --emerald:#107C41;
    --lime:#C9E265;
    --lime-deep:#8FA83E;
    --gold:#D4AF37;
    --gold-soft:#E6CB6E;
    --rose:#B8735E;
    --rose-deep:#8C5443;
    --cream:#F7F2E6;
    --cream-2:#EFE6D1;
    --choc:#2E1F18;
    --line: rgba(46,31,24,0.14);
    --radius: 2px;
    --serif: "Fraunces", serif;
    --sans: "Manrope", sans-serif;
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  html{scroll-behavior:smooth;}
  body{
    background:var(--cream); color:var(--choc); font-family:var(--sans);
    -webkit-font-smoothing:antialiased;
  }
  a{ color:inherit; text-decoration:none; }
  img{ display:block; max-width:100%; }
  .wrap{ max-width:1200px; margin:0 auto; padding:0 32px; }

  .btn{
    display:inline-flex; align-items:center; gap:10px;
    padding:14px 28px; font-family:var(--sans); font-size:13px; font-weight:700;
    letter-spacing:.06em; text-transform:uppercase; border:1px solid var(--choc);
    background:transparent; cursor:pointer; transition:all .25s ease;
  }
  .btn.solid{ background:var(--emerald-deep); color:var(--cream); border-color:var(--emerald-deep); }
  .btn.solid:hover{ background:var(--choc); border-color:var(--choc); }
  .btn.solid:disabled{ background:#ccc; border-color:#ccc; cursor:not-allowed; }
  .btn.ghost{ border-color:var(--choc); color:var(--choc); background:transparent; }
  .btn.ghost:hover{ background:rgba(46,31,24,.06); }

  nav.site-nav{
    position:sticky; top:0; z-index:50; background:var(--cream);
    border-bottom:1px solid var(--line);
  }
  .nav-inner{
    display:flex; align-items:center; justify-content:space-between;
    padding:20px 32px; max-width:1200px; margin:0 auto;
  }
  .logo{ font-family:"Playfair Display", serif; font-size:24px; font-weight:700; }
  .logo em{ font-style:normal; color:var(--rose); }
  .logo span{ font-family:var(--sans); letter-spacing:.14em; text-transform:uppercase; font-size:14px; margin-left:6px; }
  .cart-pill{
    display:flex; align-items:center; gap:8px; font-size:13px; font-weight:700;
    border:1px solid var(--choc); padding:10px 18px;
  }

  h1.page-title{
    font-family:var(--serif); font-weight:600; font-size:clamp(26px,3vw,36px);
    margin:36px 0 24px;
  }

  main{ max-width:1200px; margin:0 auto; padding:0 32px 64px; }
</style>
</head>
<body>

<nav class="site-nav">
  <div class="nav-inner">
    <a href="catalog.php" class="logo">MM'S<em></em> <span>Boutique</span></a>
    <a href="cart.php" class="cart-pill">Carrinho (<?= cartItemCount() ?>)</a>
  </div>
</nav>

<main>
