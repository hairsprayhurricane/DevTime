<?php
require_once 'data.php';
requireRole('teamlead', 'employee');

$user = getCurrentUser();

if (isset($_POST['export'])) {
    $dateFrom = $_POST['date_from'] ?? date('Y-m-01');
    $dateTo   = $_POST['date_to']   ?? date('Y-m-d');
    $db       = getDB();

    if ($user['role'] === 'employee') {
        $stmt = $db->prepare("
            SELECT u.full_name, u.position, u.project,
                   dr.report_date,
                   wl_first.started_at AS shift_start,
                   wl_last.ended_at    AS shift_end,
                   dr.total_work_minutes,
                   dr.overtime_minutes
            FROM daily_reports dr
            JOIN users u ON u.id = dr.user_id
            LEFT JOIN LATERAL (
                SELECT started_at FROM work_logs
                WHERE user_id = dr.user_id AND DATE(started_at) = dr.report_date
                  AND type = 'work' ORDER BY started_at ASC LIMIT 1
            ) wl_first ON true
            LEFT JOIN LATERAL (
                SELECT ended_at FROM work_logs
                WHERE user_id = dr.user_id AND DATE(started_at) = dr.report_date
                  AND type = 'work' AND ended_at IS NOT NULL ORDER BY ended_at DESC LIMIT 1
            ) wl_last ON true
            WHERE dr.user_id = ? AND dr.report_date BETWEEN ? AND ?
            ORDER BY dr.report_date DESC
        ");
        $stmt->execute([$user['id'], $dateFrom, $dateTo]);
    } else {
        $stmt = $db->prepare("
            SELECT u.full_name, u.position, u.project,
                   dr.report_date,
                   wl_first.started_at AS shift_start,
                   wl_last.ended_at    AS shift_end,
                   dr.total_work_minutes,
                   dr.overtime_minutes
            FROM daily_reports dr
            JOIN users u ON u.id = dr.user_id
            LEFT JOIN LATERAL (
                SELECT started_at FROM work_logs
                WHERE user_id = dr.user_id AND DATE(started_at) = dr.report_date
                  AND type = 'work' ORDER BY started_at ASC LIMIT 1
            ) wl_first ON true
            LEFT JOIN LATERAL (
                SELECT ended_at FROM work_logs
                WHERE user_id = dr.user_id AND DATE(started_at) = dr.report_date
                  AND type = 'work' AND ended_at IS NOT NULL ORDER BY ended_at DESC LIMIT 1
            ) wl_last ON true
            WHERE dr.report_date BETWEEN ? AND ?
            ORDER BY dr.report_date DESC, u.full_name
        ");
        $stmt->execute([$dateFrom, $dateTo]);
    }

    $rows = $stmt->fetchAll();

    // Сбрасываем буфер чтобы HTML не попал в CSV
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="devtime_report_' . $dateFrom . '_' . $dateTo . '.csv"');

    $out = fopen('php://output', 'w');
    // BOM для корректного открытия в Excel
    fputs($out, "\xEF\xBB\xBF");

    // Передаём escape='' чтобы убрать Deprecated warning в PHP 8.4
    fputcsv($out, ['Сотрудник','Должность','Проект','Дата','Начало смены','Конец смены','Часов за день','Переработка (мин)'], ';', '"', '');

    foreach ($rows as $r) {
        $mins  = (int)$r['total_work_minutes'];
        $h     = intdiv($mins, 60);
        $m     = $mins % 60;
        $hours = "{$h}ч {$m}м";

        fputcsv($out, [
            $r['full_name'],
            $r['position'],
            $r['project'],
            $r['report_date'] ? date('d.m.Y', strtotime($r['report_date'])) : '—',
            $r['shift_start'] ? date('H:i', strtotime($r['shift_start'])) : '—',
            $r['shift_end']   ? date('H:i', strtotime($r['shift_end']))   : '—',
            $hours,
            $r['overtime_minutes'],
        ], ';', '"', '');
    }

    fclose($out);
    exit;
}

$pageTitle = 'Отчёты';
$activeNav = 'reports';
$extraCss  = '
    .page-content { padding: 60px 0; }
    .report-card { background: #fff; border-radius: 24px; padding: 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.06); border: 1px solid #e2e8f0; max-width: 700px; margin: 0 auto; }
    .report-card h2 { font-size: 1.8rem; color: #0f172a; margin-bottom: 10px; }
    .report-card .desc { color: #64748b; margin-bottom: 35px; font-size: 0.95rem; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
    .btn-export { width: 100%; padding: 16px; border: none; border-radius: 12px; background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s; }
    .btn-export:hover { opacity: 0.9; }
    .info-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px; padding: 20px; margin-top: 25px; }
    .info-box h4 { color: #0369a1; font-size: 1rem; margin-bottom: 8px; }
    .info-box ul { list-style: none; padding: 0; }
    .info-box ul li { color: #475569; font-size: 0.9rem; padding: 4px 0; }
    .info-box ul li::before { content: "• "; color: #3b82f6; }
';
require 'layout.php';
?>

<section class="page-content">
    <div class="container">
        <div class="report-card">
            <h2>📊 Экспорт отчёта</h2>
            <p class="desc">
                <?php if ($user['role'] === 'teamlead'): ?>
                    Выгрузка отчёта по всем сотрудникам за выбранный период
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
                    <li>Часов за день (например: 7ч 34м)</li>
                    <li>Переработка в минутах</li>
                    <?php if ($user['role'] === 'teamlead'): ?>
                    <li>Данные по всем сотрудникам</li>
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