<?php
declare(strict_types=1);
require __DIR__.'/../inc/db.php';require __DIR__.'/../inc/page.php';require_once __DIR__.'/../inc/env.php';require_admin_role();
if($_SERVER['REQUEST_METHOD']==='POST'){require_post_csrf();$confirmed=isset($_POST['prices_confirmed'])?'1':'0';$pdo->prepare("INSERT INTO system_settings(setting_key,setting_value,updated_by_user_id) VALUES('business_prices_confirmed',?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by_user_id=VALUES(updated_by_user_id)")->execute([$confirmed,user_id()]);redirect('readiness.php');}
$counts=[];
$counts['shop']=(int)$pdo->query('SELECT COUNT(*) FROM stores WHERE is_active=1')->fetchColumn();
$counts['cakes']=(int)$pdo->query('SELECT COUNT(*) FROM cakes WHERE available=1')->fetchColumn();
$counts['staff']=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='staff' AND is_active=1")->fetchColumn();
$counts['pickup']=(int)$pdo->query('SELECT COUNT(*) FROM pickup_capacities WHERE active=1 AND pickup_date>=CURDATE() AND capacity>0')->fetchColumn();
$counts['ai']=(int)$pdo->query("SELECT COUNT(DISTINCT CONCAT(pickup_date,':',size)) FROM ai_capacities WHERE active=1 AND pickup_date>=CURDATE() AND capacity>0")->fetchColumn();
$types=$pdo->query("SELECT option_type,COUNT(*) count FROM cake_options WHERE is_active=1 GROUP BY option_type")->fetchAll(PDO::FETCH_KEY_PAIR);
$settings=$pdo->query('SELECT setting_key,setting_value FROM system_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$checks=[
 ['Active pickup shop',$counts['shop']===1,$counts['shop'].' active'],
 ['Owner-confirmed cake prices',($settings['business_prices_confirmed']??'0')==='1','Administrator confirmation required'],
 ['Active staff account',$counts['staff']>0,$counts['staff'].' active'],
 ['Flavor, Size, Design, and Add-on options',min(array_map(fn($t)=>(int)($types[$t]??0),['Flavor','Size','Design','Add-on']))>0,'Flavor '.(int)($types['Flavor']??0).', Size '.(int)($types['Size']??0).', Design '.(int)($types['Design']??0).', Add-on '.(int)($types['Add-on']??0)],
 ['Future pickup slots',$counts['pickup']>0,$counts['pickup'].' configured'],
 ['Future AI capacities',$counts['ai']>0,$counts['ai'].' configured'],
 ['Semaphore configuration',env_bool('SMS_ENABLED')&&trim((string)env_value('SEMAPHORE_API_KEY',''))!==''&&trim((string)env_value('SEMAPHORE_SENDER_NAME',''))!=='','Sender '.(string)env_value('SEMAPHORE_SENDER_NAME','not set')],
 ['Reminder scheduled task',($settings['task_reminder_installed']??'0')==='1','Every 15 minutes'],
 ['Dispatcher scheduled task',($settings['task_dispatcher_installed']??'0')==='1','Every 15 minutes'],
];
$ready=count(array_filter($checks,fn($c)=>$c[1]));
page_start('Defense readiness');
?><main class="container py-4"><div class="d-flex justify-content-between align-items-start"><div><div class="eyebrow">Administrator</div><h1 class="h3">Defense readiness</h1><p class="help-text">This page reports configuration only. It never invents bakery data.</p></div><a href="capacity.php" class="btn btn-outline-primary">Configure capacity</a></div><div class="app-card p-4 mb-4"><div class="d-flex justify-content-between"><strong><?=$ready?> of <?=count($checks)?> checks ready</strong><span><?= $ready===count($checks)?'Ready for end-to-end testing':'Configuration required' ?></span></div><div class="progress mt-3" role="progressbar" aria-valuenow="<?=$ready?>" aria-valuemin="0" aria-valuemax="<?=count($checks)?>"><div class="progress-bar" style="width:<?=100*$ready/count($checks)?>%"></div></div></div><form method="post" class="app-card p-3 mb-4"><?=csrf_field()?><label><input type="checkbox" name="prices_confirmed" <?=($settings['business_prices_confirmed']??'0')==='1'?'checked':''?>> I confirm that every active cake price was supplied and approved by the bakery owner.</label><button class="btn btn-outline-primary btn-sm ms-2">Save confirmation</button></form><div class="list-group"><?php foreach($checks as [$label,$ok,$detail]):?><div class="list-group-item d-flex justify-content-between align-items-center"><div><strong><?=e($label)?></strong><div class="small text-muted"><?=e($detail)?></div></div><span class="badge <?=$ok?'text-bg-success':'text-bg-warning'?>"><?=$ok?'Ready':'Needs attention'?></span></div><?php endforeach;?></div></main><?php page_end();
