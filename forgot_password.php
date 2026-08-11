<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) redirect_to_dashboard();

$errors = [];
$sentLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $stmt = $pdo->prepare('UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?');
            $stmt->execute([$token, $expires, $user['id']]);
            $sentLink = BASE_URL . '/reset_password.php?token=' . $token;
        }
        set_flash('info', 'If that email exists in our system, a password reset link has been generated.');
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h3 class="card-title mb-3 text-center">Forgot Password</h3>

        <?php if ($errors): ?>
          <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
        <?php endif; ?>

        <?php if ($sentLink): ?>
          <div class="alert alert-warning small">
            <strong>Demo mode</strong> — no mail server configured, so here's your reset link
            (in production this would be emailed instead):<br>
            <a href="<?= e($sentLink) ?>"><?= e($sentLink) ?></a>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Account Email</label>
            <input type="email" name="email" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-clinic w-100">Send Reset Link</button>
        </form>

        <p class="text-center mt-3 mb-0"><a href="<?= BASE_URL ?>/login.php">Back to login</a></p>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
