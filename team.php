<?php
require_once 'data.php';
requireRole('teamlead', 'employee');

$user = getCurrentUser();
$msg  = '';

if ($user['role'] === 'teamlead') {
    $myTeams = getTeamsForLead($user['id']);
} else {
    $myTeams = getTeamsForEmployee($user['id']);
}

$pageTitle = 'Команда';
$activeNav = 'team';
$extraCss  = '
    .page-content { padding: 40px 0; }
    .teams-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 30px; }
    .team-card { background: #fff; border-radius: 20px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; }
    .team-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px; }
    .team-title h3 { font-size: 1.3rem; margin-bottom: 5px; }
    .team-title p { color: #64748b; font-size: 0.9rem; }
    .member-list { display: flex; flex-direction: column; gap: 12px; }
    .member-row { display: flex; align-items: center; justify-content: space-between; padding: 12px 15px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; }
    .member-info { display: flex; align-items: center; gap: 12px; }
    .member-avatar { width: 38px; height: 38px; background: linear-gradient(135deg, #3b82f6, #8b5cf6); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 600; font-size: 0.85rem; }
    .member-name { font-weight: 600; font-size: 0.95rem; }
    .member-pos { font-size: 0.8rem; color: #64748b; }
';
require 'layout.php';
?>

<section class="page-content">
    <div class="container">
        <div class="section-header">
            <h2>👥 Мои команды</h2>
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

<script>
    // Авто-обновление каждые 30 сек
    setTimeout(() => location.reload(), 30000);
</script>
</body>
</html>
