<?php
declare(strict_types=1);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
start_secure_session();

if (!empty($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = post_string('username', 60);
    $password = (string)($_POST['password'] ?? '');

    $statement = db()->prepare('SELECT * FROM admins WHERE username = ? OR email = ? LIMIT 1');
    $statement->execute([$username, $username]);
    $admin = $statement->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin'] = [
            'id' => (int)$admin['id'],
            'name' => $admin['name'],
            'username' => $admin['username'],
            'email' => $admin['email'],
        ];
        $_SESSION['last_activity'] = time();
        header('Location: dashboard.php');
        exit;
    }

    $error = 'Invalid username or password.';
}

$pageTitle = 'Administrator Login';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= url('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body class="login-page">
<div class="container">
    <div class="row justify-content-center align-items-center min-vh-100">
        <div class="col-md-5 col-xl-4">
            <div class="card login-card shadow-sm">
                <div class="card-body p-4">
                    <div class="text-center mb-4">
                        <div class="brand-mark mx-auto mb-3">TT</div>
                        <h1 class="h4 mb-1">College Timetable Generator</h1>
                        <p class="text-muted small">Administrator access only</p>
                    </div>
                    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="username">Username or email</label>
                            <input class="form-control" id="username" name="username" value="<?= e($username) ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <input class="form-control" type="password" id="password" name="password" required>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">Sign in</button>
                    </form>
                    <p class="text-center text-muted small mt-3 mb-0">Default demo login: admin / admin123</p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
