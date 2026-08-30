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

    header('Location: skills.php');

    exit;
}


/* =========================================
   GET CURRENT USER
========================================= */

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   VALIDATE SKILL ID
========================================= */

$skillId = filter_input(
    INPUT_POST,
    'skill_id',
    FILTER_VALIDATE_INT
);


if (!$skillId) {

    header('Location: skills.php?error=invalid');

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

    header('Location: skills.php?error=student');

    exit;
}


$studentId = (int) $student['student_id'];


/* =========================================
   VERIFY STUDENT OWNS THE SKILL
========================================= */

$checkStmt = $pdo->prepare(
    '
    SELECT
        skill_id
    FROM student_skills
    WHERE student_id = ?
      AND skill_id = ?
    LIMIT 1
    '
);


$checkStmt->execute([
    $studentId,
    $skillId
]);


if (!$checkStmt->fetch()) {

    header('Location: skills.php?error=skill');

    exit;
}


/* =========================================
   DELETE STUDENT SKILL
========================================= */

try {

    $deleteStmt = $pdo->prepare(
        '
        DELETE FROM student_skills
        WHERE student_id = ?
          AND skill_id = ?
        '
    );


    $deleteStmt->execute([
        $studentId,
        $skillId
    ]);


    if ($deleteStmt->rowCount() !== 1) {

        header('Location: skills.php?error=delete');

        exit;
    }


} catch (Throwable $e) {

    header('Location: skills.php?error=delete');

    exit;
}


/* =========================================
   SUCCESS REDIRECT
========================================= */

header('Location: skills.php?deleted=1');

exit;