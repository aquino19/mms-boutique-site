<?php
require_once __DIR__.'/../config/config.php'; require_admin(); $pdo=db();
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 foreach($_POST['settings']??[] as $k=>$v){
  if(!preg_match('/^[a-z_]+$/',$k)) continue;
  $pdo->prepare('INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)')->execute([$k,trim($v)]);
 }
 $logo=upload_image($_FILES['logo']??[]);
 if($logo) $pdo->prepare('INSERT INTO settings(`key`,`value`) VALUES("logo",?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)')->execute([$logo]);
 flash('ok','Configurações salvas.'); redirect('settings.php');
}
$keys=['brand_name','tagline','primary_color','accent_color','secondary_color','cream_color','hero_title','hero_text','whatsapp','instagram','store_address','pix_key'];
$vals=[]; foreach($keys as $k) $vals[$k]=setting($k);
require __DIR__.'/includes/header.php';
?>
<div class="content"><h1>Identidade & Configurações</h1><p class="muted">Altere a aparência e dados da loja sem editar o código.</p>
<form class="panel form" method="post" enctype="multipart/form-data">
<?=csrf_field()?>
<?php foreach($vals as $k=>$v): ?>
<label><?=e(ucwords(str_replace('_',' ',$k)))?>
<?php if(str_contains($k,'color')): ?><input type="color" name="settings[<?=e($k)?>]" value="<?=e($v)?>">
<?php elseif($k==='hero_text'): ?><textarea name="settings[<?=e($k)?>]"><?=e($v)?></textarea>
<?php else: ?><input name="settings[<?=e($k)?>]" value="<?=e($v)?>"><?php endif; ?>
</label>
<?php endforeach; ?>
<label>Logo<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
<button class="btn">Salvar configurações</button></form></div>
<?php require __DIR__.'/includes/footer.php';
