<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

// -------- Toggle status --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'toggle_status') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ? AND role = 'patient'");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $new = $row['status'] === 'active' ? 'inactive' : 'active';
        $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$new, $id]);
        audit_log($_SESSION['user_id'], 'user_status_toggle', "id={$id} -> {$new}");
        set_flash('success', 'User status updated.');
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

// -------- Delete --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_user') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('
        SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id
        WHERE p.user_id = ? AND a.status IN ("Pending","Approved")
    ');
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        set_flash('danger', 'Cannot delete: this user has pending or approved appointments.');
    } else {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'patient'")->execute([$id]);
        audit_log($_SESSION['user_id'], 'user_delete', "id={$id}");
        set_flash('success', 'User account deleted.');
    }
    header('Location: ' . BASE_URL . '/admin/users.php');
    exit;
}

$q = trim($_GET['q'] ?? '');
if ($q !== '') {
    $stmt = $pdo->prepare("
        SELECT u.*, p.id AS patient_id, p.phone,
               (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS appt_count
        FROM users u JOIN patients p ON p.user_id = u.id
        WHERE u.role = 'patient' AND (u.name LIKE ? OR u.email LIKE ? OR p.phone LIKE ?)
        ORDER BY u.created_at DESC
    ");
    $like = "%{$q}%";
    $stmt->execute([$like, $like, $like]);
} else {
    $stmt = $pdo->query("
        SELECT u.*, p.id AS patient_id, p.phone,
               (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id) AS appt_count
        FROM users u JOIN patients p ON p.user_id = u.id
        WHERE u.role = 'patient'
        ORDER BY u.created_at DESC
    ");
}
$users = $stmt->fetchAll();

$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="mb-0">Manage Users <span class="badge bg-secondary"><?= count($users) ?></span></h3>
</div>

<form method="get" class="mb-3">
  <div class="input-group" style="max-width:400px;">
    <input type="text" name="q" class="form-control" placeholder="Search by name, email, or phone" value="<?= e($q) ?>">
    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    <?php if ($q !== ''): ?><a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-secondary">Clear</a><?php endif; ?>
  </div>
</form>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead>
    <tr><th>Name</th><th>Email</th><th>Phone</th><th>Appointments</th><th>Status</th><th>Joined</th><th style="width:160px;">Actions</th></tr>
  </thead>
  <tbody>
    <?php if (!$users): ?>
      <tr><td colspan="7" class="text-center text-muted">No users found.</td></tr>
    <?php endif; ?>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><?= e($u['name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><?= e($u['phone']) ?></td>
        <td><span class="badge bg-secondary"><?= (int)$u['appt_count'] ?></span></td>
        <td>
          <span class="badge <?= $u['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>">
            <?= e(ucfirst($u['status'])) ?>
          </span>
        </td>
        <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
        <td>
          <form method="post" class="d-inline" onsubmit="return confirm('<?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?> this user?');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="toggle_status">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
              <?= $u['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
            </button>
          </form>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this user account? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="delete_user">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
