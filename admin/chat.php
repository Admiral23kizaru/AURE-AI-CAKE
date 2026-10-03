<?php declare(strict_types=1);$number=isset($_GET['order_number'])?'?order_number='.urlencode((string)$_GET['order_number']):'';header('Location: ../staff/chat.php'.$number);exit;
