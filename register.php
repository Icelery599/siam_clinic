<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) redirect_to_dashboard();

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm_password'] ?? '';

    if ($old['name'] === '') $errors[] = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) $errors[] = 'An account with this email already exists.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([$old['name'], $old['email'], $hash, 'patient']);
            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO patients (user_id, phone) VALUES (?, ?)');
            $stmt->execute([$userId, $old['phone']]);

            $pdo->commit();
            audit_log($userId, 'register', 'New patient account created');

            set_flash('success', 'Account created successfully. Please log in.');
            header('Location: ' . BASE_URL . '/login.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Something went wrong while creating your account. Please try again.';
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h3 class="card-title mb-3 text-center">Create Patient Account</h3>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($old['name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" value="<?= e($old['phone']) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" minlength="8" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" minlength="8" required>
          </div>
          <button type="submit" class="btn btn-clinic w-100">Register</button>
        </form>

        <p class="text-center mt-3 mb-0">
          Already have an account? <a href="<?= BASE_URL ?>/login.php">Login</a>
        </p>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
