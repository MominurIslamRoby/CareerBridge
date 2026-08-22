<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: resume.php');
    exit;
}

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

if (
    !isset($_FILES['resume']) ||
    $_FILES['resume']['error'] !== UPLOAD_ERR_OK
) {
    exit('Resume upload failed.');
}

$file = $_FILES['resume'];

$maxSize = 5 * 1024 * 1024;

if ($file['size'] > $maxSize) {
    exit('Resume file is too large. Maximum size is 5 MB.');
}

$originalName = basename($file['name']);

$extension = strtolower(
    pathinfo($originalName, PATHINFO_EXTENSION)
);

if ($extension !== 'pdf') {
    exit('Only PDF files are allowed.');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if ($mimeType !== 'application/pdf') {
    exit('Invalid PDF file.');
}

$uploadDirectory = __DIR__ . '/../uploads/resumes';

if (!is_dir($uploadDirectory)) {
    if (!mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
        exit('Could not create the resume upload directory.');
    }
}

$storedName =
    'student_' .
    $studentId .
    '_' .
    bin2hex(random_bytes(8)) .
    '.pdf';

$relativePath = 'uploads/resumes/' . $storedName;
$absolutePath = __DIR__ . '/../' . $relativePath;

if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
    exit('Could not save the uploaded resume.');
}

$isPrimary = isset($_POST['is_primary']);

try {
    $pdo->beginTransaction();

    if ($isPrimary) {
        $stmt = $pdo->prepare(
            'UPDATE resumes
             SET is_primary = 0
             WHERE student_id = ?'
        );

        $stmt->execute([$studentId]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO resumes
            (student_id, file_name, file_path, is_primary)
         VALUES (?, ?, ?, ?)'
    );

    $stmt->execute([
        $studentId,
        $originalName,
        $relativePath,
        $isPrimary ? 1 : 0
    ]);

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (is_file($absolutePath)) {
        unlink($absolutePath);
    }

    exit('Resume could not be saved.');
}

header('Location: resume.php');
exit;