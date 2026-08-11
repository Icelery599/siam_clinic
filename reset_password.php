<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) redirect_to_dashboard();

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$errors = [];
$valid = false;
$user = null;

if ($token) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    $valid = (bool) $user;
}

if (!$valid) {
    $errors[] = 'This password reset link is invalid or has expired. Please request a new one.';
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?');
        $stmt->execute([$hash, $user['id']]);
        audit_log($user['id'], 'password_reset', 'Password reset via email link');

        set_flash('success', 'Your password has been reset. Please log in.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

$pageTitle = 'Reset Password';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h3 class="card-title mb-3 text-center">Reset Password</h3>

        <?php if ($errors): ?>
          <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if ($valid): ?>
        <form method="post" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="token" value="<?= e($token) ?>">
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="password" class="form-control" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
          </div>
          <button type="submit" class="btn btn-clinic w-100">Reset Password</button>
        </form>
        <?php else: ?>
          <p class="text-center"><a href="<?= BASE_URL ?>/forgot_password.php">Request a new link</a></p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
