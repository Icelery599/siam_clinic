<?php
require_once __DIR__ . '/../config/config.php';
require_role(['admin']);

$logs = $pdo->query("
    SELECT s.*, u.name AS patient_name, dep.name AS department_name
    FROM siam_assessments s
    LEFT JOIN patients p ON p.id = s.patient_id
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN departments dep ON dep.id = s.matched_department_id
    ORDER BY s.created_at DESC
    LIMIT 100
")->fetchAll();

// Quick stats: which departments SIAM is routing people to most
$byDept = $pdo->query("
    SELECT dep.name, COUNT(*) AS c
    FROM siam_assessments s
    JOIN departments dep ON dep.id = s.matched_department_id
    GROUP BY dep.id, dep.name
    ORDER BY c DESC
")->fetchAll();

$pageTitle = 'SIAM Assessment Log';
require __DIR__ . '/../includes/header.php';
?>

<h3 class="mb-1">SIAM Assessment Log</h3>
<p class="text-muted">Every symptom description patients type into SIAM, what it matched, and how confident the match was. Use this to spot missing keywords and tune department keyword lists.</p>

<?php if ($byDept): ?>
<div class="row g-3 mb-4">
  <?php foreach ($byDept as $bd): ?>
    <div class="col-md-3 col-lg-2">
      <div class="card summary-card orange">
        <div class="display-6"><?= (int)$bd['c'] ?></div>
        <div class="small"><?= e($bd['name']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-bordered bg-white align-middle">
  <thead>
    <tr><th>When</th><th>Patient</th><th>What they typed</th><th>Matched Department</th><th>Keywords Matched</th><th>Confidence</th></tr>
  </thead>
  <tbody>
    <?php if (!$logs): ?>
      <tr><td colspan="6" class="text-center text-muted">No SIAM intakes logged yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td class="small text-muted"><?= e($l['created_at']) ?></td>
        <td><?= e($l['patient_name'] ?? 'Guest') ?></td>
        <td class="small"><?= e($l['input_text']) ?></td>
        <td><?= e($l['department_name'] ?? 'No match') ?></td>
        <td class="small text-muted"><?= e($l['matched_keywords']) ?></td>
        <td>
          <?php if ($l['confidence']): ?>
            <span class="badge badge-confidence-<?= e($l['confidence']) ?>"><?= e(ucfirst($l['confidence'])) ?></span>
          <?php else: ?>
            <span class="text-muted small">—</span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
