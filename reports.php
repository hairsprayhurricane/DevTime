<?php
require_once 'data.php';
requireRole('teamlead', 'employee');

$user = getCurrentUser();

// Обработка экспорта
if (isset($_POST['export'])) {
    $dateFrom = $_POST['date_from'] ?? date('Y-m-01');
    $dateTo   = $_POST['date_to']   ?? date('Y-m-d');

    // TODO: SELECT * FROM time_logs WHERE date BETWEEN ? AND ? [AND user_id=?]
    $logs = $_SESSION['time_logs'];

    // Для сотрудника — только свои записи
    if ($user['role'] === 'employee') {
        $logs = array_filter($logs, fn($l) => $l['user_id'] === $user['id']);
    }

    // Обогатить именами
    $rows = [];
    foreach ($logs as $log) {
        $empUser = null;
        foreach ($_SESSION['users'] as $u) {
            if ($u['id'] === $log['user_id']) { $empUser = $u; break; }
        }
        if (!$empUser) continue;
        $rows[] = [
            'name'        => $empUser['name'],
            'position'    => $empUser['position'],
            'project'     => $empUser['project'],
            'date'        => $log['date'],
            'start'       => $log['start'],
            'end'         => $log['end'] ?? '—',
            'total_today' => $log['total_today'],
            'total_week'  => $log['total_week'],
            'overtime'    => $log['overtime'],
        ];
    }

    // Отдаём CSV (открывается Excel)
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="devtime_report_' . $dateFrom . '_' . $dateTo . '.csv"');
    $out = fopen('php://output', 'w');
    // BOM для корректного отображения кириллицы в Excel
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Сотрудник','Должность','Проект','Дата','Начало','Конец','Сегодня (ч)','За неделю (ч)','Переработка (ч)'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['name'], $r['position'], $r['project'], $r['date'],
            $r['start'], $r['end'], $r['total_today'], $r['total_week'], $r['overtime']
        ], ';');
    }
    fclose($out);
    exit;
}

$currentDate  = date('d.m.Y');
$currentTime  = date('H:i');
$daysMap = ['Monday'=>'Понедельник','Tuesday'=>'Вторник','Wednesday'=>'Среда',
            'Thursday'=>'Четверг','Friday'=>'Пятница','Saturday'=>'Суббота','Sunday'=>'Воскресенье'];
$currentDayRu = $daysMap[date('l')];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT-Стартап | Отчёты</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #1e293b; background-color: #f8fafc; }
        .container { max-width: 1400px; margin: 0 auto; padding: 0 20px; }
        header { background: linear-gradient(135deg, #0f172a, #1e293b); color: #fff; padding: 20px 0; box-shadow: 0 4px 20px rgba(0,0,0,0.1); position: sticky; top: 0; z-index: 100; }
        header .container { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .logo h1 { font-size: 1.8rem; background: linear-gradient(135deg, #60a5fa, #a78bfa); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .logo p { font-size: 0.9rem; color: #94a3b8; }
        nav ul { display: flex; list-style: none; gap: 30px; }
        nav ul li a { color: #cbd5e1; text-decoration: none; font-weight: 500; padding: 5px 0; }
        nav ul li a:hover, nav ul li a.active { color: #fff; border-bottom: 2px solid #60a5fa; }
        .user-info { display: flex; align-items: center; gap: 15px; background-color: rgba(255,255,255,0.1); padding: 10px 20px; border-radius: 50px; }
        .user-avatar { width: 40px; height: 40px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .logout-btn { color: #94a3b8; text-decoration: none; font-size: 0.85rem; }
        .date-bar { background-color: #fff; padding: 15px 0; border-bottom: 1px solid #e2e8f0; }
        .date-bar .container { display: flex; justify-content: space-between; align-items: center; }
        .current-date { display: flex; align-items: center; gap: 10px; color: #64748b; }
        .current-date strong { color: #1e293b; font-size: 1.2rem; }
        .week-info { background-color: #f1f5f9; padding: 8px 15px; border-radius: 50px; font-size: 0.9rem; color: #475569; }
        .page-content { padding: 60px 0; }
        .report-card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; max-width: 700px; margin: 0 auto; }
        .report-card h2 { font-size: 1.8rem; color: #0f172a; margin-bottom: 10px; }
        .report-card .desc { color: #64748b; margin-bottom: 35px; font-size: 0.95rem; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
        .form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 7px; font-size: 0.9rem; }
        .form-group input { width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
        .form-group input:focus { border-color: #3b82f6; }
        .btn-export { width: 100%; padding: 16px; border: none; border-radius: 12px; background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-export:hover { opacity: 0.9; }
        .info-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 20px; margin-top: 25px; }
        .info-box h4 { color: #0369a1; font-size: 1rem; margin-bottom: 8px; }
        .info-box ul { list-style: none; padding: 0; }
        .info-box ul li { color: #475569; font-size: 0.9rem; padding: 4px 0; }
        .info-box ul li::before { content: '• '; color: #3b82f6; }
        footer { background-color: #0f172a; color: #94a3b8; padding: 30px 0; text-align: center; margin-top: 60px; }
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
                <li><a href="dashboard.php">Дашборд</a></li>
                <li><a href="team.php">Команда</a></li>
                <li><a href="reports.php" class="active">Отчёты</a></li>
            </ul>
        </nav>
        <div class="user-info">
            <span><?php echo htmlspecialchars($user['name']); ?></span>
            <div class="user-avatar"><?php echo initials($user['name']); ?></div>
            <a href="logout.php" class="logout-btn">Выйти</a>
        </div>
    </div>
</header>

<div class="date-bar">
    <div class="container">
        <div class="current-date">
            <span>📅</span><strong><?php echo $currentDate; ?></strong><span>(<?php echo $currentDayRu; ?>)</span>
        </div>
        <div class="week-info">⏱️ <?php echo $currentTime; ?> | Неделя <?php echo date('W'); ?></div>
    </div>
</div>

<section class="page-content">
    <div class="container">
        <div class="report-card">
            <h2>📊 Экспорт отчёта</h2>
            <p class="desc">
                <?php if ($user['role'] === 'teamlead'): ?>
                    Выгрузка отчёта по всем закреплённым сотрудникам за выбранный период
                <?php else: ?>
                    Выгрузка персонального отчёта по рабочему времени за выбранный период
                <?php endif; ?>
            </p>

            <form method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label>📅 Дата от</label>
                        <input type="date" name="date_from" value="<?php echo date('Y-m-01'); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>📅 Дата до</label>
                        <input type="date" name="date_to" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
                <button type="submit" name="export" value="1" class="btn-export">
                    ⬇️ Скачать отчёт (Excel / CSV)
                </button>
            </form>

            <div class="info-box">
                <h4>📋 Что будет в отчёте:</h4>
                <ul>
                    <li>Сотрудник, должность, проект</li>
                    <li>Дата, начало и конец смены</li>
                    <li>Часы за день, за неделю</li>
                    <li>Переработка</li>
                    <?php if ($user['role'] === 'teamlead'): ?>
                    <li>Данные по всем закреплённым сотрудникам</li>
                    <?php else: ?>
                    <li>Только ваши личные записи</li>
                    <?php endif; ?>
                    <li>Формат CSV — открывается в Microsoft Excel и Google Sheets</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<footer>
    <div class="container">
        <p>⚡ DevTime — система учета рабочего времени для IT-стартапов</p>
    </div>
</footer>
</body>
</html>
