<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$stmt = $pdo->prepare(
    'SELECT
        student_id,
        university_name,
        department,
        academic_level
     FROM students
     WHERE user_id = ?
     LIMIT 1'
);

$stmt->execute([$user['id']]);
$student = $stmt->fetch();

$studentId = $student ? (int) $student['student_id'] : 0;

$totalApplications = 0;
$submittedApplications = 0;
$shortlistedApplications = 0;
$selectedApplications = 0;

if ($studentId > 0) {
    $stmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS total,
            SUM(status = "submitted") AS submitted,
            SUM(status IN ("shortlisted", "interview")) AS shortlisted,
            SUM(status = "selected") AS selected
         FROM applications
         WHERE student_id = ?'
    );

    $stmt->execute([$studentId]);

    $applicationStats = $stmt->fetch();

    if ($applicationStats) {
        $totalApplications =
            (int) ($applicationStats['total'] ?? 0);

        $submittedApplications =
            (int) ($applicationStats['submitted'] ?? 0);

        $shortlistedApplications =
            (int) ($applicationStats['shortlisted'] ?? 0);

        $selectedApplications =
            (int) ($applicationStats['selected'] ?? 0);
    }
}

$stmt = $pdo->query(
    'SELECT COUNT(*) AS total
     FROM opportunities
     WHERE status = "open"'
);

$opportunityStats = $stmt->fetch();

$totalOpportunities =
    (int) ($opportunityStats['total'] ?? 0);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Student Dashboard | CareerBridge</title>
</head>

<body>

<h1>CareerBridge</h1>

<h2>Student Dashboard</h2>

<p>
    Welcome,
    <strong>
        <?= htmlspecialchars(
            $user['full_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </strong>
</p>

<p>
    <strong>Role:</strong> Student
</p>

<hr>

<h2>Quick Navigation</h2>

<p>
    <a href="profile.php">
        Career Profile
    </a>
</p>

<p>
    <a href="skills.php">
        My Skills
    </a>
</p>

<p>
    <a href="resume.php">
        Resume / CV
    </a>
</p>

<p>
    <a href="opportunities.php">
        Find Opportunities
    </a>
</p>

<p>
    <a href="applications.php">
        My Applications
    </a>
</p>

<hr>

<h2>Application Summary</h2>

<p>
    <strong>Total Applications:</strong>
    <?= $totalApplications ?>
</p>

<p>
    <strong>Submitted:</strong>
    <?= $submittedApplications ?>
</p>

<p>
    <strong>Shortlisted / Interview:</strong>
    <?= $shortlistedApplications ?>
</p>

<p>
    <strong>Selected:</strong>
    <?= $selectedApplications ?>
</p>

<hr>

<h2>Opportunities</h2>

<p>
    <strong>Currently Open:</strong>
    <?= $totalOpportunities ?>
</p>

<p>
    <a href="opportunities.php">
        Browse Available Opportunities
    </a>
</p>

<hr>

<h2>Profile Information</h2>

<?php if ($student): ?>

    <p>
        <strong>University:</strong>
        <?= htmlspecialchars(
            $student['university_name'] ?? 'Not provided',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        <strong>Department:</strong>
        <?= htmlspecialchars(
            $student['department'] ?? 'Not provided',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        <strong>Academic Level:</strong>
        <?= htmlspecialchars(
            $student['academic_level'] ?? 'Not provided',
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        <a href="profile.php">
            Update Profile
        </a>
    </p>

<?php else: ?>

    <p>
        Student profile not found.
    </p>

    <p>
        <a href="profile.php">
            Create Profile
        </a>
    </p>

<?php endif; ?>

<hr>

<p>
    <a href="../auth/logout.php">
        Logout
    </a>
</p>

</body>

</html>