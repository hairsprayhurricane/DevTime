# DevTime — Система учёта рабочего времени

> Демо-версия без подключения к БД. Все данные хранятся в PHP-сессии и сбрасываются при её завершении.  
> Проект рассчитан на последующую интеграцию с реляционной БД — все места подстановки запросов помечены комментарием `// TODO`.

---

## Содержание

1. [Роли и права доступа](#1-роли-и-права-доступа)
2. [Архитектура файлов](#2-архитектура-файлов)
3. [Модель данных (заглушка)](#3-модель-данных-заглушка)
4. [Маршруты и навигация](#4-маршруты-и-навигация)
5. [Бизнес-логика по страницам](#5-бизнес-логика-по-страницам)
6. [Управление состоянием (сессия)](#6-управление-состоянием-сессия)
7. [Экспорт данных](#7-экспорт-данных)
8. [Запуск проекта](#8-запуск-проекта)
9. [Интеграция с БД — чеклист](#9-интеграция-с-бд--чеклист)

---

## 1. Роли и права доступа

В системе три роли с жёсткой проверкой на уровне каждой страницы через хелпер `requireRole()`.

| Роль | Константа | Точка входа после логина | Описание |
|---|---|---|---|
| Администратор | `admin` | `admin.php` | Полный CRUD по пользователям и командам. Не имеет доступа к дашборду и командам |
| Тим Лид | `teamlead` | `dashboard.php` | Видит всю команду, управляет командами, экспортирует общий отчёт |
| Сотрудник | `employee` | `dashboard.php` | Видит только себя, управляет только своим статусом, экспортирует только свой отчёт |

**Матрица доступа к страницам:**

| Страница | admin | teamlead | employee |
|---|:---:|:---:|:---:|
| `login.php` | ✅ | ✅ | ✅ |
| `dashboard.php` | ❌ | ✅ | ✅ |
| `team.php` | ❌ | ✅ | ✅ |
| `reports.php` | ❌ | ✅ | ✅ |
| `admin.php` | ✅ | ❌ | ❌ |

Попытка обратиться к странице без нужной роли → редирект на `dashboard.php`.

---

## 2. Архитектура файлов

```
/
├── index.php        — роутер входа: редирект по роли (admin→admin.php, остальные→dashboard.php)
├── login.php        — аутентификация, форма логин/пароль
├── logout.php       — уничтожение сессии + редирект на login.php
│
├── data.php         — ядро: заглушки данных, инициализация сессии, все хелперы
├── actions.php      — обработчик POST-запросов смены статуса (start/stop/break/resume)
│
├── dashboard.php    — главный дашборд (панель управления + таблица учёта времени)
├── team.php         — страница команд (просмотр + CRUD команд для тим лида)
├── reports.php      — страница экспорта отчётов в CSV/Excel
└── admin.php        — административная панель (CRUD пользователей и команд)
```

**Принцип зависимостей:** каждый файл начинается с `require_once 'data.php'`. Никакой другой файл не подключает данные напрямую — только через `data.php`.

---

## 3. Модель данных (заглушка)

Все данные объявлены в `data.php` и сразу записываются в `$_SESSION` при первой инициализации.

### Таблица `users` (`$_SESSION['users']`)

| Поле | Тип | Описание |
|---|---|---|
| `id` | int | Первичный ключ |
| `login` | string | Логин для входа |
| `password` | string | Пароль (plain-text в демо, заменить на хэш) |
| `name` | string | Отображаемое имя (ФИО) |
| `role` | enum | `admin` / `teamlead` / `employee` |
| `team_id` | int\|null | FK → teams.id (null если без команды) |
| `position` | string | Должность |
| `project` | string | Проект |

### Таблица `teams` (`$_SESSION['teams']`)

| Поле | Тип | Описание |
|---|---|---|
| `id` | int | Первичный ключ |
| `name` | string | Название команды |
| `description` | string | Описание |
| `lead_id` | int | FK → users.id (тим лид команды) |

### Таблица `time_logs` (`$_SESSION['time_logs']`)

| Поле | Тип | Описание |
|---|---|---|
| `id` | int | Первичный ключ |
| `user_id` | int | FK → users.id |
| `date` | date | Дата записи |
| `start` | string | Время начала смены |
| `end` | string\|null | Время окончания смены (null если смена активна) |
| `total_today` | float | Итого часов за день |
| `total_week` | float | Итого часов за неделю |
| `overtime` | float | Переработка в часах |

### Оперативное состояние (только сессия, не персистентно)

| Ключ сессии | Тип | Описание |
|---|---|---|
| `$_SESSION['statuses']` | array[user_id → status] | Текущий статус каждого сотрудника: `working` / `resting` / `offline` |
| `$_SESSION['session_starts']` | array[user_id → time] | Время начала текущей активной смены |
| `$_SESSION['user_id']` | int | ID авторизованного пользователя |

---

## 4. Маршруты и навигация

```
GET  /login.php                  — форма входа
POST /login.php                  — обработка логина → редирект по роли

GET  /index.php                  — роутер (редирект по роли или на login)
GET  /logout.php                 — выход

GET  /dashboard.php              — дашборд
POST /actions.php                — смена статуса сотрудника → редирект обратно

GET  /team.php                   — просмотр команд
POST /team.php                   — CRUD команд (form_action: create_team / edit_team / delete_team)

GET  /reports.php                — форма экспорта
POST /reports.php  [export=1]    — скачивание CSV-файла

GET  /admin.php[?tab=users]      — список пользователей
GET  /admin.php?tab=teams        — список команд
POST /admin.php                  — CRUD (form_action: create_user / edit_user / delete_user / create_team / edit_team / delete_team)
```

**Параметры редиректа после `actions.php`:**
- `?notify={action}` — тип действия для показа уведомления (`start` / `stop` / `break` / `resume`)
- `?emp={name}` — имя сотрудника для текста уведомления

---

## 5. Бизнес-логика по страницам

### `dashboard.php`

- Определяет роль текущего пользователя
- **teamlead** → `$visibleEmps` = все сотрудники, `$tableLogs` = все записи
- **employee** → фильтрация по `$user['id']`, видит только свою карточку и свою строку в таблице
- Сотрудник не может менять статус чужой карточки (кнопки заменяются на "Только просмотр")
- Итоговые карточки (всего часов, переработка, работают сейчас) считаются динамически из `$tableLogs`
- Авто-перезагрузка страницы каждые 30 секунд для синхронизации статусов

### `team.php`

- **teamlead** → видит только команды где `lead_id === $user['id']`, может создавать/редактировать/удалять
- **employee** → видит только команды где его `team_id` совпадает с `id` команды, CRUD недоступен
- Статусы участников отображаются в реальном времени из `$_SESSION['statuses']`
- Модальные окна для создания/редактирования — без перезагрузки страницы, отправка через стандартный POST

### `reports.php`

- Принимает `date_from` и `date_to`
- **teamlead** → выгружает все записи из `time_logs`
- **employee** → фильтрует только записи с `user_id === $user['id']`
- При подключении БД фильтрацию по датам перенести в SQL: `WHERE date BETWEEN :from AND :to`

### `admin.php`

- Два таба: `users` и `teams` (переключение через GET-параметр `?tab=`)
- Все CRUD-операции POST → обработка на той же странице → редирект с сохранением таба
- Защита от удаления самого себя: `if ($uid !== $user['id'])`
- При создании нового пользователя сразу инициализируются его статус и время старта в сессии

---

## 6. Управление состоянием (сессия)

Поскольку БД отсутствует, вся мутация данных происходит через `$_SESSION`. Схема работы:

```
Пользователь нажимает кнопку
        ↓
POST → actions.php
        ↓
Изменение $_SESSION['statuses'][$empId]
Изменение $_SESSION['session_starts'][$empId]
        ↓
// TODO: здесь будет INSERT/UPDATE в БД
        ↓
Редирект обратно на dashboard.php?notify=...
        ↓
dashboard.php читает актуальное состояние из $_SESSION
```

**Важно:** при подключении БД сессию следует использовать только для хранения `user_id`. Все остальные данные (`statuses`, `users`, `teams`, `time_logs`) читать из БД при каждом запросе.

---

## 7. Экспорт данных

Экспорт реализован в `reports.php` как прямая отдача CSV через `php://output`.

- Кодировка: UTF-8 с BOM (`\xEF\xBB\xBF`) — обязательно для корректного отображения кириллицы в Microsoft Excel
- Разделитель: `;` (точка с запятой) — стандарт для Excel в русской локали
- Заголовки: `Content-Type: text/csv` + `Content-Disposition: attachment`
- Имя файла: `devtime_report_{date_from}_{date_to}.csv`

При подключении БД заменить источник данных: вместо `$_SESSION['time_logs']` выполнять запрос с фильтром по датам и `user_id`.

---

## 8. Запуск проекта

**Вариант 1 — встроенный сервер PHP (рекомендуется для разработки):**
```bash
cd /путь/к/проекту
php -S localhost:8000
# Открыть http://localhost:8000
```

**Вариант 2 — XAMPP / MAMP:**
Скопировать файлы в `htdocs`, запустить Apache, открыть `http://localhost/devtime`.

**Требования:** PHP >= 8.0 (используется синтаксис match, стрелочные функции, named arguments).

### Тестовые аккаунты

| Логин | Пароль | Роль |
|---|---|---|
| `admin` | `admin123` | Администратор |
| `teamlead` | `lead123` | Тим Лид |
| `maria` | `pass123` | Сотрудник (команда 1) |
| `dmitry` | `pass123` | Сотрудник (команда 1) |
| `elena` | `pass123` | Сотрудник (команда 2) |
| `pavel` | `pass123` | Сотрудник (команда 2) |

---

## 9. Интеграция с БД — чеклист

Все места для подстановки реальных запросов помечены в коде комментарием `// TODO`. Ниже сводный список:

### `data.php`
- [ ] Заменить `$USERS_DB` на `SELECT * FROM users`
- [ ] Заменить `$TEAMS_DB` на `SELECT * FROM teams`
- [ ] Заменить `$TIME_LOGS_DB` на `SELECT * FROM time_logs WHERE date = TODAY()`
- [ ] Загружать `statuses` из поля `status` в таблице `users` или отдельной таблице `user_sessions`

### `login.php`
- [ ] Заменить перебор массива на `SELECT * FROM users WHERE login = ? AND password = hash(?)`
- [ ] Использовать `password_hash()` / `password_verify()` для паролей

### `actions.php`
- [ ] `start` → `INSERT INTO time_logs (user_id, date, start) VALUES (?, NOW(), ?)`
- [ ] `stop` → `UPDATE time_logs SET end = NOW(), total_today = TIMEDIFF(NOW(), start) WHERE user_id = ? AND date = TODAY()`
- [ ] `break` → `INSERT INTO breaks (user_id, start) VALUES (?, NOW())`
- [ ] `resume` → `UPDATE breaks SET end = NOW() WHERE user_id = ? AND end IS NULL`
- [ ] Обновлять `status` в таблице `users` при каждом действии

### `reports.php`
- [ ] Заменить фильтрацию массива на `SELECT ... FROM time_logs WHERE date BETWEEN ? AND ? [AND user_id = ?]`

### `admin.php`
- [ ] `create_user` → `INSERT INTO users (...) VALUES (...)`
- [ ] `edit_user` → `UPDATE users SET ... WHERE id = ?`
- [ ] `delete_user` → `DELETE FROM users WHERE id = ?`
- [ ] `create_team` → `INSERT INTO teams (...) VALUES (...)`
- [ ] `edit_team` → `UPDATE teams SET ... WHERE id = ?`
- [ ] `delete_team` → `DELETE FROM teams WHERE id = ?`

### `team.php`
- [ ] `create_team` / `edit_team` / `delete_team` — аналогично admin.php

---

*DevTime — демо-версия. Версия документа: 1.0*