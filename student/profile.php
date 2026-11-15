<?php
require_once dirname(__DIR__).'/includes/functions.php';
$user=require_role('student');$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $name=trim((string)($_POST['name']??''));$reg=trim((string)($_POST['reg_no']??''));$dob=(string)($_POST['dob']??'');$department=trim((string)($_POST['department']??''));$phone=trim((string)($_POST['phone']??''));$email=strtolower(trim((string)($_POST['email']??'')));
 $pass=(string)($_POST['password']??'');
 if(!$name||!$reg||!$department||!valid_iso_date($dob)||$dob>date('Y-m-d')||!preg_match('/^\d{10}$/',$phone)||!filter_var($email,FILTER_VALIDATE_EMAIL)||($pass&&!valid_password($pass)))$error='Check your details. Use a valid date of birth, 10-digit phone and a strong new password.';
 else{
  $s=db()->prepare('SELECT id FROM users WHERE (email=? OR reg_no=?) AND id<>?');$s->execute([$email,$reg,$user['id']]);
  if($s->fetch())$error='Email or registration ID is already used by another account.';
  else{
   $sql='UPDATE users SET name=?,reg_no=?,dob=?,department=?,phone=?,email=?'.($pass?',password_hash=?':'').' WHERE id=?';
   $args=[$name,$reg,$dob,$department,$phone,$email];if($pass)$args[]=password_hash($pass,PASSWORD_BCRYPT);$args[]=$user['id'];
   db()->prepare($sql)->execute($args);
   $_SESSION['user']['name']=$name;$_SESSION['user']['email']=$email;
   flash('success','Your profile was updated.');redirect('student/profile.php');
  }
 }
}
$s=db()->prepare('SELECT * FROM users WHERE id=?');$s->execute([$user['id']]);$profile=$s->fetch();$pageTitle='My profile';require dirname(__DIR__).'/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8"><div class="card p-4 p-lg-5"><h1 class="h3 fw-bold">My profile</h1><p class="text-secondary">Keep your contact and academic information current.</p><?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<form method="post"><?=csrf_field()?><div class="row g-3"><?php foreach(['name'=>'Full name','reg_no'=>'Registration ID','dob'=>'Date of birth','department'=>'Department','phone'=>'Phone','email'=>'Email'] as $key=>$label):?><div class="col-md-6"><label class="form-label"><?=e($label)?></label><input class="form-control" name="<?=e($key)?>" value="<?=e((string)$profile[$key])?>" <?=$key==='dob'?'type="date"':($key==='email'?'type="email"':($key==='phone'?'inputmode="numeric" pattern="[0-9]{10}"':''))?> required></div><?php endforeach;?></div><hr><label class="form-label">New password <span class="text-secondary">(leave blank to keep current)</span></label><input class="form-control" type="password" name="password" minlength="8"><button class="btn btn-primary mt-4">Save profile</button></form></div></div></div>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>
