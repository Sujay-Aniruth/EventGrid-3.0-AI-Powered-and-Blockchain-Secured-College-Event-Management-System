<?php
require_once dirname(__DIR__).'/includes/functions.php';require_once dirname(__DIR__).'/includes/blockchain.php';$admin=require_role('admin');
$chain=verify_chain(true);
$registrations=db()->query("SELECT id FROM registrations WHERE status<>'Cancelled' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
$secure=0;$tampered=0;
foreach($registrations as $registrationId){$check=verify_registration((int)$registrationId);if($check['secure'])$secure++;else$tampered++;}
$counts=[
 'events'=>(int)db()->query("SELECT COUNT(*) FROM events WHERE deleted_at IS NULL")->fetchColumn(),
 'upcoming'=>(int)db()->query("SELECT COUNT(*) FROM events WHERE event_date>=CURDATE() AND status='Open' AND deleted_at IS NULL")->fetchColumn(),
 'registrations'=>(int)db()->query("SELECT COUNT(*) FROM registrations WHERE status<>'Cancelled'")->fetchColumn(),
 'verified'=>(int)db()->query("SELECT COUNT(*) FROM registrations WHERE status='Verified'")->fetchColumn(),
];
$chart=db()->query("SELECT DATE_FORMAT(event_date,'%b') label,COUNT(*) total FROM events WHERE event_date>=DATE_SUB(CURDATE(),INTERVAL 5 MONTH) AND event_date<=DATE_ADD(CURDATE(),INTERVAL 6 MONTH) AND deleted_at IS NULL GROUP BY YEAR(event_date),MONTH(event_date),label ORDER BY YEAR(event_date),MONTH(event_date)")->fetchAll();
$pageTitle='Admin dashboard';require dirname(__DIR__).'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4"><div><p class="text-primary text-uppercase small fw-bold mb-1">Control center</p><h1 class="h2 fw-bold mb-0">Admin dashboard</h1></div><a class="btn btn-primary" href="<?=e(url('admin/event_form.php'))?>"><i class="bi bi-plus-lg me-1"></i>Create event</a></div>
<div class="row g-3 mb-4"><?php foreach([['Total events',$counts['events'],'bi-calendar-event'],['Upcoming events',$counts['upcoming'],'bi-calendar2-check'],['Registrations',$counts['registrations'],'bi-people'],['Verified registrations',$counts['verified'],'bi-patch-check'],['Secured registrations',$secure,'bi-shield-check'],['Integrity failures',$tampered,'bi-shield-exclamation']] as [$label,$number,$icon]):?><div class="col-sm-6 col-xl-4"><div class="card p-3"><div class="d-flex justify-content-between align-items-center"><div><div class="small text-secondary"><?=e($label)?></div><div class="metric"><?=$number?></div></div><div class="metric-icon"><i class="bi <?=e($icon)?>"></i></div></div></div></div><?php endforeach;?></div>
<div class="row g-4"><div class="col-lg-8"><div class="card p-4"><h2 class="h5 fw-bold">Event calendar overview</h2><canvas id="eventChart" height="110"></canvas></div></div><div class="col-lg-4"><div class="card p-4 h-100"><h2 class="h5 fw-bold">Blockchain health</h2><div class="display-6 <?=$chain['healthy']?'text-success':'text-danger'?>"><i class="bi <?=$chain['healthy']?'bi-shield-check':'bi-shield-exclamation'?>"></i></div><p class="fw-semibold"><?=$chain['healthy']?'Chain healthy':'Chain break detected'?></p><p class="small text-secondary"><?=$chain['count']?> blocks checked<?php if($chain['breaks']):?> · broken blocks: <?=e(implode(', ',$chain['breaks']))?><?php endif;?></p><a href="<?=e(url('admin/integrity_monitor.php'))?>">Open integrity monitor</a></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script><script>new Chart(document.getElementById('eventChart'),{type:'bar',data:{labels:<?=json_encode(array_column($chart,'label'))?>,datasets:[{label:'Events',data:<?=json_encode(array_map('intval',array_column($chart,'total')) )?>,backgroundColor:'#625bf6',borderRadius:8}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0}}}}});</script>
<?php require dirname(__DIR__).'/includes/footer.php';?>
