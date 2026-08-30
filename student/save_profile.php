<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');


/* =========================================
   ONLY ALLOW POST REQUESTS
========================================= */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: profile.php');

    exit;
}


/* =========================================
   GET CURRENT USER
========================================= */

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   GET AND SANITIZE FORM DATA
========================================= */

$phone = trim(
    (string) ($_POST['phone'] ?? '')
);

$location = trim(
    (string) ($_POST['location'] ?? '')
);

$universityName = trim(
    (string) ($_POST['university_name'] ?? '')
);

$department = trim(
    (string) ($_POST['department'] ?? '')
);

$academicLevel = trim(
    (string) ($_POST['academic_level'] ?? '')
);

$careerSummary = trim(
    (string) ($_POST['career_summary'] ?? '')
);

$careerInterests = trim(
    (string) ($_POST['career_interests'] ?? '')
);


/* =========================================
   VALIDATE FIELD LENGTHS
========================================= */

if (
    strlen($phone) > 50 ||
    strlen($location) > 150 ||
    strlen($universityName) > 255 ||
    strlen($department) > 150 ||
    strlen($academicLevel) > 100 ||
    strlen($careerSummary) > 3000 ||
    strlen($careerInterests) > 2000
) {

    header('Location: profile.php?error=length');

    exit;
}


/* =========================================
   GET CURRENT STUDENT
========================================= */

$studentStmt = $pdo->prepare(
    '
    SELECT
        student_id
    FROM students
    WHERE user_id = ?
    LIMIT 1
    '
);

$studentStmt->execute([
    $userId
]);

$student = $studentStmt->fetch();


if (!$student) {

    header('Location: profile.php?error=student');

    exit;
}


$studentId = (int) $student['student_id'];


/* =========================================
   UPDATE STUDENT PROFILE
========================================= */

try {

    $updateStmt = $pdo->prepare(
        '
        UPDATE students
        SET
            phone = ?,
            location = ?,
            university_name = ?,
            department = ?,
            academic_level = ?,
            career_summary = ?,
            career_interests = ?
        WHERE student_id = ?
        '
    );


    $updateStmt->execute([
        $phone !== '' ? $phone : null,
        $location !== '' ? $location : null,
        $universityName !== '' ? $universityName : null,
        $department !== '' ? $department : null,
        $academicLevel !== '' ? $academicLevel : null,
        $careerSummary !== '' ? $careerSummary : null,
        $careerInterests !== '' ? $careerInterests : null,
        $studentId
    ]);


} catch (Throwable $e) {

    header('Location: profile.php?error=database');

    exit;
}


/* =========================================
   SUCCESS REDIRECT
========================================= */

header('Location: profile.php?success=1');

exit;