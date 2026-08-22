<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$applicationId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$applicationId) {
    http_response_code(400);
    exit('Invalid application ID.');
}

$studentStmt = $pdo->prepare(
    'SELECT student_id
     FROM students
     WHERE user_id = ?
     LIMIT 1'
);

$studentStmt->execute([$user['id']]);
$student = $studentStmt->fetch();

if (!$student) {
    exit('Student profile not found.');
}

$studentId = (int) $student['student_id'];

$stmt = $pdo->prepare(
    'SELECT
        a.application_id,
        a.resume_id,
        a.cover_letter,
        a.status,
        a.applied_at,
        a.updated_at,

        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.description,
        o.responsibilities,
        o.qualifications,
        o.location,
        o.duration,
        o.deadline,

        e.company_name,
        e.company_description,
        e.industry,
        e.website,

        r.file_name AS resume_file_name,
        r.file_path AS resume_file_path,
        r.is_primary AS resume_is_primary

     FROM applications a

     INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

     INNER JOIN employers e
        ON e.employer_id = o.employer_id

     LEFT JOIN resumes r
        ON r.resume_id = a.resume_id

     WHERE a.application_id = ?
       AND a.student_id = ?

     LIMIT 1'
);

$stmt->execute([
    $applicationId,
    $studentId
]);

$application = $stmt->fetch();

if (!$application) {
    http_response_code(404);
    exit('Application not found or access denied.');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Application Details | CareerBridge</title>
</head>

<body>

<h1>Application Details</h1>

<p>
    <a href="dashboard.php">Dashboard</a> |
    <a href="applications.php">My Applications</a> |
    <a href="opportunities.php">Opportunities</a> |
    <a href="resume.php">Resume / CV</a> |
    <a href="../auth/logout.php">Logout</a>
</p>

<hr>

<h2>Application Information</h2>

<p>
    <strong>Application ID:</strong>
    <?= (int) $application['application_id'] ?>
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
    <strong>Applied At:</strong>
    <?= htmlspecialchars(
        $application['applied_at'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Last Updated:</strong>
    <?= htmlspecialchars(
        $application['updated_at'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<hr>

<h2>Opportunity</h2>

<p>
    <strong>Title:</strong>
    <?= htmlspecialchars(
        $application['title'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

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
    <strong>Duration:</strong>
    <?= htmlspecialchars(
        $application['duration'] ?? 'Not specified',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Deadline:</strong>
    <?= htmlspecialchars(
        $application['deadline'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<?php if (!empty($application['description'])): ?>

    <h3>Description</h3>

    <p>
        <?= nl2br(
            htmlspecialchars(
                $application['description'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($application['responsibilities'])): ?>

    <h3>Responsibilities</h3>

    <p>
        <?= nl2br(
            htmlspecialchars(
                $application['responsibilities'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($application['qualifications'])): ?>

    <h3>Qualifications</h3>

    <p>
        <?= nl2br(
            htmlspecialchars(
                $application['qualifications'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </p>

<?php endif; ?>

<hr>

<h2>Submitted Resume</h2>

<?php if (!empty($application['resume_id'])): ?>

    <p>
        <strong>File:</strong>
        <?= htmlspecialchars(
            $application['resume_file_name'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <?php if ((int) $application['resume_is_primary'] === 1): ?>

        <p>
            <strong>Primary Resume:</strong>
            Yes
        </p>

    <?php endif; ?>

    <p>
        <a
            href="../<?= htmlspecialchars(
                $application['resume_file_path'],
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            target="_blank"
            rel="noopener noreferrer"
        >
            View Resume
        </a>
    </p>

<?php else: ?>

    <p>
        No resume was attached to this application.
    </p>

<?php endif; ?>

<hr>

<h2>Cover Letter</h2>

<?php if (!empty($application['cover_letter'])): ?>

    <p>
        <?= nl2br(
            htmlspecialchars(
                $application['cover_letter'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </p>

<?php else: ?>

    <p>
        No cover letter was submitted.
    </p>

<?php endif; ?>

<hr>

<h2>Company Information</h2>

<p>
    <strong>Company:</strong>
    <?= htmlspecialchars(
        $application['company_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<?php if (!empty($application['company_description'])): ?>

    <p>
        <strong>Description:</strong><br>
        <?= nl2br(
            htmlspecialchars(
                $application['company_description'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($application['industry'])): ?>

    <p>
        <strong>Industry:</strong>
        <?= htmlspecialchars(
            $application['industry'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($application['website'])): ?>

    <p>
        <strong>Website:</strong>
        <?= htmlspecialchars(
            $application['website'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<hr>

<p>
    <a href="applications.php">
        ← Back to My Applications
    </a>
</p>

</body>

</html>