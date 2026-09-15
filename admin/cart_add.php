<?php
require_once __DIR__.'/../config/config.php';
$email='admin@mmsboutique.local'; $password='TroqueEstaSenha!123';
$st=db()->prepare('SELECT id FROM admins WHERE email=?'); $st->execute([$email]);
if(!$st->fetch()){ $q=db()->prepare('INSERT INTO admins(name,email,password_hash) VALUES(?,?,?)'); $q->execute(['Administrador',$email,password_hash($password,PASSWORD_DEFAULT)]); echo "Admin criado: $email / $password\n"; } else echo "Admin já existe.\n";
