<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$userId = (int) $_SESSION['user_id'];

$studentStmt = $pdo->prepare(
    '
    SELECT
        student_id,
        student_id_number,
        university_name,
        department,
        academic_level
    FROM students
    WHERE user_id = ?
    LIMIT 1
    '
);

$studentStmt->execute([$userId]);

$student = $studentStmt->fetch();

if (!$student) {
    exit('Student profile not found.');
}

$studentId = (int) $student['student_id'];

$opportunityId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$opportunityId) {
    http_response_code(400);
    exit('Invalid opportunity ID.');
}

/*
 * Get opportunity information.
 */
$opportunityStmt = $pdo->prepare(
    '
    SELECT
        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.description,
        o.location,
        o.duration,
        o.deadline,
        o.status,
        e.company_name
    FROM opportunities o
    INNER JOIN employers e
        ON e.employer_id = o.employer_id
    WHERE o.opportunity_id = ?
    LIMIT 1
    '
);

$opportunityStmt->execute([$opportunityId]);

$opportunity = $opportunityStmt->fetch();

if (!$opportunity) {
    http_response_code(404);
    exit('Opportunity not found.');
}

$error = '';
$success = '';

/*
 * Check whether the student has already applied.
 */
$existingStmt = $pdo->prepare(
    '
    SELECT
        application_id,
        status,
        applied_at
    FROM applications
    WHERE opportunity_id = ?
      AND student_id = ?
    LIMIT 1
    '
);

$existingStmt->execute([
    $opportunityId,
    $studentId
]);

$existingApplication = $existingStmt->fetch();

/*
 * Get student's resumes.
 */
$resumeStmt = $pdo->prepare(
    '
    SELECT
        resume_id,
        file_name,
        file_path,
        is_primary
    FROM resumes
    WHERE student_id = ?
    ORDER BY is_primary DESC, uploaded_at DESC
    '
);

$resumeStmt->execute([$studentId]);

$resumes = $resumeStmt->fetchAll();

/*
 * Find the primary resume.
 */
$primaryResumeId = null;

foreach ($resumes as $resume) {
    if ((int) $resume['is_primary'] === 1) {
        $primaryResumeId = (int) $resume['resume_id'];
        break;
    }
}

/*
 * Handle application submission.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($existingApplication) {

        $error = 'You have already applied for this opportunity.';

    } elseif ($opportunity['status'] !== 'open') {

        $error = 'This opportunity is no longer accepting applications.';

    } else {

        $resumeId = filter_input(
            INPUT_POST,
            'resume_id',
            FILTER_VALIDATE_INT
        );

        /*
         * If the student did not explicitly select a resume,
         * automatically use the primary resume.
         */
        if ($resumeId === false || $resumeId === null) {
            $resumeId = $primaryResumeId;
        }

        $coverLetter = trim(
            $_POST['cover_letter'] ?? ''
        );

        /*
         * Validate resume if one is being used.
         */
        if ($resumeId !== null) {

            $resumeCheckStmt = $pdo->prepare(
                '
                SELECT resume_id
                FROM resumes
                WHERE resume_id = ?
                  AND student_id = ?
                LIMIT 1
                '
            );

            $resumeCheckStmt->execute([
                $resumeId,
                $studentId
            ]);

            if (!$resumeCheckStmt->fetch()) {
                $error = 'Invalid resume selected.';
            }
        }

        /*
         * Require a cover letter.
         */
        if ($error === '' && $coverLetter === '') {
            $error = 'Please enter a cover letter.';
        }

        if ($error === '') {

            try {

                $insertStmt = $pdo->prepare(
                    '
                    INSERT INTO applications
                        (
                            opportunity_id,
                            student_id,
                            resume_id,
                            cover_letter,
                            status
                        )
                    VALUES
                        (?, ?, ?, ?, ?)
                    '
                );

                $insertStmt->execute([
                    $opportunityId,
                    $studentId,
                    $resumeId,
                    $coverLetter,
                    'submitted'
                ]);

                $success = 'Application submitted successfully.';

                /*
                 * Refresh existing application information.
                 */
                $existingStmt->execute([
                    $opportunityId,
                    $studentId
                ]);

                $existingApplication = $existingStmt->fetch();

            } catch (PDOException $e) {

                $error = 'Application could not be submitted. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Apply | CareerBridge</title>
</head>

<body>

<h1>Apply for Opportunity</h1>

<p>
    <a href="dashboard.php">Dashboard</a> |
    <a href="opportunities.php">Opportunities</a> |
    <a href="profile.php">Career Profile</a> |
    <a href="skills.php">Skills</a> |
    <a href="resume.php">Resume</a> |
    <a href="../auth/logout.php">Logout</a>
</p>

<hr>

<h2>
    <?= htmlspecialchars(
        $opportunity['title'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</h2>

<p>
    <strong>Company:</strong>
    <?= htmlspecialchars(
        $opportunity['company_name'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Type:</strong>
    <?= htmlspecialchars(
        $opportunity['opportunity_type'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Location:</strong>
    <?= htmlspecialchars(
        $opportunity['location'] ?? 'Not specified',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Duration:</strong>
    <?= htmlspecialchars(
        $opportunity['duration'] ?? 'Not specified',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<p>
    <strong>Deadline:</strong>
    <?= htmlspecialchars(
        $opportunity['deadline'],
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<hr>

<?php if ($error !== ''): ?>

    <p>
        <strong>
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>

<?php endif; ?>

<?php if ($success !== ''): ?>

    <p>
        <strong>
            <?= htmlspecialchars(
                $success,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>
    </p>

<?php endif; ?>

<?php if ($existingApplication): ?>

    <h3>Application Submitted</h3>

    <p>
        You have already applied for this opportunity.
    </p>

    <p>
        <strong>Application Status:</strong>
        <?= htmlspecialchars(
            $existingApplication['status'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

    <p>
        <strong>Applied At:</strong>
        <?= htmlspecialchars(
            $existingApplication['applied_at'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </p>

<?php elseif ($opportunity['status'] !== 'open'): ?>

    <p>
        This opportunity is not currently accepting applications.
    </p>

<?php else: ?>

    <h3>Application Form</h3>

    <form method="POST" action="">

        <?php if ($resumes): ?>

            <div>

                <label for="resume_id">
                    Select Resume
                </label>

                <br>

                <select
                    id="resume_id"
                    name="resume_id"
                >

                    <?php foreach ($resumes as $resume): ?>

                        <option
                            value="<?= (int) $resume['resume_id'] ?>"
                            <?= (
                                (int) $resume['resume_id']
                                === $primaryResumeId
                            ) ? 'selected' : '' ?>
                        >

                            <?= htmlspecialchars(
                                $resume['file_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <?php if ((int) $resume['is_primary'] === 1): ?>
                                (Primary)
                            <?php endif; ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <?php if ($primaryResumeId !== null): ?>

                    <p>
                        Your primary resume is selected automatically.
                    </p>

                <?php endif; ?>

            </div>

            <br>

        <?php else: ?>

            <p>
                You currently have no uploaded resume.
            </p>

            <p>
                You can still submit an application with a cover letter.
            </p>

            <p>
                <a href="resume.php">
                    Upload a Resume
                </a>
            </p>

        <?php endif; ?>

        <div>

            <label for="cover_letter">
                Cover Letter
            </label>

            <br>

            <textarea
                id="cover_letter"
                name="cover_letter"
                rows="12"
                cols="70"
                required
                placeholder="Write your cover letter here..."
            ></textarea>

        </div>

        <br>

        <button type="submit">
            Submit Application
        </button>

    </form>

<?php endif; ?>

<hr>

<p>
    <a
        href="opportunity_details.php?id=<?= (int) $opportunity['opportunity_id'] ?>"
    >
        ← Back to Opportunity
    </a>
</p>

</body>

</html>