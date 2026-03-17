<?php
require_once 'data.php';
requireRole('teamlead', 'employee');

$user = getCurrentUser();

// Обработка создания/редактирования команды (только тим лид)
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['role'] === 'teamlead') {
    $postAction = $_POST['form_action'] ?? '';

    if ($postAction === 'create_team') {
        $newTeam = [
            'id'          => max(array_column($_SESSION['teams'], 'id')) + 1,
            'name'        => trim($_POST['team_name'] ?? ''),
            'description' => trim($_POST['team_desc'] ?? ''),
            'lead_id'     => $user['id'],
        ];
        if ($newTeam['name']) {
            // TODO: INSERT INTO teams (name, description, lead_id) VALUES (?,?,?)
            $_SESSION['teams'][] = $newTeam;
            $msg = '✅ Команда «' . htmlspecialchars($newTeam['name']) . '» создана';
        }
    }

    if ($postAction === 'edit_team') {
        $tid  = (int)($_POST['team_id'] ?? 0);
        foreach ($_SESSION['teams'] as &$t) {
            if ($t['id'] === $tid && $t['lead_id'] === $user['id']) {
                // TODO: UPDATE teams SET name=?, description=? WHERE id=?
                $t['name']        = trim($_POST['team_name'] ?? $t['name']);
                $t['description'] = trim($_POST['team_desc'] ?? $t['description']);
                $msg = '✅ Команда обновлена';
                break;
            }
        }
        unset($t);
    }

    if ($postAction === 'delete_team') {
        $tid = (int)($_POST['team_id'] ?? 0);
        // TODO: DELETE FROM teams WHERE id=? AND lead_id=?
        $_SESSION['teams'] = array_values(array_filter($_SESSION['teams'],
            fn($t) => !($t['id'] === $tid && $t['lead_id'] === $user['id'])
        ));
        $msg = '🗑️ Команда удалена';
    }
}

$currentDate  = date('d.m.Y');
$currentTime  = date('H:i');
$daysMap = ['Monday'=>'Понедельник','Tuesday'=>'Вторник','Wednesday'=>'Среда',
            'Thursday'=>'Четверг','Friday'=>'Пятница','Saturday'=>'Суббота','Sunday'=>'Воскресенье'];
$currentDayRu = $daysMap[date('l')];

if ($user['role'] === 'teamlead') {
    $myTeams = getTeamsForLead($user['id']);
} else {
    $myTeams = getTeamsForEmployee($user['id']);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT-Стартап | Команда</title>
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
        .logout-btn { color: #94a3b8; text-decoration: none; font-size: 0.85rem; }
        .logout-btn:hover { color: #fff; }
        .date-bar { background-color: #fff; padding: 15px 0; border-bottom: 1px solid #e2e8f0; }
        .date-bar .container { display: flex; justify-content: space-between; align-items: center; }
        .current-date { display: flex; align-items: center; gap: 10px; color: #64748b; }
        .current-date strong { color: #1e293b; font-size: 1.2rem; }
        .week-info { background-color: #f1f5f9; padding: 8px 15px; border-radius: 50px; font-size: 0.9rem; color: #475569; }
        .page-content { padding: 40px 0; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; flex-wrap: wrap; gap: 15px; }
        .section-header h2 { font-size: 1.8rem; color: #0f172a; }
        /* Кнопка открытия модала */
        .btn-new { padding: 12px 24px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: #fff; border: none; border-radius: 12px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: opacity 0.2s; }
        .btn-new:hover { opacity: 0.9; }
        /* Карточки команд */
        .teams-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 30px; }
        .team-card { background: #fff; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
        .team-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
        .team-title h3 { font-size: 1.3rem; margin-bottom: 5px; }
        .team-title p { color: #64748b; font-size: 0.9rem; }
        .team-actions { display: flex; gap: 8px; }
        .btn-sm { padding: 7px 14px; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: opacity 0.2s; }
        .btn-edit { background: #e0f2fe; color: #0369a1; }
        .btn-edit:hover { background: #bae6fd; }
        .btn-del  { background: #fee2e2; color: #dc2626; }
        .btn-del:hover  { background: #fecaca; }
        /* Список участников */
        .member-list { display: flex; flex-direction: column; gap: 12px; }
        .member-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; }
        .member-info { display: flex; align-items: center; gap: 12px; }
        .member-avatar { width: 38px; height: 38px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 0.85rem; }
        .member-name { font-weight: 600; font-size: 0.95rem; }
        .member-pos { font-size: 0.8rem; color: #64748b; }
        .status-badge { padding: 4px 10px; border-radius: 50px; font-size: 0.8rem; font-weight: 600; }
        .status-working { background-color: #22c55e; color: #fff; }
        .status-resting  { background-color: #f97316; color: #fff; }
        .status-offline  { background-color: #94a3b8; color: #fff; }
        .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
        .empty-state .icon { font-size: 3rem; margin-bottom: 15px; }
        /* Модалы */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 500; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal { background: #fff; border-radius: 20px; padding: 35px; width: 100%; max-width: 480px; box-shadow: 0 30px 60px rgba(0,0,0,0.2); }
        .modal h3 { font-size: 1.4rem; margin-bottom: 25px; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 7px; font-size: 0.9rem; }
        .form-group input, .form-group textarea { width: 100%; padding: 12px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 0.95rem; outline: none; transition: border-color 0.2s; font-family: inherit; }
        .form-group input:focus, .form-group textarea:focus { border-color: #3b82f6; }
        .modal-btns { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { padding: 11px 22px; background: #f1f5f9; color: #475569; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
        .btn-save   { padding: 11px 22px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); color: #fff; border: none; border-radius: 10px; font-weight: 600; cursor: pointer; }
        /* Уведомление */
        .msg-box { background: #f0fdf4; border: 1px solid #86efac; color: #16a34a; padding: 14px 20px; border-radius: 12px; margin-bottom: 25px; font-weight: 500; }
        footer { background-color: #0f172a; color: #94a3b8; padding: 30px 0; text-align: center; }
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
                <li><a href="team.php" class="active">Команда</a></li>
                <li><a href="reports.php">Отчёты</a></li>
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
        <div class="section-header">
            <h2>👥 <?php echo $user['role'] === 'teamlead' ? 'Мои команды' : 'Мои команды'; ?></h2>
            <?php if ($user['role'] === 'teamlead'): ?>
                <button class="btn-new" onclick="openCreateModal()">+ Создать команду</button>
            <?php endif; ?>
        </div>

        <?php if ($msg): ?>
            <div class="msg-box"><?php echo $msg; ?></div>
        <?php endif; ?>

        <?php if (empty($myTeams)): ?>
            <div class="empty-state">
                <div class="icon">🏠</div>
                <p>Команды не найдены</p>
            </div>
        <?php else: ?>
        <div class="teams-grid">
            <?php foreach ($myTeams as $team):
                $members = getMembersOfTeam($team['id']);
            ?>
            <div class="team-card">
                <div class="team-card-header">
                    <div class="team-title">
                        <h3>🏷️ <?php echo htmlspecialchars($team['name']); ?></h3>
                        <p><?php echo htmlspecialchars($team['description']); ?></p>
                    </div>
                    <?php if ($user['role'] === 'teamlead'): ?>
                    <div class="team-actions">
                        <button class="btn-sm btn-edit"
                            onclick="openEditModal(<?php echo $team['id']; ?>,'<?php echo addslashes($team['name']); ?>','<?php echo addslashes($team['description']); ?>')">
                            ✏️ Изменить
                        </button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Удалить команду?')">
                            <input type="hidden" name="form_action" value="delete_team">
                            <input type="hidden" name="team_id"     value="<?php echo $team['id']; ?>">
                            <button type="submit" class="btn-sm btn-del">🗑️</button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="member-list">
                    <?php if (empty($members)): ?>
                        <p style="color:#94a3b8;font-size:0.9rem;">Нет участников</p>
                    <?php else: ?>
                        <?php foreach ($members as $m):
                            $sl = statusLabel($m['status']);
                        ?>
                        <div class="member-row">
                            <div class="member-info">
                                <div class="member-avatar"><?php echo initials($m['name']); ?></div>
                                <div>
                                    <div class="member-name"><?php echo htmlspecialchars($m['name']); ?></div>
                                    <div class="member-pos"><?php echo htmlspecialchars($m['position']); ?></div>
                                </div>
                            </div>
                            <span class="status-badge <?php echo $sl['class']; ?>"><?php echo $sl['text']; ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div style="margin-top:15px; font-size:0.85rem; color:#64748b;">
                    👤 Участников: <?php echo count($members); ?>
                    &nbsp;|&nbsp;
                    🟢 Работают: <?php echo count(array_filter($members, fn($m) => $m['status'] === 'working')); ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<footer>
    <div class="container">
        <p>⚡ DevTime — система учета рабочего времени для IT-стартапов</p>
    </div>
</footer>

<!-- Модал создания -->
<div class="modal-overlay" id="createModal">
    <div class="modal">
        <h3>➕ Новая команда</h3>
        <form method="POST">
            <input type="hidden" name="form_action" value="create_team">
            <div class="form-group">
                <label>Название команды</label>
                <input type="text" name="team_name" placeholder="Например: Frontend Squad" required>
            </div>
            <div class="form-group">
                <label>Описание</label>
                <textarea name="team_desc" rows="3" placeholder="Краткое описание..."></textarea>
            </div>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModals()">Отмена</button>
                <button type="submit" class="btn-save">Создать</button>
            </div>
        </form>
    </div>
</div>

<!-- Модал редактирования -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <h3>✏️ Редактировать команду</h3>
        <form method="POST">
            <input type="hidden" name="form_action" value="edit_team">
            <input type="hidden" name="team_id"     id="editTeamId">
            <div class="form-group">
                <label>Название команды</label>
                <input type="text" name="team_name" id="editTeamName" required>
            </div>
            <div class="form-group">
                <label>Описание</label>
                <textarea name="team_desc" id="editTeamDesc" rows="3"></textarea>
            </div>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModals()">Отмена</button>
                <button type="submit" class="btn-save">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openCreateModal() {
        document.getElementById('createModal').classList.add('open');
    }
    function openEditModal(id, name, desc) {
        document.getElementById('editTeamId').value   = id;
        document.getElementById('editTeamName').value = name;
        document.getElementById('editTeamDesc').value = desc;
        document.getElementById('editModal').classList.add('open');
    }
    function closeModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open'));
    }
    // Закрытие по клику вне модала
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', e => { if (e.target === overlay) closeModals(); });
    });
    // Авто-обновление каждые 30 сек
    setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>
