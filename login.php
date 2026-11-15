<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect(current_user()['role'].'/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $s = db()->prepare('SELECT id,name,email,password_hash,role FROM users WHERE email=?'); $s->execute([$email]); $user = $s->fetch();
    if ($user && password_verify((string)($_POST['password'] ?? ''), $user['password_hash'])) {
        session_regenerate_id(true); unset($user['password_hash']); $_SESSION['user']=$user;
        redirect($user['role'].'/dashboard.php');
    }
    $error='Email or password is incorrect.';
}
$pageTitle='Sign in'; require __DIR__.'/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="card p-4 p-lg-5"><div class="text-center mb-4"><div class="metric-icon mx-auto mb-3"><i class="bi bi-person-check"></i></div><h1 class="h3 fw-bold">Welcome back</h1><p class="text-secondary">Sign in to your EventGrid account</p></div>
<?php if($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" data-validate><?= csrf_field() ?><label class="form-label">Email</label><input class="form-control mb-3" type="email" name="email" autocomplete="email" required><label class="form-label">Password</label><input class="form-control mb-4" type="password" name="password" autocomplete="current-password" required><button class="btn btn-primary w-100 btn-lg">Sign in</button></form>
<p class="text-center mt-4 mb-0">New here? <a href="<?= e(url('signup.php')) ?>">Create a student account</a></p><p class="small text-secondary text-center mt-3">Admin: Admin@eventgrid.in · Password: Admin@1234</p></div></div></div>
<?php require __DIR__.'/includes/footer.php'; ?>
