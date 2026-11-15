<?php
require_once dirname(__DIR__).'/includes/functions.php';
require_once dirname(__DIR__).'/includes/blockchain.php';
$user=require_role('student');$eventId=(int)($_GET['event']??$_POST['event_id']??0);$s=db()->prepare("SELECT * FROM events WHERE id=? AND deleted_at IS NULL");$s->execute([$eventId]);$event=$s->fetch();
if(!$event){http_response_code(404);exit('Event not found.');}
$err='';$done=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 try{
 $id=create_registration((int)$user['id'],$eventId,$_POST);
 if(!empty($_POST['update_profile'])){
  $_SESSION['user']['name']=trim($_POST['name']);
  $_SESSION['user']['email']=strtolower(trim($_POST['email']));
 }
  flash('success','Registration confirmed. Your entry is protected by the local blockchain ledger.');redirect('student/my_registrations.php');
 }catch(Throwable $e){$err=$e->getMessage();}
}
$p=db()->prepare('SELECT * FROM users WHERE id=?');$p->execute([$user['id']]);$profile=$p->fetch();
$existing=db()->prepare("SELECT id FROM registrations WHERE user_id=? AND event_id=? AND status<>'Cancelled'");$existing->execute([$user['id'],$eventId]);
$can=$event['status']==='Open'&&strtotime($event['reg_deadline'])>=time()&&event_seats_left($eventId,(int)$event['max_participants'])>0&&!$existing->fetch();
$pageTitle='Register for '.$event['title'];require dirname(__DIR__).'/includes/header.php';
?>
<div class="row g-4"><div class="col-lg-5"><div class="card p-4"><span class="badge text-bg-light align-self-start"><?=e($event['category'])?></span><h1 class="h3 fw-bold mt-2"><?=e($event['title'])?></h1><p><?=e($event['description'])?></p><p class="mb-1"><i class="bi bi-calendar3 me-2"></i><?=e(date('l, M j, Y',strtotime($event['event_date'])))?>, <?=e(date('g:i A',strtotime($event['start_time'])))?></p><p><i class="bi bi-geo-alt me-2"></i><?=e($event['venue'])?></p><div class="alert alert-light mb-0"><?=event_seats_left($eventId,(int)$event['max_participants'])?> of <?=e((string)$event['max_participants'])?> seats remain. Deadline: <?=e(date('M j, g:i A',strtotime($event['reg_deadline'])))?>.</div></div></div>
<div class="col-lg-7"><div class="card p-4 p-lg-5"><h2 class="h4 fw-bold">Your registration details</h2><p class="text-secondary">Your profile details are pre-filled. They can be adjusted for this event.</p><?php if($err):?><div class="alert alert-danger"><?=e($err)?></div><?php endif;?><?php if(!$can):?><div class="alert alert-warning">Registration is unavailable: this event is closed, past deadline, full, or you have already registered.</div><?php else:?>
<form method="post" data-validate><?=csrf_field()?><input type="hidden" name="event_id" value="<?=$eventId?>"><div class="row g-3">
<?php foreach(['name'=>'Full name','reg_no'=>'Registration ID','dob'=>'Date of birth','department'=>'Department','phone'=>'Phone','email'=>'Email'] as $key=>$label):?><div class="col-md-6"><label class="form-label"><?=e($label)?></label><input class="form-control" name="<?=e($key)?>" value="<?=e((string)$profile[$key])?>" required <?=$key==='dob'?'type="date"':($key==='email'?'type="email"':($key==='phone'?'pattern="[0-9]{10}" maxlength="10"':''))?>></div><?php endforeach;?>
<div class="col-md-4"><label class="form-label">Study year</label><select class="form-select" name="study_year"><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option></select></div><div class="col-md-4"><label class="form-label">Section</label><input class="form-control" name="section" maxlength="30"></div><div class="col-md-4"><label class="form-label">Team name</label><input class="form-control" name="team_name" maxlength="120"></div>
<div class="col-md-4"><label class="form-label">T-shirt size</label><select class="form-select" name="tshirt_size"><option value="">Not applicable</option><?php foreach(['XS','S','M','L','XL','XXL'] as $size):?><option><?=e($size)?></option><?php endforeach;?></select></div><div class="col-md-8"><label class="form-label">Dietary preference</label><input class="form-control" name="diet" maxlength="100"></div><div class="col-md-6"><label class="form-label">Emergency contact</label><input class="form-control" name="emergency_contact" maxlength="30"></div><div class="col-md-6"><label class="form-label">Special requirements</label><input class="form-control" name="special_requirements"></div>
<div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="update_profile" id="update_profile"><label class="form-check-label" for="update_profile">Also update my profile with the details above</label></div>
<div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="consent" value="1" required id="consent"><label class="form-check-label" for="consent">I confirm these details and consent to their use for event administration.</label></div></div><button class="btn btn-primary btn-lg mt-4">Confirm registration</button></form><?php endif;?></div></div></div>
<?php require dirname(__DIR__).'/includes/footer.php';?>
