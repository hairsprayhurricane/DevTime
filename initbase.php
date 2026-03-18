<?php
/**
 * Автоматически создаёт базу, схему и дефолтных пользователей.
 * Запускается сам когда база недоступна (редирект из data.php).
 * После успешного выполнения можно оставить — повторный запуск безопасен.
 */
require_once 'data.php';

echo "<pre>";
echo "🔧 Настройки подключения:\n";
echo "   host:   " . DB_HOST . "\n";
echo "   port:   " . DB_PORT . "\n";
echo "   dbname: " . DB_NAME . "\n";
echo "   user:   " . DB_USER . "\n\n";

// ============================================================
// Шаг 1: Подключаемся к системной базе postgres
// ============================================================
try {
    $sys = new PDO('pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=postgres', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "✅ Подключение к системной базе postgres — OK\n\n";
} catch (PDOException $e) {
    die("❌ Не удалось подключиться к PostgreSQL: " . $e->getMessage() . "\n\nПроверь:\n- запущен ли PostgreSQL\n- правильные ли DB_USER и DB_PASS в data.php\n");
}

// ============================================================
// Шаг 2: Создаём базу если не существует
// ============================================================
$stmt = $sys->prepare("SELECT 1 FROM pg_database WHERE datname = ?");
$stmt->execute([DB_NAME]);

if ($stmt->fetchColumn()) {
    echo "ℹ️  База «" . DB_NAME . "» уже существует.\n\n";
} else {
    $sys->exec('CREATE DATABASE "' . DB_NAME . '" OWNER "' . DB_USER . '"');
    echo "✅ База «" . DB_NAME . "» создана.\n\n";
}

// ============================================================
// Шаг 3: Подключаемся к нашей базе
// ============================================================
try {
    $db = new PDO('pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "✅ Подключение к «" . DB_NAME . "» — OK\n\n";
} catch (PDOException $e) {
    die("❌ " . $e->getMessage() . "\n");
}

// ============================================================
// Шаг 4: Проверяем есть ли уже таблицы
// ============================================================
$tableCount = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public'")->fetchColumn();

if ($tableCount > 0) {
    echo "ℹ️  Таблицы уже существуют ($tableCount шт.), схема не пересоздаётся.\n\n";
    echo "✅ База готова к работе. ";
    echo '<a href="login.php">Перейти к входу →</a>';
    echo "</pre>";
    exit;
}

// ============================================================
// Шаг 5: Создаём схему
// ============================================================
echo "📦 Создаём таблицы...\n";

$db->exec("
CREATE TABLE roles (
    id   SERIAL PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE teams (
    id          SERIAL PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    description TEXT
);

CREATE TABLE users (
    id            SERIAL PRIMARY KEY,
    full_name     VARCHAR(100) NOT NULL,
    position      VARCHAR(100),
    project       VARCHAR(100),
    login         VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL
);

CREATE TABLE user_roles (
    id      SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE (user_id, role_id)
);

CREATE TABLE team_members (
    id      SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    team_id INTEGER NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE (user_id, team_id)
);

CREATE TABLE work_logs (
    id         SERIAL PRIMARY KEY,
    user_id    INTEGER     NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    started_at TIMESTAMP   NOT NULL DEFAULT NOW(),
    ended_at   TIMESTAMP   NULL,
    type       VARCHAR(10) NOT NULL DEFAULT 'work'
                   CHECK (type IN ('work', 'break'))
);

CREATE INDEX idx_work_logs_user ON work_logs (user_id, started_at);

CREATE TABLE daily_reports (
    id                 SERIAL PRIMARY KEY,
    user_id            INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    report_date        DATE    NOT NULL,
    total_work_minutes INTEGER NOT NULL DEFAULT 0,
    overtime_minutes   INTEGER NOT NULL DEFAULT 0,
    UNIQUE (user_id, report_date)
);

CREATE TABLE overtime_requests (
    id               SERIAL PRIMARY KEY,
    user_id          INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    report_date      DATE    NOT NULL,
    overtime_minutes INTEGER NOT NULL,
    status           VARCHAR(20) NOT NULL DEFAULT 'pending'
                         CHECK (status IN ('pending', 'approved', 'rejected')),
    reviewed_by      INTEGER NULL REFERENCES users(id),
    reviewed_at      TIMESTAMP NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (user_id, report_date)
);
");

echo "✅ Таблицы созданы.\n\n";

// ============================================================
// Шаг 6: Справочники
// ============================================================
echo "📋 Заполняем роли и команды...\n";

$db->exec("INSERT INTO roles (name) VALUES ('admin'), ('teamlead'), ('employee')");
$db->exec("
    INSERT INTO teams (name, description) VALUES
        ('Frontend Squad', 'Фронтенд и мобильная разработка'),
        ('Backend Guild',  'Бэкенд, DevOps и тестирование')
");
echo "✅ OK\n\n";

// ============================================================
// Шаг 7: Пользователи
// ============================================================
echo "👤 Создаём пользователей...\n";

$users = [
    ['Администратор',   'Administrator',      '—',              'admin',    'admin123', 1],
    ['Алексей Иванов',  'Team Lead',          'Mobile App',     'teamlead', 'lead123',  2],
    ['Мария Петрова',   'Frontend Developer', 'Web Dashboard',  'maria',    'pass123',  3],
    ['Дмитрий Сидоров', 'Backend Developer',  'API Gateway',    'dmitry',   'pass123',  3],
    ['Елена Козлова',   'QA Engineer',        'Testing',        'elena',    'pass123',  3],
    ['Павел Новиков',   'DevOps',             'Infrastructure', 'pavel',    'pass123',  3],
];

$teamMap = [
    'teamlead' => [1, 2],
    'maria'    => [1],
    'dmitry'   => [1],
    'elena'    => [2],
    'pavel'    => [2],
];

foreach ($users as [$fullName, $position, $project, $login, $password, $roleId]) {
    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare("INSERT INTO users (full_name, position, project, login, password_hash) VALUES (?, ?, ?, ?, ?) RETURNING id");
    $stmt->execute([$fullName, $position, $project, $login, $hash]);
    $userId = $stmt->fetchColumn();

    $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$userId, $roleId]);

    foreach ($teamMap[$login] ?? [] as $teamId) {
        $db->prepare("INSERT INTO team_members (user_id, team_id) VALUES (?, ?)")->execute([$userId, $teamId]);
    }

    echo "   ✅ $login / $password\n";
}

echo "\n🎉 База полностью готова!\n\n";
echo '<a href="login.php" style="font-size:1.2em">Перейти к входу →</a>';
echo "</pre>";