<?php
/** @var string $pageTitle definido antes de incluir este arquivo */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — MM'S Boutique</title>
<style>
  :root{
    --emerald-deep:#0E5C33;
    --gold:#D4AF37;
    --cream:#F7F2E6;
    --cream-2:#EFE6D1;
    --choc:#2E1F18;
    --line: rgba(46,31,24,0.14);
    --danger:#B8735E;
  }
  *{box-sizing:border-box; margin:0; padding:0;}
  body{
    background:var(--cream); color:var(--choc);
    font-family:"Manrope", Arial, sans-serif; padding:0;
  }
  a{color:inherit;}
  header{
    background:var(--emerald-deep); color:var(--cream);
    padding:16px 32px; display:flex; justify-content:space-between; align-items:center;
  }
  header .logo{font-weight:700; font-size:18px;}
  header nav a{
    color:var(--cream); text-decoration:none; margin-left:20px; font-size:14px; font-weight:600;
  }
  header nav a:hover{ text-decoration:underline; }
  main{ max-width:960px; margin:0 auto; padding:32px; }
  h1{ font-size:22px; margin-bottom:20px; }
  table{ width:100%; border-collapse:collapse; background:white; }
  th, td{ text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); font-size:14px; }
  th{ background:var(--cream-2); font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  .btn{
    display:inline-block; padding:10px 18px; background:var(--emerald-deep); color:var(--cream);
    text-decoration:none; border:none; border-radius:4px; font-size:14px; font-weight:600; cursor:pointer;
  }
  .btn.secondary{ background:transparent; color:var(--choc); border:1px solid var(--line); }
  .btn.danger{ background:var(--danger); }
  .btn-sm{ padding:6px 12px; font-size:12px; }
  form.card{
    background:white; padding:24px; border-radius:6px; max-width:480px; margin-bottom:24px;
  }
  label{ display:block; font-size:13px; font-weight:700; margin:14px 0 6px; }
  input[type=text], input[type=email], input[type=password], input[type=number], textarea{
    width:100%; padding:10px; border:1px solid var(--line); border-radius:4px; font-size:14px; font-family:inherit;
  }
  textarea{ min-height:80px; resize:vertical; }
  .error{ background:#FADBD8; color:#7B241C; padding:10px 14px; border-radius:4px; margin-bottom:16px; font-size:14px; }
  .success{ background:#D5F5E3; color:#145A32; padding:10px 14px; border-radius:4px; margin-bottom:16px; font-size:14px; }
  .badge{ padding:3px 8px; border-radius:10px; font-size:11px; font-weight:700; }
  .badge.active{ background:#D5F5E3; color:#145A32; }
  .badge.inactive{ background:#EAEAEA; color:#666; }
  .toolbar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
</style>
</head>
<body>
<?php if (isLoggedIn()): ?>
<header>
  <div class="logo">MM'S Boutique — Admin</div>
  <nav>
    <a href="products.php">Produtos</a>
    <a href="orders.php">Pedidos</a>
    <a href="settings.php">Configurações</a>
    <a href="logout.php">Sair</a>
  </nav>
</header>
<?php endif; ?>
<main>
