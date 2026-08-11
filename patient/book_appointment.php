<?php
require_once __DIR__ . '/../config/config.php';
require_role(['patient']);

$stmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

if (!$patient) {
    set_flash('danger', 'Your patient profile could not be found. Please contact the clinic.');
    header('Location: ' . BASE_URL . '/patient/dashboard.php');
    exit;
}

$errors = [];
$preselectedDept = (int) ($_GET['department_id'] ?? 0);
$siamSymptoms = $_GET['siam_symptoms'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'book_appointment') {
    csrf_verify();

    $doctorId    = (int) ($_POST['doctor_id'] ?? 0);
    $date        = $_POST['appointment_date'] ?? '';
    $time        = $_POST['appointment_time'] ?? '';
    $reason      = trim($_POST['reason'] ?? '');
    $siamText    = trim($_POST['siam_symptoms'] ?? '');
    $wasSiam     = !empty($siamText) ? 1 : 0;

    $stmt = $pdo->prepare('SELECT department_id FROM doctors WHERE id = ? AND status = "active"');
    $stmt->execute([$doctorId]);
    $doctorRow = $stmt->fetch();

    if (!$doctorRow) $errors[] = 'Please select a valid, available doctor.';
    if (!$date || strtotime($date) < strtotime(date('Y-m-d'))) $errors[] = 'Please choose a valid future date.';
    if (!$time) $errors[] = 'Please choose a time.';

    // Prevent double-booking the same doctor at the same date/time
    if (!$errors) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM appointments
            WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('Pending','Approved')
        ");
        $stmt->execute([$doctorId, $date, $time]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = 'That time slot is already booked. Please choose another.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('
            INSERT INTO appointments (patient_id, doctor_id, department_id, appointment_date, appointment_time, reason, siam_symptoms, siam_suggested)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$patient['id'], $doctorId, $doctorRow['department_id'], $date, $time, $reason, $siamText ?: null, $wasSiam]);
        audit_log($_SESSION['user_id'], 'appointment_book', "doctor_id={$doctorId} date={$date} {$time}");

        set_flash('success', 'Appointment requested! You\'ll see it as "Pending" until the clinic approves it.');
        header('Location: ' . BASE_URL . '/patient/appointments.php');
        exit;
    }
}

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();
$doctors = $pdo->query("
    SELECT d.*, dep.name AS department_name FROM doctors d
    LEFT JOIN departments dep ON dep.id = d.department_id
    WHERE d.status = 'active'
    ORDER BY d.name ASC
")->fetchAll();

// Doctor schedules, keyed by doctor id, for the JS-driven date/day picker
$scheduleStmt = $pdo->query('SELECT doctor_id, day, start_time, end_time FROM clinic_schedule');
$schedulesByDoctor = [];
foreach ($scheduleStmt->fetchAll() as $row) {
    $schedulesByDoctor[$row['doctor_id']][] = $row;
}

$pageTitle = 'Book Appointment';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-4">Book an Appointment</h3>

<?php if ($siamSymptoms): ?>
  <div class="alert" style="background-color: var(--clinic-green-light); border-left: 4px solid var(--clinic-green);">
    <i class="bi bi-stars"></i> Pre-filled from your SIAM intake: "<em><?= e($siamSymptoms) ?></em>"
  </div>
<?php endif; ?>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-body">
    <form method="post" id="bookingForm">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="book_appointment">
      <input type="hidden" name="siam_symptoms" value="<?= e($siamSymptoms) ?>">

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Department</label>
          <select id="departmentFilter" class="form-select">
            <option value="">— All Departments —</option>
            <?php foreach ($departments as $dep): ?>
              <option value="<?= (int)$dep['id'] ?>" <?= $preselectedDept === (int)$dep['id'] ? 'selected' : '' ?>><?= e($dep['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Doctor</label>
          <select name="doctor_id" id="doctorSelect" class="form-select" required>
            <option value="">— Select a doctor —</option>
            <?php foreach ($doctors as $doc): ?>
              <option value="<?= (int)$doc['id'] ?>" data-department="<?= (int)$doc['department_id'] ?>">
                Dr. <?= e($doc['name']) ?> — <?= e($doc['specialization']) ?> (<?= e($doc['department_name'] ?? 'General') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-6">
          <label class="form-label">Date</label>
          <input type="date" name="appointment_date" id="apptDate" class="form-control" min="<?= date('Y-m-d') ?>" required>
          <div class="form-text" id="dateHint"></div>
        </div>

        <div class="col-md-6">
          <label class="form-label">Time</label>
          <input type="time" name="appointment_time" id="apptTime" class="form-control" required>
        </div>

        <div class="col-12">
          <label class="form-label">Reason for Visit</label>
          <textarea name="reason" class="form-control" rows="3" placeholder="Briefly describe the reason for your visit"><?= e($siamSymptoms) ?></textarea>
        </div>
      </div>

      <button type="submit" class="btn btn-clinic mt-4"><i class="bi bi-calendar-check"></i> Request Appointment</button>
    </form>
  </div>
</div>

<script>
const schedules = <?= json_encode($schedulesByDoctor) ?>;
const preselectedDept = <?= (int) $preselectedDept ?>;

const deptFilter = document.getElementById('departmentFilter');
const doctorSelect = document.getElementById('doctorSelect');
const dateInput = document.getElementById('apptDate');
const dateHint = document.getElementById('dateHint');

function filterDoctorsByDepartment() {
    const dept = deptFilter.value;
    Array.from(doctorSelect.options).forEach(opt => {
        if (!opt.value) return;
        const matches = !dept || opt.dataset.department === dept;
        opt.hidden = !matches;
    });
    // If current selection no longer matches, clear it
    const current = doctorSelect.selectedOptions[0];
    if (current && current.hidden) doctorSelect.value = '';
}

function updateDateHint() {
    const doctorId = doctorSelect.value;
    if (!doctorId || !schedules[doctorId]) {
        dateHint.textContent = '';
        return;
    }
    const days = schedules[doctorId].map(s => s.day).join(', ');
    dateHint.textContent = 'Available: ' + days;
}

deptFilter.addEventListener('change', filterDoctorsByDepartment);
doctorSelect.addEventListener('change', updateDateHint);

if (preselectedDept) filterDoctorsByDepartment();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
