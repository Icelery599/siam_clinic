<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$totalUsers   = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'patient'")->fetchColumn();
$totalDoctors = $pdo->query("SELECT COUNT(*) FROM doctors")->fetchColumn();
$pendingAppts = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Pending'")->fetchColumn();
$approvedAppts = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'Approved'")->fetchColumn();
$todayAppts = $pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_date = CURDATE()")->fetchColumn();
$siamCount = $pdo->query("SELECT COUNT(*) FROM siam_assessments")->fetchColumn();

$recentPending = $pdo->query("
    SELECT a.*, p.id AS pid, u.name AS patient_name, d.name AS doctor_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN users u ON u.id = p.user_id
    JOIN doctors d ON d.id = a.doctor_id
    WHERE a.status = 'Pending'
    ORDER BY a.created_at DESC LIMIT 5
")->fetchAll();

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Admin Dashboard</h3>

<div class="row g-3 mb-4">
  <div class="col-md-4 col-lg-2">
    <div class="card summary-card green">
      <div class="display-6"><?= (int)$totalUsers ?></div>
      <div>Registered Users</div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card summary-card orange">
      <div class="display-6"><?= (int)$totalDoctors ?></div>
      <div>Doctors Available</div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card summary-card black">
      <div class="display-6"><?= (int)$pendingAppts ?></div>
      <div>Pending Approvals</div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card summary-card green">
      <div class="display-6"><?= (int)$approvedAppts ?></div>
      <div>Approved Appointments</div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card summary-card orange">
      <div class="display-6"><?= (int)$todayAppts ?></div>
      <div>Today's Appointments</div>
    </div>
  </div>
  <div class="col-md-4 col-lg-2">
    <div class="card summary-card white">
      <div class="display-6"><?= (int)$siamCount ?></div>
      <div>SIAM Intakes Logged</div>
    </div>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
  <a href="<?= BASE_URL ?>/admin/users.php" class="btn btn-outline-primary"><i class="bi bi-people"></i> Manage Users</a>
  <a href="<?= BASE_URL ?>/admin/doctors.php" class="btn btn-outline-primary"><i class="bi bi-person-badge"></i> Manage Doctors</a>
  <a href="<?= BASE_URL ?>/admin/departments.php" class="btn btn-outline-primary"><i class="bi bi-diagram-3"></i> Manage Departments</a>
  <a href="<?= BASE_URL ?>/admin/appointments.php" class="btn btn-accent"><i class="bi bi-calendar-check"></i> Approve Appointments</a>
  <a href="<?= BASE_URL ?>/admin/siam_log.php" class="btn btn-outline-primary"><i class="bi bi-stars"></i> SIAM Log</a>
</div>

<h5>Awaiting Your Approval</h5>
<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead><tr><th>Patient</th><th>Doctor</th><th>Date</th><th>Time</th><th>Reason</th><th></th></tr></thead>
  <tbody>
    <?php if (!$recentPending): ?>
      <tr><td colspan="6" class="text-center text-muted">Nothing pending right now.</td></tr>
    <?php endif; ?>
    <?php foreach ($recentPending as $a): ?>
      <tr>
        <td><?= e($a['patient_name']) ?></td>
        <td>Dr. <?= e($a['doctor_name']) ?></td>
        <td><?= e($a['appointment_date']) ?></td>
        <td><?= e(substr($a['appointment_time'],0,5)) ?></td>
        <td><?= e($a['reason']) ?></td>
        <td><a href="<?= BASE_URL ?>/admin/appointments.php" class="btn btn-sm btn-accent">Review</a></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
