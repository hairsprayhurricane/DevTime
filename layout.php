<?php
/**
 * Общий layout: header + date-bar.
 * Подключается после require_once 'data.php' и получения $user.
 *
 * Параметры (должны быть определены до include):
 *   $pageTitle   — строка заголовка вкладки
 *   $activeNav   — 'dashboard' | 'team' | 'reports' | 'admin'
 *   $headerTheme — 'default' | 'admin'  (влияет на цвет логотипа)
 */

$pageTitle   = $pageTitle   ?? 'DevTime';
$activeNav   = $activeNav   ?? '';
$headerTheme = $headerTheme ?? 'default';

$logoGradient = $headerTheme === 'admin'
    ? 'linear-gradient(135deg, #f59e0b, #ef4444)'
    : 'linear-gradient(135deg, #60a5fa, #a78bfa)';

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
    <title>IT-Стартап | <?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="style.css">
    <?php if (!empty($extraCss)): ?>
    <style><?php echo $extraCss; ?></style>
    <?php endif; ?>
</head>
<body>

<header>
    <div class="container">
        <div class="logo">
            <h1 style="background:<?php echo $logoGradient; ?>;-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">
                <?php echo $headerTheme === 'admin' ? '🛡️ DevTime Admin' : '⚡ DevTime'; ?>
            </h1>
            <p><?php echo $headerTheme === 'admin' ? 'Панель администратора' : 'Учет рабочего времени IT-стартапа'; ?></p>
        </div>
        <?php if ($headerTheme !== 'admin'): ?>
        <nav>
            <ul>
                <li><a href="dashboard.php" <?php echo $activeNav === 'dashboard' ? 'class="active"' : ''; ?>>Дашборд</a></li>
                <li><a href="team.php"      <?php echo $activeNav === 'team'      ? 'class="active"' : ''; ?>>Команда</a></li>
                <li><a href="reports.php"   <?php echo $activeNav === 'reports'   ? 'class="active"' : ''; ?>>Отчёты</a></li>
            </ul>
        </nav>
        <?php endif; ?>
        <div class="user-info">
            <span><?php echo htmlspecialchars($user['name']); ?></span>
            <?php if ($headerTheme !== 'admin'): ?>
                <span class="role-badge"><?php echo $user['role'] === 'teamlead' ? 'Тим Лид' : 'Сотрудник'; ?></span>
            <?php endif; ?>
            <div class="user-avatar" style="<?php echo $headerTheme === 'admin' ? 'background:linear-gradient(135deg,#f59e0b,#ef4444)' : ''; ?>">
                <?php echo $headerTheme === 'admin' ? 'АД' : initials($user['name']); ?>
            </div>
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
        <div class="week-info" <?php echo $headerTheme === 'admin' ? 'style="background:#fef3c7;color:#92400e;"' : ''; ?>>
            <?php if ($headerTheme === 'admin'): ?>
                🛡️ Режим администратора | <?php echo $currentTime; ?>
            <?php else: ?>
                ⏱️ Текущее время: <span id="live-time"><?php echo $currentTime; ?></span> | Неделя <?php echo date('W'); ?>
            <?php endif; ?>
        </div>
    </div>
</div>
