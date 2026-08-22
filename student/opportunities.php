<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$keyword = trim($_GET['keyword'] ?? '');
$type = $_GET['type'] ?? '';
$location = trim($_GET['location'] ?? '');

$sql = '
    SELECT
        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.description,
        o.location,
        o.duration,
        o.deadline,
        o.status,
        e.company_name
    FROM opportunities o
    INNER JOIN employers e
        ON e.employer_id = o.employer_id
    WHERE o.status = "open"
';

$params = [];

if ($keyword !== '') {
    $sql .= '
        AND (
            o.title LIKE ?
            OR o.description LIKE ?
            OR e.company_name LIKE ?
        )
    ';

    $search = '%' . $keyword . '%';

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
}

if (in_array($type, ['internship', 'job'], true)) {
    $sql .= ' AND o.opportunity_type = ?';
    $params[] = $type;
}

if ($location !== '') {
    $sql .= ' AND o.location LIKE ?';
    $params[] = '%' . $location . '%';
}

$sql .= ' ORDER BY o.deadline ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$opportunities = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Opportunities | CareerBridge</title>
</head>
<body>

<h1>Internship & Job Opportunities</h1>

<p>
    <a href="dashboard.php">Dashboard</a> |
    <a href="profile.php">Career Profile</a> |
    <a href="skills.php">Skills</a> |
    <a href="../auth/logout.php">Logout</a>
</p>

<h2>Search & Filter</h2>

<form method="GET" action="">

    <label for="keyword">Keyword</label><br>
    <input
        type="text"
        id="keyword"
        name="keyword"
        value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="Job title, company..."
    >

    <br><br>

    <label for="type">Opportunity Type</label><br>
    <select id="type" name="type">
        <option value="">All</option>
        <option value="internship" <?= $type === 'internship' ? 'selected' : '' ?>>
            Internship
        </option>
        <option value="job" <?= $type === 'job' ? 'selected' : '' ?>>
            Job
        </option>
    </select>

    <br><br>

    <label for="location">Location</label><br>
    <input
        type="text"
        id="location"
        name="location"
        value="<?= htmlspecialchars($location, ENT_QUOTES, 'UTF-8') ?>"
        placeholder="Dhaka, Remote..."
    >

    <br><br>

    <button type="submit">Search</button>
    <a href="opportunities.php">Clear</a>

</form>

<hr>

<h2>Available Opportunities</h2>

<?php if (!$opportunities): ?>

    <p>No opportunities found.</p>

<?php else: ?>

    <?php foreach ($opportunities as $opportunity): ?>

        <article>

            <h3>
                <?= htmlspecialchars($opportunity['title'], ENT_QUOTES, 'UTF-8') ?>
            </h3>

            <p>
                <strong>Company:</strong>
                <?= htmlspecialchars($opportunity['company_name'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p>
                <strong>Type:</strong>
                <?= htmlspecialchars($opportunity['opportunity_type'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p>
                <strong>Location:</strong>
                <?= htmlspecialchars($opportunity['location'] ?? 'Not specified', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p>
                <strong>Duration:</strong>
                <?= htmlspecialchars($opportunity['duration'] ?? 'Not specified', ENT_QUOTES, 'UTF-8') ?>
            </p>

            <p>
                <strong>Deadline:</strong>
                <?= htmlspecialchars($opportunity['deadline'], ENT_QUOTES, 'UTF-8') ?>
            </p>

            <a href="opportunity_details.php?id=<?= (int) $opportunity['opportunity_id'] ?>">
                View Details
            </a>

        </article>

        <hr>

    <?php endforeach; ?>

<?php endif; ?>

</body>
</html>