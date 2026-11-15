<?php
require_once __DIR__.'/includes/functions.php';
if(current_user()) redirect(current_user()['role'].'/dashboard.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $name=trim((string)($_POST['name']??''));$reg=trim((string)($_POST['reg_no']??''));$dob=(string)($_POST['dob']??'');$dept=trim((string)($_POST['department']??''));$phone=trim((string)($_POST['phone']??''));$email=strtolower(trim((string)($_POST['email']??'')));$pass=(string)($_POST['password']??'');
 if(!$name||!$reg||!$dept||!valid_iso_date($dob)||$dob>date('Y-m-d')||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^\d{10}$/',$phone)||!valid_password($pass)||$pass!==($_POST['confirm_password']??'')) $error='Check all fields. Use a valid date of birth, 10-digit phone, and matching strong passwords.';
 else {
  $s=db()->prepare('SELECT id FROM users WHERE email=? OR reg_no=?');$s->execute([$email,$reg]);
  if($s->fetch())$error='That email or registration ID is already in use.';
  else {
   $s=db()->prepare('INSERT INTO users (name,reg_no,dob,department,phone,email,password_hash,role) VALUES (?,?,?,?,?,?,?,?)');
   $s->execute([$name,$reg,$dob,$dept,$phone,$email,password_hash($pass,PASSWORD_BCRYPT),'student']);
   flash('success','Account created. Sign in to explore and register for events.');redirect('login.php');
  }
 }
}
$departments=['Computer Science','Information Technology','Electronics','Mechanical','Civil','Business','Arts & Humanities','Other'];
$pageTitle='Create account';require __DIR__.'/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8"><div class="card p-4 p-lg-5"><h1 class="h3 fw-bold">Create your student account</h1><p class="text-secondary">A few details and you’re ready to explore.</p><?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" data-validate><?= csrf_field() ?><div class="row g-3">
<div class="col-md-6"><label class="form-label">Full name</label><input class="form-control" name="name" required maxlength="120"></div><div class="col-md-6"><label class="form-label">Registration ID</label><input class="form-control" name="reg_no" required maxlength="40"></div>
<div class="col-md-6"><label class="form-label">Date of birth</label><input class="form-control" type="date" name="dob" required max="<?= e(date('Y-m-d')) ?>"></div><div class="col-md-6"><label class="form-label">Department</label><select class="form-select" name="department" required><option value="">Choose…</option><?php foreach($departments as $d): ?><option><?= e($d) ?></option><?php endforeach; ?></select></div>
<div class="col-md-6"><label class="form-label">Phone number</label><input class="form-control" name="phone" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" required></div><div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required></div>
<div class="col-md-6"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password"><div class="form-text">8+ characters including uppercase, lowercase, number and symbol.</div></div><div class="col-md-6"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="confirm_password" required minlength="8"></div>
</div><button class="btn btn-primary btn-lg mt-4">Create account</button></form></div></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
