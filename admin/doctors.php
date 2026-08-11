<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$errors = [];
$days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];

// -------- Save (Add/Edit) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'save_doctor') {
    csrf_verify();

    $id              = (int) ($_POST['id'] ?? 0);
    $name            = trim($_POST['name'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $phone           = trim($_POST['phone'] ?? '');
    $departmentId    = (int) ($_POST['department_id'] ?? 0) ?: null;
    $specialization  = trim($_POST['specialization'] ?? '');
    $consultationFee = (float) ($_POST['consultation_fee'] ?? 0);
    $bio             = trim($_POST['bio'] ?? '');
    $status          = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($name === '') $errors[] = 'Doctor name is required.';

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE doctors SET name=?, department_id=?, specialization=?, consultation_fee=?, phone=?, email=?, bio=?, status=? WHERE id=?');
                $stmt->execute([$name, $departmentId, $specialization, $consultationFee, $phone, $email, $bio, $status, $id]);
                $doctorId = $id;
                set_flash('success', 'Doctor updated.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO doctors (name, department_id, specialization, consultation_fee, phone, email, bio, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$name, $departmentId, $specialization, $consultationFee, $phone, $email, $bio, $status]);
                $doctorId = (int) $pdo->lastInsertId();
                set_flash('success', 'Doctor added.');
            }

            $pdo->prepare('DELETE FROM clinic_schedule WHERE doctor_id = ?')->execute([$doctorId]);
            foreach ($days as $day) {
                $key = strtolower($day);
                if (!empty($_POST["day_{$key}"])) {
                    $start = $_POST["start_{$key}"] ?? '09:00';
                    $end   = $_POST["end_{$key}"] ?? '17:00';
                    $stmt = $pdo->prepare('INSERT INTO clinic_schedule (doctor_id, day, start_time, end_time) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$doctorId, $day, $start, $end]);
                }
            }

            $pdo->commit();
            audit_log($_SESSION['user_id'], 'doctor_save', $name);
            header('Location: ' . BASE_URL . '/admin/doctors.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Could not save doctor: ' . $e->getMessage();
        }
    }
}

// -------- Delete --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_doctor') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM appointments WHERE doctor_id = ? AND status IN ("Pending","Approved")');
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        set_flash('danger', 'Cannot delete: this doctor has pending or approved appointments.');
    } else {
        $pdo->prepare('DELETE FROM doctors WHERE id = ?')->execute([$id]);
        audit_log($_SESSION['user_id'], 'doctor_delete', "id={$id}");
        set_flash('success', 'Doctor removed.');
    }
    header('Location: ' . BASE_URL . '/admin/doctors.php');
    exit;
}

$departments = $pdo->query('SELECT id, name FROM departments ORDER BY name ASC')->fetchAll();

$doctors = $pdo->query('
    SELECT d.*, dep.name AS department_name
    FROM doctors d LEFT JOIN departments dep ON dep.id = d.department_id
    ORDER BY d.name ASC
')->fetchAll();

$scheduleStmt = $pdo->prepare('SELECT day, start_time, end_time FROM clinic_schedule WHERE doctor_id = ?');
foreach ($doctors as &$doc) {
    $scheduleStmt->execute([$doc['id']]);
    $doc['schedule'] = $scheduleStmt->fetchAll();
}
unset($doc);

$pageTitle = 'Manage Doctors';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="mb-0">Manage Doctors <span class="badge bg-secondary"><?= count($doctors) ?></span></h3>
  <button class="btn btn-clinic" data-bs-toggle="modal" data-bs-target="#doctorModal" onclick="openAddModal()">
    <i class="bi bi-plus-lg"></i> Add Doctor
  </button>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead>
    <tr><th>Name</th><th>Department</th><th>Specialization</th><th>Fee</th><th>Status</th><th style="width:160px;">Actions</th></tr>
  </thead>
  <tbody>
    <?php if (!$doctors): ?>
      <tr><td colspan="6" class="text-center text-muted">No doctors yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($doctors as $d): ?>
      <tr>
        <td>Dr. <?= e($d['name']) ?></td>
        <td><?= e($d['department_name'] ?? '—') ?></td>
        <td><?= e($d['specialization']) ?></td>
        <td>₦<?= number_format((float)$d['consultation_fee'], 2) ?></td>
        <td><span class="badge <?= $d['status'] === 'active' ? 'bg-success' : 'bg-secondary' ?>"><?= e(ucfirst($d['status'])) ?></span></td>
        <td>
          <button class="btn btn-sm btn-outline-primary"
                  onclick='openEditModal(<?= json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
            <i class="bi bi-pencil"></i>
          </button>
          <form method="post" class="d-inline" onsubmit="return confirm('Remove this doctor? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="delete_doctor">
            <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="doctorModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="save_doctor">
        <input type="hidden" name="id" id="docId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="docModalTitle">Add Doctor</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" id="docName" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" id="docEmail" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" id="docPhone" class="form-control">
            </div>
            <div class="col-md-6">
              <label class="form-label">Department</label>
              <select name="department_id" id="docDepartment" class="form-select">
                <option value="">— None —</option>
                <?php foreach ($departments as $dep): ?>
                  <option value="<?= (int)$dep['id'] ?>"><?= e($dep['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Specialization</label>
              <input type="text" name="specialization" id="docSpecialization" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Consultation Fee</label>
              <input type="number" step="0.01" min="0" name="consultation_fee" id="docFee" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Status</label>
              <select name="status" id="docStatus" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Bio</label>
              <textarea name="bio" id="docBio" class="form-control" rows="2"></textarea>
            </div>
          </div>

          <hr>
          <h6>Weekly Schedule</h6>
          <table class="table table-sm">
            <thead><tr><th style="width:40px;"></th><th>Day</th><th>Start</th><th>End</th></tr></thead>
            <tbody>
              <?php foreach ($days as $day): $key = strtolower($day); ?>
                <tr>
                  <td><input type="checkbox" class="form-check-input" name="day_<?= $key ?>" id="day_<?= $key ?>"></td>
                  <td><label for="day_<?= $key ?>"><?= $day ?></label></td>
                  <td><input type="time" name="start_<?= $key ?>" id="start_<?= $key ?>" class="form-control form-control-sm" value="09:00"></td>
                  <td><input type="time" name="end_<?= $key ?>" id="end_<?= $key ?>" class="form-control form-control-sm" value="17:00"></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-clinic">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
const dayKeys = <?= json_encode(array_map('strtolower', $days)) ?>;

function resetScheduleInputs() {
    dayKeys.forEach(k => {
        document.getElementById('day_' + k).checked = false;
        document.getElementById('start_' + k).value = '09:00';
        document.getElementById('end_' + k).value = '17:00';
    });
}

function openAddModal() {
    document.getElementById('docModalTitle').textContent = 'Add Doctor';
    document.getElementById('docId').value = 0;
    document.getElementById('docName').value = '';
    document.getElementById('docEmail').value = '';
    document.getElementById('docPhone').value = '';
    document.getElementById('docDepartment').value = '';
    document.getElementById('docSpecialization').value = '';
    document.getElementById('docFee').value = '';
    document.getElementById('docStatus').value = 'active';
    document.getElementById('docBio').value = '';
    resetScheduleInputs();
}

function openEditModal(d) {
    document.getElementById('docModalTitle').textContent = 'Edit Doctor';
    document.getElementById('docId').value = d.id;
    document.getElementById('docName').value = d.name;
    document.getElementById('docEmail').value = d.email || '';
    document.getElementById('docPhone').value = d.phone || '';
    document.getElementById('docDepartment').value = d.department_id || '';
    document.getElementById('docSpecialization').value = d.specialization || '';
    document.getElementById('docFee').value = d.consultation_fee || '';
    document.getElementById('docStatus').value = d.status || 'active';
    document.getElementById('docBio').value = d.bio || '';

    resetScheduleInputs();
    (d.schedule || []).forEach(s => {
        const key = s.day.toLowerCase();
        const cb = document.getElementById('day_' + key);
        if (cb) {
            cb.checked = true;
            document.getElementById('start_' + key).value = s.start_time.substring(0,5);
            document.getElementById('end_' + key).value = s.end_time.substring(0,5);
        }
    });

    new bootstrap.Modal(document.getElementById('doctorModal')).show();
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
