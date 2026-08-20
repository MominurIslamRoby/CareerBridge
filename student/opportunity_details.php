<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$opportunityId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$opportunityId) {
    http_response_code(400);
    exit('Invalid opportunity ID.');
}

$stmt = $pdo->prepare(
    '
    SELECT
        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.description,
        o.responsibilities,
        o.qualifications,
        o.location,
        o.duration,
        o.deadline,
        o.status,
        e.company_name,
        e.company_description,
        e.industry,
        e.website,
        e.company_email,
        e.phone,
        e.address
    FROM opportunities o
    INNER JOIN employers e
        ON e.employer_id = o.employer_id
    WHERE o.opportunity_id = ?
    LIMIT 1
    '
);

$stmt->execute([$opportunityId]);

$opportunity = $stmt->fetch();

if (!$opportunity) {
    http_response_code(404);
    exit('Opportunity not found.');
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
    <title>
        <?= htmlspecialchars(
            $opportunity['title'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        | CareerBridge
    </title>
</head>

<body>

<h1>Opportunity Details</h1>

<p>
    <a href="dashboard.php">Dashboard</a> |
    <a href="opportunities.php">Opportunities</a> |
    <a href="profile.php">Career Profile</a> |
    <a href="skills.php">Skills</a> |
    <a href="../auth/logout.php">Logout</a>
</p>

<hr>

<h2>
    <?= htmlspecialchars(
        $opportunity['title'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</h2>

<p>
    <strong>Company:</strong>
    <?= htmlspecialchars(
        $opportunity['company_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Type:</strong>
    <?= htmlspecialchars(
        $opportunity['opportunity_type'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Location:</strong>
    <?= htmlspecialchars(
        $opportunity['location'] ?? 'Not specified',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Duration:</strong>
    <?= htmlspecialchars(
        $opportunity['duration'] ?? 'Not specified',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Application Deadline:</strong>
    <?= htmlspecialchars(
        $opportunity['deadline'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Status:</strong>
    <?= htmlspecialchars(
        $opportunity['status'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<hr>

<h3>Description</h3>

<p>
    <?= nl2br(
        htmlspecialchars(
            $opportunity['description'],
            ENT_QUOTES,
            'UTF-8'
        )
    ) ?>
</p>

<?php if (!empty($opportunity['responsibilities'])): ?>

    <h3>Responsibilities</h3>

    <p>
        <?= nl2br(
            htmlspecialchars(
                $opportunity['responsibilities'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($opportunity['qualifications'])): ?>

    <h3>Qualifications</h3>

    <p>
        <?= nl2br(
            htmlspecialchars(
                $opportunity['qualifications'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </p>

<?php endif; ?>

<hr>

<h3>About the Company</h3>

<p>
    <strong>Company:</strong>
    <?= htmlspecialchars(
        $opportunity['company_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<?php if (!empty($opportunity['company_description'])): ?>

    <p>
        <strong>Description:</strong><br>
        <?= nl2br(
            htmlspecialchars(
                $opportunity['company_description'],
                ENT_QUOTES,
                'UTF-8'
            )
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($opportunity['industry'])): ?>

    <p>
        <strong>Industry:</strong>
        <?= htmlspecialchars(
            $opportunity['industry'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($opportunity['website'])): ?>

    <p>
        <strong>Website:</strong>
        <?= htmlspecialchars(
            $opportunity['website'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($opportunity['company_email'])): ?>

    <p>
        <strong>Email:</strong>
        <?= htmlspecialchars(
            $opportunity['company_email'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($opportunity['phone'])): ?>

    <p>
        <strong>Phone:</strong>
        <?= htmlspecialchars(
            $opportunity['phone'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<?php if (!empty($opportunity['address'])): ?>

    <p>
        <strong>Address:</strong>
        <?= htmlspecialchars(
            $opportunity['address'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php endif; ?>

<hr>

<?php if ($opportunity['status'] === 'open'): ?>

    <h3>Ready to Apply?</h3>

    <p>
        <a href="apply.php?id=<?= (int) $opportunity['opportunity_id'] ?>">
            Apply Now
        </a>
    </p>

<?php else: ?>

    <p>
        Applications are currently closed for this opportunity.
    </p>

<?php endif; ?>

<p>
    <a href="opportunities.php">← Back to Opportunities</a>
</p>

</body>
</html>