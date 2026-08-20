<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

requireRole('employer');

$user = currentUser();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employer Dashboard | CareerBridge</title>
</head>
<body>

<h1>Employer Dashboard</h1>

<p>
    Welcome,
    <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
</p>

<p>Role: Employer</p>

<a href="../auth/logout.php">Logout</a>

</body>
</html>