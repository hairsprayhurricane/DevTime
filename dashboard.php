<?php
require_once 'data.php';
requireRole('teamlead', 'employee');

$user      = getCurrentUser();
$allEmps   = getEmployees();

// Сотрудник видит только себя
$visibleEmps = ($user['role'] === 'employee')
    ? array_filter($allEmps, fn($e) => $e['id'] === $user['id'])
    : $allEmps;
$visibleEmps = array_values($visibleEmps);

// Учёт рабочего времени
$tableLogs = ($user['role'] === 'employee')
    ? array_filter($allEmps, fn($e) => $e['id'] === $user['id'])
    : $allEmps;
$tableLogs = array_values($tableLogs);

$currentDate  = date('d.m.Y');
$currentTime  = date('H:i');
$daysMap = ['Monday'=>'Понедельник','Tuesday'=>'Вторник','Wednesday'=>'Среда',
            'Thursday'=>'Четверг','Friday'=>'Пятница','Saturday'=>'Суббота','Sunday'=>'Воскресенье'];
$currentDayRu = $daysMap[date('l')];

// Уведомление после action
$notify     = $_GET['notify'] ?? '';
$notifyEmp  = $_GET['emp'] ?? '';
$notifyMsgs = [
    'start'  => 'Начало работы отмечено',
    'stop'   => 'Окончание работы отмечено',
    'break'  => 'Начало перерыва отмечено',
    'resume' => 'Возврат с перерыва отмечен',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT-Стартап | Дашборд</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1e293b; background-color: #f8fafc; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        header { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; padding: 20px 0; box-shadow: 0 4px 20px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        header .container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .logo h1 { font-size: 1.8rem; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo p { font-size: 0.9rem; color: #94a3b8; }
        nav ul { display: flex; list-style: none; gap: 30px; }
        nav ul li a { color: #cbd5e1; text-decoration: none; font-weight: 500; transition: color 0.3s; padding: 5px 0; }
        nav ul li a:hover, nav ul li a.active { color: #fff; border-bottom: 2px solid #60a5fa; }
        .user-info { display: flex; align-items: center; gap: 15px; background-color: rgba(255,255,255,0.1); padding: 10px 20px; border-radius: 50px; }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .logout-btn { color: #94a3b8; text-decoration: none; font-size: 0.85rem; transition: color 0.2s; }
        .logout-btn:hover { color: #fff; }
        .date-bar { background-color: #fff; padding: 15px 0; border-bottom: 1px solid #e2e8f0; }
        .date-bar .container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .current-date { display: flex; align-items: center; gap: 10px; color: #64748b; }
        .current-date strong { color: #1e293b; font-size: 1.2rem; }
        .week-info { background-color: #f1f5f9; padding: 8px 15px; border-radius: 50px; font-size: 0.9rem; color: #475569; }
        .action-panel { padding: 40px 0; }
        .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
        .action-card { background: linear-gradient(135deg, #fff, #f8fafc); border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: transform 0.3s, box-shadow 0.3s; }
        .action-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .employee-info { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; }
        .employee-avatar { width: 70px; height: 70px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 600; color: #fff; }
        .employee-details h3 { font-size: 1.4rem; margin-bottom: 5px; }
        .employee-details p { color: #64748b; font-size: 0.95rem; }
        .current-status { background-color: #f1f5f9; padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .status-badge { padding: 5px 12px; border-radius: 50px; font-size: 0.85rem; font-weight: 600; }
        .status-working { background-color: #22c55e; color: #fff; }
        .status-resting { background-color: #f97316; color: #fff; }
        .status-offline { background-color: #94a3b8; color: #fff; }
        .time-display { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
        .action-buttons { display: flex; gap: 15px; }
        .btn { flex: 1; padding: 15px; border: none; border-radius: 12px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-start { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
        .btn-start:hover { background: linear-gradient(135deg, #16a34a, #15803d); transform: scale(1.02); }
        .btn-stop { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
        .btn-stop:hover { background: linear-gradient(135deg, #dc2626, #b91c1c); transform: scale(1.02); }
        .btn-break { background: linear-gradient(135deg, #f97316, #ea580c); color: #fff; }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
        .stats-section { padding: 40px 0; background-color: #fff; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 20px; }
        .section-header h2 { font-size: 1.8rem; color: #0f172a; }
        .date-picker { display: flex; gap: 10px; }
        .date-picker select, .date-picker input { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; background-color: #fff; }
        .table-container { background-color: #fff; border-radius: 20px; border: 1px solid #e2e8f0; overflow-x: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        th { background-color: #f8fafc; padding: 20px 15px; text-align: left; font-weight: 600; color: #475569; border-bottom: 2px solid #e2e8f0; }
        td { padding: 15px; border-bottom: 1px solid #e2e8f0; }
        tr:last-child td { border-bottom: none; }
        tr:hover td { background-color: #f8fafc; }
        .employee-cell { display: flex; align-items: center; gap: 12px; }
        .employee-mini-avatar { width: 35px; height: 35px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 0.9rem; }
        .project-tag { background-color: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 50px; font-size: 0.85rem; font-weight: 500; }
        .hours-positive { color: #16a34a; font-weight: 600; }
        .hours-warning { color: #f97316; font-weight: 600; }
        .progress-bar { width: 100%; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #3b82f6, #8b5cf6); border-radius: 4px; }
        .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; }
        .summary-card { background: linear-gradient(135deg, #fff, #f8fafc); padding: 20px; border-radius: 16px; border: 1px solid #e2e8f0; }
        .summary-card h4 { color: #64748b; font-size: 0.9rem; margin-bottom: 10px; }
        .summary-card .value { font-size: 2rem; font-weight: 700; color: #0f172a; }
        .summary-card .sub { color: #94a3b8; font-size: 0.85rem; margin-top: 5px; }
        footer { background-color: #0f172a; color: #94a3b8; padding: 30px 0; text-align: center; }
        .notification { position: fixed; bottom: 20px; right: 20px; background-color: #1e293b; color: #fff; padding: 15px 25px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 1000; border-left: 4px solid #22c55e; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .role-badge { font-size: 0.75rem; padding: 3px 10px; border-radius: 50px; background: rgba(255,255,255,0.15); color: #94a3b8; }
        /* Live clock */
        #live-time { font-weight: 700; }
    </style>
</head>
<body>

<header>
    <div class="container">
        <div class="logo">
            <h1>⚡ DevTime</h1>
            <p>Учет рабочего времени IT-стартапа</p>
        </div>
        <nav>
            <ul>
                <li><a href="dashboard.php" class="active">Дашборд</a></li>
                <li><a href="team.php">Команда</a></li>
                <li><a href="reports.php">Отчёты</a></li>
                <?php if ($user['role'] === 'admin'): ?>
                    <li><a href="admin.php">Администрирование</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="user-info">
            <span><?php echo htmlspecialchars($user['name']); ?></span>
            <span class="role-badge"><?php echo $user['role'] === 'teamlead' ? 'Тим Лид' : 'Сотрудник'; ?></span>
            <div class="user-avatar"><?php echo initials($user['name']); ?></div>
            <a href="logout.php" class="logout-btn">Выйти</a>
        </div>
    </div>
</header>

<div class="date-bar">
    <div class="container">
        <div class="current-date">
            <span>📅</span>
            <strong><?php echo $currentDate; ?></strong>
            <span>(<?php echo $currentDayRu; ?>)</span>
        </div>
        <div class="week-info">
            ⏱️ Текущее время: <span id="live-time"><?php echo $currentTime; ?></span> | Неделя <?php echo date('W'); ?>
        </div>
    </div>
</div>

<!-- Панель управления -->
<section class="action-panel">
    <div class="container">
        <h2 style="margin-bottom: 30px; color: #0f172a;">
            <?php echo $user['role'] === 'teamlead' ? '👥 Панель управления командой' : '🧑‍💻 Моя панель'; ?>
        </h2>
        <div class="action-grid">
            <?php foreach ($visibleEmps as $emp):
                $sl = statusLabel($emp['status']);
                // Сотрудник управляет только своими кнопками
                $canControl = ($user['role'] === 'teamlead' || $emp['id'] === $user['id']);
            ?>
            <div class="action-card" id="card-<?php echo $emp['id']; ?>">
                <div class="employee-info">
                    <div class="employee-avatar"><?php echo initials($emp['name']); ?></div>
                    <div class="employee-details">
                        <h3><?php echo htmlspecialchars($emp['name']); ?></h3>
                        <p><?php echo htmlspecialchars($emp['position']); ?> • <?php echo htmlspecialchars($emp['project']); ?></p>
                    </div>
                </div>

                <div class="current-status">
                    <span class="status-badge <?php echo $sl['class']; ?>"><?php echo $sl['text']; ?></span>
                    <span class="time-display">
                        <?php if ($emp['status'] === 'working'): ?>
                            <?php echo htmlspecialchars($emp['current_session']); ?>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if ($canControl): ?>
                <form method="POST" action="actions.php" class="action-buttons">
                    <input type="hidden" name="employee_id"   value="<?php echo $emp['id']; ?>">
                    <input type="hidden" name="employee_name" value="<?php echo htmlspecialchars($emp['name']); ?>">
                    <input type="hidden" name="redirect"      value="dashboard.php">

                    <?php if ($emp['status'] === 'working'): ?>
                        <button type="submit" name="action" value="stop"   class="btn btn-stop">⏹️ Закончить</button>
                        <button type="submit" name="action" value="break"  class="btn btn-break">☕ Перерыв</button>
                    <?php elseif ($emp['status'] === 'resting'): ?>
                        <button type="submit" name="action" value="resume" class="btn btn-start">▶️ Вернуться</button>
                        <button type="submit" name="action" value="stop"   class="btn btn-stop">⏹️ Закончить</button>
                    <?php else: ?>
                        <button type="submit" name="action" value="start"  class="btn btn-start">▶️ Начать работу</button>
                        <button type="button" class="btn btn-stop" disabled>⏹️ Закончить</button>
                    <?php endif; ?>
                </form>
                <?php else: ?>
                <div style="text-align:center; color:#94a3b8; font-size:0.9rem; padding-top:10px;">
                    👁️ Только просмотр
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Учёт рабочего времени -->
<section class="stats-section">
    <div class="container">
        <div class="section-header">
            <h2>📊 Учет рабочего времени</h2>
            <div class="date-picker">
                <select>
                    <option>Текущая неделя</option>
                    <option>Прошлая неделя</option>
                    <option>Текущий месяц</option>
                </select>
                <input type="date" value="<?php echo date('Y-m-d'); ?>">
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Сотрудник</th>
                        <th>Должность</th>
                        <th>Проект</th>
                        <th>Статус</th>
                        <th>Начало смены</th>
                        <th>Сегодня</th>
                        <th>За неделю</th>
                        <th>Переработка</th>
                        <th>Прогресс (40ч)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableLogs as $emp):
                        $sl       = statusLabel($emp['status']);
                        $progress = min(100, ($emp['total_week'] / 40) * 100);
                    ?>
                    <tr>
                        <td>
                            <div class="employee-cell">
                                <div class="employee-mini-avatar"><?php echo initials($emp['name']); ?></div>
                                <?php echo htmlspecialchars($emp['name']); ?>
                            </div>
                        </td>
                        <td><?php echo htmlspecialchars($emp['position']); ?></td>
                        <td><span class="project-tag"><?php echo htmlspecialchars($emp['project']); ?></span></td>
                        <td><span class="status-badge <?php echo $sl['class']; ?>"><?php echo $sl['text']; ?></span></td>
                        <td><?php echo htmlspecialchars($emp['current_session']); ?></td>
                        <td class="hours-positive"><?php echo $emp['total_today']; ?> ч</td>
                        <td class="<?php echo $emp['total_week'] > 40 ? 'hours-warning' : 'hours-positive'; ?>">
                            <?php echo $emp['total_week']; ?> ч
                        </td>
                        <td class="hours-warning"><?php echo $emp['overtime']; ?> ч</td>
                        <td style="min-width:150px;">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width:<?php echo $progress; ?>%"></div>
                            </div>
                            <div style="font-size:0.8rem;color:#64748b;margin-top:5px;"><?php echo round($progress); ?>% от нормы</div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Итоги -->
        <div class="summary-cards">
            <?php
                $totalWeek   = array_sum(array_column($tableLogs, 'total_week'));
                $totalOvt    = array_sum(array_column($tableLogs, 'overtime'));
                $workingNow  = count(array_filter($tableLogs, fn($e) => $e['status'] === 'working'));
                $cnt         = count($tableLogs);
            ?>
            <div class="summary-card">
                <h4>Всего часов за неделю</h4>
                <div class="value"><?php echo $totalWeek; ?></div>
                <div class="sub">по всем сотрудникам</div>
            </div>
            <div class="summary-card">
                <h4>Переработка</h4>
                <div class="value"><?php echo $totalOvt; ?></div>
                <div class="sub">часов сверх нормы</div>
            </div>
            <div class="summary-card">
                <h4>Сейчас работают</h4>
                <div class="value"><?php echo $workingNow; ?></div>
                <div class="sub">из <?php echo $cnt; ?> сотрудников</div>
            </div>
            <div class="summary-card">
                <h4>Средняя нагрузка</h4>
                <div class="value"><?php echo $cnt > 0 ? round($totalWeek / $cnt, 1) : 0; ?></div>
                <div class="sub">часов на сотрудника</div>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <p>⚡ DevTime — система учета рабочего времени для IT-стартапов</p>
        <p style="margin-top:10px;font-size:0.9rem;">© <?php echo date('Y'); ?> Все права защищены</p>
    </div>
</footer>

<?php if ($notify && isset($notifyMsgs[$notify])): ?>
<div class="notification" id="notif">
    <strong>✅ Готово</strong>
    <p style="margin-top:5px;font-size:0.9rem;"><?php echo htmlspecialchars($notifyEmp); ?>: <?php echo $notifyMsgs[$notify]; ?></p>
    <p style="font-size:0.8rem;color:#94a3b8;margin-top:5px;">Время: <?php echo date('H:i:s'); ?></p>
</div>
<script>
    setTimeout(() => {
        const n = document.getElementById('notif');
        if (n) { n.style.opacity = '0'; n.style.transition = 'opacity 0.5s'; setTimeout(() => n.remove(), 500); }
    }, 3000);
</script>
<?php endif; ?>

<script>
    // Живые часы
    function updateClock() {
        const now = new Date();
        document.getElementById('live-time').textContent =
            String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
    }
    setInterval(updateClock, 10000);

    // Авто-обновление страницы каждые 30 сек для синхронизации статусов
    setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>
