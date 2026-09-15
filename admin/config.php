<?php require_once __DIR__.'/../../config/config.php';
function cart(): array { return $_SESSION['cart'] ?? []; }
function cart_count(): int { return array_sum(array_column(cart(),'quantity')); }
function cart_total(): float { $t=0; foreach(cart() as $i)$t += $i['price']*$i['quantity']; return $t; }
function cart_add(int $id,int $qty=1,string $size='',string $color=''): void { $st=db()->prepare('SELECT id,name,price,stock,image FROM products WHERE id=? AND active=1');$st->execute([$id]);$p=$st->fetch();if(!$p||$qty<1)throw new RuntimeException('Produto indisponível.');$key=$id.'|'.md5($size.'|'.$color);$old=$_SESSION['cart'][$key]['quantity']??0;$new=min($p['stock'], $old+$qty);if($new<1)throw new RuntimeException('Produto sem estoque.');$_SESSION['cart'][$key]=['product_id'=>$p['id'],'name'=>$p['name'],'price'=>(float)$p['price'],'quantity'=>$new,'size'=>$size,'color'=>$color,'image'=>$p['image']];}
function cart_remove(string $key): void { unset($_SESSION['cart'][$key]); }
