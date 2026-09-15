<?php
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function redirect(string $url): never { header('Location: ' . $url); exit; }
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Token CSRF inválido.'); } }
function flash(?string $type=null, ?string $message=null): ?array { if ($message !== null) { $_SESSION['flash'] = [$type,$message]; return null; } $f=$_SESSION['flash']??null; unset($_SESSION['flash']); return $f; }
function admin_user(): ?array { return $_SESSION['admin_user'] ?? null; }
function require_admin(): void { if (!admin_user()) redirect('../login.php'); }
function setting(string $key, string $default=''): string { static $cache=[]; if(array_key_exists($key,$cache)) return $cache[$key]; $s=db()->prepare('SELECT value FROM settings WHERE `key`=? LIMIT 1'); $s->execute([$key]); return $cache[$key]=$s->fetchColumn() ?: $default; }
function slugify(string $text): string { $text=iconv('UTF-8','ASCII//TRANSLIT',$text); $text=preg_replace('/[^a-zA-Z0-9]+/','-',strtolower($text)); return trim($text,'-'); }
function upload_image(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Falha no upload.');
    if ($file['size'] > 5*1024*1024) throw new RuntimeException('Imagem acima de 5 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Formato de imagem não permitido.');
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR,0755,true);
    $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'],UPLOAD_DIR.$name)) throw new RuntimeException('Não foi possível salvar a imagem.');
    return 'uploads/'.$name;
}
function money(float $v): string { return 'R$ '.number_format($v,2,',','.'); }
function audit(string $action, ?int $orderId=null, ?string $meta=null): void { $u=admin_user(); $st=db()->prepare('INSERT INTO audit_logs(user_id,action,order_id,metadata,ip_address) VALUES(?,?,?,?,?)'); $st->execute([$u['id']??null,$action,$orderId,$meta,$_SERVER['REMOTE_ADDR']??'']); }
