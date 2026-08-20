<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Email and password are required.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT user_id, full_name, email, password_hash, role, account_status
             FROM users
             WHERE email = ?
             LIMIT 1'
        );

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } elseif ($user['account_status'] !== 'active') {
            $error = 'Your account is not active.';
        } else {
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];

            switch ($user['role']) {
                case 'student':
                    header('Location: ../student/dashboard.php');
                    break;

                case 'employer':
                    header('Location: ../employer/dashboard.php');
                    break;

                case 'administrator':
                    header('Location: ../admin/dashboard.php');
                    break;

                default:
                    session_unset();
                    session_destroy();
                    $error = 'Invalid account role.';
            }

            if ($error === '') {
                exit;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | CareerBridge</title>
</head>
<body>

<h1>CareerBridge</h1>
<h2>Login</h2>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="POST" action="">
    <div>
        <label for="email">Email</label>
        <input
            type="email"
            id="email"
            name="email"
            required
        >
    </div>

    <div>
        <label for="password">Password</label>
        <input
            type="password"
            id="password"
            name="password"
            required
        >
    </div>

    <button type="submit">Login</button>
</form>

<p>
    Don't have an account?
    <a href="register.php">Register</a>
</p>

</body>
</html>