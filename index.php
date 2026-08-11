<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) redirect_to_dashboard();

$pageTitle = 'Welcome to SIAM Clinic';
require __DIR__ . '/includes/header.php';
?>

<div class="p-5 mb-4 rounded-3 text-center text-white" style="background: linear-gradient(135deg, var(--clinic-green) 0%, var(--clinic-green-dark) 100%);">
  <h1 class="display-5 fw-bold"><i class="bi bi-hospital"></i> SIAM Clinic Management System</h1>
  <p class="fs-5">Book appointments, manage patients and doctors, and let <strong>SIAM</strong> point you to the right department based on how you're feeling.</p>
  <a href="<?= BASE_URL ?>/login.php" class="btn btn-light btn-lg me-2">Login</a>
  <a href="<?= BASE_URL ?>/register.php" class="btn btn-accent btn-lg">Register as Patient</a>
</div>

<div class="row g-4">
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body text-center">
        <i class="bi bi-stars fs-1" style="color: var(--clinic-orange);"></i>
        <h5 class="mt-3">Meet SIAM</h5>
        <p class="text-muted">Describe your symptoms in plain language and SIAM suggests the right department and doctor — no guesswork.</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body text-center">
        <i class="bi bi-calendar-check fs-1" style="color: var(--clinic-green);"></i>
        <h5 class="mt-3">Easy Booking</h5>
        <p class="text-muted">Pick a department, doctor, date and time — book in a few clicks.</p>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card h-100">
      <div class="card-body text-center">
        <i class="bi bi-shield-check fs-1" style="color: var(--clinic-black);"></i>
        <h5 class="mt-3">Admin Control</h5>
        <p class="text-muted">Admins manage users, doctors, and appointment approvals from one dashboard.</p>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
