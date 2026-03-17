# DevTime — Система учёта рабочего времени

> Демо-версия без постоянного хранилища. Все данные живут в PHP-сессии и сбрасываются при её завершении. Точки интеграции с БД помечены комментариями `// TODO` в каждом файле.

---

## 1. Архитектура и файловая структура

```
/
├── index.php        — Точка входа. Роутер по роли.
├── login.php        — Аутентификация. Редирект после логина.
├── logout.php       — Уничтожение сессии. Редирект на login.php.
├── data.php         — Ядро: данные-заглушки, инициализация сессии, хелперы.
├── actions.php      — Обработчик POST-действий над сменами (start/stop/break/resume).
├── dashboard.php    — Дашборд. Панель управления + таблица учёта времени.
├── team.php         — Страница команд.
├── reports.php      — Экспорт отчёта в CSV/Excel.
└── admin.php        — Панель администратора. Полный CRUD пользователей и команд.
```

Приложение построено по принципу **MPA (Multi-Page Application)** — каждая страница самодостаточна, рендерится на сервере, взаимодействие через стандартные HTML-формы с методом `POST`.

---

## 2. Роли и права доступа

| Роль | Идентификатор | Точка входа после логина | Доступные страницы |
|---|---|---|---|
| Администратор | `admin` | `admin.php` | только `admin.php` |
| Тим Лид | `teamlead` | `dashboard.php` | dashboard, team, reports |
| Сотрудник | `employee` | `dashboard.php` | dashboard, team, reports |

Проверка прав реализована через хелперы в `data.php`:
- `requireAuth()` — проверяет наличие активной сессии, иначе редирект на `login.php`
- `requireRole(...$roles)` — проверяет роль текущего пользователя, иначе редирект на `dashboard.php`

Каждая защищённая страница начинается с вызова одного из этих хелперов в самом верху файла.

---

## 3. Хранилище данных (сессия)

Файл `data.php` при первом запуске инициализирует в `$_SESSION` три коллекции:

### `$_SESSION['users']` — массив пользователей
```
id | login | password | name | role | team_id | position | project
```

### `$_SESSION['teams']` — массив команд
```
id | name | description | lead_id
```

### `$_SESSION['time_logs']` — записи рабочего времени
```
id | user_id | date | start | end | total_today | total_week | overtime
```

### `$_SESSION['statuses']` — текущий статус каждого пользователя
```
[user_id => 'working' | 'resting' | 'offline']
```

### `$_SESSION['session_starts']` — время начала текущей смены
```
[user_id => 'HH:MM' | '—']
```

> **Для интеграции с БД:** каждый из этих массивов заменяется на соответствующий SQL-запрос. Все места помечены `// TODO` с подсказкой запроса.

---

## 4. Поток аутентификации

```
GET /index.php
    └─► Сессия есть?
            ├─ НЕТ  ──► login.php (форма логин/пароль)
            │                └─► POST → проверка credentials
            │                          ├─ role=admin    ──► admin.php
            │                          └─ иначе         ──► dashboard.php
            ├─ admin    ──► admin.php
            └─ иначе    ──► dashboard.php
```

---

## 5. Поток смены (действия над временем)

Все кнопки на `dashboard.php` отправляют форму на `actions.php`:

```
POST /actions.php
  Параметры: action, employee_id, employee_name, redirect

  action=start   → статус: offline → working, записать session_start=now()
  action=stop    → статус: * → offline, очистить session_start
  action=break   → статус: working → resting
  action=resume  → статус: resting → working

  → редирект на redirect?notify=<action>&emp=<name>
```

Права: сотрудник может менять **только свой** `employee_id` (принудительно перезаписывается в `actions.php`). Тим лид — любого.

---

## 6. Логика видимости данных по ролям

### Dashboard
| Что | Тим Лид | Сотрудник |
|---|---|---|
| Карточки в панели управления | Все сотрудники | Только своя карточка |
| Строки в таблице учёта времени | Все сотрудники | Только своя строка |
| Итоговые карточки (часы, переработка) | Агрегат по всем | Только свои данные |

### Team
| Что | Тим Лид | Сотрудник |
|---|---|---|
| Видит команды | Все свои (где `lead_id = user.id`) | Команды где состоит (`team_id = team.id`) |
| Статусы участников | Видит | Видит |
| Создание / редактирование / удаление | Да | Нет |

### Reports
Оба могут скачать CSV. Фильтрация на уровне `reports.php`:
- Тим Лид — все записи из `time_logs`
- Сотрудник — только записи где `user_id = session.user_id`

---

## 7. Страница администратора (`admin.php`)

Доступна **исключительно** роли `admin`. Имеет два таба:

**Таб "Сотрудники"** — CRUD по таблице `users`:
- Создать: форма в модальном окне → POST `form_action=create_user`
- Редактировать: модал с предзаполненными полями → POST `form_action=edit_user`
- Удалить: форма с подтверждением → POST `form_action=delete_user` (нельзя удалить себя)

**Таб "Команды"** — CRUD по таблице `teams`:
- Создать: POST `form_action=create_team`
- Редактировать: POST `form_action=edit_team`
- Удалить: POST `form_action=delete_team`

Все изменения применяются к `$_SESSION` немедленно и видны на других страницах в рамках той же сессии.

---

## 8. Экспорт отчёта

`reports.php` при POST с параметром `export=1` отдаёт HTTP-ответ с заголовками:
```
Content-Type: text/csv; charset=utf-8
Content-Disposition: attachment; filename="devtime_report_<from>_<to>.csv"
```

Файл содержит BOM (`\xEF\xBB\xBF`) для корректного отображения кириллицы в Microsoft Excel. Разделитель — точка с запятой (`;`) — стандарт для русской локали Excel.

---

## 9. Тестовые учётные записи

| Логин | Пароль | Роль | Команда |
|---|---|---|---|
| `admin` | `admin123` | Администратор | — |
| `teamlead` | `lead123` | Тим Лид | Frontend Squad, Backend Guild |
| `maria` | `pass123` | Сотрудник | Frontend Squad |
| `dmitry` | `pass123` | Сотрудник | Frontend Squad |
| `elena` | `pass123` | Сотрудник | Backend Guild |
| `pavel` | `pass123` | Сотрудник | Backend Guild |

---

## 10. Запуск локально

```bash
# Требования: PHP >= 8.0
php -v

# Запуск встроенного сервера
cd /путь/к/проекту
php -S localhost:8000

# Открыть в браузере
http://localhost:8000
```

---

## 11. Интеграция с БД — что и где менять

Все места интеграции помечены в коде. Краткая карта:

| Файл | Что заменить |
|---|---|
| `data.php` | Массивы `$USERS_DB`, `$TEAMS_DB`, `$TIME_LOGS_DB` → SELECT-запросы при инициализации сессии |
| `actions.php` | Комментарии TODO → INSERT/UPDATE в `time_logs`, `breaks` |
| `admin.php` | Блоки `if ($action === '...')` → INSERT/UPDATE/DELETE запросы |
| `team.php` | Блоки создания/редактирования команд → INSERT/UPDATE/DELETE |
| `reports.php` | `$logs = $_SESSION['time_logs']` → SELECT с фильтром по датам и user_id |

Рекомендуемая схема БД (минимальная):
```sql
users      (id, login, password_hash, name, role, team_id, position, project)
teams      (id, name, description, lead_id)
time_logs  (id, user_id, date, start, end, total_today, total_week, overtime)
breaks     (id, user_id, start, end)
```