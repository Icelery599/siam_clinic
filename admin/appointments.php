<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

// -------- Approve / Reject / Complete / Cancel --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'update_status') {
    csrf_verify();
    $id     = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (in_array($status, ['Approved','Rejected','Completed','Cancelled'], true)) {
        $stmt = $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        audit_log($_SESSION['user_id'], 'appointment_status', "id={$id} -> {$status}");
        set_flash('success', "Appointment marked as {$status}.");
    }
    header('Location: ' . BASE_URL . '/admin/appointments.php' . (!empty($_POST['redirect_qs']) ? '?' . $_POST['redirect_qs'] : ''));
    exit;
}

// -------- Filters --------
$status = $_GET['status'] ?? 'Pending';
$validStatuses = ['All','Pending','Approved','Completed','Cancelled','Rejected'];
if (!in_array($status, $validStatuses, true)) $status = 'Pending';

$sql = "
    SELECT a.*, u.name AS patient_name, d.name AS doctor_name, dep.name AS department_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN users u ON u.id = p.user_id
    JOIN doctors d ON d.id = a.doctor_id
    LEFT JOIN departments dep ON dep.id = a.department_id
";
$params = [];
if ($status !== 'All') {
    $sql .= " WHERE a.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$pageTitle = 'Manage Appointments';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Manage Appointments</h3>

<ul class="nav nav-pills mb-3">
  <?php foreach ($validStatuses as $s): ?>
    <li class="nav-item">
      <a class="nav-link <?= $s === $status ? 'active' : '' ?>"
         style="<?= $s === $status ? 'background-color: var(--clinic-green);' : 'color: var(--clinic-green);' ?>"
         href="?status=<?= urlencode($s) ?>"><?= e($s) ?></a>
    </li>
  <?php endforeach; ?>
</ul>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead>
    <tr>
      <th>Patient</th><th>Doctor</th><th>Department</th><th>Date</th><th>Time</th>
      <th>Reason / SIAM Notes</th><th>Status</th><th style="width:220px;">Actions</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!$appointments): ?>
      <tr><td colspan="8" class="text-center text-muted">No appointments in this view.</td></tr>
    <?php endif; ?>
    <?php foreach ($appointments as $a): ?>
      <tr>
        <td><?= e($a['patient_name']) ?></td>
        <td>Dr. <?= e($a['doctor_name']) ?></td>
        <td><?= e($a['department_name'] ?? '—') ?></td>
        <td><?= e($a['appointment_date']) ?></td>
        <td><?= e(substr($a['appointment_time'],0,5)) ?></td>
        <td class="small">
          <?= e($a['reason']) ?>
          <?php if (!empty($a['siam_suggested'])): ?>
            <div class="text-muted"><i class="bi bi-stars"></i> Booked via SIAM suggestion</div>
          <?php endif; ?>
        </td>
        <td><span class="badge badge-status-<?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
        <td>
          <?php if ($a['status'] === 'Pending'): ?>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="form" value="update_status">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="status" value="Approved">
              <input type="hidden" name="redirect_qs" value="status=<?= urlencode($status) ?>">
              <button type="submit" class="btn btn-sm btn-clinic">Approve</button>
            </form>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="form" value="update_status">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="status" value="Rejected">
              <input type="hidden" name="redirect_qs" value="status=<?= urlencode($status) ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
            </form>
          <?php elseif ($a['status'] === 'Approved'): ?>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="form" value="update_status">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="status" value="Completed">
              <input type="hidden" name="redirect_qs" value="status=<?= urlencode($status) ?>">
              <button type="submit" class="btn btn-sm btn-accent">Mark Completed</button>
            </form>
            <form method="post" class="d-inline">
              <?= csrf_field() ?>
              <input type="hidden" name="form" value="update_status">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <input type="hidden" name="status" value="Cancelled">
              <input type="hidden" name="redirect_qs" value="status=<?= urlencode($status) ?>">
              <button type="submit" class="btn btn-sm btn-outline-secondary">Cancel</button>
            </form>
          <?php else: ?>
            <span class="text-muted small">No further actions</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
