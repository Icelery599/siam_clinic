<?php
require_once __DIR__ . '/../config/config.php';
require_role(['patient']);

$stmt = $pdo->prepare('SELECT p.*, u.name, u.email FROM patients p JOIN users u ON u.id = p.user_id WHERE p.user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'update_profile') {
    csrf_verify();

    $name       = trim($_POST['name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $gender     = $_POST['gender'] ?? '';
    $dob        = $_POST['dob'] ?? '';
    $address    = trim($_POST['address'] ?? '');
    $bloodGroup = trim($_POST['blood_group'] ?? '');

    if ($name === '') $errors[] = 'Name is required.';
    if (!in_array($gender, ['male','female','other',''], true)) $gender = '';

    if (!$errors) {
        $pdo->prepare('UPDATE users SET name = ? WHERE id = ?')->execute([$name, $_SESSION['user_id']]);
        $pdo->prepare('UPDATE patients SET gender = ?, dob = ?, phone = ?, address = ?, blood_group = ? WHERE user_id = ?')
            ->execute([$gender ?: null, $dob ?: null, $phone, $address, $bloodGroup, $_SESSION['user_id']]);

        $_SESSION['user_name'] = $name;
        audit_log($_SESSION['user_id'], 'profile_update', 'Patient updated their own profile');
        set_flash('success', 'Profile updated.');
        header('Location: ' . BASE_URL . '/patient/profile.php');
        exit;
    }
}

$pageTitle = 'My Profile';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">My Profile</h3>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card" style="max-width:600px;">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="update_profile">

      <div class="mb-3">
        <label class="form-label">Full Name</label>
        <input type="text" name="name" class="form-control" value="<?= e($patient['name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" value="<?= e($patient['email']) ?>" disabled>
        <div class="form-text">Contact the clinic to change your email address.</div>
      </div>
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= e($patient['phone']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Gender</label>
          <select name="gender" class="form-select">
            <option value="">— Select —</option>
            <option value="male" <?= $patient['gender'] === 'male' ? 'selected' : '' ?>>Male</option>
            <option value="female" <?= $patient['gender'] === 'female' ? 'selected' : '' ?>>Female</option>
            <option value="other" <?= $patient['gender'] === 'other' ? 'selected' : '' ?>>Other</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Date of Birth</label>
          <input type="date" name="dob" class="form-control" value="<?= e($patient['dob']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Blood Group</label>
          <input type="text" name="blood_group" class="form-control" value="<?= e($patient['blood_group']) ?>" placeholder="e.g. O+">
        </div>
        <div class="col-12">
          <label class="form-label">Address</label>
          <input type="text" name="address" class="form-control" value="<?= e($patient['address']) ?>">
        </div>
      </div>

      <button type="submit" class="btn btn-clinic mt-4">Save Changes</button>
    </form>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
