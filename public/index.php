<?php
declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/config.php';
ini_set('display_errors', '1'); 
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
session_start();

$errors = [];
// смотрим что это за CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));

    // обработка регистрации
    if ($action === 'register') {
        $password_confirm = (string)($_POST['password_confirm'] ?? '');
        $bg = trim((string)($_POST['bg_color'] ?? '#ffffff')); // по умолчанию задаём цвет
        
        // Валидация
        if ($username === '' || $password === '' || $email === '') {
            $errors[] = 'Заполните все обязательные поля';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Пароль должен содержать минимум 6 символов';
        } elseif ($password !== $password_confirm) {
            $errors[] = 'Пароли не совпадают';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Некорректный email адрес';
        } else {
            $pdo = getPdo(); // открываем подключение к бд
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u OR email = :e'); // проверка на совпадение ввёднного имени пользователя с базой данных
            $stmt->execute([':u' => $username, ':e' => $email]);
            // если запрос к бд нам вернул id, то пользователь существует, возвращаем ошибку
            if ($stmt->fetch()) {
                $errors[] = 'Пользователь с таким именем или email уже существует';
            } else {
                // иначе создаём пользователя, вставляем в таблицу  имя, email, хэш пароля, и цвет фона страницы
                $hash = password_hash($password, PASSWORD_DEFAULT); // PHP сам выбирает алгоритм хэширования
                $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, bg_color) VALUES (:u, :e, :h, :bg)');
                $stmt->execute([':u' => $username, ':e' => $email, ':h' => $hash, ':bg' => $bg]);
                $_SESSION['user'] = ['id' => (int)$pdo->lastInsertId(), 'username' => $username, 'bg_color' => $bg];
                // задаём cookie чтобы держать сессию логина в течении одного дня, параметры cookie лежат в ../src/config.php
                setcookie('bg_color', $bg, [
                    'expires' => time() + COOKIE_TTL,
                    'path' => COOKIE_PATH,
                    'secure' => COOKIE_SECURE,
                    'httponly' => COOKIE_HTTPONLY,
                    'samesite' => COOKIE_SAMESITE,
                ]);
                // при успешной регистрации — после INSERT
                $token = bin2hex(random_bytes(24));
                $tokenExpires = date('Y-m-d H:i:s', time()  + COOKIE_TTL);
                $update = $pdo->prepare('UPDATE users SET auth_token = :t, token_expires = :e WHERE id = :id');
                $update->execute([':t' => $token, ':e' => $tokenExpires, ':id' => $_SESSION['user']['id']]);
                setcookie('auth_token', $token, [
                    'expires' => time() + COOKIE_TTL,
                    'path' => COOKIE_PATH,
                    'secure' => COOKIE_SECURE,
                    'httponly' => COOKIE_HTTPONLY,
                    'samesite' => COOKIE_SAMESITE,
                ]);
                header('Location: index.php');
                exit;
            }
        }
    // обработка авторизации    
    } elseif ($action === 'login') {
        if ($username === '' || $password === '') {
            $errors[] = 'Введите имя и пароль';
        } else {
            // подключаемся к базе данных, чтобы проверить хэш пароля и имя пользователя
            $pdo = getPdo();
            $stmt = $pdo->prepare('SELECT id, password_hash, bg_color FROM users WHERE username = :u');
            $stmt->execute([':u' => $username]);
            $u = $stmt->fetch();
            // возвращаем ошибку если не совпадает имя пользователя и пароль
            if (!$u || !password_verify($password, $u['password_hash'])) {
                $errors[] = 'Неверные учётные данные';
            // иначе залогиниваем пользователя и ставим TTL в 1 день
            } else {
                // при успешном логине
                $token = bin2hex(random_bytes(24));
                $tokenExpires = date('Y-m-d H:i:s', time() + COOKIE_TTL);
                $update = $pdo->prepare('UPDATE users SET auth_token = :t, token_expires = :e WHERE id = :id');
                $update->execute([':t' => $token, ':e' => $tokenExpires, ':id' => $u['id']]);
                setcookie('auth_token', $token, [
                    'expires' => time() + COOKIE_TTL,
                    'path' => COOKIE_PATH,
                    'secure' => COOKIE_SECURE,
                    'httponly' => true,
                    'samesite' => COOKIE_SAMESITE,
                ]);
                $_SESSION['user'] = ['id' => $u['id'], 'username' => $username, 'bg_color' => $u['bg_color'] ?? '#ffffff'];
                setcookie('bg_color', $_SESSION['user']['bg_color'], [
                    'expires' => time() + COOKIE_TTL,
                    'path' => COOKIE_PATH,
                    'secure' => COOKIE_SECURE,
                    'httponly' => COOKIE_HTTPONLY,
                    'samesite' => COOKIE_SAMESITE,
                ]);
                header('Location: index.php');
                exit;
            }
        }
    // сохранить цвет фона без перехода на другую страницу
    } elseif ($action === 'save_bg' && !empty($_SESSION['user'])) {
        $bg = trim((string)($_POST['bg_color'] ?? '#ffffff'));
        // простая валидация hex-цвета
        if (!preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $bg)) {
            $errors[] = 'Неверный формат цвета. Используйте #RRGGBB или #RGB.';
        } else {
            try {
                $pdo = getPdo();
                $stmt = $pdo->prepare('UPDATE users SET bg_color = :bg WHERE id = :id');
                $stmt->execute([':bg' => $bg, ':id' => $_SESSION['user']['id']]);
                $_SESSION['user']['bg_color'] = $bg;
                setcookie('bg_color', $bg, [
                    'expires' => time() + COOKIE_TTL,
                    'path' => COOKIE_PATH,
                    'secure' => COOKIE_SECURE,
                    'httponly' => COOKIE_HTTPONLY,
                    'samesite' => COOKIE_SAMESITE,
                ]);
                header('Location: index.php'); // чтобы избежать повторной отправки формы
                exit;
            } catch (Throwable $e) {
                $errors[] = 'Ошибка сохранения: ' . $e->getMessage();
            }
        }
    // выход — очищаем сессию и куки и возвращаемся на страницу входа/регистрации
    } elseif ($action === 'logout') {
        // очистка session
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        //очиститка токена в БД
        if (!empty($_SESSION['user'])) {
            try {
                $stmt = getPdo()->prepare('UPDATE users SET auth_token = NULL, token_expires = NULL WHERE id = :id');
                $stmt->execute([':id' => $_SESSION['user']['id']]);
            } catch (Throwable $e) { /* ignore */ }
        }
        setcookie('auth_token', '', time() - 3600, COOKIE_PATH);
        setcookie('bg_color', '', time() - 3600, COOKIE_PATH);
        header('Location: index.php');
        exit;
    }
}

// Если пользователь уже в сессии — показать защищённую часть
if (!empty($_SESSION['user'])) {
    $user = $_SESSION['user'];
    $bg = htmlspecialchars($user['bg_color'] ?? '#ffffff', ENT_QUOTES);
    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Home</title>
        <style>body{background:<?= $bg ?>;font-family:Arial;padding:20px}</style>
    </head>
    <body>
        <h1>Добро пожаловать, <?= htmlspecialchars($user['username'], ENT_QUOTES) ?></h1>
        <p>Текущий фон: <?= $bg ?></p>

        <form method="post" style="margin:10px 0">
            <label>Новый цвет (hex):
                <input name="bg_color" value="<?= htmlspecialchars($user['bg_color'] ?? '#ffffff', ENT_QUOTES) ?>">
            </label>
            <button type="submit" name="action" value="save_bg">Сохранить</button>
        </form>

        <form method="post" style="display:inline">
            <button type="submit" name="action" value="logout">Выйти</button>
        </form>
        <p><a href="">Обновить страницу</a></p>
    </body>
    </html>
    <?php
    exit;
}
// TODO: попробовать развернуть на Nginx proxy manager через Duckdns,
//  и подписать сертификаты TLS для HTTPS
// Форма с двумя кнопками
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Login / Register</title></head>
<body>
<h1>Вход / Регистрация</h1>
<?php foreach ($errors as $e): ?><div style="color:red"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<form method="post">
    <label>Имя пользователя: <input name="username" required></label><br>
    <label>Email: <input type="email" name="email" required></label><br>
    <label>Пароль (мин. 6 символов): <input type="password" name="password" required></label><br>
    <label>Подтверждение пароля: <input type="password" name="password_confirm" required></label><br>
    <label>Цвет фона (hex): <input name="bg_color" value="#ffffff"></label><br>
    <button type="submit" name="action" value="login">Войти</button>
    <button type="submit" name="action" value="register">Зарегистрироваться</button>
</form>
</body>
</html>
