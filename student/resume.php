<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$stmt = $pdo->prepare(
    'SELECT student_id
     FROM students
     WHERE user_id = ?
     LIMIT 1'
);

$stmt->execute([$user['id']]);
$student = $stmt->fetch();

if (!$student) {
    exit('Student profile not found.');
}

$studentId = (int) $student['student_id'];

$stmt = $pdo->prepare(
    'SELECT
        resume_id,
        file_name,
        file_path,
        uploaded_at,
        is_primary
     FROM resumes
     WHERE student_id = ?
     ORDER BY is_primary DESC, uploaded_at DESC'
);

$stmt->execute([$studentId]);
$resumes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Management | CareerBridge</title>
</head>

<body>

<h1>Resume / CV Management</h1>

<p>
    <a href="dashboard.php">Dashboard</a> |
    <a href="profile.php">Career Profile</a> |
    <a href="skills.php">Skills</a> |
    <a href="opportunities.php">Opportunities</a> |
    <a href="applications.php">My Applications</a> |
    <a href="../auth/logout.php">Logout</a>
</p>

<hr>

<h2>Upload Resume / CV</h2>

<form
    method="POST"
    action="upload_resume.php"
    enctype="multipart/form-data"
>

    <label for="resume">Select PDF Resume/CV</label>

    <br><br>

    <input
        type="file"
        id="resume"
        name="resume"
        accept=".pdf"
        required
    >

    <br><br>

    <label>
        <input
            type="checkbox"
            name="is_primary"
            value="1"
        >
        Set as primary resume
    </label>

    <br><br>

    <button type="submit">
        Upload Resume
    </button>

</form>

<hr>

<h2>My Resumes</h2>

<?php if (!$resumes): ?>

    <p>No resume has been uploaded yet.</p>

<?php else: ?>

    <table border="1" cellpadding="8">

        <tr>
            <th>File Name</th>
            <th>Uploaded At</th>
            <th>Primary</th>
            <th>Action</th>
        </tr>

        <?php foreach ($resumes as $resume): ?>

            <tr>

                <td>
                    <?= htmlspecialchars(
                        $resume['file_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>

                <td>
                    <?= htmlspecialchars(
                        $resume['uploaded_at'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>

                <td>
                    <?= (int) $resume['is_primary'] === 1
                        ? 'Yes'
                        : 'No'
                    ?>
                </td>

                <td>
                    <a
                        href="delete_resume.php?id=<?= (int) $resume['resume_id'] ?>"
                        onclick="return confirm('Delete this resume?');"
                    >
                        Delete
                    </a>
                </td>

            </tr>

        <?php endforeach; ?>

    </table>

<?php endif; ?>

</body>
</html>