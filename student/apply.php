<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   GET STUDENT INFORMATION
========================================= */

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

    http_response_code(404);

    exit('Student profile not found.');
}

$studentId = (int) $student['student_id'];


/* =========================================
   VALIDATE OPPORTUNITY ID
========================================= */

$opportunityId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$opportunityId) {

    http_response_code(400);

    exit('Invalid opportunity ID.');
}


/* =========================================
   GET OPPORTUNITY INFORMATION
========================================= */

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


/* =========================================
   INITIALIZE VARIABLES
========================================= */

$error = '';

$success = '';

$coverLetter = '';

$selectedResumeId = null;


/* =========================================
   CHECK APPLICATION STATUS
========================================= */

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


/* =========================================
   FORMAT DEADLINE
========================================= */

$formattedDeadline = 'Not specified';

if (!empty($opportunity['deadline'])) {

    $deadlineTimestamp = strtotime(
        $opportunity['deadline']
    );

    if ($deadlineTimestamp !== false) {

        $formattedDeadline = date(
            'd M Y',
            $deadlineTimestamp
        );
    }
}


/* =========================================
   CHECK DEADLINE STATUS
========================================= */

$isDeadlinePassed = false;

if (!empty($opportunity['deadline'])) {

    $deadlineTimestamp = strtotime(
        $opportunity['deadline']
    );

    if (
        $deadlineTimestamp !== false
        && $deadlineTimestamp < strtotime('today')
    ) {

        $isDeadlinePassed = true;
    }
}


/* =========================================
   GET STUDENT RESUMES
========================================= */

$resumeStmt = $pdo->prepare(
    '
    SELECT
        resume_id,
        file_name,
        file_path,
        is_primary

    FROM resumes

    WHERE student_id = ?

    ORDER BY
        is_primary DESC,
        uploaded_at DESC
    '
);

$resumeStmt->execute([$studentId]);

$resumes = $resumeStmt->fetchAll();


/* =========================================
   FIND PRIMARY RESUME
========================================= */

$primaryResumeId = null;

foreach ($resumes as $resume) {

    if ((int) $resume['is_primary'] === 1) {

        $primaryResumeId = (int) $resume['resume_id'];

        break;
    }
}


/* =========================================
   DEFAULT SELECTED RESUME
========================================= */

$selectedResumeId = $primaryResumeId;


/* =========================================
   APPLICATION AVAILABILITY
========================================= */

$canApply =
    $opportunity['status'] === 'open'
    && !$isDeadlinePassed
    && !$existingApplication;


/* =========================================
   HANDLE APPLICATION SUBMISSION
========================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /* -----------------------------------------
       GET FORM DATA
    ----------------------------------------- */

    $coverLetter = trim(
        $_POST['cover_letter'] ?? ''
    );

    $resumeId = filter_input(
        INPUT_POST,
        'resume_id',
        FILTER_VALIDATE_INT
    );


    /* -----------------------------------------
       PRESERVE SELECTED RESUME
    ----------------------------------------- */

    if ($resumeId !== false && $resumeId !== null) {

        $selectedResumeId = (int) $resumeId;

    } else {

        $resumeId = $primaryResumeId;

        $selectedResumeId = $primaryResumeId;
    }


    /* -----------------------------------------
       RE-CHECK APPLICATION STATUS
       SERVER-SIDE SECURITY
    ----------------------------------------- */

    $existingStmt->execute([
        $opportunityId,
        $studentId
    ]);

    $existingApplication = $existingStmt->fetch();


    /* -----------------------------------------
       VALIDATE APPLICATION
    ----------------------------------------- */

    if ($existingApplication) {

        $error =
            'You have already applied for this opportunity.';

    } elseif ($opportunity['status'] !== 'open') {

        $error =
            'This opportunity is no longer accepting applications.';

    } elseif ($isDeadlinePassed) {

        $error =
            'The application deadline for this opportunity has passed.';

    } elseif ($coverLetter === '') {

        $error =
            'Please enter a cover letter.';
    }


    /* -----------------------------------------
       VALIDATE SELECTED RESUME
    ----------------------------------------- */

    if ($error === '' && $resumeId !== null) {

        $resumeCheckStmt = $pdo->prepare(
            '
            SELECT
                resume_id

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

        $validResume = $resumeCheckStmt->fetch();


        if (!$validResume) {

            $error =
                'Invalid resume selected.';
        }
    }


    /* -----------------------------------------
       SUBMIT APPLICATION
       + CREATE NOTIFICATION
    ----------------------------------------- */

    if ($error === '') {

        try {

            $pdo->beginTransaction();


            /* -----------------------------------------
               INSERT APPLICATION
            ----------------------------------------- */

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


            /* -----------------------------------------
               CREATE NOTIFICATION
            ----------------------------------------- */

            $notificationStmt = $pdo->prepare(
                '
                INSERT INTO notifications
                    (
                        user_id,
                        title,
                        message,
                        type
                    )

                VALUES
                    (?, ?, ?, ?)
                '
            );


            $notificationTitle =
                'Application Submitted';


            $notificationMessage =
                'Your application for "' .
                $opportunity['title'] .
                '" at ' .
                $opportunity['company_name'] .
                ' has been submitted successfully.';


            $notificationStmt->execute([
                $userId,
                $notificationTitle,
                $notificationMessage,
                'application'
            ]);


            /* -----------------------------------------
               COMMIT TRANSACTION
            ----------------------------------------- */

            $pdo->commit();


            /* -----------------------------------------
               SUCCESS MESSAGE
            ----------------------------------------- */

            $success =
                'Application submitted successfully.';


            /* -----------------------------------------
               REFRESH APPLICATION INFORMATION
            ----------------------------------------- */

            $existingStmt->execute([
                $opportunityId,
                $studentId
            ]);

            $existingApplication =
                $existingStmt->fetch();


            $canApply = false;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            $error =
                'Application could not be submitted. Please try again.';
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

    <title>
        Apply for <?= htmlspecialchars(
            $opportunity['title'],
            ENT_QUOTES,
            'UTF-8'
        ) ?> | CareerBridge
    </title>


    <!-- MAIN STYLESHEET -->

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        referrerpolicy="no-referrer"
    >

</head>


<body>


<div class="app-layout">


    <!-- =====================================
         SIDEBAR
    ====================================== -->

    <aside class="sidebar">


        <!-- BRAND -->

        <div class="sidebar-brand">


            <div class="brand-logo">
                CB
            </div>


            <div>

                <h2>
                    CareerBridge
                </h2>

                <span>
                    Student Portal
                </span>

            </div>


        </div>


        <div class="sidebar-divider"></div>


        <p class="menu-label">
            MAIN MENU
        </p>


        <!-- NAVIGATION -->

        <nav class="sidebar-nav">


            <a href="dashboard.php">

                <span>
                    <i class="fa-solid fa-house"></i>
                </span>

                Dashboard

            </a>


            <a href="profile.php">

                <span>
                    <i class="fa-solid fa-user"></i>
                </span>

                Career Profile

            </a>


            <a href="skills.php">

                <span>
                    <i class="fa-solid fa-bolt"></i>
                </span>

                My Skills

            </a>


            <a href="resume.php">

                <span>
                    <i class="fa-solid fa-file-lines"></i>
                </span>

                Resume / CV

            </a>


            <a
                href="opportunities.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-briefcase"></i>
                </span>

                Opportunities

            </a>


            <a href="applications.php">

                <span>
                    <i class="fa-solid fa-file-circle-check"></i>
                </span>

                My Applications

            </a>


            <a href="notifications.php">

                <span>
                    <i class="fa-solid fa-bell"></i>
                </span>

                Notifications

            </a>


        </nav>


        <!-- LOGOUT -->

        <div class="sidebar-bottom">

            <a
                href="../auth/logout.php"
                class="logout-link"
            >

                <i class="fa-solid fa-right-from-bracket"></i>

                Logout

            </a>

        </div>


    </aside>



    <!-- =====================================
         MAIN CONTENT
    ====================================== -->

    <main class="main-content">


        <!-- =====================================
             PAGE HEADER
        ====================================== -->

        <div class="page-header">


            <div>

                <p class="breadcrumb">
                    STUDENT PORTAL / OPPORTUNITIES / APPLY
                </p>


                <h1>
                    Apply for Opportunity
                </h1>


                <p class="page-subtitle">

                    Complete your application and take the next step
                    toward your professional career.

                </p>

            </div>


            <!-- USER CARD -->

            <div class="user-card">


                <div class="user-avatar">

                    <?= strtoupper(
                        substr(
                            $user['full_name'],
                            0,
                            1
                        )
                    ) ?>

                </div>


                <div>

                    <strong>

                        <?= htmlspecialchars(
                            $user['full_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                    <span>
                        Student
                    </span>

                </div>


            </div>


        </div>



        <!-- =====================================
             OPPORTUNITY SUMMARY
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>

                    <p class="section-label">
                        OPPORTUNITY DETAILS
                    </p>


                    <h2>

                        <?= htmlspecialchars(
                            $opportunity['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h2>


                    <p>

                        <?= htmlspecialchars(
                            $opportunity['company_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>

                </div>


                <div class="section-icon">

                    <i class="fa-solid fa-briefcase"></i>

                </div>


            </div>



            <div class="application-meta opportunity-meta">


                <div>

                    <span class="meta-label">
                        TYPE
                    </span>

                    <strong>

                        <i class="fa-solid fa-briefcase"></i>

                        <?= htmlspecialchars(
                            ucfirst(
                                $opportunity['opportunity_type']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        LOCATION
                    </span>

                    <strong>

                        <i class="fa-solid fa-location-dot"></i>

                        <?= htmlspecialchars(
                            $opportunity['location']
                                ?? 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        DURATION
                    </span>

                    <strong>

                        <i class="fa-solid fa-clock"></i>

                        <?= htmlspecialchars(
                            $opportunity['duration']
                                ?? 'Not specified',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        DEADLINE
                    </span>

                    <strong>

                        <i class="fa-regular fa-calendar"></i>

                        <?= htmlspecialchars(
                            $formattedDeadline,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>


            </div>


        </section>



        <!-- =====================================
             ERROR MESSAGE
        ====================================== -->

        <?php if ($error !== ''): ?>


            <div class="alert alert-error">

                <strong>

                    <i class="fa-solid fa-circle-exclamation"></i>

                    Error

                </strong>


                <span>

                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>


        <?php endif; ?>



        <!-- =====================================
             SUCCESS MESSAGE
        ====================================== -->

        <?php if ($success !== ''): ?>


            <div class="alert alert-success">

                <strong>

                    <i class="fa-solid fa-circle-check"></i>

                    Success

                </strong>


                <span>

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </span>

            </div>


        <?php endif; ?>



        <!-- =====================================
             ALREADY APPLIED
        ====================================== -->

        <?php if ($existingApplication): ?>


            <section class="content-card application-complete-card">


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-circle-check"></i>

                    </div>


                    <h2>
                        Application Submitted
                    </h2>


                    <p>

                        You have already submitted an application
                        for this opportunity.

                    </p>


                    <div class="application-status-info">


                        <div>

                            <span>
                                APPLICATION STATUS
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                    ucfirst(
                                        $existingApplication['status']
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>

                        </div>



                        <div>

                            <span>
                                APPLIED ON
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                    date(
                                        'd M Y',
                                        strtotime(
                                            $existingApplication['applied_at']
                                        )
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>

                        </div>


                    </div>



                    <div class="form-actions">


                        <a
                            href="applications.php"
                            class="btn btn-primary"
                        >

                            View My Applications

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>


                        <a
                            href="opportunities.php"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-briefcase"></i>

                            Browse Opportunities

                        </a>


                    </div>


                </div>


            </section>



        <!-- =====================================
             DEADLINE PASSED
        ====================================== -->

        <?php elseif ($isDeadlinePassed): ?>


            <section class="content-card">


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-clock"></i>

                    </div>


                    <h2>
                        Application Deadline Passed
                    </h2>


                    <p>

                        The application deadline for this opportunity
                        has already passed.

                    </p>


                    <a
                        href="opportunities.php"
                        class="btn btn-primary"
                    >

                        Browse Other Opportunities

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>


                </div>


            </section>



        <!-- =====================================
             OPPORTUNITY CLOSED
        ====================================== -->

        <?php elseif ($opportunity['status'] !== 'open'): ?>


            <section class="content-card">


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-lock"></i>

                    </div>


                    <h2>
                        Applications Closed
                    </h2>


                    <p>

                        This opportunity is no longer accepting applications.

                    </p>


                    <a
                        href="opportunities.php"
                        class="btn btn-primary"
                    >

                        Browse Other Opportunities

                        <i class="fa-solid fa-arrow-right"></i>

                    </a>


                </div>


            </section>



        <!-- =====================================
             APPLICATION FORM
        ====================================== -->

        <?php else: ?>


            <form
                method="POST"
                action=""
                class="profile-form"
            >


                <!-- =====================================
                     RESUME SECTION
                ====================================== -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                APPLICATION DOCUMENT
                            </p>

                            <h2>
                                Select Resume
                            </h2>

                            <p>

                                Choose the resume or CV you want
                                to submit with your application.

                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-file-lines"></i>

                        </div>


                    </div>



                    <?php if ($resumes): ?>


                        <div class="form-group form-full">


                            <label for="resume_id">

                                Resume / CV

                            </label>


                            <select
                                id="resume_id"
                                name="resume_id"
                            >


                                <?php foreach ($resumes as $resume): ?>


                                    <option

                                        value="<?= (int) $resume['resume_id'] ?>"

                                        <?= (
                                            (int) $resume['resume_id']
                                            === $selectedResumeId
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>

                                    >

                                        <?= htmlspecialchars(
                                            $resume['file_name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>


                                        <?php if (
                                            (int) $resume['is_primary'] === 1
                                        ): ?>

                                            - Primary Resume

                                        <?php endif; ?>


                                    </option>


                                <?php endforeach; ?>


                            </select>



                            <?php if ($primaryResumeId !== null): ?>


                                <p class="form-help">

                                    Your primary resume is selected automatically.

                                </p>


                            <?php endif; ?>


                        </div>


                    <?php else: ?>


                        <div class="empty-state resume-empty">


                            <div class="empty-icon">

                                <i class="fa-solid fa-folder-open"></i>

                            </div>


                            <h3>
                                No Resume Uploaded
                            </h3>


                            <p>

                                You can still apply using a cover letter,
                                but adding a resume can strengthen your application.

                            </p>


                            <a
                                href="resume.php"
                                class="btn btn-secondary"
                            >

                                <i class="fa-solid fa-upload"></i>

                                Upload Resume

                            </a>


                        </div>


                    <?php endif; ?>


                </section>



                <!-- =====================================
                     COVER LETTER
                ====================================== -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                APPLICATION MESSAGE
                            </p>

                            <h2>
                                Cover Letter
                            </h2>

                            <p>

                                Explain why you are a strong candidate
                                for this opportunity.

                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-envelope"></i>

                        </div>


                    </div>



                    <div class="form-group form-full">


                        <label for="cover_letter">

                            Write Your Cover Letter

                        </label>


                        <textarea
                            id="cover_letter"
                            name="cover_letter"
                            rows="12"
                            required
                            placeholder="Introduce yourself, explain your interest in this opportunity, and highlight relevant skills and experience..."
                        ><?= htmlspecialchars(
                            $coverLetter,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></textarea>


                        <p class="form-help">

                            Explain your interest in the role and highlight
                            relevant skills, qualifications, and experience.

                        </p>


                    </div>


                </section>



                <!-- =====================================
                     FORM ACTIONS
                ====================================== -->

                <div class="form-actions">


                    <a
                        href="opportunity_details.php?id=<?= (int) $opportunity['opportunity_id'] ?>"
                        class="btn btn-secondary"
                    >

                        <i class="fa-solid fa-arrow-left"></i>

                        Back to Opportunity

                    </a>


                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        Submit Application

                        <i class="fa-solid fa-paper-plane"></i>

                    </button>


                </div>


            </form>


        <?php endif; ?>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            <div class="footer-content">

                <p>

                    &copy; <?= date('Y') ?>
                    <strong>CareerBridge</strong>.
                    All rights reserved.

                </p>

                <p class="footer-text">

                    The Ultimate Career Management Platform

                </p>

            </div>

        </footer>


    </main>


</div>


</body>

</html>