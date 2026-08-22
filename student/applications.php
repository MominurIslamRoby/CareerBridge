<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$userId = (int) $_SESSION['user_id'];

/*
 * Get student ID.
 */
$studentStmt = $pdo->prepare(
    '
    SELECT student_id
    FROM students
    WHERE user_id = ?
    LIMIT 1
    '
);

$studentStmt->execute([$userId]);

$student = $studentStmt->fetch();

if (!$student) {
    exit('Student profile not found.');
}

$studentId = (int) $student['student_id'];

/*
 * Get all applications submitted by this student.
 */
$stmt = $pdo->prepare(
    '
    SELECT
        a.application_id,
        a.opportunity_id,
        a.status,
        a.applied_at,
        o.title,
        o.opportunity_type,
        o.location,
        o.deadline,
        e.company_name
    FROM applications a
    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id
    INNER JOIN employers e
        ON e.employer_id = o.employer_id
    WHERE a.student_id = ?
    ORDER BY a.applied_at DESC
    '
);

$stmt->execute([$studentId]);

$applications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>My Applications | CareerBridge</title>
</head>

<body>

<h1>My Applications</h1>

<p>
    <a href="dashboard.php">Dashboard</a> |
    <a href="opportunities.php">Opportunities</a> |
    <a href="profile.php">Career Profile</a> |
    <a href="skills.php">Skills</a> |
    <a href="../auth/logout.php">Logout</a>
</p>

<hr>

<?php if (!$applications): ?>

    <p>
        You have not submitted any applications yet.
    </p>

    <p>
        <a href="opportunities.php">
            Browse Opportunities
        </a>
    </p>

<?php else: ?>

    <h2>Application History</h2>

    <?php foreach ($applications as $application): ?>

        <article>

            <h3>
                <?= htmlspecialchars(
                    $application['title'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </h3>

            <p>
                <strong>Company:</strong>
                <?= htmlspecialchars(
                    $application['company_name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Type:</strong>
                <?= htmlspecialchars(
                    $application['opportunity_type'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Location:</strong>
                <?= htmlspecialchars(
                    $application['location'] ?? 'Not specified',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Applied:</strong>
                <?= htmlspecialchars(
                    $application['applied_at'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <strong>Status:</strong>

                <?= htmlspecialchars(
                    $application['status'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <p>
                <a
                    href="opportunity_details.php?id=<?= (int) $application['opportunity_id'] ?>"
                >
                    View Opportunity
                </a>
            </p>

        </article>

        <hr>

    <?php endforeach; ?>

<?php endif; ?>

</body>

</html>