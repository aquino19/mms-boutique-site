<?php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: products.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Preencha e-mail e senha.';
    } elseif (attemptLogin($email, $password)) {
        header('Location: products.php');
        exit;
    } else {
        $error = 'E-mail ou senha incorretos.';
    }
}

$pageTitle = 'Entrar';
require __DIR__ . '/includes/layout_start.php';
?>
<h1>Entrar no admin</h1>

<?php if ($error): ?>
  <div class="error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form class="card" method="post" action="login.php">
  <label for="email">E-mail</label>
  <input type="email" id="email" name="email" required autofocus>

  <label for="password">Senha</label>
  <input type="password" id="password" name="password" required>

  <div style="margin-top:20px;">
    <button type="submit" class="btn">Entrar</button>
  </div>
</form>

<?php require __DIR__ . '/includes/layout_end.php'; ?>
