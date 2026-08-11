<?php
$user = current_user();
$role = $user['role'] ?? '';

$links = [
    'admin' => [
        ['admin/dashboard.php', 'Dashboard'],
        ['admin/users.php', 'Users'],
        ['admin/doctors.php', 'Doctors'],
        ['admin/departments.php', 'Departments'],
        ['admin/appointments.php', 'Appointments'],
        ['admin/siam_log.php', 'SIAM Log'],
    ],
    'patient' => [
        ['patient/dashboard.php', 'Dashboard'],
        ['patient/siam.php', 'Ask SIAM'],
        ['patient/book_appointment.php', 'Book Appointment'],
        ['patient/appointments.php', 'My Appointments'],
        ['patient/profile.php', 'Profile'],
    ],
];
$navItems = $links[$role] ?? [];
?>
<nav class="navbar navbar-expand-lg navbar-siam">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>/<?= $role ?>/dashboard.php">
      <i class="bi bi-hospital"></i> SIAM Clinic <span class="siam-badge">AI-Assisted</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto">
        <?php foreach ($navItems as [$href, $label]): ?>
          <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL . '/' . $href ?>"><?= e($label) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
      <ul class="navbar-nav">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= e($user['name']) ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text text-muted small text-capitalize"><?= e($role) ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/change_password.php">Change Password</a></li>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">Logout</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
