<?php
session_start();

// Для стандартной установки XAMPP: MySQL на localhost, пользователь root без пароля.
$dbHost = '127.0.0.1';
$dbName = 'php1';
$dbUser = 'root';
$dbPassword = '';

$view = $_GET['view'] ?? 'login';
$error = '';
$success = '';

if (!in_array($view, ['login', 'register'], true)) {
    $view = 'login';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $view = $action === 'register' ? 'register' : 'login';

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = 'Форма устарела. Обновите страницу и попробуйте снова.';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: index.php');
        exit;
    } elseif (in_array($action, ['register', 'login'], true)) {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $login)) {
            $error = 'Логин: от 3 до 32 символов, латинские буквы, цифры и _.';
        } elseif ($action === 'register' && strlen($password) < 8) {
            $error = 'Пароль должен содержать не менее 8 символов.';
        } else {
            try {
                $pdo = new PDO(
                    "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                    $dbUser,
                    $dbPassword,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]
                );

                if ($action === 'register') {
                    $statement = $pdo->prepare(
                        'INSERT INTO users (login, password_hash) VALUES (:login, :password_hash)'
                    );
                    $statement->execute([
                        'login' => $login,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);

                    session_regenerate_id(true);
                    $_SESSION['user'] = ['id' => (int) $pdo->lastInsertId(), 'login' => $login];
                } else {
                    $statement = $pdo->prepare(
                        'SELECT id, login, password_hash FROM users WHERE login = :login'
                    );
                    $statement->execute(['login' => $login]);
                    $user = $statement->fetch();

                    if (!$user || !password_verify($password, $user['password_hash'])) {
                        $error = 'Неверный логин или пароль.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['user'] = ['id' => (int) $user['id'], 'login' => $user['login']];
                    }
                }
            } catch (PDOException $exception) {
                if ($action === 'register' && $exception->getCode() === '23000') {
                    $error = 'Этот логин уже занят.';
                } else {
                    $error = 'Не удалось подключиться к базе данных. Проверьте настройки и создайте таблицу users.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= isset($_SESSION['user']) ? 'Мой профиль' : 'Вход и регистрация' ?></title>
    <style>
        :root {
            color-scheme: light;
            --ink: #18312b;
            --muted: #63736d;
            --paper: #f4f5ef;
            --white: #fffefa;
            --green: #17664f;
            --green-dark: #104b3b;
            --line: #dce3da;
            --error: #a33c32;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            color: var(--ink);
            background: radial-gradient(ellipse at 15% 10%, #e2eadc 0, transparent 42%),
                        radial-gradient(ellipse at 90% 85%, #dce9e3 0, transparent 38%), var(--paper);
            font-family: Georgia, 'Times New Roman', serif;
        }
        main { width: min(100%, 430px); animation: arrive .35s ease-out both; }
        .eyebrow { margin: 0 0 10px; color: var(--green); font: 700 12px/1.4 Arial, sans-serif; text-transform: uppercase; }
        h1 { margin: 0 0 8px; font-size: 34px; font-weight: 500; }
        .intro { margin: 0 0 28px; color: var(--muted); font: 15px/1.5 Arial, sans-serif; }
        .tabs { display: flex; gap: 22px; border-bottom: 1px solid var(--line); margin-bottom: 24px; }
        .tabs a { padding: 0 0 12px; color: var(--muted); font: 600 14px Arial, sans-serif; text-decoration: none; }
        .tabs a[aria-current="page"] { color: var(--green-dark); border-bottom: 2px solid var(--green); }
        label { display: block; margin: 0 0 8px; font: 600 13px Arial, sans-serif; }
        input {
            width: 100%; height: 48px; margin: 0 0 19px; padding: 0 13px;
            border: 1px solid var(--line); border-radius: 4px; background: var(--white);
            color: var(--ink); font: 16px Arial, sans-serif;
        }
        input:focus { outline: 2px solid #80ab96; outline-offset: 1px; }
        button {
            width: 100%; min-height: 48px; border: 0; border-radius: 4px;
            background: var(--green); color: white; cursor: pointer;
            font: 700 14px Arial, sans-serif;
        }
        button:hover { background: var(--green-dark); }
        .message { margin: 0 0 18px; padding: 12px 14px; border-radius: 4px; font: 14px/1.45 Arial, sans-serif; }
        .error { background: #f8e9e6; color: var(--error); }
        .success { background: #e5f0e9; color: var(--green-dark); }
        .profile { padding: 24px; border: 1px solid var(--line); border-radius: 4px; background: var(--white); }
        .profile p { margin: 0 0 22px; font: 15px/1.5 Arial, sans-serif; }
        @keyframes arrive { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @media (prefers-reduced-motion: reduce) { main { animation: none; } }
    </style>
</head>
<body>
<main>
    <?php if (isset($_SESSION['user'])): ?>
        <p class="eyebrow">Личный кабинет</p>
        <h1>Здравствуйте, <?= escape($_SESSION['user']['login']) ?></h1>
        <p class="intro">Вы вошли в свою учетную запись.</p>
        <?php if ($error !== ''): ?><p class="message error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <div class="profile">
            <p>Логин: <strong><?= escape($_SESSION['user']['login']) ?></strong></p>
            <form method="get">
                <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="logout">
                <button type="submit">Выйти</button>
            </form>
        </div>
    <?php else: ?>
        <p class="eyebrow">Учетная запись</p>
        <h1><?= $view === 'register' ? 'Создать аккаунт' : 'С возвращением' ?></h1>
        <p class="intro">Войдите или зарегистрируйтесь, чтобы продолжить.</p>
        <nav class="tabs" aria-label="Вход и регистрация">
            <a href="?view=login" <?= $view === 'login' ? 'aria-current="page"' : '' ?>>Вход</a>
            <a href="?view=register" <?= $view === 'register' ? 'aria-current="page"' : '' ?>>Регистрация</a>
        </nav>
        <?php if ($error !== ''): ?><p class="message error" role="alert"><?= escape($error) ?></p><?php endif; ?>
        <?php if ($success !== ''): ?><p class="message success" role="status"><?= escape($success) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= escape($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="<?= $view === 'register' ? 'register' : 'login' ?>">
            <label for="login">Логин</label>
            <input id="login" name="login" type="text" minlength="3" maxlength="32" pattern="[A-Za-z0-9_]{3,32}" autocomplete="username" required>
            <label for="password">Пароль</label>
            <input id="password" name="password" type="password" minlength="8" autocomplete="<?= $view === 'register' ? 'new-password' : 'current-password' ?>" required>
            <button type="submit"><?= $view === 'register' ? 'Зарегистрироваться' : 'Войти' ?></button>
        </form>
    <?php endif; ?>
</main>
</body>
</html>
