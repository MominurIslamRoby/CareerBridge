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
   GET RESUME
   Ensure the resume belongs to
   the currently logged-in student.
========================================= */

$stmt = $pdo->prepare(
    '
    SELECT
        r.resume_id,
        r.student_id,
        r.file_name,
        r.file_path,
        r.is_primary

    FROM resumes r

    INNER JOIN students s
        ON s.student_id = r.student_id

    WHERE r.resume_id = ?
      AND s.user_id = ?

    LIMIT 1
    '
);

$stmt->execute([
    $resumeId,
    $userId
]);

$resume = $stmt->fetch();


if (!$resume) {

    header('Location: resume.php?error=notfound');

    exit;
}


/* =========================================
   PREPARE DATA
========================================= */

$filePath =
    __DIR__ . '/../' .
    $resume['file_path'];


$studentId =
    (int) $resume['student_id'];


$wasPrimary =
    (int) $resume['is_primary'] === 1;


/* =========================================
   DELETE FROM DATABASE
========================================= */

try {

    $pdo->beginTransaction();


    /*
     * Delete the selected resume.
     */

    $deleteStmt = $pdo->prepare(
        '
        DELETE FROM resumes
        WHERE resume_id = ?
          AND student_id = ?
        '
    );

    $deleteStmt->execute([
        $resumeId,
        $studentId
    ]);


    /*
     * If the deleted resume was primary,
     * automatically make the newest
     * remaining resume primary.
     */

    if ($wasPrimary) {

        $nextStmt = $pdo->prepare(
            '
            SELECT resume_id
            FROM resumes
            WHERE student_id = ?
            ORDER BY uploaded_at DESC
            LIMIT 1
            '
        );

        $nextStmt->execute([
            $studentId
        ]);

        $nextResume = $nextStmt->fetch();


        if ($nextResume) {

            $primaryStmt = $pdo->prepare(
                '
                UPDATE resumes
                SET is_primary = 1
                WHERE resume_id = ?
                  AND student_id = ?
                '
            );

            $primaryStmt->execute([
                $nextResume['resume_id'],
                $studentId
            ]);
        }
    }


    /*
     * Commit database changes.
     */

    $pdo->commit();


} catch (Throwable $e) {


    /*
     * Roll back transaction if something fails.
     */

    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    header('Location: resume.php?error=delete');

    exit;
}


/* =========================================
   DELETE PHYSICAL FILE
========================================= */

if (is_file($filePath)) {

    if (!@unlink($filePath)) {

        /*
         * Database deletion was successful,
         * so we do not roll it back here.
         * The missing physical file does not
         * affect database integrity.
         */

    }
}


/* =========================================
   SUCCESS REDIRECT
========================================= */

header('Location: resume.php?success=deleted');

exit;