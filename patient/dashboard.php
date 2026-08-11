<?php
require_once __DIR__ . '/../config/config.php';
require_role(['patient']);

$stmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

$upcoming = [];
if ($patient) {
    $stmt = $pdo->prepare("
        SELECT a.*, d.name AS doctor_name FROM appointments a
        JOIN doctors d ON d.id = a.doctor_id
        WHERE a.patient_id = ? AND a.appointment_date >= CURDATE() AND a.status != 'Cancelled'
        ORDER BY a.appointment_date ASC, a.appointment_time ASC
        LIMIT 5
    ");
    $stmt->execute([$patient['id']]);
    $upcoming = $stmt->fetchAll();
}

$pageTitle = 'Patient Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Welcome, <?= e($_SESSION['user_name']) ?></h3>

<div class="siam-panel mb-4">
  <div class="d-flex align-items-center gap-3">
    <div class="siam-icon">S</div>
    <div>
      <h5 class="mb-1">Not sure who to see?</h5>
      <p class="mb-0">Tell SIAM what's going on and it'll suggest the right department before you book.</p>
    </div>
    <a href="<?= BASE_URL ?>/patient/siam.php" class="btn btn-light ms-auto">Ask SIAM</a>
  </div>
</div>

<div class="d-flex gap-2 mb-4">
  <a href="<?= BASE_URL ?>/patient/book_appointment.php" class="btn btn-clinic"><i class="bi bi-calendar-plus"></i> Book Appointment</a>
  <a href="<?= BASE_URL ?>/patient/appointments.php" class="btn btn-outline-primary"><i class="bi bi-calendar3"></i> My Appointments</a>
</div>

<h5>Upcoming Appointments</h5>
<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead><tr><th>Date</th><th>Time</th><th>Doctor</th><th>Status</th></tr></thead>
  <tbody>
    <?php if (!$upcoming): ?>
      <tr><td colspan="4" class="text-center text-muted">No upcoming appointments.</td></tr>
    <?php endif; ?>
    <?php foreach ($upcoming as $a): ?>
      <tr>
        <td><?= e($a['appointment_date']) ?></td>
        <td><?= e(substr($a['appointment_time'],0,5)) ?></td>
        <td>Dr. <?= e($a['doctor_name']) ?></td>
        <td><span class="badge badge-status-<?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
