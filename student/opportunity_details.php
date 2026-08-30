<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('student');

$user = currentUser();


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

$studentStmt->execute([$user['id']]);

$student = $studentStmt->fetch();

if (!$student) {
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
   GET OPPORTUNITY DETAILS
========================================= */

$stmt = $pdo->prepare(
    '
    SELECT
        o.opportunity_id,
        o.title,
        o.opportunity_type,
        o.description,
        o.responsibilities,
        o.qualifications,
        o.location,
        o.duration,
        o.deadline,
        o.status,

        e.company_name,
        e.company_description,
        e.industry,
        e.website,
        e.company_email,
        e.phone,
        e.address

    FROM opportunities o

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    WHERE o.opportunity_id = ?

    LIMIT 1
    '
);

$stmt->execute([$opportunityId]);

$opportunity = $stmt->fetch();

if (!$opportunity) {
    http_response_code(404);
    exit('Opportunity not found.');
}


/* =========================================
   CHECK APPLICATION STATUS
========================================= */

$applicationStmt = $pdo->prepare(
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

$applicationStmt->execute([
    $opportunityId,
    $studentId
]);

$existingApplication = $applicationStmt->fetch();


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

    $deadlineDate = strtotime(
        $opportunity['deadline']
    );

    if (
        $deadlineDate !== false
        && $deadlineDate < strtotime('today')
    ) {
        $isDeadlinePassed = true;
    }
}


/* =========================================
   APPLICATION AVAILABILITY
========================================= */

$canApply =
    $opportunity['status'] === 'open'
    && !$isDeadlinePassed
    && !$existingApplication;

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
        <?= htmlspecialchars(
            $opportunity['title'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        | CareerBridge
    </title>

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>


<body>


<div class="app-layout">


    <!-- =====================================
         SIDEBAR
    ====================================== -->

    <aside class="sidebar">

        <div class="sidebar-brand">

            <div class="brand-logo">
                CB
            </div>

            <div>

                <h2>CareerBridge</h2>

                <span>Student Portal</span>

            </div>

        </div>


        <div class="sidebar-divider"></div>


        <p class="menu-label">
            MAIN MENU
        </p>


        <nav class="sidebar-nav">

            <a href="dashboard.php">

                <span>⌂</span>
                Dashboard

            </a>


            <a href="profile.php">

                <span>♟</span>
                Career Profile

            </a>


            <a href="skills.php">

                <span>⚡</span>
                My Skills

            </a>


            <a href="resume.php">

                <span>▣</span>
                Resume / CV

            </a>


            <a
                href="opportunities.php"
                class="active"
            >

                <span>💼</span>
                Opportunities

            </a>


            <a href="applications.php">

                <span>▤</span>
                My Applications

            </a>


            <a href="notifications.php">

                <span>🔔</span>
                Notifications

            </a>

        </nav>


        <div class="sidebar-bottom">

            <a
                href="../auth/logout.php"
                class="logout-link"
            >

                ↪ Logout

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
                    STUDENT PORTAL / OPPORTUNITIES / DETAILS
                </p>

                <h1>
                    Opportunity Details
                </h1>

                <p class="page-subtitle">
                    Explore the position requirements and company information.
                </p>

            </div>


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
             OPPORTUNITY HERO CARD
        ====================================== -->

        <section class="content-card opportunity-detail-card">


            <div class="opportunity-detail-header">


                <div class="opportunity-detail-icon">

                    <?= $opportunity['opportunity_type'] === 'internship'
                        ? '🎓'
                        : '💼'
                    ?>

                </div>


                <div class="opportunity-title-area">


                    <div class="opportunity-badges">


                        <span class="status-badge">

                            <?= htmlspecialchars(
                                ucfirst(
                                    $opportunity['opportunity_type']
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </span>


                        <?php if (
                            $opportunity['status'] === 'open'
                            && !$isDeadlinePassed
                        ): ?>


                            <span class="open-badge">
                                ● Open
                            </span>


                        <?php else: ?>


                            <span class="closed-badge">
                                ● Closed
                            </span>


                        <?php endif; ?>


                    </div>


                    <h2>

                        <?= htmlspecialchars(
                            $opportunity['title'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h2>


                    <p class="opportunity-company">

                        🏢

                        <?= htmlspecialchars(
                            $opportunity['company_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>


                </div>

            </div>



            <!-- QUICK DETAILS -->

            <div class="opportunity-details-grid">


                <!-- LOCATION -->

                <div class="detail-item">

                    <span class="detail-icon">
                        📍
                    </span>

                    <div>

                        <small>
                            Location
                        </small>

                        <strong>

                            <?= htmlspecialchars(
                                $opportunity['location']
                                    ?? 'Not specified',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </div>

                </div>



                <!-- DURATION -->

                <div class="detail-item">

                    <span class="detail-icon">
                        ⏳
                    </span>

                    <div>

                        <small>
                            Duration
                        </small>

                        <strong>

                            <?= htmlspecialchars(
                                $opportunity['duration']
                                    ?? 'Not specified',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </div>

                </div>



                <!-- DEADLINE -->

                <div class="detail-item">

                    <span class="detail-icon">
                        📅
                    </span>

                    <div>

                        <small>
                            Application Deadline
                        </small>

                        <strong>

                            <?= htmlspecialchars(
                                $formattedDeadline,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </div>

                </div>


            </div>


        </section>



        <!-- =====================================
             DESCRIPTION
        ====================================== -->

        <section class="content-card detail-content-card">


            <div class="section-heading">

                <div>

                    <p class="section-label">
                        POSITION OVERVIEW
                    </p>

                    <h2>
                        Description
                    </h2>

                    <p>
                        Learn more about this opportunity.
                    </p>

                </div>


                <div class="section-icon">
                    📋
                </div>

            </div>


            <div class="content-text">

                <?= nl2br(
                    htmlspecialchars(
                        $opportunity['description']
                            ?? 'No description provided.',
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) ?>

            </div>


        </section>



        <!-- =====================================
             RESPONSIBILITIES
        ====================================== -->

        <?php if (!empty($opportunity['responsibilities'])): ?>


            <section class="content-card detail-content-card">


                <div class="section-heading">

                    <div>

                        <p class="section-label">
                            ROLE
                        </p>

                        <h2>
                            Responsibilities
                        </h2>

                        <p>
                            What you will be expected to do.
                        </p>

                    </div>


                    <div class="section-icon">
                        ✓
                    </div>

                </div>


                <div class="content-text">

                    <?= nl2br(
                        htmlspecialchars(
                            $opportunity['responsibilities'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>

                </div>


            </section>


        <?php endif; ?>



        <!-- =====================================
             QUALIFICATIONS
        ====================================== -->

        <?php if (!empty($opportunity['qualifications'])): ?>


            <section class="content-card detail-content-card">


                <div class="section-heading">

                    <div>

                        <p class="section-label">
                            REQUIREMENTS
                        </p>

                        <h2>
                            Qualifications
                        </h2>

                        <p>
                            Skills and qualifications required for this role.
                        </p>

                    </div>


                    <div class="section-icon">
                        🎯
                    </div>

                </div>


                <div class="content-text">

                    <?= nl2br(
                        htmlspecialchars(
                            $opportunity['qualifications'],
                            ENT_QUOTES,
                            'UTF-8'
                        )
                    ) ?>

                </div>


            </section>


        <?php endif; ?>



        <!-- =====================================
             COMPANY INFORMATION
        ====================================== -->

        <section class="content-card company-card">


            <div class="section-heading">

                <div>

                    <p class="section-label">
                        EMPLOYER
                    </p>

                    <h2>
                        About the Company
                    </h2>

                    <p>
                        Information about the organization offering this opportunity.
                    </p>

                </div>


                <div class="section-icon">
                    🏢
                </div>

            </div>



            <div class="company-info-grid">


                <!-- COMPANY -->

                <div class="company-info-item">

                    <span>
                        Company
                    </span>

                    <strong>

                        <?= htmlspecialchars(
                            $opportunity['company_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </strong>

                </div>



                <!-- INDUSTRY -->

                <?php if (!empty($opportunity['industry'])): ?>


                    <div class="company-info-item">

                        <span>
                            Industry
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $opportunity['industry'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </div>


                <?php endif; ?>



                <!-- WEBSITE -->

                <?php if (!empty($opportunity['website'])): ?>


                    <div class="company-info-item">

                        <span>
                            Website
                        </span>

                        <strong>

                            <a
                                href="<?= htmlspecialchars(
                                    $opportunity['website'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                            >

                                Visit Website

                            </a>

                        </strong>

                    </div>


                <?php endif; ?>



                <!-- EMAIL -->

                <?php if (!empty($opportunity['company_email'])): ?>


                    <div class="company-info-item">

                        <span>
                            Email
                        </span>

                        <strong>

                            <a
                                href="mailto:<?= htmlspecialchars(
                                    $opportunity['company_email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    $opportunity['company_email'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </a>

                        </strong>

                    </div>


                <?php endif; ?>



                <!-- PHONE -->

                <?php if (!empty($opportunity['phone'])): ?>


                    <div class="company-info-item">

                        <span>
                            Phone
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $opportunity['phone'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </div>


                <?php endif; ?>



                <!-- ADDRESS -->

                <?php if (!empty($opportunity['address'])): ?>


                    <div class="company-info-item">

                        <span>
                            Address
                        </span>

                        <strong>

                            <?= htmlspecialchars(
                                $opportunity['address'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </strong>

                    </div>


                <?php endif; ?>


            </div>



            <!-- COMPANY DESCRIPTION -->

            <?php if (!empty($opportunity['company_description'])): ?>


                <div class="company-description">

                    <h3>

                        About <?= htmlspecialchars(
                            $opportunity['company_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h3>


                    <div class="content-text">

                        <?= nl2br(
                            htmlspecialchars(
                                $opportunity['company_description'],
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        ) ?>

                    </div>

                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             APPLICATION ACTION
        ====================================== -->

        <section class="content-card apply-card">


            <?php if ($existingApplication): ?>


                <!-- ALREADY APPLIED -->

                <div class="apply-content">


                    <div>

                        <p class="section-label">
                            APPLICATION SUBMITTED
                        </p>

                        <h2>
                            You have already applied
                        </h2>

                        <p>

                            Your application status is:

                            <strong>

                                <?= htmlspecialchars(
                                    ucfirst(
                                        $existingApplication['status']
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>

                        </p>

                    </div>


                    <a
                        href="applications.php"
                        class="btn btn-primary"
                    >
                        View My Applications →
                    </a>


                </div>


            <?php elseif ($canApply): ?>


                <!-- CAN APPLY -->

                <div class="apply-content">


                    <div>

                        <p class="section-label">
                            READY TO APPLY?
                        </p>

                        <h2>
                            Interested in this opportunity?
                        </h2>

                        <p>
                            Submit your application and take the next step in your career.
                        </p>

                    </div>


                    <a
                        href="apply.php?id=<?= (int) $opportunity['opportunity_id'] ?>"
                        class="btn btn-primary"
                    >
                        Apply Now →
                    </a>


                </div>


            <?php elseif ($isDeadlinePassed): ?>


                <!-- DEADLINE PASSED -->

                <div class="empty-state">

                    <div class="empty-icon">
                        ⏰
                    </div>

                    <h2>
                        Application Deadline Passed
                    </h2>

                    <p>
                        The application deadline for this opportunity has already passed.
                    </p>

                </div>


            <?php else: ?>


                <!-- CLOSED -->

                <div class="empty-state">

                    <div class="empty-icon">
                        🔒
                    </div>

                    <h2>
                        Applications Closed
                    </h2>

                    <p>
                        Applications are currently closed for this opportunity.
                    </p>

                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             BACK BUTTON
        ====================================== -->

        <div class="form-actions">

            <a
                href="opportunities.php"
                class="btn btn-secondary"
            >

                ← Back to Opportunities

            </a>

        </div>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            © 2026 CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>