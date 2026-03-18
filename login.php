<?php
require_once 'data.php';

if (isset($_SESSION['user_id'])) {
    $user = getCurrentUser();
    header($user['role'] === 'admin' ? 'Location: admin.php' : 'Location: dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login    = trim($_POST['login']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = getDB()->prepare("
        SELECT u.id, u.password_hash, r.name AS role
        FROM users u
        JOIN user_roles ur ON ur.user_id = u.id
        JOIN roles r       ON r.id = ur.role_id
        WHERE u.login = ?
    ");
    $stmt->execute([$login]);
    $found = $stmt->fetch();

    if ($found && password_verify($password, $found['password_hash'])) {
        $_SESSION['user_id'] = $found['id'];
        header($found['role'] === 'admin' ? 'Location: admin.php' : 'Location: dashboard.php');
        exit;
    } else {
        $error = 'Неверный логин или пароль';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT-Стартап | Вход</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #0f172a, #1e293b);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #fff;
            border-radius: 24px;
            padding: 50px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
        }
        .logo { text-align: center; margin-bottom: 35px; }
        .logo h1 {
            font-size: 2rem;
            background: linear-gradient(135deg, #60a5fa, #a78bfa);
            -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
        }
        .logo p { color: #64748b; margin-top: 5px; font-size: 0.95rem; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #374151; margin-bottom: 8px; font-size: 0.9rem; }
        .form-group input {
            width: 100%; padding: 14px 16px; border: 2px solid #e2e8f0; border-radius: 12px;
            font-size: 1rem; transition: border-color 0.2s; outline: none;
        }
        .form-group input:focus { border-color: #3b82f6; }
        .btn-login {
            width: 100%; padding: 15px; border: none; border-radius: 12px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            color: #fff; font-size: 1rem; font-weight: 600; cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-login:hover { opacity: 0.9; }
        .error {
            background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626;
            padding: 12px 16px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9rem;
        }
        .hint {
            margin-top: 25px; padding: 15px; background: #f8fafc;
            border-radius: 12px; font-size: 0.82rem; color: #64748b;
        }
        .hint strong { color: #374151; }
        .hint div { margin-top: 5px; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">
            <h1>⚡ DevTime</h1>
            <p>Учет рабочего времени IT-стартапа</p>
        </div>

        <?php if ($error): ?>
            <div class="error">⚠️ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="login" placeholder="Введите логин"
                       value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>"
                       required autofocus>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" placeholder="Введите пароль" required>
            </div>
            <button type="submit" class="btn-login">Войти →</button>
        </form>

        <div class="hint">
            <strong>Тестовые аккаунты:</strong>
            <div>👤 <strong>admin</strong> / admin123 — Администратор</div>
            <div>👤 <strong>teamlead</strong> / lead123 — Тим Лид</div>
            <div>👤 <strong>maria</strong> / pass123 — Сотрудник</div>
            <div>👤 <strong>elena</strong> / pass123 — Сотрудник (другая команда)</div>
        </div>
    </div>
</body>
</html>
