<?php
session_start();

// ============================================================
// ЗАГЛУШКА ДАННЫХ — здесь будут заменены на запросы к БД
// ============================================================

// --- Пользователи ---
// TODO: заменить на SELECT * FROM users
$USERS_DB = [
    ['id' => 1, 'login' => 'admin',    'password' => 'admin123',  'name' => 'Администратор',   'role' => 'admin',    'team_id' => null, 'position' => 'Administrator',      'project' => '—'],
    ['id' => 2, 'login' => 'teamlead', 'password' => 'lead123',   'name' => 'Алексей Иванов',  'role' => 'teamlead', 'team_id' => 1,    'position' => 'Team Lead',           'project' => 'Mobile App'],
    ['id' => 3, 'login' => 'maria',    'password' => 'pass123',   'name' => 'Мария Петрова',   'role' => 'employee', 'team_id' => 1,    'position' => 'Frontend Developer',  'project' => 'Web Dashboard'],
    ['id' => 4, 'login' => 'dmitry',   'password' => 'pass123',   'name' => 'Дмитрий Сидоров', 'role' => 'employee', 'team_id' => 1,    'position' => 'Backend Developer',   'project' => 'API Gateway'],
    ['id' => 5, 'login' => 'elena',    'password' => 'pass123',   'name' => 'Елена Козлова',   'role' => 'employee', 'team_id' => 2,    'position' => 'QA Engineer',         'project' => 'Testing'],
    ['id' => 6, 'login' => 'pavel',    'password' => 'pass123',   'name' => 'Павел Новиков',   'role' => 'employee', 'team_id' => 2,    'position' => 'DevOps',              'project' => 'Infrastructure'],
];

// --- Команды ---
// TODO: заменить на SELECT * FROM teams
$TEAMS_DB = [
    ['id' => 1, 'name' => 'Frontend Squad', 'description' => 'Фронтенд и мобильная разработка', 'lead_id' => 2],
    ['id' => 2, 'name' => 'Backend Guild',  'description' => 'Бэкенд, DevOps и тестирование',   'lead_id' => 2],
];

// --- Записи рабочего времени ---
// TODO: заменить на SELECT * FROM time_logs
$TIME_LOGS_DB = [
    ['id' => 1, 'user_id' => 2, 'date' => date('Y-m-d'), 'start' => '09:15', 'end' => null,    'total_today' => 3.5, 'total_week' => 28.5, 'overtime' => 2.0],
    ['id' => 2, 'user_id' => 3, 'date' => date('Y-m-d'), 'start' => '10:00', 'end' => null,    'total_today' => 4.0, 'total_week' => 32.0, 'overtime' => 0.0],
    ['id' => 3, 'user_id' => 4, 'date' => date('Y-m-d'), 'start' => '09:30', 'end' => '12:00', 'total_today' => 2.5, 'total_week' => 25.0, 'overtime' => 0.0],
    ['id' => 4, 'user_id' => 5, 'date' => date('Y-m-d'), 'start' => '08:30', 'end' => null,    'total_today' => 5.5, 'total_week' => 30.5, 'overtime' => 3.5],
    ['id' => 5, 'user_id' => 6, 'date' => date('Y-m-d'), 'start' => '—',     'end' => '—',     'total_today' => 0.0, 'total_week' => 18.0, 'overtime' => 0.0],
];

// ============================================================
// Инициализация сессионного состояния
// ============================================================
if (!isset($_SESSION['statuses'])) {
    // TODO: загрузить из БД реальные статусы
    $_SESSION['statuses'] = [
        2 => 'working',
        3 => 'working',
        4 => 'resting',
        5 => 'working',
        6 => 'offline',
    ];
}
if (!isset($_SESSION['session_starts'])) {
    $_SESSION['session_starts'] = [
        2 => '09:15',
        3 => '10:00',
        4 => '—',
        5 => '08:30',
        6 => '—',
    ];
}
if (!isset($_SESSION['teams'])) {
    $_SESSION['teams'] = $TEAMS_DB;
}
if (!isset($_SESSION['users'])) {
    $_SESSION['users'] = $USERS_DB;
}
if (!isset($_SESSION['time_logs'])) {
    $_SESSION['time_logs'] = $TIME_LOGS_DB;
}

// ============================================================
// Хелперы
// ============================================================

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    foreach ($_SESSION['users'] as $u) {
        if ($u['id'] === $_SESSION['user_id']) return $u;
    }
    return null;
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(...$roles) {
    requireAuth();
    $user = getCurrentUser();
    if (!in_array($user['role'], $roles)) {
        header('Location: dashboard.php');
        exit;
    }
}

function getEmployees() {
    // Возвращает всех сотрудников с живыми статусами из сессии
    $result = [];
    foreach ($_SESSION['users'] as $u) {
        if ($u['role'] === 'employee' || $u['role'] === 'teamlead') {
            $u['status']          = $_SESSION['statuses'][$u['id']] ?? 'offline';
            $u['current_session'] = $_SESSION['session_starts'][$u['id']] ?? '—';
            // Найти лог
            $log = getLogForUser($u['id']);
            $u['total_today'] = $log ? $log['total_today'] : 0;
            $u['total_week']  = $log ? $log['total_week']  : 0;
            $u['overtime']    = $log ? $log['overtime']    : 0;
            $result[] = $u;
        }
    }
    return $result;
}

function getLogForUser($userId) {
    foreach ($_SESSION['time_logs'] as $log) {
        if ($log['user_id'] === $userId) return $log;
    }
    return null;
}

function getTeamsForLead($leadId) {
    $result = [];
    foreach ($_SESSION['teams'] as $t) {
        if ($t['lead_id'] === $leadId) $result[] = $t;
    }
    return $result;
}

function getTeamsForEmployee($userId) {
    $result = [];
    foreach ($_SESSION['teams'] as $t) {
        // Найти пользователя и проверить team_id
        foreach ($_SESSION['users'] as $u) {
            if ($u['id'] === $userId && $u['team_id'] === $t['id']) {
                $result[] = $t;
                break;
            }
        }
    }
    return $result;
}

function getMembersOfTeam($teamId) {
    $result = [];
    foreach ($_SESSION['users'] as $u) {
        if (($u['team_id'] ?? null) === $teamId && $u['role'] !== 'admin') {
            $u['status'] = $_SESSION['statuses'][$u['id']] ?? 'offline';
            $result[] = $u;
        }
    }
    return $result;
}

function statusLabel($status) {
    return match($status) {
        'working' => ['text' => 'Работает',  'class' => 'status-working'],
        'resting' => ['text' => 'Перерыв',   'class' => 'status-resting'],
        default   => ['text' => 'Не в сети', 'class' => 'status-offline'],
    };
}

function initials($name) {
    $parts = explode(' ', $name);
    return substr($parts[0], 0, 1) . substr($parts[1] ?? '', 0, 1);
}
