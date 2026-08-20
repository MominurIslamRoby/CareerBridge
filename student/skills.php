<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

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

$stmt = $pdo->prepare(
    'SELECT
        s.skill_id,
        s.skill_name,
        ss.proficiency_level
     FROM student_skills ss
     INNER JOIN skills s ON s.skill_id = ss.skill_id
     WHERE ss.student_id = ?
     ORDER BY s.skill_name'
);

$stmt->execute([$student['student_id']]);
$skills = $stmt->fetchAll();

$allSkills = $pdo->query(
    'SELECT skill_id, skill_name
     FROM skills
     ORDER BY skill_name'
)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skills | CareerBridge</title>
</head>
<body>

<h1>Skills Management</h1>

<p>
    <a href="dashboard.php">Dashboard</a> |
    <a href="profile.php">Career Profile</a> |
    <a href="../auth/logout.php">Logout</a>
</p>

<h2>Current Skills</h2>

<?php if (!$skills): ?>

    <p>No skills added yet.</p>

<?php else: ?>

    <table border="1">
        <tr>
            <th>Skill</th>
            <th>Proficiency</th>
        </tr>

        <?php foreach ($skills as $skill): ?>
            <tr>
                <td>
                    <?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
                </td>
                <td>
                    <?= htmlspecialchars($skill['proficiency_level'], ENT_QUOTES, 'UTF-8') ?>
                </td>
            </tr>
        <?php endforeach; ?>

    </table>

<?php endif; ?>

<h2>Add Skill</h2>

<form method="POST" action="save_skill.php">

    <label for="skill_id">Skill</label><br>

    <select id="skill_id" name="skill_id" required>
        <option value="">Select a skill</option>

        <?php foreach ($allSkills as $skill): ?>
            <option value="<?= (int) $skill['skill_id'] ?>">
                <?= htmlspecialchars($skill['skill_name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>

    </select>

    <br><br>

    <label for="proficiency_level">Proficiency</label><br>

    <select id="proficiency_level" name="proficiency_level" required>
        <option value="beginner">Beginner</option>
        <option value="intermediate">Intermediate</option>
        <option value="advanced">Advanced</option>
        <option value="expert">Expert</option>
    </select>

    <br><br>

    <button type="submit">Add Skill</button>

</form>

</body>
</html>