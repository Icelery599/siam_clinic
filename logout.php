<?php
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    audit_log($_SESSION['user_id'], 'logout', 'User logged out');
}

$_SESSION = [];
session_destroy();

session_start();
set_flash('success', 'You have been logged out.');
header('Location: ' . BASE_URL . '/login.php');
exit;
