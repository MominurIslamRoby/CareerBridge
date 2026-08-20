<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: skills.php');
    exit;
}

$skillId = filter_input(INPUT_POST, 'skill_id', FILTER_VALIDATE_INT);
$proficiency = $_POST['proficiency_level'] ?? '';

$allowedLevels = [
    'beginner',
    'intermediate',
    'advanced',
    'expert'
];

if (!$skillId || !in_array($proficiency, $allowedLevels, true)) {
    exit('Invalid skill data.');
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

$skillStmt = $pdo->prepare(
    'SELECT skill_id
     FROM skills
     WHERE skill_id = ?
     LIMIT 1'
);

$skillStmt->execute([$skillId]);

if (!$skillStmt->fetch()) {
    exit('Skill not found.');
}

$stmt = $pdo->prepare(
    'INSERT INTO student_skills
        (student_id, skill_id, proficiency_level)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE
        proficiency_level = VALUES(proficiency_level)'
);

$stmt->execute([
    $student['student_id'],
    $skillId,
    $proficiency
]);

header('Location: skills.php');
exit;