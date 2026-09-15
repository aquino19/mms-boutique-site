<?php require __DIR__.'/includes/bootstrap.php';if($_SERVER['REQUEST_METHOD']==='POST')cart_remove($_POST['key']??'');redirect('cart.php');
