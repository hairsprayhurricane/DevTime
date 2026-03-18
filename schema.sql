-- ============================================================
-- DevTime — PostgreSQL Schema
-- ============================================================

-- CREATE DATABASE devtime OWNER postgres;

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

-- ============================================================
-- Роли
-- ============================================================
INSERT INTO roles (name) VALUES ('admin'), ('teamlead'), ('employee');

-- ============================================================
-- Команды
-- ============================================================
INSERT INTO teams (name, description) VALUES
    ('Frontend Squad', 'Фронтенд и мобильная разработка'),
    ('Backend Guild',  'Бэкенд, DevOps и тестирование');

-- ============================================================
-- Пользователи (пароли: admin=admin123, teamlead=lead123, остальные=pass123)
-- ============================================================
INSERT INTO users (full_name, position, project, login, password_hash) VALUES
    ('Администратор',   'Administrator',      '—',              'admin',    '$2b$10$LFHFmInz7..chsMq7qyqqevNLtR9s/ZfxOI033ndDz3UlWfQiUUxy'),
    ('Алексей Иванов',  'Team Lead',          'Mobile App',     'teamlead', '$2b$10$MVm/Sfcatx/xmh79jFUZkO1o3PS2ZcF2afLQSJru/8JFadgl27ghK'),
    ('Мария Петрова',   'Frontend Developer', 'Web Dashboard',  'maria',    '$2b$10$T0ZwLGW/UPDjxxyhvcN7vu/0Wuu8R90vHrNn3Mc90lrgLQe7YHG0K'),
    ('Дмитрий Сидоров', 'Backend Developer',  'API Gateway',    'dmitry',   '$2b$10$T0ZwLGW/UPDjxxyhvcN7vu/0Wuu8R90vHrNn3Mc90lrgLQe7YHG0K'),
    ('Елена Козлова',   'QA Engineer',        'Testing',        'elena',    '$2b$10$T0ZwLGW/UPDjxxyhvcN7vu/0Wuu8R90vHrNn3Mc90lrgLQe7YHG0K'),
    ('Павел Новиков',   'DevOps',             'Infrastructure', 'pavel',    '$2b$10$T0ZwLGW/UPDjxxyhvcN7vu/0Wuu8R90vHrNn3Mc90lrgLQe7YHG0K');

-- ============================================================
-- Роли пользователей
-- ============================================================
INSERT INTO user_roles (user_id, role_id) VALUES
    (1, 1), -- admin    → admin
    (2, 2), -- teamlead → teamlead
    (3, 3), -- maria    → employee
    (4, 3), -- dmitry   → employee
    (5, 3), -- elena    → employee
    (6, 3); -- pavel    → employee

-- ============================================================
-- Участники команд
-- ============================================================
INSERT INTO team_members (user_id, team_id) VALUES
    (2, 1), -- teamlead → Frontend Squad
    (3, 1), -- maria    → Frontend Squad
    (4, 1), -- dmitry   → Frontend Squad
    (2, 2), -- teamlead → Backend Guild
    (5, 2), -- elena    → Backend Guild
    (6, 2); -- pavel    → Backend Guild