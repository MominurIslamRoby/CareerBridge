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

    header('Location: resume.php');

    exit;
}


/* =========================================
   GET CURRENT USER
========================================= */

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   VALIDATE RESUME ID
========================================= */

$resumeId = filter_input(
    INPUT_POST,
    'resume_id',
    FILTER_VALIDATE_INT
);


if (!$resumeId) {

    header('Location: resume.php?error=invalid');

    exit;
}


/* =========================================
   GET STUDENT
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

    header('Location: resume.php?error=student');

    exit;
}


$studentId = (int) $student['student_id'];


/* =========================================
   VERIFY RESUME OWNERSHIP
========================================= */

$resumeStmt = $pdo->prepare(
    '
    SELECT
        resume_id,
        is_primary
    FROM resumes
    WHERE resume_id = ?
      AND student_id = ?
    LIMIT 1
    '
);

$resumeStmt->execute([
    $resumeId,
    $studentId
]);

$resume = $resumeStmt->fetch();


if (!$resume) {

    header('Location: resume.php?error=notfound');

    exit;
}


/* =========================================
   CHECK IF ALREADY PRIMARY
========================================= */

if ((int) $resume['is_primary'] === 1) {

    header('Location: resume.php?success=primary');

    exit;
}


/* =========================================
   SET PRIMARY RESUME
========================================= */

try {

    $pdo->beginTransaction();


    /*
     * Remove primary status from
     * all student's resumes.
     */

    $resetStmt = $pdo->prepare(
        '
        UPDATE resumes
        SET is_primary = 0
        WHERE student_id = ?
        '
    );

    $resetStmt->execute([
        $studentId
    ]);


    /*
     * Set selected resume as primary.
     */

    $primaryStmt = $pdo->prepare(
        '
        UPDATE resumes
        SET is_primary = 1
        WHERE resume_id = ?
          AND student_id = ?
        '
    );

    $primaryStmt->execute([
        $resumeId,
        $studentId
    ]);


    $pdo->commit();


} catch (Throwable $e) {


    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    header('Location: resume.php?error=primary');

    exit;
}


/* =========================================
   SUCCESS REDIRECT
========================================= */

header('Location: resume.php?success=primary');

exit;