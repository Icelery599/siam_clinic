<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/siam.php';
require_role(['patient']);

$stmt = $pdo->prepare('SELECT id FROM patients WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$patient = $stmt->fetch();

$assessment = null;
$inputText = '';

// Simple, transparent urgency flags — separate from department matching.
$urgentPhrases = [
    'severe pain', "can't breathe", 'cannot breathe', 'difficulty breathing',
    'chest pain', 'heavy bleeding', 'unconscious', 'seizure', 'severe bleeding',
    'suicidal', 'not breathing', 'choking',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'siam_assess') {
    csrf_verify();
    $inputText = trim($_POST['symptoms'] ?? '');

    if ($inputText === '') {
        set_flash('danger', 'Please describe what you\'re experiencing so SIAM can help.');
    } else {
        $assessment = siam_assess($pdo, $inputText);
        siam_log_assessment($pdo, $patient['id'] ?? null, $inputText, $assessment);

        $normalized = strtolower($inputText);
        $isUrgent = false;
        foreach ($urgentPhrases as $phrase) {
            if (str_contains($normalized, $phrase)) { $isUrgent = true; break; }
        }
        $assessment['urgent'] = $isUrgent;
    }
}

$pageTitle = 'Ask SIAM';
require __DIR__ . '/../includes/header.php';
?>

<div class="siam-panel mb-4">
  <div class="d-flex align-items-center gap-3">
    <div class="siam-icon">S</div>
    <div>
      <h4 class="mb-1">SIAM — Smart Intake Assistant</h4>
      <p class="mb-0">Describe your symptoms or what you need in your own words. SIAM reads it and points you to the right department — no AI black box, just transparent keyword matching you can double-check yourself.</p>
    </div>
  </div>
</div>

<div class="card mb-4">
  <div class="card-body">
    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="form" value="siam_assess">
      <label class="form-label fw-semibold">What's going on?</label>
      <textarea name="symptoms" class="form-control mb-3" rows="4" placeholder="e.g. My tooth has been hurting for two days and my gum is swollen"><?= e($inputText) ?></textarea>
      <button type="submit" class="btn btn-clinic">
        <i class="bi bi-stars"></i> Analyze with SIAM
      </button>
    </form>
  </div>
</div>

<?php if ($assessment): ?>
  <?php if (!empty($assessment['urgent'])): ?>
    <div class="alert alert-danger">
      <strong><i class="bi bi-exclamation-triangle-fill"></i> This sounds urgent.</strong>
      If you're experiencing a medical emergency, please call your local emergency number or go to the nearest emergency room right away — don't wait for an appointment booking.
    </div>
  <?php endif; ?>

  <?php if ($assessment['best']): ?>
    <div class="siam-suggestion p-3 mb-3">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
          <div class="text-muted small">SIAM suggests</div>
          <h5 class="mb-1"><?= e($assessment['best']['name']) ?></h5>
          <div class="small text-muted">
            Matched on: <?= e(implode(', ', $assessment['best']['matched_keywords'])) ?>
          </div>
        </div>
        <div class="text-end">
          <span class="badge badge-confidence-<?= e($assessment['confidence']) ?> mb-2"><?= e(ucfirst($assessment['confidence'])) ?> confidence</span><br>
          <a href="<?= BASE_URL ?>/patient/book_appointment.php?department_id=<?= (int)$assessment['best']['department_id'] ?>&siam_symptoms=<?= urlencode($inputText) ?>"
             class="btn btn-accent">Book in this Department</a>
        </div>
      </div>
    </div>

    <?php if (count($assessment['ranked']) > 1): ?>
      <p class="text-muted small mb-1">Other possible matches:</p>
      <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach (array_slice($assessment['ranked'], 1) as $r): ?>
          <a href="<?= BASE_URL ?>/patient/book_appointment.php?department_id=<?= (int)$r['department_id'] ?>&siam_symptoms=<?= urlencode($inputText) ?>"
             class="btn btn-outline-secondary btn-sm"><?= e($r['name']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <div class="alert alert-warning">
      SIAM couldn't confidently match your description to a department. That's alright —
      you can still <a href="<?= BASE_URL ?>/patient/book_appointment.php?siam_symptoms=<?= urlencode($inputText) ?>">book manually</a>
      and pick General Medicine as a safe starting point, or try describing it differently.
    </div>
  <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
