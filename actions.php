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

if ($empId > 0 && $action) {
    $db = getDB();

    switch ($action) {
        case 'start':
            // Закрываем все случайно незакрытые записи за сегодня (защита от дублей)
            $db->prepare("
                UPDATE work_logs SET ended_at = NOW()
                WHERE user_id = ? AND ended_at IS NULL
            ")->execute([$empId]);

            // Открываем новый рабочий отрезок
            $db->prepare("
                INSERT INTO work_logs (user_id, started_at, type)
                VALUES (?, NOW(), 'work')
            ")->execute([$empId]);
            break;

        case 'stop':
            // Закрываем текущий открытый отрезок (work или break)
            $db->prepare("
                UPDATE work_logs SET ended_at = NOW()
                WHERE user_id = ? AND ended_at IS NULL
            ")->execute([$empId]);

            // Пересчитываем итоги дня и переработку
            recalcDailyReport($empId);
            break;

        case 'break':
            // Закрываем рабочий отрезок
            $db->prepare("
                UPDATE work_logs SET ended_at = NOW()
                WHERE user_id = ? AND ended_at IS NULL AND type = 'work'
            ")->execute([$empId]);

            // Открываем отрезок-перерыв
            $db->prepare("
                INSERT INTO work_logs (user_id, started_at, type)
                VALUES (?, NOW(), 'break')
            ")->execute([$empId]);
            break;

        case 'resume':
            // Закрываем перерыв
            $db->prepare("
                UPDATE work_logs SET ended_at = NOW()
                WHERE user_id = ? AND ended_at IS NULL AND type = 'break'
            ")->execute([$empId]);

            // Открываем новый рабочий отрезок
            $db->prepare("
                INSERT INTO work_logs (user_id, started_at, type)
                VALUES (?, NOW(), 'work')
            ")->execute([$empId]);
            break;
    }
}

$redirect = $_POST['redirect'] ?? 'dashboard.php';
$allowed  = ['dashboard.php', 'team.php'];
if (!in_array($redirect, $allowed)) $redirect = 'dashboard.php';

// Обработка подтверждения/отклонения переработки (только тимлид)
if (in_array($action, ['approve_overtime', 'reject_overtime']) && $user['role'] === 'teamlead') {
    $requestId = (int)($_POST['request_id'] ?? 0);
    if ($requestId > 0) {
        $status = $action === 'approve_overtime' ? 'approved' : 'rejected';
        getDB()->prepare("
            UPDATE overtime_requests
            SET status = ?, reviewed_by = ?, reviewed_at = NOW()
            WHERE id = ?
        ")->execute([$status, $user['id'], $requestId]);
    }
    header("Location: {$redirect}");
    exit;
}

$empName = htmlspecialchars(strip_tags($_POST['employee_name'] ?? ''), ENT_QUOTES);
$_SESSION['notify']     = $action;
$_SESSION['notify_emp'] = $empName;
header("Location: {$redirect}");
exit;
