<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$resumeId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$resumeId) {
    exit('Invalid resume ID.');
}

$stmt = $pdo->prepare(
    'SELECT
        r.resume_id,
        r.file_path,
        r.is_primary,
        s.student_id
     FROM resumes r
     INNER JOIN students s
        ON s.student_id = r.student_id
     WHERE r.resume_id = ?
       AND s.user_id = ?
     LIMIT 1'
);

$stmt->execute([
    $resumeId,
    $user['id']
]);

$resume = $stmt->fetch();

if (!$resume) {
    exit('Resume not found or access denied.');
}

$filePath = __DIR__ . '/../' . $resume['file_path'];

try {
    $pdo->beginTransaction();

    $deleteStmt = $pdo->prepare(
        'DELETE FROM resumes
         WHERE resume_id = ?'
    );

    $deleteStmt->execute([$resumeId]);

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    exit('Resume could not be deleted.');
}

if (is_file($filePath)) {
    unlink($filePath);
}

if ((int) $resume['is_primary'] === 1) {

    $nextStmt = $pdo->prepare(
        'SELECT resume_id
         FROM resumes
         WHERE student_id = ?
         ORDER BY uploaded_at DESC
         LIMIT 1'
    );

    $nextStmt->execute([
        $resume['student_id']
    ]);

    $nextResume = $nextStmt->fetch();

    if ($nextResume) {

        $primaryStmt = $pdo->prepare(
            'UPDATE resumes
             SET is_primary = 1
             WHERE resume_id = ?'
        );

        $primaryStmt->execute([
            $nextResume['resume_id']
        ]);
    }
}

header('Location: resume.php');
exit;