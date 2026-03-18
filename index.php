<?php
require_once 'data.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();

if (!$user) {
    // Сессия есть но юзер не найден в БД — чистим и на логин
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($user['role'] === 'admin') {
    header('Location: admin.php');
} else {
    header('Location: dashboard.php');
}
exit;