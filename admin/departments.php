<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$errors = [];

// -------- Save (Add/Edit) --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'save_department') {
    csrf_verify();

    $id          = (int) ($_POST['id'] ?? 0);
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $keywords    = trim($_POST['keywords'] ?? '');

    if ($name === '') $errors[] = 'Department name is required.';

    if (!$errors) {
        if ($id > 0) {
            $stmt = $pdo->prepare('UPDATE departments SET name = ?, description = ?, keywords = ? WHERE id = ?');
            $stmt->execute([$name, $description, $keywords, $id]);
            set_flash('success', 'Department updated.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO departments (name, description, keywords) VALUES (?, ?, ?)');
            $stmt->execute([$name, $description, $keywords]);
            set_flash('success', 'Department added.');
        }
        audit_log($_SESSION['user_id'], 'department_save', $name);
        header('Location: ' . BASE_URL . '/admin/departments.php');
        exit;
    }
}

// -------- Delete --------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'delete_department') {
    csrf_verify();
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM doctors WHERE department_id = ?');
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        set_flash('danger', 'Cannot delete: this department still has doctors assigned to it.');
    } else {
        $pdo->prepare('DELETE FROM departments WHERE id = ?')->execute([$id]);
        audit_log($_SESSION['user_id'], 'department_delete', "id={$id}");
        set_flash('success', 'Department deleted.');
    }
    header('Location: ' . BASE_URL . '/admin/departments.php');
    exit;
}

$departments = $pdo->query('
    SELECT d.*, (SELECT COUNT(*) FROM doctors WHERE department_id = d.id) AS doctor_count
    FROM departments d ORDER BY d.name ASC
')->fetchAll();

$pageTitle = 'Manage Departments';
require __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <h3 class="mb-0">Manage Departments</h3>
  <button class="btn btn-clinic" data-bs-toggle="modal" data-bs-target="#deptModal" onclick="openAddModal()">
    <i class="bi bi-plus-lg"></i> Add Department
  </button>
</div>

<?php if ($errors): ?>
  <div class="alert alert-danger"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<p class="text-muted small">
  The <strong>keywords</strong> field is what SIAM matches against patient symptom descriptions —
  comma-separated words or short phrases (e.g. <code>tooth, toothache, gum, bleeding gums</code>).
</p>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead>
    <tr><th>Name</th><th>Description</th><th>SIAM Keywords</th><th># Doctors</th><th style="width:140px;">Actions</th></tr>
  </thead>
  <tbody>
    <?php if (!$departments): ?>
      <tr><td colspan="5" class="text-center text-muted">No departments yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($departments as $d): ?>
      <tr>
        <td><?= e($d['name']) ?></td>
        <td><?= e($d['description']) ?></td>
        <td class="small text-muted"><?= e($d['keywords']) ?></td>
        <td><span class="badge bg-secondary"><?= (int)$d['doctor_count'] ?></span></td>
        <td>
          <button class="btn btn-sm btn-outline-primary"
                  onclick='openEditModal(<?= json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
            <i class="bi bi-pencil"></i>
          </button>
          <form method="post" class="d-inline" onsubmit="return confirm('Delete this department? This cannot be undone.');">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="delete_department">
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
<div class="modal fade" id="deptModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="form" value="save_department">
        <input type="hidden" name="id" id="deptId" value="0">
        <div class="modal-header">
          <h5 class="modal-title" id="deptModalTitle">Add Department</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" id="deptName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" id="deptDescription" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">SIAM Keywords <span class="text-muted small">(comma-separated)</span></label>
            <textarea name="keywords" id="deptKeywords" class="form-control" rows="3" placeholder="e.g. tooth, toothache, gum, cavity"></textarea>
          </div>
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
function openAddModal() {
    document.getElementById('deptModalTitle').textContent = 'Add Department';
    document.getElementById('deptId').value = 0;
    document.getElementById('deptName').value = '';
    document.getElementById('deptDescription').value = '';
    document.getElementById('deptKeywords').value = '';
}
function openEditModal(d) {
    document.getElementById('deptModalTitle').textContent = 'Edit Department';
    document.getElementById('deptId').value = d.id;
    document.getElementById('deptName').value = d.name;
    document.getElementById('deptDescription').value = d.description || '';
    document.getElementById('deptKeywords').value = d.keywords || '';
    new bootstrap.Modal(document.getElementById('deptModal')).show();
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
