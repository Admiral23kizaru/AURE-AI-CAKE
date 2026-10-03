<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/inc/db.php';require dirname(__DIR__).'/inc/notification_service.php';require dirname(__DIR__).'/inc/cli_lock.php';
$lock=acquire_cli_lock('pickup-reminders');
$q=$pdo->query("SELECT id,order_number,customer_phone FROM order_headers WHERE status IN ('Confirmed','Preparing','Ready for Pickup') AND TIMESTAMP(pickup_date,pickup_time) BETWEEN DATE_ADD(NOW(),INTERVAL 23 HOUR) AND DATE_ADD(NOW(),INTERVAL 25 HOUR) AND customer_phone IS NOT NULL");
$count=0;foreach($q as $o){queue_sms($pdo,'order:'.$o['id'].':reminder_24h',$o['customer_phone'],'pickup_reminder',['order_number'=>$o['order_number']]);$count++;}
echo $count." reminder candidate(s) processed.\n";
