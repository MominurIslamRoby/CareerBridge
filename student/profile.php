<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$stmt = $pdo->prepare(
    'SELECT
        s.student_id,
        s.student_id_number,
        s.university_name,
        s.department,
        s.academic_level,
        s.phone,
        s.location,
        s.career_summary,
        s.career_interests,
        u.full_name,
        u.email
     FROM students s
     INNER JOIN users u ON u.user_id = s.user_id
     WHERE s.user_id = ?
     LIMIT 1'
);

$stmt->execute([$user['id']]);
$student = $stmt->fetch();

if (!$student) {
    exit('Student profile not found.');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Profile | CareerBridge</title>
</head>
<body>

<h1>Career Profile</h1>

<p>
    <a href="dashboard.php">Dashboard</a> |
    <a href="../auth/logout.php">Logout</a>
</p>

<form method="POST" action="save_profile.php">

    <label>Full Name</label><br>
    <input
        type="text"
        value="<?= htmlspecialchars($student['full_name'], ENT_QUOTES, 'UTF-8') ?>"
        disabled
    >
    <br><br>

    <label>Email</label><br>
    <input
        type="email"
        value="<?= htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8') ?>"
        disabled
    >
    <br><br>

    <label for="student_id_number">Student ID</label><br>
    <input
        type="text"
        id="student_id_number"
        value="<?= htmlspecialchars($student['student_id_number'], ENT_QUOTES, 'UTF-8') ?>"
        disabled
    >
    <br><br>

    <label for="university_name">University</label><br>
    <input
        type="text"
        id="university_name"
        name="university_name"
        value="<?= htmlspecialchars($student['university_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    >
    <br><br>

    <label for="department">Department</label><br>
    <input
        type="text"
        id="department"
        name="department"
        value="<?= htmlspecialchars($student['department'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    >
    <br><br>

    <label for="academic_level">Academic Level</label><br>
    <input
        type="text"
        id="academic_level"
        name="academic_level"
        value="<?= htmlspecialchars($student['academic_level'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    >
    <br><br>

    <label for="phone">Phone</label><br>
    <input
        type="text"
        id="phone"
        name="phone"
        value="<?= htmlspecialchars($student['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    >
    <br><br>

    <label for="location">Location</label><br>
    <input
        type="text"
        id="location"
        name="location"
        value="<?= htmlspecialchars($student['location'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
    >
    <br><br>

    <label for="career_summary">Career Summary</label><br>
    <textarea
        id="career_summary"
        name="career_summary"
        rows="5"
        cols="50"
    ><?= htmlspecialchars($student['career_summary'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    <br><br>

    <label for="career_interests">Career Interests</label><br>
    <textarea
        id="career_interests"
        name="career_interests"
        rows="4"
        cols="50"
    ><?= htmlspecialchars($student['career_interests'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
    <br><br>

    <button type="submit">Save Profile</button>

</form>

</body>
</html>