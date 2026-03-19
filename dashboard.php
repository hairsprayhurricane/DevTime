<?php
require_once 'data.php';
requireRole('teamlead', 'employee');

$user      = getCurrentUser();

$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterWeek = $_GET['week'] ?? 'current';

$leadId  = $user['role'] === 'teamlead' ? $user['id'] : null;
$allEmps = getEmployees($filterDate, $filterWeek, $leadId);

$visibleEmps = ($user['role'] === 'employee')
    ? array_filter($allEmps, fn($e) => $e['id'] === $user['id'])
    : $allEmps;
$visibleEmps = array_values($visibleEmps);

$tableLogs = ($user['role'] === 'employee')
    ? array_filter($allEmps, fn($e) => $e['id'] === $user['id'])
    : $allEmps;
$tableLogs = array_values($tableLogs);


$pageTitle   = 'Дашборд';
$activeNav   = 'dashboard';
$extraCss    = '
    .action-panel { padding: 40px 0; }
    .action-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px; }
    .action-card { background: linear-gradient(135deg, #fff, #f8fafc); border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; transition: transform 0.3s, box-shadow 0.3s; }
    .action-card:hover { transform: translateY(-5px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
    .employee-info { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; }
    .employee-avatar { width: 70px; height: 70px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-weight: 600; color: #fff; }
    .employee-details h3 { font-size: 1.4rem; margin-bottom: 5px; }
    .employee-details p { color: #64748b; font-size: 0.95rem; }
    .current-status { background-color: #f1f5f9; padding: 15px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
    .time-display { font-size: 1.5rem; font-weight: 700; color: #1e293b; }
    .action-buttons { display: flex; gap: 15px; }
    .stats-section { padding: 40px 0; background-color: #fff; }
    .date-picker { display: flex; gap: 10px; }
    .date-picker select, .date-picker input { padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; background-color: #fff; }
    #live-time { font-weight: 700; }
';
require 'layout.php';
?>

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
            <form method="GET" action="dashboard.php" class="date-picker">
                <select name="week" onchange="this.form.submit()">
                    <option value="current" <?php echo $filterWeek === 'current' ? 'selected' : ''; ?>>Текущая неделя</option>
                    <option value="prev"    <?php echo $filterWeek === 'prev'    ? 'selected' : ''; ?>>Прошлая неделя</option>
                    <option value="month"   <?php echo $filterWeek === 'month'   ? 'selected' : ''; ?>>Текущий месяц</option>
                </select>
                <input type="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>"
                       onchange="this.form.submit()">
            </form>
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
                        <th><?php echo $filterDate === date('Y-m-d') ? 'Сегодня' : date('d.m', strtotime($filterDate)); ?></th>
                        <th>За период</th>
                        <th>Переработка</th>
                        <th>Прогресс (40ч)</th>
                </thead>
                <tbody>
                    <?php foreach ($tableLogs as $emp):
                        $sl       = statusLabel($emp['status']);
                        $progress = min(100, ($emp['total_week'] / 40) * 100);
                        $ovtReq   = ($emp['overtime'] > 0) ? getOvertimeRequest($emp['id']) : null;
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
                        <td>
                            <?php if ($ovtReq): ?>
                                <?php if ($ovtReq['status'] === 'pending' && $user['role'] === 'teamlead'): ?>
                                    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">
                                        <span class="hours-warning"><?php echo $emp['overtime']; ?> ч</span>
                                        <form method="POST" action="actions.php" style="display:inline">
                                            <input type="hidden" name="action"     value="approve_overtime">
                                            <input type="hidden" name="request_id" value="<?php echo $ovtReq['id']; ?>">
                                            <input type="hidden" name="redirect"   value="dashboard.php">
                                            <button type="submit" style="padding:3px 8px;background:#22c55e;color:#fff;border:none;border-radius:6px;font-size:0.75rem;cursor:pointer;">✓</button>
                                        </form>
                                        <form method="POST" action="actions.php" style="display:inline">
                                            <input type="hidden" name="action"     value="reject_overtime">
                                            <input type="hidden" name="request_id" value="<?php echo $ovtReq['id']; ?>">
                                            <input type="hidden" name="redirect"   value="dashboard.php">
                                            <button type="submit" style="padding:3px 8px;background:#ef4444;color:#fff;border:none;border-radius:6px;font-size:0.75rem;cursor:pointer;">✗</button>
                                        </form>
                                    </div>
                                <?php elseif ($ovtReq['status'] === 'approved'): ?>
                                    <span style="color:#16a34a;font-weight:600;"><?php echo $emp['overtime']; ?> ч ✓</span>
                                <?php elseif ($ovtReq['status'] === 'rejected'): ?>
                                    <span style="color:#94a3b8;text-decoration:line-through;"><?php echo $emp['overtime']; ?> ч</span>
                                <?php else: ?>
                                    <span class="hours-warning"><?php echo $emp['overtime']; ?> ч</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="hours-warning"><?php echo $emp['overtime']; ?> ч</span>
                            <?php endif; ?>
                        </td>
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

<script>
    // Живые часы
    function updateClock() {
        const now = new Date();
        document.getElementById('live-time').textContent =
            String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
    }
    setInterval(updateClock, 60000);

    // Авто-обновление страницы каждые 30 сек для синхронизации статусов
    setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>
