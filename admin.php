<?php
require_once 'data.php';
requireRole('admin');

$user = getCurrentUser();
$msg  = '';
$tab  = $_GET['tab'] ?? 'users';
$db   = getDB();

// ============================================================
// CRUD — Пользователи и Команды
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['form_action'] ?? '';

    // --- Создать пользователя ---
    if ($action === 'create_user') {
        $login    = trim($_POST['login']    ?? '');
        $name     = trim($_POST['name']     ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = $_POST['role']     ?? 'employee';
        $teamId   = $_POST['team_id']  !== '' ? (int)$_POST['team_id'] : null;
        $position = trim($_POST['position'] ?? '');
        $project  = trim($_POST['project']  ?? '');

        if ($login && $name && $password) {
            // Проверка длины полей
            if (mb_strlen($login) > 50 || mb_strlen($name) > 100 || mb_strlen($password) > 50
                || mb_strlen($position) > 50 || mb_strlen($project) > 50) {
                $msg = '❌ Логин, пароль, должность и проект не должны превышать 50 символов';
            } else {
                // Проверка уникальности логина
                $check = $db->prepare("SELECT id FROM users WHERE login = ?");
                $check->execute([$login]);
                if ($check->fetch()) {
                    $msg = '❌ Пользователь с логином «' . htmlspecialchars($login) . '» уже существует';
                } else {
                    // Проверка: в команде не может быть 2 тимлида
                    $teamConflict = false;
                    if ($teamId && $role === 'teamlead') {
                        $leadCheck = $db->prepare("
                            SELECT COUNT(*) FROM team_members tm
                            JOIN user_roles ur ON ur.user_id = tm.user_id
                            JOIN roles r ON r.id = ur.role_id
                            WHERE tm.team_id = ? AND r.name = 'teamlead'
                        ");
                        $leadCheck->execute([$teamId]);
                        if ((int)$leadCheck->fetchColumn() > 0) {
                            $msg = '❌ В этой команде уже есть тим лид';
                            $teamConflict = true;
                        }
                    }

                    if (!$teamConflict) {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $stmt = $db->prepare("
                            INSERT INTO users (full_name, position, project, login, password_hash)
                            VALUES (?, ?, ?, ?, ?) RETURNING id
                        ");
                        $stmt->execute([$name, $position, $project, $login, $hash]);
                        $newId = $stmt->fetchColumn();

                        $roleRow = $db->prepare("SELECT id FROM roles WHERE name = ?");
                        $roleRow->execute([$role]);
                        $roleId = $roleRow->fetchColumn();
                        $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")
                           ->execute([$newId, $roleId]);

                        if ($teamId) {
                            $db->prepare("INSERT INTO team_members (user_id, team_id) VALUES (?, ?)")
                               ->execute([$newId, $teamId]);
                        }

                        $msg = '✅ Пользователь «' . htmlspecialchars($name) . '» создан';
                    }
                }
            }
        }
        $tab = 'users';
    }

    // --- Редактировать пользователя ---
    if ($action === 'edit_user') {
        $uid      = (int)$_POST['user_id'];
        $name     = trim($_POST['name']     ?? '');
        $position = trim($_POST['position'] ?? '');
        $project  = trim($_POST['project']  ?? '');

        // Проверка длины
        if (mb_strlen($name) > 100 || mb_strlen($position) > 50 || mb_strlen($project) > 50) {
            $msg = '❌ Должность и проект не должны превышать 50 символов';
            $tab = 'users';
        } else {
            // Логин и пароль можно менять только себе
            if ($uid === $user['id']) {
                $login = trim($_POST['login'] ?? '');
                if (mb_strlen($login) > 50) {
                    $msg = '❌ Логин не должен превышать 50 символов';
                    $tab = 'users';
                } else {
                    // Проверка уникальности логина (исключая себя)
                    $check = $db->prepare("SELECT id FROM users WHERE login = ? AND id != ?");
                    $check->execute([$login, $uid]);
                    if ($check->fetch()) {
                        $msg = '❌ Логин «' . htmlspecialchars($login) . '» уже занят';
                        $tab = 'users';
                    } else {
                        $db->prepare("UPDATE users SET full_name=?, login=?, position=?, project=? WHERE id=?")
                           ->execute([$name, $login, $position, $project, $uid]);
                        if (!empty($_POST['password'])) {
                            $pw = $_POST['password'];
                            if (mb_strlen($pw) > 50) {
                                $msg = '❌ Пароль не должен превышать 50 символов';
                                $tab = 'users';
                            } else {
                                $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
                                   ->execute([password_hash($pw, PASSWORD_BCRYPT), $uid]);
                                $msg = '✅ Пользователь обновлён';
                            }
                        } else {
                            $msg = '✅ Пользователь обновлён';
                        }
                        $tab = 'users';
                    }
                }
            } else {
                $db->prepare("UPDATE users SET full_name=?, position=?, project=? WHERE id=?")
                   ->execute([$name, $position, $project, $uid]);
                $msg = '✅ Пользователь обновлён';
            }

            // Роль нельзя менять у самого себя
            if ($uid !== $user['id'] && empty($msg) || ($uid !== $user['id'] && str_starts_with($msg, '✅'))) {
                $role = $_POST['role'] ?? 'employee';
                $teamId = $_POST['team_id'] !== '' ? (int)$_POST['team_id'] : null;

                // Проверка: в команде не может быть 2 тимлида
                if ($teamId && $role === 'teamlead') {
                    $leadCheck = $db->prepare("
                        SELECT COUNT(*) FROM team_members tm
                        JOIN user_roles ur ON ur.user_id = tm.user_id
                        JOIN roles r ON r.id = ur.role_id
                        WHERE tm.team_id = ? AND r.name = 'teamlead' AND tm.user_id != ?
                    ");
                    $leadCheck->execute([$teamId, $uid]);
                    if ((int)$leadCheck->fetchColumn() > 0) {
                        $msg = '❌ В этой команде уже есть тим лид';
                        $tab = 'users';
                    }
                }

                if (str_starts_with($msg, '✅')) {
                    $roleRow = $db->prepare("SELECT id FROM roles WHERE name = ?");
                    $roleRow->execute([$role]);
                    $roleId = $roleRow->fetchColumn();
                    $db->prepare("DELETE FROM user_roles WHERE user_id = ?")->execute([$uid]);
                    $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)")->execute([$uid, $roleId]);

                    $db->prepare("DELETE FROM team_members WHERE user_id = ?")->execute([$uid]);
                    if ($teamId) {
                        $db->prepare("INSERT INTO team_members (user_id, team_id) VALUES (?, ?)")->execute([$uid, $teamId]);
                    }
                }
            }

            $tab = 'users';
        }
    }

    // --- Удалить пользователя ---
    if ($action === 'delete_user') {
        $uid = (int)$_POST['user_id'];
        if ($uid !== $user['id']) {
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $msg = '🗑️ Пользователь удалён';
        }
        $tab = 'users';
    }

    // --- Создать команду ---
    if ($action === 'create_team') {
        $name    = trim($_POST['team_name'] ?? '');
        $desc    = trim($_POST['team_desc'] ?? '');
        $leadId  = (int)($_POST['lead_id'] ?? 0);

        if ($name) {
            $stmt = $db->prepare("INSERT INTO teams (name, description) VALUES (?, ?) RETURNING id");
            $stmt->execute([$name, $desc]);
            $teamId = $stmt->fetchColumn();

            if ($leadId) {
                $db->prepare("INSERT INTO team_members (user_id, team_id) VALUES (?, ?) ON CONFLICT DO NOTHING")
                   ->execute([$leadId, $teamId]);
            }
            $msg = '✅ Команда создана';
        }
        $tab = 'teams';
    }

    // --- Редактировать команду ---
    if ($action === 'edit_team') {
        $tid    = (int)$_POST['team_id'];
        $name   = trim($_POST['team_name'] ?? '');
        $desc   = trim($_POST['team_desc'] ?? '');
        $leadId = (int)($_POST['lead_id'] ?? 0);

        $db->prepare("UPDATE teams SET name=?, description=? WHERE id=?")->execute([$name, $desc, $tid]);
        $msg = '✅ Команда обновлена';
        $tab = 'teams';
    }

    // --- Удалить команду ---
    if ($action === 'delete_team') {
        $tid = (int)$_POST['team_id'];
        $db->prepare("DELETE FROM teams WHERE id = ?")->execute([$tid]);
        $msg = '🗑️ Команда удалена';
        $tab = 'teams';
    }
}

$allUsers  = getAllUsers();
$allTeams  = getAllTeams();
$teamLeads = array_filter($allUsers, fn($u) => $u['role'] === 'teamlead');

$pageTitle   = 'Администрирование';
$activeNav   = 'admin';
$headerTheme = 'admin';
$extraCss    = '
    .page-content { padding: 40px 0; }
    .tabs { display: inline-flex; gap: 5px; margin-bottom: 30px; background: #fff; padding: 6px; border-radius: 14px; border: 1px solid #e2e8f0; }
    .tab-btn { padding: 10px 24px; border: none; border-radius: 10px; font-weight: 600; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; background: transparent; color: #64748b; }
    .tab-btn.active { background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; }
    .tab-btn:hover:not(.active) { background: #f1f5f9; }
    .btn-new { padding: 12px 24px; background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; border: none; border-radius: 12px; font-weight: 600; cursor: pointer; }
    .cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }
    .admin-card { background: #fff; border-radius: 18px; padding: 25px; box-shadow: 0 6px 20px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .card-head { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
    .card-avatar { width: 55px; height: 55px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 700; color: #fff; flex-shrink: 0; }
    .card-avatar.admin    { background: linear-gradient(135deg, #f59e0b, #ef4444); }
    .card-avatar.teamlead { background: linear-gradient(135deg, #8b5cf6, #3b82f6); }
    .card-avatar.employee { background: linear-gradient(135deg, #3b82f6, #06b6d4); }
    .card-avatar.team     { background: linear-gradient(135deg, #10b981, #059669); }
    .card-info h3 { font-size: 1.1rem; margin-bottom: 4px; }
    .card-info p  { font-size: 0.85rem; color: #64748b; }
    .card-actions { display: flex; gap: 8px; flex-shrink: 0; }
    .card-fields { display: flex; flex-direction: column; gap: 8px; }
    .field-row { display: flex; justify-content: space-between; font-size: 0.88rem; padding: 8px 12px; background: #f8fafc; border-radius: 8px; }
    .field-row .label { color: #64748b; }
    .field-row .value { font-weight: 600; color: #1e293b; }
    .role-chip { padding: 3px 10px; border-radius: 50px; font-size: 0.78rem; font-weight: 600; }
    .role-admin    { background: #fef3c7; color: #92400e; }
    .role-teamlead { background: #ede9fe; color: #5b21b6; }
    .role-employee { background: #e0f2fe; color: #0369a1; }
    .btn-save { background: linear-gradient(135deg, #f59e0b, #ef4444) !important; }
    .form-group input:focus, .form-group select:focus { border-color: #f59e0b !important; }
';
require 'layout.php';
?>

<section class="page-content">
    <div class="container">

        <?php if ($msg): ?>
            <div class="msg-box" style="<?php echo str_starts_with($msg, '❌') ? 'background:#fef2f2;border-color:#fca5a5;color:#dc2626;' : ''; ?>">
                <?php echo $msg; ?>
            </div>
        <?php endif; ?>

        <div class="tabs">
            <a href="?tab=users"><button class="tab-btn <?php echo $tab === 'users' ? 'active' : ''; ?>">👤 Сотрудники</button></a>
            <a href="?tab=teams"><button class="tab-btn <?php echo $tab === 'teams' ? 'active' : ''; ?>">👥 Команды</button></a>
        </div>

        <?php if ($tab === 'users'): ?>
        <div class="section-header">
            <h2>👤 Все пользователи (<?php echo count($allUsers); ?>)</h2>
            <button class="btn-new" onclick="openModal('createUserModal')">+ Добавить сотрудника</button>
        </div>

        <div class="cards-grid">
            <?php foreach ($allUsers as $u):
                $teamName = '—';
                if ($u['team_id']) {
                    foreach ($allTeams as $t) {
                        if ($t['id'] === $u['team_id']) { $teamName = $t['name']; break; }
                    }
                }
                $status = getUserStatus($u['id']);
            ?>
            <div class="admin-card">
                <div class="card-head">
                    <div style="display:flex;gap:15px;align-items:center;">
                        <div class="card-avatar <?php echo $u['role']; ?>"><?php echo initials($u['name']); ?></div>
                        <div class="card-info">
                            <h3><?php echo htmlspecialchars($u['name']); ?></h3>
                            <p><?php echo htmlspecialchars($u['login']); ?></p>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn-sm btn-edit" onclick="openEditUserModal(
                            <?php echo $u['id']; ?>,
                            '<?php echo addslashes($u['name']); ?>',
                            '<?php echo addslashes($u['login']); ?>',
                            '<?php echo $u['role']; ?>',
                            '<?php echo $u['team_id'] ?? ''; ?>',
                            '<?php echo addslashes($u['position'] ?? ''); ?>',
                            '<?php echo addslashes($u['project'] ?? ''); ?>'
                        )">✏️</button>
                        <?php if ($u['id'] !== $user['id']): ?>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Удалить пользователя?')">
                            <input type="hidden" name="form_action" value="delete_user">
                            <input type="hidden" name="user_id"    value="<?php echo $u['id']; ?>">
                            <button type="submit" class="btn-sm btn-del">🗑️</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-fields">
                    <div class="field-row">
                        <span class="label">Роль</span>
                        <span class="role-chip role-<?php echo $u['role']; ?>">
                            <?php echo match($u['role']) { 'admin' => 'Администратор', 'teamlead' => 'Тим Лид', default => 'Сотрудник' }; ?>
                        </span>
                    </div>
                    <div class="field-row"><span class="label">Должность</span><span class="value"><?php echo htmlspecialchars($u['position'] ?: '—'); ?></span></div>
                    <div class="field-row"><span class="label">Проект</span><span class="value"><?php echo htmlspecialchars($u['project'] ?: '—'); ?></span></div>
                    <div class="field-row"><span class="label">Команда</span><span class="value"><?php echo htmlspecialchars($teamName); ?></span></div>
                    <div class="field-row"><span class="label">Статус</span><span class="value"><?php
                        echo match($status) { 'working' => '🟢 Работает', 'resting' => '🟡 Перерыв', default => '⚫ Не в сети' };
                    ?></span></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <div class="section-header">
            <h2>👥 Все команды (<?php echo count($allTeams); ?>)</h2>
            <button class="btn-new" onclick="openModal('createTeamModal')">+ Создать команду</button>
        </div>

        <div class="cards-grid">
            <?php foreach ($allTeams as $team):
                $members = getMembersOfTeam($team['id']);
                $lead    = null;
                foreach ($members as $m) {
                    if ($m['role'] === 'teamlead') { $lead = $m; break; }
                }
            ?>
            <div class="admin-card">
                <div class="card-head">
                    <div style="display:flex;gap:15px;align-items:center;">
                        <div class="card-avatar team">👥</div>
                        <div class="card-info">
                            <h3><?php echo htmlspecialchars($team['name']); ?></h3>
                            <p><?php echo htmlspecialchars($team['description'] ?? ''); ?></p>
                        </div>
                    </div>
                    <div class="card-actions">
                        <button class="btn-sm btn-edit" onclick="openEditTeamModal(
                            <?php echo $team['id']; ?>,
                            '<?php echo addslashes($team['name']); ?>',
                            '<?php echo addslashes($team['description'] ?? ''); ?>'
                        )">✏️</button>
                        <form method="POST" style="display:inline" onsubmit="return confirm('Удалить команду?')">
                            <input type="hidden" name="form_action" value="delete_team">
                            <input type="hidden" name="team_id"    value="<?php echo $team['id']; ?>">
                            <button type="submit" class="btn-sm btn-del">🗑️</button>
                        </form>
                    </div>
                </div>
                <div class="card-fields">
                    <div class="field-row"><span class="label">Участников</span><span class="value"><?php echo count($members); ?></span></div>
                    <div class="field-row"><span class="label">Работают сейчас</span><span class="value"><?php echo count(array_filter($members, fn($m) => $m['status'] === 'working')); ?></span></div>
                </div>
                <div style="margin-top:15px;">
                    <?php foreach ($members as $m): ?>
                    <div style="display:flex;align-items:center;gap:8px;padding:5px 0;font-size:0.88rem;">
                        <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#3b82f6,#8b5cf6);display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.75rem;font-weight:700;">
                            <?php echo initials($m['name']); ?>
                        </div>
                        <span><?php echo htmlspecialchars($m['name']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</section>

<footer>
    <div class="container"><p>🛡️ DevTime Admin Panel</p></div>
</footer>

<!-- Модал: создать пользователя -->
<div class="modal-overlay" id="createUserModal">
    <div class="modal">
        <h3>➕ Новый сотрудник</h3>
        <form method="POST">
            <input type="hidden" name="form_action" value="create_user">
            <div class="form-grid">
                <div class="form-group full"><label>ФИО</label><input type="text" name="name" placeholder="Иван Иванов" required></div>
                <div class="form-group"><label>Логин</label><input type="text" name="login" required></div>
                <div class="form-group"><label>Пароль</label><input type="password" name="password" required></div>
                <div class="form-group"><label>Должность</label><input type="text" name="position"></div>
                <div class="form-group"><label>Проект</label><input type="text" name="project"></div>
                <div class="form-group"><label>Роль</label>
                    <select name="role">
                        <option value="employee">Сотрудник</option>
                        <option value="teamlead">Тим Лид</option>
                        <option value="admin">Администратор</option>
                    </select>
                </div>
                <div class="form-group"><label>Команда</label>
                    <select name="team_id">
                        <option value="">— без команды —</option>
                        <?php foreach ($allTeams as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModals()">Отмена</button>
                <button type="submit" class="btn-save">Создать</button>
            </div>
        </form>
    </div>
</div>

<!-- Модал: редактировать пользователя -->
<div class="modal-overlay" id="editUserModal">
    <div class="modal">
        <h3>✏️ Редактировать сотрудника</h3>
        <form method="POST">
            <input type="hidden" name="form_action" value="edit_user">
            <input type="hidden" name="user_id"    id="editUserId">
            <div class="form-grid">
                <div class="form-group full"><label>ФИО</label><input type="text" name="name" id="editUserName" required></div>
                <!-- Логин и пароль — только для своей карточки -->
                <div class="form-group" id="editLoginGroup"><label>Логин</label><input type="text" name="login" id="editUserLogin"></div>
                <div class="form-group" id="editPasswordGroup"><label>Новый пароль (необяз.)</label><input type="password" name="password"></div>
                <div class="form-group"><label>Должность</label><input type="text" name="position" id="editUserPosition"></div>
                <div class="form-group"><label>Проект</label><input type="text" name="project" id="editUserProject"></div>
                <!-- Роль и команда — только для чужих карточек -->
                <div class="form-group" id="editRoleGroup"><label>Роль</label>
                    <select name="role" id="editUserRole">
                        <option value="employee">Сотрудник</option>
                        <option value="teamlead">Тим Лид</option>
                        <option value="admin">Администратор</option>
                    </select>
                </div>
                <div class="form-group" id="editTeamGroup"><label>Команда</label>
                    <select name="team_id" id="editUserTeam">
                        <option value="">— без команды —</option>
                        <?php foreach ($allTeams as $t): ?>
                        <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModals()">Отмена</button>
                <button type="submit" class="btn-save">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<!-- Модал: создать команду -->
<div class="modal-overlay" id="createTeamModal">
    <div class="modal">
        <h3>➕ Новая команда</h3>
        <form method="POST">
            <input type="hidden" name="form_action" value="create_team">
            <div class="form-grid">
                <div class="form-group full"><label>Название</label><input type="text" name="team_name" required></div>
                <div class="form-group full"><label>Описание</label><input type="text" name="team_desc"></div>
                <div class="form-group full"><label>Тим Лид</label>
                    <select name="lead_id">
                        <option value="0">— не назначен —</option>
                        <?php foreach ($teamLeads as $tl): ?>
                        <option value="<?php echo $tl['id']; ?>"><?php echo htmlspecialchars($tl['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModals()">Отмена</button>
                <button type="submit" class="btn-save">Создать</button>
            </div>
        </form>
    </div>
</div>

<!-- Модал: редактировать команду -->
<div class="modal-overlay" id="editTeamModal">
    <div class="modal">
        <h3>✏️ Редактировать команду</h3>
        <form method="POST">
            <input type="hidden" name="form_action" value="edit_team">
            <input type="hidden" name="team_id"    id="editTeamId">
            <div class="form-grid">
                <div class="form-group full"><label>Название</label><input type="text" name="team_name" id="editTeamName" required></div>
                <div class="form-group full"><label>Описание</label><input type="text" name="team_desc" id="editTeamDesc"></div>
            </div>
            <div class="modal-btns">
                <button type="button" class="btn-cancel" onclick="closeModals()">Отмена</button>
                <button type="submit" class="btn-save">Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModals() { document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('open')); }
    document.querySelectorAll('.modal-overlay').forEach(o => o.addEventListener('click', e => { if (e.target === o) closeModals(); }));

    function openEditUserModal(id, name, login, role, teamId, position, project) {
        const isSelf = (id === <?php echo $user['id']; ?>);
        document.getElementById('editUserId').value       = id;
        document.getElementById('editUserName').value     = name;
        document.getElementById('editUserLogin').value    = login;
        document.getElementById('editUserRole').value     = role;
        document.getElementById('editUserTeam').value     = teamId || '';
        document.getElementById('editUserPosition').value = position;
        document.getElementById('editUserProject').value  = project;

        // Своя карточка: показываем логин/пароль, скрываем роль/команду
        document.getElementById('editLoginGroup').style.display    = isSelf ? '' : 'none';
        document.getElementById('editPasswordGroup').style.display = isSelf ? '' : 'none';
        document.getElementById('editRoleGroup').style.display     = isSelf ? 'none' : '';
        document.getElementById('editTeamGroup').style.display     = isSelf ? 'none' : '';

        openModal('editUserModal');
    }
    function openEditTeamModal(id, name, desc) {
        document.getElementById('editTeamId').value   = id;
        document.getElementById('editTeamName').value = name;
        document.getElementById('editTeamDesc').value = desc;
        openModal('editTeamModal');
    }
</script>
</body>
</html>
