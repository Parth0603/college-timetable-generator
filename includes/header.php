<?php
$pageTitle = $pageTitle ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | <?= e(COLLEGE_NAME) ?></title>
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-body">
<div class="app-shell d-flex">
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="app-main flex-grow-1 min-vw-0">
<header class="topbar sticky-top d-flex align-items-center justify-content-between">
    <div>
        <div class="fs-5 fw-semibold"><?= e($pageTitle) ?></div>
        <div class="text-muted small">College timetable management and automatic scheduling</div>
    </div>
    <div class="d-flex align-items-center gap-3">
        <span class="d-none d-md-inline text-muted small"><strong><?= e((current_admin()['name'] ?? 'Administrator')) ?></strong></span>
        <a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a>
    </div>
</header>
<main class="content-grid px-3 px-md-4 py-4">
<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>
