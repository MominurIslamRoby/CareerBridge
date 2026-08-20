<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('student');

$user = currentUser();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | CareerBridge</title>
</head>
<body>

<h1>Student Dashboard</h1>

<p>
    Welcome,
    <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
</p>

<p>Role: Student</p>

<a href="../auth/logout.php">Logout</a>

</body>
</html>