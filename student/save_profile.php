<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit;
}

$universityName = trim($_POST['university_name'] ?? '');
$department = trim($_POST['department'] ?? '');
$academicLevel = trim($_POST['academic_level'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$location = trim($_POST['location'] ?? '');
$careerSummary = trim($_POST['career_summary'] ?? '');
$careerInterests = trim($_POST['career_interests'] ?? '');

$stmt = $pdo->prepare(
    'UPDATE students
     SET university_name = ?,
         department = ?,
         academic_level = ?,
         phone = ?,
         location = ?,
         career_summary = ?,
         career_interests = ?
     WHERE user_id = ?'
);

$stmt->execute([
    $universityName,
    $department,
    $academicLevel,
    $phone,
    $location,
    $careerSummary,
    $careerInterests,
    $user['id']
]);

header('Location: profile.php');
exit;