<?php
declare(strict_types=1);
require_once __DIR__.'/notification_service.php';
function otp_delivery_feedback(array $result): array {
    if(!empty($result['disabled']))return ['type'=>'warning','message'=>'SMS delivery is disabled. Your account remains pending until a code can be sent.'];
    if(!empty($result['sent']))return ['type'=>'success','message'=>'A six-digit verification code was sent to your mobile number.'];
    return ['type'=>'danger','message'=>'The verification code could not be sent. Please use Resend after the waiting period or contact the shop.'];
}
function issue_otp(PDO $pdo,?int $userId,string $purpose,string $phone,?string $email=null): array {
    $phone=normalize_ph_phone($phone); if(!$phone) throw new InvalidArgumentException('Enter a valid Philippine mobile number.');
    $rate=$pdo->prepare('SELECT COUNT(*) FROM otp_challenges WHERE send_succeeded=1 AND (destination=? OR (? IS NOT NULL AND email_key=?)) AND created_at>=DATE_SUB(NOW(),INTERVAL 1 HOUR)');$rate->execute([$phone,$email?strtolower($email):null,$email?strtolower($email):null]);
    if((int)$rate->fetchColumn()>=5) throw new RuntimeException('Too many codes were requested. Try again in one hour.');
    $latest=$pdo->prepare('SELECT resend_after>NOW() AS must_wait FROM otp_challenges WHERE destination=? AND consumed_at IS NULL ORDER BY id DESC LIMIT 1');$latest->execute([$phone]);$mustWait=(bool)$latest->fetchColumn();
    if($mustWait) throw new RuntimeException('Please wait 60 seconds before requesting another code.');
    $code=(string)random_int(100000,999999);$result=semaphore_otp($phone,$code);$sent=!empty($result['ok']);
    $stmt=$pdo->prepare('INSERT INTO otp_challenges(user_id,purpose,destination,email_key,code_hash,expires_at,resend_after,provider_message_id,provider_status,last_error,send_succeeded,sent_at) VALUES(?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE),DATE_ADD(NOW(),INTERVAL 60 SECOND),?,?,?,?,IF(?=1,NOW(),NULL))');
    $stmt->execute([$userId,$purpose,$phone,$email?strtolower($email):null,password_hash($code,PASSWORD_DEFAULT),$result['id']??null,$result['status']??null,$result['error']??null,$sent?1:0,$sent?1:0]);
    return ['challenge_id'=>(int)$pdo->lastInsertId(),'sent'=>$sent,'disabled'=>!empty($result['disabled']),'error'=>$result['error']??null];
}
function verify_otp(PDO $pdo,int $challengeId,string $code): array {
    $pdo->beginTransaction();
    try{$q=$pdo->prepare('SELECT *,expires_at<NOW() AS is_expired FROM otp_challenges WHERE id=? FOR UPDATE');$q->execute([$challengeId]);$c=$q->fetch();
        if(!$c||$c['consumed_at'])throw new RuntimeException('This code has already been used.');
        if((int)$c['is_expired']===1)throw new RuntimeException('This code has expired.');
        if((int)$c['attempts_left']<=0)throw new RuntimeException('Too many incorrect attempts.');
        if(!password_verify($code,$c['code_hash'])){$pdo->prepare('UPDATE otp_challenges SET attempts_left=GREATEST(attempts_left-1,0) WHERE id=?')->execute([$challengeId]);$pdo->commit();throw new RuntimeException('Incorrect verification code.');}
        $pdo->prepare("UPDATE otp_challenges SET consumed_at=NOW(),code_hash='' WHERE id=?")->execute([$challengeId]);$pdo->commit();return $c;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
