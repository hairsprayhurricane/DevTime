<?php
require_once 'data.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user = getCurrentUser();
if ($user['role'] === 'admin') {
    header('Location: admin.php');
} else {
    header('Location: dashboard.php');
}
exit;
