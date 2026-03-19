<?php
session_start();

// ============================================================
// Подключение к PostgreSQL
// ============================================================
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '5432');
define('DB_NAME', 'devtime');
define('DB_USER', 'postgres');
define('DB_PASS', '12345');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO('pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            // База недоступна — отправляем на initbase.php
            $current = basename($_SERVER['SCRIPT_FILENAME'] ?? '');
            if ($current !== 'initbase.php') {
                header('Location: initbase.php');
                exit;
            }
            throw $e; // initbase.php сам обработает ошибку
        }
    }
    return $pdo;
}

// ============================================================
// Аутентификация
// ============================================================

function getCurrentUser(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    $stmt = getDB()->prepare("
        SELECT u.id, u.full_name AS name, u.position, u.project, u.login, r.name AS role
        FROM users u
        JOIN user_roles ur ON ur.user_id = u.id
        JOIN roles r       ON r.id = ur.role_id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function requireAuth(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(string ...$roles): void {
    requireAuth();
    $user = getCurrentUser();
    if (!in_array($user['role'], $roles)) {
        header('Location: dashboard.php');
        exit;
    }
}

// ============================================================
// Статус пользователя (по незакрытой записи в work_logs)
// ============================================================

function getUserStatus(int $userId): string {
    $stmt = getDB()->prepare("
        SELECT type FROM work_logs
        WHERE user_id = ? AND ended_at IS NULL
        ORDER BY started_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if (!$row) return 'offline';
    return $row['type'] === 'break' ? 'resting' : 'working';
}

function getSessionStart(int $userId): string {
    $stmt = getDB()->prepare("
        SELECT started_at FROM work_logs
        WHERE user_id = ? AND type = 'work'
          AND DATE(started_at) = CURRENT_DATE
        ORDER BY started_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ? date('H:i', strtotime($row['started_at'])) : '—';
}

// ============================================================
// Сотрудники
// ============================================================

function getEmployees(string $filterDate = '', string $filterWeek = 'current', ?int $leadId = null): array {
    if ($leadId !== null) {
        // Тимлид видит только участников своих команд
        $stmt = getDB()->prepare("
            SELECT DISTINCT u.id, u.full_name AS name, u.position, u.project, r.name AS role
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r       ON r.id = ur.role_id
            JOIN team_members tm ON tm.user_id = u.id
            WHERE r.name IN ('employee', 'teamlead')
              AND tm.team_id IN (
                  SELECT team_id FROM team_members WHERE user_id = ?
              )
            ORDER BY u.id
        ");
        $stmt->execute([$leadId]);
    } else {
        $stmt = getDB()->query("
            SELECT u.id, u.full_name AS name, u.position, u.project, r.name AS role
            FROM users u
            JOIN user_roles ur ON ur.user_id = u.id
            JOIN roles r       ON r.id = ur.role_id
            WHERE r.name IN ('employee', 'teamlead')
            ORDER BY u.id
        ");
    }
    $employees = $stmt->fetchAll();

    foreach ($employees as &$emp) {
        $emp['status']          = getUserStatus($emp['id']);
        $emp['current_session'] = getSessionStart($emp['id']);
        $emp['total_today']     = getLiveMinutesForDate($emp['id'], $filterDate);
        $emp['total_week']      = getWeeklyMinutes($emp['id'], $filterWeek);
        $emp['overtime']        = max(0, $emp['total_today'] - 480);
    }
    unset($emp);
    return $employees;
}

function getDailyReport(int $userId, string $date = ''): ?array {
    $d = $date ?: date('Y-m-d');
    $stmt = getDB()->prepare("
        SELECT * FROM daily_reports
        WHERE user_id = ? AND report_date = ?
    ");
    $stmt->execute([$userId, $d]);
    return $stmt->fetch() ?: null;
}

// Считает рабочие минуты за день прямо из work_logs (включая текущий незакрытый отрезок)
function getLiveMinutesForDate(int $userId, string $date = ''): int {
    $d = $date ?: date('Y-m-d');
    $stmt = getDB()->prepare("
        SELECT COALESCE(SUM(
            EXTRACT(EPOCH FROM (COALESCE(ended_at, NOW()) - started_at)) / 60
        ), 0)::INT
        FROM work_logs
        WHERE user_id = ?
          AND type = 'work'
          AND DATE(started_at) = ?
    ");
    $stmt->execute([$userId, $d]);
    return (int)$stmt->fetchColumn();
}

function getWeeklyHours(int $userId, string $week = 'current'): float {
    return round(getWeeklyMinutes($userId, $week) / 60, 1);
}

function getWeeklyMinutes(int $userId, string $week = 'current'): int {
    if ($week === 'prev') {
        $from = "DATE_TRUNC('week', CURRENT_DATE) - INTERVAL '7 days'";
        $to   = "DATE_TRUNC('week', CURRENT_DATE) - INTERVAL '1 day'";
    } elseif ($week === 'month') {
        $from = "DATE_TRUNC('month', CURRENT_DATE)";
        $to   = "CURRENT_DATE";
    } else {
        $from = "DATE_TRUNC('week', CURRENT_DATE)";
        $to   = "CURRENT_DATE";
    }
    $stmt = getDB()->prepare("
        SELECT COALESCE(SUM(
            EXTRACT(EPOCH FROM (COALESCE(ended_at, NOW()) - started_at)) / 60
        ), 0)::INT
        FROM work_logs
        WHERE user_id = ?
          AND type = 'work'
          AND DATE(started_at) BETWEEN $from AND $to
    ");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getLogForUser(int $userId): ?array {
    $daily = getDailyReport($userId);
    if (!$daily) return null;
    return [
        'user_id'     => $userId,
        'total_today' => (int)$daily['total_work_minutes'],
        'total_week'  => getWeeklyMinutes($userId),
        'overtime'    => (int)$daily['overtime_minutes'],
    ];
}

// ============================================================
// Команды
// ============================================================

function getAllTeams(): array {
    return getDB()->query("SELECT * FROM teams ORDER BY id")->fetchAll();
}

function getTeamsForLead(int $leadId): array {
    $stmt = getDB()->prepare("
        SELECT DISTINCT t.*
        FROM teams t
        JOIN team_members tm ON tm.team_id = t.id
        WHERE tm.user_id = ?
    ");
    $stmt->execute([$leadId]);
    return $stmt->fetchAll();
}

function getTeamsForEmployee(int $userId): array {
    $stmt = getDB()->prepare("
        SELECT t.* FROM teams t
        JOIN team_members tm ON tm.team_id = t.id
        WHERE tm.user_id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function getMembersOfTeam(int $teamId): array {
    $stmt = getDB()->prepare("
        SELECT u.id, u.full_name AS name, u.position, r.name AS role
        FROM users u
        JOIN team_members tm ON tm.user_id = u.id
        JOIN user_roles ur   ON ur.user_id = u.id
        JOIN roles r         ON r.id = ur.role_id
        WHERE tm.team_id = ? AND r.name != 'admin'
        ORDER BY u.full_name
    ");
    $stmt->execute([$teamId]);
    $members = $stmt->fetchAll();
    foreach ($members as &$m) {
        $m['status'] = getUserStatus($m['id']);
    }
    unset($m);
    return $members;
}

function getAllUsers(): array {
    // DISTINCT ON (u.id) предотвращает дубли когда пользователь
    // состоит в нескольких командах (LEFT JOIN team_members даёт
    // по одной строке на каждую команду)
    $stmt = getDB()->query("
        SELECT DISTINCT ON (u.id)
               u.id, u.full_name AS name, u.login, u.position, u.project,
               r.name AS role, tm.team_id
        FROM users u
        JOIN user_roles ur        ON ur.user_id = u.id
        JOIN roles r              ON r.id = ur.role_id
        LEFT JOIN team_members tm ON tm.user_id = u.id
        ORDER BY u.id
    ");
    return $stmt->fetchAll();
}

// ============================================================
// Пересчёт итогов дня (вызывается при «Стоп»)
// ============================================================

function recalcDailyReport(int $userId): void {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT COALESCE(
            SUM(EXTRACT(EPOCH FROM (ended_at - started_at)) / 60), 0
        )::INT AS total_minutes
        FROM work_logs
        WHERE user_id = ? AND type = 'work'
          AND DATE(started_at) = CURRENT_DATE
          AND ended_at IS NOT NULL
    ");
    $stmt->execute([$userId]);
    $totalMinutes    = (int)$stmt->fetchColumn();
    $overtimeMinutes = max(0, $totalMinutes - 480); // норма 8 ч = 480 мин

    $db->prepare("
        INSERT INTO daily_reports (user_id, report_date, total_work_minutes, overtime_minutes)
        VALUES (?, CURRENT_DATE, ?, ?)
        ON CONFLICT (user_id, report_date) DO UPDATE SET
            total_work_minutes = EXCLUDED.total_work_minutes,
            overtime_minutes   = EXCLUDED.overtime_minutes
    ")->execute([$userId, $totalMinutes, $overtimeMinutes]);

    if ($overtimeMinutes > 0) {
        $db->prepare("
            INSERT INTO overtime_requests (user_id, report_date, overtime_minutes)
            VALUES (?, CURRENT_DATE, ?)
            ON CONFLICT (user_id, report_date) DO NOTHING
        ")->execute([$userId, $overtimeMinutes]);
    }
}

// ============================================================
// Утилиты
// ============================================================

function getOvertimeRequest(int $userId): ?array {
    $stmt = getDB()->prepare("
        SELECT id, overtime_minutes, status
        FROM overtime_requests
        WHERE user_id = ? AND report_date = CURRENT_DATE
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: null;
}

function statusLabel(string $status): array {
    return match($status) {
        'working' => ['text' => 'Работает',  'class' => 'status-working'],
        'resting' => ['text' => 'Перерыв',   'class' => 'status-resting'],
        default   => ['text' => 'Не в сети', 'class' => 'status-offline'],
    };
}

function initials(string $name): string {
    $parts = explode(' ', trim($name));
    return mb_substr($parts[0], 0, 1, 'UTF-8') . mb_substr($parts[1] ?? '', 0, 1, 'UTF-8');
}