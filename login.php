<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) redirect_to_dashboard();

$errors = [];
$email = '';
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($_SESSION['login_attempts'] >= 6) {
        $errors[] = 'Too many failed attempts. Please wait a moment and try again.';
    } elseif ($email === '' || $password === '') {
        $errors[] = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $errors[] = 'Invalid email or password.';
            $_SESSION['login_attempts']++;
        } elseif ($user['status'] !== 'active') {
            $errors[] = 'Your account is inactive. Please contact the clinic administrator.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id']    = $user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];
            $_SESSION['login_attempts'] = 0;

            audit_log($user['id'], 'login', 'User logged in');
            set_flash('success', 'Welcome back, ' . $user['name'] . '!');
            redirect_to_dashboard();
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h3 class="card-title mb-3 text-center">Login</h3>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($email) ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="d-flex justify-content-between mb-3">
            <a href="<?= BASE_URL ?>/forgot_password.php" class="small">Forgot password?</a>
          </div>
          <button type="submit" class="btn btn-clinic w-100">Login</button>
        </form>

        <p class="text-center mt-3 mb-0">
          New patient? <a href="<?= BASE_URL ?>/register.php">Create an account</a>
        </p>
      </div>
    </div>
  </div>
</div>


