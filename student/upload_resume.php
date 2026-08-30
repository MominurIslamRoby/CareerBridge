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
   GET STUDENT
========================================= */

$studentStmt = $pdo->prepare(
    '
    SELECT student_id
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
   VALIDATE FILE UPLOAD
========================================= */

if (!isset($_FILES['resume'])) {

    header('Location: resume.php?error=nofile');

    exit;
}


if (!is_array($_FILES['resume'])) {

    header('Location: resume.php?error=upload');

    exit;
}


if ($_FILES['resume']['error'] !== UPLOAD_ERR_OK) {

    $uploadError = (int) $_FILES['resume']['error'];

    switch ($uploadError) {

        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:

            header('Location: resume.php?error=size');
            exit;


        case UPLOAD_ERR_PARTIAL:

            header('Location: resume.php?error=partial');
            exit;


        case UPLOAD_ERR_NO_FILE:

            header('Location: resume.php?error=nofile');
            exit;


        default:

            header('Location: resume.php?error=upload');
            exit;
    }
}


$file = $_FILES['resume'];


/* =========================================
   VALIDATE FILE SIZE
   Maximum: 5 MB
========================================= */

$maxSize = 5 * 1024 * 1024;


if (
    !isset($file['size']) ||
    (int) $file['size'] <= 0
) {

    header('Location: resume.php?error=empty');

    exit;
}


if ((int) $file['size'] > $maxSize) {

    header('Location: resume.php?error=size');

    exit;
}


/* =========================================
   VALIDATE TEMPORARY UPLOAD
========================================= */

if (
    !isset($file['tmp_name']) ||
    !is_uploaded_file($file['tmp_name'])
) {

    header('Location: resume.php?error=temp');

    exit;
}


/* =========================================
   GET ORIGINAL FILE NAME
========================================= */

$originalName = basename(
    (string) $file['name']
);


if ($originalName === '') {

    header('Location: resume.php?error=filename');

    exit;
}


/* =========================================
   VALIDATE FILE EXTENSION
========================================= */

$extension = strtolower(
    pathinfo(
        $originalName,
        PATHINFO_EXTENSION
    )
);


if ($extension !== 'pdf') {

    header('Location: resume.php?error=format');

    exit;
}


/* =========================================
   VALIDATE MIME TYPE
========================================= */

$finfo = new finfo(
    FILEINFO_MIME_TYPE
);


$mimeType = $finfo->file(
    $file['tmp_name']
);


/*
 * Different PHP environments may report
 * valid PDF files with slightly different
 * MIME types.
 */

$allowedMimeTypes = [
    'application/pdf',
    'application/x-pdf',
    'application/acrobat',
    'applications/vnd.pdf',
    'text/pdf',
    'text/x-pdf'
];


if (!in_array($mimeType, $allowedMimeTypes, true)) {

    header('Location: resume.php?error=format');

    exit;
}


/* =========================================
   CREATE UPLOAD DIRECTORY
========================================= */

$uploadDirectory =
    __DIR__ . '/../uploads/resumes';


if (!is_dir($uploadDirectory)) {

    if (
        !mkdir(
            $uploadDirectory,
            0775,
            true
        )
        &&
        !is_dir($uploadDirectory)
    ) {

        header('Location: resume.php?error=directory');

        exit;
    }
}


/* =========================================
   CHECK DIRECTORY WRITABILITY
========================================= */

if (!is_writable($uploadDirectory)) {

    header('Location: resume.php?error=directory');

    exit;
}


/* =========================================
   GENERATE SECURE FILE NAME
========================================= */

try {

    $randomName = bin2hex(
        random_bytes(16)
    );

} catch (Throwable $e) {

    header('Location: resume.php?error=random');

    exit;
}


$storedName =
    'student_' .
    $studentId .
    '_' .
    $randomName .
    '.pdf';


/* =========================================
   FILE PATHS
========================================= */

$relativePath =
    'uploads/resumes/' .
    $storedName;


$absolutePath =
    $uploadDirectory .
    '/' .
    $storedName;


/* =========================================
   MOVE UPLOADED FILE
========================================= */

if (
    !move_uploaded_file(
        $file['tmp_name'],
        $absolutePath
    )
) {

    header('Location: resume.php?error=move');

    exit;
}


/* =========================================
   VERIFY FILE WAS SAVED
========================================= */

if (!is_file($absolutePath)) {

    header('Location: resume.php?error=save');

    exit;
}


/* =========================================
   PRIMARY RESUME STATUS
========================================= */

$isPrimary =
    isset($_POST['is_primary']) &&
    $_POST['is_primary'] === '1';


/* =========================================
   SAVE RESUME TO DATABASE
========================================= */

try {

    $pdo->beginTransaction();


    /*
     * If this is the student's first resume,
     * automatically make it primary.
     */

    $countStmt = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM resumes
        WHERE student_id = ?
        '
    );

    $countStmt->execute([
        $studentId
    ]);


    $existingResumeCount =
        (int) $countStmt->fetchColumn();


    if ($existingResumeCount === 0) {

        $isPrimary = true;
    }


    /*
     * If user selected this resume as primary,
     * remove primary status from all others.
     */

    if ($isPrimary) {

        $updateStmt = $pdo->prepare(
            '
            UPDATE resumes
            SET is_primary = 0
            WHERE student_id = ?
            '
        );

        $updateStmt->execute([
            $studentId
        ]);
    }


    /*
     * Insert new resume.
     */

    $insertStmt = $pdo->prepare(
        '
        INSERT INTO resumes
        (
            student_id,
            file_name,
            file_path,
            is_primary
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?
        )
        '
    );


    $insertResult = $insertStmt->execute([
        $studentId,
        $originalName,
        $relativePath,
        $isPrimary ? 1 : 0
    ]);


    if (!$insertResult) {

        throw new RuntimeException(
            'Resume database insertion failed.'
        );
    }


    /*
     * Verify a new ID was generated.
     */

    $newResumeId =
        (int) $pdo->lastInsertId();


    if ($newResumeId <= 0) {

        throw new RuntimeException(
            'Resume ID was not generated.'
        );
    }


    $pdo->commit();


} catch (Throwable $e) {


    if ($pdo->inTransaction()) {

        $pdo->rollBack();
    }


    /*
     * Remove physical file because
     * database insertion failed.
     */

    if (is_file($absolutePath)) {

        @unlink($absolutePath);
    }


    /*
     * Temporary debugging log.
     */

    error_log(
        'Resume Upload Error: ' .
        $e->getMessage()
    );


    header('Location: resume.php?error=database');

    exit;
}


/* =========================================
   SUCCESS REDIRECT
========================================= */

header('Location: resume.php?success=uploaded');

exit;