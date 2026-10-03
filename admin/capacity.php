<?php
declare(strict_types=1);
require __DIR__.'/../inc/db.php';require __DIR__.'/../inc/page.php';require_admin_role();
$message='';
function selected_weekdays(array $raw): array { $days=[];foreach($raw as $d){$n=(int)$d;if($n>=1&&$n<=7)$days[$n]=true;}return array_keys($days); }
if($_SERVER['REQUEST_METHOD']==='POST'){
 require_post_csrf();
 try{
  $start=(string)($_POST['start_date']??'');$end=(string)($_POST['end_date']??'');$capacity=(int)($_POST['capacity']??-1);
  if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$start)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$end)||$start<date('Y-m-d')||$end<$start)throw new RuntimeException('Choose a valid future date range.');
  $first=new DateTimeImmutable($start);$last=new DateTimeImmutable($end);if($first->diff($last)->days>366)throw new RuntimeException('A date range cannot exceed one year.');
  if($capacity<0||$capacity>1000)throw new RuntimeException('Capacity must be between 0 and 1000.');
  $weekdays=selected_weekdays((array)($_POST['weekdays']??[]));if(!$weekdays)throw new RuntimeException('Select at least one operating weekday.');
  $pdo->beginTransaction();$created=0;
  if(isset($_POST['generate_pickup'])){
   $times=preg_split('/[\s,]+/',trim((string)($_POST['times']??'')),-1,PREG_SPLIT_NO_EMPTY);if(!$times)throw new RuntimeException('Enter at least one pickup time.');
   foreach($times as $time)if(!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$time))throw new RuntimeException('Pickup times must use HH:MM format.');
   for($date=$first;$date<=$last;$date=$date->modify('+1 day')){if(!in_array((int)$date->format('N'),$weekdays,true))continue;foreach($times as $time){$q=$pdo->prepare('SELECT id,reserved FROM pickup_capacities WHERE pickup_date=? AND pickup_time=? FOR UPDATE');$q->execute([$date->format('Y-m-d'),$time]);$row=$q->fetch();if($row&&(int)$row['reserved']>$capacity)throw new RuntimeException('Capacity cannot be lower than existing reservations for '.$date->format('Y-m-d').' '.$time.'.');$pdo->prepare('INSERT INTO pickup_capacities(pickup_date,pickup_time,capacity,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE capacity=VALUES(capacity),active=1')->execute([$date->format('Y-m-d'),$time,$capacity]);$created++;}}
  }else{
   $sizes=array_values(array_intersect(['Small','Medium','Large'],(array)($_POST['sizes']??[])));if(!$sizes)throw new RuntimeException('Select at least one cake size.');
   for($date=$first;$date<=$last;$date=$date->modify('+1 day')){if(!in_array((int)$date->format('N'),$weekdays,true))continue;foreach($sizes as $size){$q=$pdo->prepare('SELECT id,reserved FROM ai_capacities WHERE pickup_date=? AND size=? FOR UPDATE');$q->execute([$date->format('Y-m-d'),$size]);$row=$q->fetch();if($row&&(int)$row['reserved']>$capacity)throw new RuntimeException('Capacity cannot be lower than existing '.$size.' reservations on '.$date->format('Y-m-d').'.');$pdo->prepare('INSERT INTO ai_capacities(pickup_date,size,capacity,active) VALUES(?,?,?,1) ON DUPLICATE KEY UPDATE capacity=VALUES(capacity),active=1')->execute([$date->format('Y-m-d'),$size,$capacity]);$created++;}}
  }
  $pdo->commit();$message=$created.' capacity records saved.';
 }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$message=$e->getMessage();}
}
$pickup=$pdo->query('SELECT * FROM pickup_capacities WHERE pickup_date>=CURDATE() ORDER BY pickup_date,pickup_time LIMIT 200')->fetchAll();$ai=$pdo->query('SELECT * FROM ai_capacities WHERE pickup_date>=CURDATE() ORDER BY pickup_date,size LIMIT 200')->fetchAll();
page_start('Capacity settings');
?>
<main class="container py-4"><div class="d-flex justify-content-between align-items-start"><div><h1 class="h3">Pickup and production capacity</h1><p class="help-text">Only owner-approved dates are configured. Unconfigured dates remain unavailable.</p></div><a href="readiness.php" class="btn btn-outline-primary">Defense readiness</a></div>
<?php if($message):?><div class="alert alert-info"><?=e($message)?></div><?php endif;?>
<div class="row g-4"><div class="col-lg-6"><section class="app-card p-4"><h2 class="h5">Generate pickup slots</h2><form method="post"><?=csrf_field()?><?php include __DIR__.'/capacity_range_fields.php';?><label class="form-label mt-3">Times (HH:MM, separated by spaces)</label><input name="times" class="form-control" placeholder="09:00 11:00 14:00" required><button name="generate_pickup" class="btn btn-purple mt-3">Save pickup slots</button></form><div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Date</th><th>Time</th><th>Reserved</th><th>Capacity</th></tr></thead><tbody><?php foreach($pickup as $x):?><tr><td><?=e($x['pickup_date'])?></td><td><?=e(substr($x['pickup_time'],0,5))?></td><td><?=(int)$x['reserved']?></td><td><?=(int)$x['capacity']?></td></tr><?php endforeach;?></tbody></table></div></section></div>
<div class="col-lg-6"><section class="app-card p-4"><h2 class="h5">Generate AI capacity</h2><form method="post"><?=csrf_field()?><?php include __DIR__.'/capacity_range_fields.php';?><fieldset class="mt-3"><legend class="form-label">Sizes</legend><?php foreach(['Small','Medium','Large'] as $size):?><label class="me-3"><input type="checkbox" name="sizes[]" value="<?=$size?>"> <?=$size?></label><?php endforeach;?></fieldset><button name="generate_ai" class="btn btn-purple mt-3">Save AI capacity</button></form><div class="table-responsive mt-3"><table class="table table-sm"><thead><tr><th>Date</th><th>Size</th><th>Reserved</th><th>Capacity</th></tr></thead><tbody><?php foreach($ai as $x):?><tr><td><?=e($x['pickup_date'])?></td><td><?=e($x['size'])?></td><td><?=(int)$x['reserved']?></td><td><?=(int)$x['capacity']?></td></tr><?php endforeach;?></tbody></table></div></section></div></div></main><?php page_end();
