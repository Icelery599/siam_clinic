<?php
require_once __DIR__ . '/../config/config.php';
require_role(['patient']);

$stmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'cancel_appointment') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND patient_id = ? AND status IN ('Pending','Approved')");
    $stmt->execute([$id, $patient['id']]);
    audit_log($_SESSION['user_id'], 'appointment_cancel', "id={$id}");
    set_flash('success', 'Appointment cancelled.');
    header('Location: ' . BASE_URL . '/patient/appointments.php');
    exit;
}

$appointments = [];
if ($patient) {
    $stmt = $pdo->prepare("
        SELECT a.*, d.name AS doctor_name, dep.name AS department_name
        FROM appointments a
        JOIN doctors d ON d.id = a.doctor_id
        LEFT JOIN departments dep ON dep.id = a.department_id
        WHERE a.patient_id = ?
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute([$patient['id']]);
    $appointments = $stmt->fetchAll();
}

$pageTitle = 'My Appointments';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">My Appointments</h3>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead>
    <tr><th>Date</th><th>Time</th><th>Doctor</th><th>Department</th><th>Reason</th><th>Status</th><th style="width:100px;"></th></tr>
  </thead>
  <tbody>
    <?php if (!$appointments): ?>
      <tr><td colspan="7" class="text-center text-muted">You have no appointments yet. <a href="<?= BASE_URL ?>/patient/book_appointment.php">Book one now</a>.</td></tr>
    <?php endif; ?>
    <?php foreach ($appointments as $a): ?>
      <tr>
        <td><?= e($a['appointment_date']) ?></td>
        <td><?= e(substr($a['appointment_time'],0,5)) ?></td>
        <td>Dr. <?= e($a['doctor_name']) ?></td>
        <td><?= e($a['department_name'] ?? '—') ?></td>
        <td class="small"><?= e($a['reason']) ?></td>
        <td><span class="badge badge-status-<?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
        <td>
          <?php if (in_array($a['status'], ['Pending','Approved'], true)): ?>
            <form method="post" onsubmit="return confirm('Cancel this appointment?');">
              <?= csrf_field() ?>
              <input type="hidden" name="form" value="cancel_appointment">
              <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-danger">Cancel</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
