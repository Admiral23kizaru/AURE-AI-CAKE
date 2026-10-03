<?php
declare(strict_types=1);
require __DIR__.'/inc/db.php';
$id=(int)($_GET['id']??0);
if($id<1){header('Location: view-all.php',true,302);exit;}
$query=$pdo->prepare('SELECT 1 FROM cakes WHERE id=? AND available=1');$query->execute([$id]);
if(!$query->fetchColumn()){http_response_code(404);exit('Cake not found.');}
header('Location: cake_add.php?id='.$id,true,302);exit;
