<?php
require_once 'data.php';
requireAuth();

$user   = getCurrentUser();
$action = $_POST['action'] ?? '';
$empId  = (int)($_POST['employee_id'] ?? 0);

// Сотрудник может менять только свой статус
if ($user['role'] === 'employee') {
    $empId = $user['id'];
}

// TODO: здесь будут INSERT/UPDATE запросы к БД вместо работы с сессией
if ($empId > 0 && $action) {
    $now = date('H:i');

    switch ($action) {
        case 'start':
            $_SESSION['statuses'][$empId]       = 'working';
            $_SESSION['session_starts'][$empId] = $now;
            // TODO: INSERT INTO time_logs (user_id, date, start) VALUES (?, NOW(), ?)
            break;

        case 'stop':
            $_SESSION['statuses'][$empId]       = 'offline';
            $_SESSION['session_starts'][$empId] = '—';
            // TODO: UPDATE time_logs SET end=NOW(), total_today=TIMEDIFF(NOW(), start) WHERE user_id=? AND date=TODAY()
            // Пересчитать total_today заглушкой
            foreach ($_SESSION['time_logs'] as &$log) {
                if ($log['user_id'] === $empId) {
                    $log['total_today'] = round($log['total_today'] + 0, 1); // реальный расчёт — из БД
                    break;
                }
            }
            unset($log);
            break;

        case 'break':
            $_SESSION['statuses'][$empId] = 'resting';
            // TODO: INSERT INTO breaks (user_id, start) VALUES (?, NOW())
            break;

        case 'resume':
            $_SESSION['statuses'][$empId] = 'working';
            // TODO: UPDATE breaks SET end=NOW() WHERE user_id=? AND end IS NULL
            break;
    }
}

// Вернуться на страницу откуда пришли
$redirect = $_POST['redirect'] ?? 'dashboard.php';
// Безопасность: только внутренние страницы
$allowed = ['dashboard.php', 'team.php'];
if (!in_array($redirect, $allowed)) $redirect = 'dashboard.php';

header("Location: {$redirect}?notify={$action}&emp=" . urlencode($_POST['employee_name'] ?? ''));
exit;
