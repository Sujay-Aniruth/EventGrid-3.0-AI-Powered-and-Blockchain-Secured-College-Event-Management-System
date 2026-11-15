<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) redirect(current_user()['role'] . '/dashboard.php');
$events = db()->query("SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id AND r.status<>'Cancelled') AS registered FROM events e WHERE e.status='Open' AND e.deleted_at IS NULL AND e.event_date>=CURDATE() ORDER BY e.event_date LIMIT 3")->fetchAll();
$pageTitle = 'Discover campus events';
require __DIR__ . '/includes/header.php';
?>
<section class="hero mb-5">
 <div class="row align-items-center"><div class="col-lg-7"><span class="badge rounded-pill text-bg-light text-primary mb-3">Campus life, in one place</span><h1 class="display-4 fw-bold">Make room for your next big moment.</h1><p class="lead opacity-75">Discover events, reserve your place, and keep every registration verifiable with EventGrid 3.0.</p><a class="btn btn-light btn-lg me-2" href="<?= e(url('signup.php')) ?>">Join EventGrid</a><a class="btn btn-outline-light btn-lg" href="<?= e(url('login.php')) ?>">Sign in</a></div><div class="col-lg-5 d-none d-lg-block text-center"><i class="bi bi-calendar2-heart" style="font-size:12rem;color:#a9f5e4"></i></div></div>
</section>
<div class="d-flex justify-content-between align-items-end mb-3"><div><p class="text-uppercase small fw-bold text-primary mb-1">Coming up</p><h2 class="fw-bold mb-0">Events worth showing up for</h2></div><a href="<?= e(url('login.php')) ?>">Explore all events <i class="bi bi-arrow-right"></i></a></div>
<div class="row g-4"><?php foreach($events as $event): ?><div class="col-md-4"><article class="card card-hover h-100"><?php if($event['poster']): ?><img class="card-img-top poster" src="<?= e(url('assets/uploads/posters/'.basename($event['poster']))) ?>" alt=""><?php else: ?><div class="poster d-grid place-items-center text-primary fs-1 text-center pt-5"><i class="bi bi-stars"></i></div><?php endif; ?><div class="card-body"><span class="badge text-bg-light"><?= e($event['category']) ?></span><h3 class="h5 mt-2"><?= e($event['title']) ?></h3><p class="text-secondary small"><i class="bi bi-calendar3"></i> <?= e(date('M j, Y',strtotime($event['event_date']))) ?> · <?= e($event['venue']) ?></p><p class="mb-0"><?= e(mb_strimwidth($event['description'],0,125,'…')) ?></p></div></article></div><?php endforeach; ?><?php if(!$events): ?><div class="col"><div class="empty-state card">New events are being planned. Check back soon.</div></div><?php endif; ?></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
