<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? '';

    $allowedRoles = ['student', 'employer'];

    if (
        $fullName === '' ||
        $email === '' ||
        $password === '' ||
        $confirmPassword === '' ||
        $role === ''
    ) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (!in_array($role, $allowedRoles, true)) {
        $error = 'Invalid registration role.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must contain at least 8 characters.';
    } else {
        $checkStmt = $pdo->prepare(
            'SELECT user_id FROM users WHERE email = ? LIMIT 1'
        );

        $checkStmt->execute([$email]);

        if ($checkStmt->fetch()) {
            $error = 'An account with this email already exists.';
        } else {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                $userStmt = $pdo->prepare(
                    'INSERT INTO users
                        (full_name, email, password_hash, role, account_status)
                     VALUES (?, ?, ?, ?, ?)'
                );

                $userStmt->execute([
                    $fullName,
                    $email,
                    $passwordHash,
                    $role,
                    'active'
                ]);

                $userId = (int) $pdo->lastInsertId();

                if ($role === 'student') {
                    $studentStmt = $pdo->prepare(
                        'INSERT INTO students
                            (user_id, student_id_number)
                         VALUES (?, ?)'
                    );

                    $studentStmt->execute([
                        $userId,
                        'STU-' . $userId
                    ]);
                } elseif ($role === 'employer') {
                    $employerStmt = $pdo->prepare(
                        'INSERT INTO employers
                            (user_id, company_name)
                         VALUES (?, ?)'
                    );

                    $employerStmt->execute([
                        $userId,
                        $fullName
                    ]);
                }

                $pdo->commit();

                $success = 'Registration successful. You can now log in.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'Registration failed. Please try again.';
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
    <title>Register | CareerBridge</title>
</head>
<body>

<h1>CareerBridge</h1>
<h2>Registration</h2>

<?php if ($error !== ''): ?>
    <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<?php if ($success !== ''): ?>
    <p><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<form method="POST" action="">
    <div>
        <label for="full_name">Full Name</label>
        <input
            type="text"
            id="full_name"
            name="full_name"
            required
        >
    </div>

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
            minlength="8"
            required
        >
    </div>

    <div>
        <label for="confirm_password">Confirm Password</label>
        <input
            type="password"
            id="confirm_password"
            name="confirm_password"
            minlength="8"
            required
        >
    </div>

    <div>
        <label for="role">Register As</label>
        <select id="role" name="role" required>
            <option value="">Select Role</option>
            <option value="student">Student</option>
            <option value="employer">Employer</option>
        </select>
    </div>

    <button type="submit">Register</button>
</form>

<p>
    Already have an account?
    <a href="login.php">Login</a>
</p>

</body>
</html>