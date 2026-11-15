<?php
require_once __DIR__ . '/auth.php';
$me = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$navBase = $me && $me['role'] === 'admin' ? 'admin' : 'student';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> · <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= e(url('assets/css/app.css')) ?>" rel="stylesheet">
  <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
  <meta name="base-url" content="<?= e(url()) ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-nav">
 <div class="container"><a class="navbar-brand fw-bold" href="<?= e(url($me ? "$navBase/dashboard.php" : 'index.php')) ?>"><i class="bi bi-grid-1x2-fill me-2"></i>EventGrid <span>3.0</span></a>
 <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
 <div class="collapse navbar-collapse" id="nav"><div class="navbar-nav ms-auto align-items-lg-center">
 <?php if ($me && $me['role']==='student'): ?>
 <a class="nav-link" href="<?= e(url('student/events.php')) ?>">Events</a><a class="nav-link" href="<?= e(url('student/my_registrations.php')) ?>">My registrations</a>
 <a class="nav-link" href="<?= e(url('student/notifications.php')) ?>"><i class="bi bi-bell"></i> <span class="badge text-bg-warning" id="nav-unread"><?= unread_count((int)$me['id']) ?></span></a>
 <?php endif; ?>
 <?php if ($me && $me['role']==='student'): ?><a class="nav-link" href="<?= e(url('student/profile.php')) ?>">Profile</a><?php endif; ?>
 <?php if ($me): ?><a class="btn btn-sm btn-outline-light ms-lg-2" href="<?= e(url('logout.php')) ?>">Sign out</a>
 <?php else: ?><a class="nav-link" href="<?= e(url('login.php')) ?>">Sign in</a><a class="btn btn-sm btn-light ms-lg-2" href="<?= e(url('signup.php')) ?>">Create account</a><?php endif; ?>
 </div></div></div>
</nav>
<?php if ($me && $me['role']==='admin'): ?>
<div class="container-fluid"><div class="row">
<aside class="col-md-3 col-lg-2 admin-sidebar py-4 px-3">
 <div class="small text-uppercase fw-bold text-secondary mb-3">Admin workspace</div>
 <nav class="nav nav-pills flex-column gap-1">
  <a class="nav-link" href="<?= e(url('admin/dashboard.php')) ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
  <a class="nav-link" href="<?= e(url('admin/events.php')) ?>"><i class="bi bi-calendar-event me-2"></i>Events</a>
  <a class="nav-link" href="<?= e(url('admin/integrity_monitor.php')) ?>"><i class="bi bi-shield-check me-2"></i>Integrity monitor</a>
  <a class="nav-link" href="<?= e(url('admin/integrity_failures.php')) ?>"><i class="bi bi-exclamation-triangle me-2"></i>Integrity failures</a>
 </nav>
</aside>
<main class="col-md-9 col-lg-10 py-4 px-md-4">
<?php else: ?>
<main class="container py-4">
<?php endif; ?>
<?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show"><?= e($f['message']) ?><button class="btn-close" data-bs-dismiss="alert"></button></div><?php endforeach; ?>
