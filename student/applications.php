<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

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

$studentStmt->execute([$user['id']]);

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
        a.resume_id,
        a.status,
        a.applied_at,
        a.updated_at,

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

/*
 * Application workflow.
 */
$statusSteps = [
    'submitted',
    'under_review',
    'shortlisted',
    'interview',
    'selected'
];

$statusLabels = [
    'submitted' => 'Submitted',
    'under_review' => 'Under Review',
    'shortlisted' => 'Shortlisted',
    'interview' => 'Interview',
    'selected' => 'Selected'
];

$statusDescriptions = [
    'submitted' => 'Your application has been submitted successfully.',
    'under_review' => 'The employer is reviewing your application.',
    'shortlisted' => 'You have been shortlisted for this opportunity.',
    'interview' => 'An interview stage has been reached.',
    'selected' => 'You have been selected for this opportunity.'
];
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
    <a href="resume.php">Resume / CV</a> |
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

        <?php
        $currentStatus = $application['status'];

        /*
         * Rejected is handled separately because it is
         * not part of the successful application workflow.
         */
        $currentStepIndex = array_search(
            $currentStatus,
            $statusSteps,
            true
        );

        if ($currentStepIndex === false) {
            $currentStepIndex = -1;
        }
        ?>

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
                <strong>Deadline:</strong>
                <?= htmlspecialchars(
                    $application['deadline'],
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
                <strong>Last Updated:</strong>
                <?= htmlspecialchars(
                    $application['updated_at'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </p>

            <hr>

            <h4>Application Status</h4>

            <?php if ($currentStatus === 'rejected'): ?>

                <p>
                    <strong>Status:</strong>
                    Rejected
                </p>

                <p>
                    Your application was not selected for this opportunity.
                </p>

            <?php else: ?>

                <?php foreach ($statusSteps as $index => $step): ?>

                    <?php
                    $isCompleted =
                        $currentStepIndex >= $index;

                    $isCurrent =
                        $currentStatus === $step;
                    ?>

                    <p>
                        <?php if ($isCompleted): ?>
                            ✓
                        <?php else: ?>
                            ○
                        <?php endif; ?>

                        <strong>
                            <?= htmlspecialchars(
                                $statusLabels[$step],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </strong>

                        <?php if ($isCurrent): ?>
                            — Current Stage
                        <?php endif; ?>
                    </p>

                <?php endforeach; ?>

                <p>
                    <strong>Current Status:</strong>
                    <?= htmlspecialchars(
                        $statusLabels[$currentStatus]
                            ?? ucwords(
                                str_replace('_', ' ', $currentStatus)
                            ),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>

                <?php if (isset($statusDescriptions[$currentStatus])): ?>

                    <p>
                        <?= htmlspecialchars(
                            $statusDescriptions[$currentStatus],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                <?php endif; ?>

            <?php endif; ?>

            <hr>

            <?php if (!empty($application['resume_id'])): ?>

                <p>
                    <strong>Resume:</strong>
                    Attached
                </p>

            <?php else: ?>

                <p>
                    <strong>Resume:</strong>
                    Not attached
                </p>

            <?php endif; ?>

            <p>
                <a
                    href="application_details.php?id=<?= (int) $application['application_id'] ?>"
                >
                    View Application
                </a>
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