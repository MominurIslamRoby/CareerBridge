<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');


/* =========================================
   GET CURRENT USER
========================================= */

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   VALIDATE RESUME ID
========================================= */

$resumeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


if (!$resumeId) {

    header('Location: resume.php?error=invalid');

    exit;
}


/* =========================================
   GET RESUME
   Ensure resume belongs to logged-in student
========================================= */

$stmt = $pdo->prepare(
    '
    SELECT
        r.resume_id,
        r.file_name,
        r.file_path

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
   GET FILE PATH
========================================= */

$filePath =
    __DIR__ . '/../' .
    $resume['file_path'];


/* =========================================
   VERIFY FILE EXISTS
========================================= */

if (
    !is_file($filePath) ||
    !is_readable($filePath)
) {

    header('Location: resume.php?error=file');

    exit;
}


/* =========================================
   SEND PDF FILE TO BROWSER
========================================= */

header('Content-Type: application/pdf');

header(
    'Content-Disposition: inline; filename="' .
    basename($resume['file_name']) .
    '"'
);

header(
    'Content-Length: ' .
    (string) filesize($filePath)
);

header('X-Content-Type-Options: nosniff');


/* =========================================
   OUTPUT FILE
========================================= */

readfile($filePath);

exit;