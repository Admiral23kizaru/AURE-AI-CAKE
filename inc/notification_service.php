<?php
declare(strict_types=1);
require_once __DIR__.'/env.php';
require_once __DIR__.'/phone.php';
function queue_sms(PDO $pdo,string $eventKey,string $recipient,string $template,array $data,?int $actorUserId=null,?string $reason=null): void {
    $phone=normalize_ph_phone($recipient); if(!$phone) throw new InvalidArgumentException('Invalid Philippine mobile number.');
    $stmt=$pdo->prepare("INSERT IGNORE INTO notification_outbox(event_key,recipient,actor_user_id,template_name,reason,template_data,final_state) VALUES(?,?,?,?,?,?,?)");
    $stmt->execute([$eventKey,$phone,$actorUserId,$template,$reason,json_encode($data,JSON_UNESCAPED_UNICODE),env_bool('SMS_ENABLED')?'pending':'disabled']);
}
function semaphore_otp(string $recipient,string $code): array {
    if(!env_bool('SMS_ENABLED')) return ['ok'=>false,'disabled'=>true];
    $key=(string)env_value('SEMAPHORE_API_KEY',''); if($key==='') return ['ok'=>false,'error'=>'not_configured'];
    $providerNumber=semaphore_phone_number($recipient); if($providerNumber===null)return ['ok'=>false,'error'=>'invalid_recipient'];
    $fields=['apikey'=>$key,'number'=>$providerNumber,'message'=>'Aure Cakes OTP: {otp}. Expires in 5 min.','code'=>$code];
    $sender=trim((string)env_value('SEMAPHORE_SENDER_NAME','')); if($sender!=='')$fields['sendername']=$sender;
    $ch=curl_init('https://api.semaphore.co/api/v4/otp');
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true]);
    $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$transportError=curl_error($ch);curl_close($ch);
    $decoded=json_decode((string)$raw,true);$row=is_array($decoded)&&isset($decoded[0])&&is_array($decoded[0])?$decoded[0]:(is_array($decoded)?$decoded:[]);
    $messageId=(string)($row['message_id']??'');$status=(string)($row['status']??'');
    $rejected=in_array(strtolower($status),['failed','rejected','refunded','error'],true);
    $ok=$http>=200&&$http<300&&$messageId!==''&&!$rejected;
    $errorCode=$transportError!==''?'transport_error':($http<200||$http>=300?'http_'.$http:($messageId===''?'provider_rejected':($rejected?'provider_'.$status:null)));
    if(!$ok)error_log('Semaphore OTP failed: '.($errorCode??'unknown_error').'; sender='.(string)($sender?:'default'));
    return ['ok'=>$ok,'id'=>$messageId,'status'=>$status,'error'=>$errorCode];
}
function render_sms_template(string $name,array $d): string {
    $order=(string)($d['order_number']??''); $map=['order_confirmed'=>"Aure Cakes: {$order} confirmed.",'order_ready'=>"Aure Cakes: {$order} ready for pickup.",'order_cancelled'=>"Aure Cakes: {$order} cancelled.",'pickup_reminder'=>"Aure Cakes: {$order} pickup is tomorrow.",'custom_message'=>(string)($d['message']??'')];
    return $map[$name]??'Aure Cakes order update.';
}

function queue_custom_sms(PDO $pdo,int $orderHeaderId,int $actorUserId,string $recipient,string $message,string $reason): void {
    $message=trim(preg_replace('/\s+/u',' ',$message)??''); $reason=trim($reason);
    if($message==='' || mb_strlen($message)>160) throw new InvalidArgumentException('Message must contain 1 to 160 characters.');
    if(mb_strlen($reason)<3 || mb_strlen($reason)>255) throw new InvalidArgumentException('A short audit reason is required.');
    $event='custom-order-'.$orderHeaderId.'-'.bin2hex(random_bytes(8));
    queue_sms($pdo,$event,$recipient,'custom_message',['message'=>$message],$actorUserId,$reason);
}
