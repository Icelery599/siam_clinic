<?php
require_once __DIR__ . '/config/config.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password'])) $errors[] = 'Current password is incorrect.';
    if (strlen($new) < 8) $errors[] = 'New password must be at least 8 characters.';
    if ($new !== $confirm) $errors[] = 'New passwords do not match.';

    if (!$errors) {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
        $stmt->execute([$hash, $_SESSION['user_id']]);
        audit_log($_SESSION['user_id'], 'password_change', 'User changed their own password');
        set_flash('success', 'Password updated successfully.');
        redirect_to_dashboard();
    }
}

$pageTitle = 'Change Password';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h3 class="card-title mb-3 text-center">Change Password</h3>

        <?php if ($errors): ?>
          <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
          </div>
          <button type="submit" class="btn btn-clinic w-100">Update Password</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
