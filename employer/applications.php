<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

requireRole('employer');

$user = currentUser();
$userId = (int) $user['id'];


/*
|--------------------------------------------------------------------------
| Get Employer Information
|--------------------------------------------------------------------------
*/

$employerStmt = $pdo->prepare(
    '
    SELECT
        employer_id,
        company_name
    FROM employers
    WHERE user_id = ?
    LIMIT 1
    '
);

$employerStmt->execute([$userId]);

$employer = $employerStmt->fetch();

if (!$employer) {
    http_response_code(404);
    exit('Employer profile not found.');
}

$employerId = (int) $employer['employer_id'];


/*
|--------------------------------------------------------------------------
| Application Statistics
|--------------------------------------------------------------------------
*/

$statsStmt = $pdo->prepare(
    "
    SELECT
        COUNT(*) AS total_applications,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'submitted' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS submitted_count,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'under_review' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS under_review_count,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'shortlisted' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS shortlisted_count,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'interview' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS interview_count,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'selected' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS selected_count,

        COALESCE(
            SUM(
                CASE
                    WHEN a.status = 'rejected' THEN 1
                    ELSE 0
                END
            ),
            0
        ) AS rejected_count

    FROM applications a

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    WHERE o.employer_id = ?
    "
);

$statsStmt->execute([$employerId]);

$stats = $statsStmt->fetch() ?: [];


$totalApplications = (int) ($stats['total_applications'] ?? 0);
$submittedCount = (int) ($stats['submitted_count'] ?? 0);
$underReviewCount = (int) ($stats['under_review_count'] ?? 0);
$shortlistedCount = (int) ($stats['shortlisted_count'] ?? 0);
$interviewCount = (int) ($stats['interview_count'] ?? 0);
$selectedCount = (int) ($stats['selected_count'] ?? 0);
$rejectedCount = (int) ($stats['rejected_count'] ?? 0);


/*
|--------------------------------------------------------------------------
| Get Applications
|--------------------------------------------------------------------------
|
| The interview subquery ensures that only one active interview
| is attached to each application.
|
*/

$applicationsStmt = $pdo->prepare(
    '
    SELECT
        a.application_id,
        a.status,
        a.applied_at,
        a.cover_letter,

        s.student_id,
        s.student_id_number,
        s.university_name,
        s.department,
        s.academic_level,

        u.full_name,
        u.email,

        o.opportunity_id,
        o.title AS opportunity_title,
        o.opportunity_type,

        r.file_name AS resume_file_name,
        r.file_path AS resume_file_path,

        i.interview_id,
        i.interview_date,
        i.interview_mode,
        i.status AS interview_status

    FROM applications a

    INNER JOIN students s
        ON s.student_id = a.student_id

    INNER JOIN users u
        ON u.user_id = s.user_id

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    LEFT JOIN resumes r
        ON r.resume_id = a.resume_id

    LEFT JOIN interviews i
        ON i.interview_id = (
            SELECT i2.interview_id
            FROM interviews i2
            WHERE i2.application_id = a.application_id
              AND i2.status IN (
                  "scheduled",
                  "rescheduled"
              )
            ORDER BY i2.interview_date DESC
            LIMIT 1
        )

    WHERE o.employer_id = ?

    ORDER BY a.applied_at DESC
    '
);

$applicationsStmt->execute([$employerId]);

$applications = $applicationsStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function getStatusClass(string $status): string
{
    switch ($status) {
        case 'under_review':
            return 'status-under-review';

        case 'shortlisted':
            return 'status-shortlisted';

        case 'interview':
            return 'status-interview';

        case 'selected':
            return 'status-selected';

        case 'rejected':
            return 'status-rejected';

        case 'submitted':
        default:
            return 'status-submitted';
    }
}


function getStatusIcon(string $status): string
{
    switch ($status) {
        case 'under_review':
            return 'fa-solid fa-magnifying-glass';

        case 'shortlisted':
            return 'fa-solid fa-star';

        case 'interview':
            return 'fa-solid fa-calendar-check';

        case 'selected':
            return 'fa-solid fa-circle-check';

        case 'rejected':
            return 'fa-solid fa-circle-xmark';

        case 'submitted':
        default:
            return 'fa-solid fa-paper-plane';
    }
}


function formatApplicationStatus(string $status): string
{
    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}


function formatApplicationDate(?string $date): string
{
    if (empty($date)) {
        return 'Not specified';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Not specified';
    }

    return date('d M Y', $timestamp);
}


function formatInterviewDate(?string $date): string
{
    if (empty($date)) {
        return 'Not scheduled';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Not scheduled';
    }

    return date('d M Y, h:i A', $timestamp);
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

    <title>Applications | CareerBridge</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="../assets/css/style.css"
    >

</head>

<body>

<div class="app-layout">


    <!-- Sidebar -->

    <aside class="sidebar">

    <div class="sidebar-brand">

        <div class="brand-logo">

            <img
                src="../assets/images/CB Logo Transparent.png"
                alt="CareerBridge Logo"
            >

        </div>


        <div class="brand-content">

            <h2>
                CareerBridge
            </h2>

            <span>
                Employer Portal
            </span>

        </div>

    </div>

        <div class="sidebar-divider"></div>


        <p class="menu-label">
            MAIN MENU
        </p>


        <nav class="sidebar-nav">

            <a href="dashboard.php">

                <span>
                    <i class="fa-solid fa-house"></i>
                </span>

                Dashboard

            </a>


            <a href="opportunities.php">

                <span>
                    <i class="fa-solid fa-briefcase"></i>
                </span>

                Opportunities

            </a>


            <a
                href="applications.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-file-lines"></i>
                </span>

                Applications

            </a>

        </nav>


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



    <!-- Main Content -->

    <main class="main-content">


        <!-- Page Header -->

        <div class="page-header">

            <div>

                <p class="breadcrumb">
                    EMPLOYER PORTAL / APPLICATIONS
                </p>

                <h1>
                    Applications
                </h1>

                <p class="page-subtitle">
                    Review and manage applications submitted to your opportunities.
                </p>

            </div>


            <!-- User Card -->

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
                        Employer
                    </span>

                </div>

            </div>

        </div>



        <!-- Statistics -->

        <section class="stats-grid">


            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-file-lines"></i>
                </div>

                <div>

                    <p>Total Applications</p>

                    <h2>
                        <?= $totalApplications ?>
                    </h2>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>

                <div>

                    <p>New Applications</p>

                    <h2>
                        <?= $submittedCount ?>
                    </h2>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>

                <div>

                    <p>Under Review</p>

                    <h2>
                        <?= $underReviewCount ?>
                    </h2>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-star"></i>
                </div>

                <div>

                    <p>Shortlisted</p>

                    <h2>
                        <?= $shortlistedCount ?>
                    </h2>

                </div>

            </div>



            <div class="stat-card">

                <div class="stat-icon">
                    <i class="fa-solid fa-calendar-check"></i>
                </div>

                <div>

                    <p>Interviews</p>

                    <h2>
                        <?= $interviewCount ?>
                    </h2>

                </div>

            </div>


        </section>



        <!-- Status Overview -->

        <section class="content-card">

            <div class="section-heading">

                <div>

                    <p class="section-label">
                        STATUS OVERVIEW
                    </p>

                    <h2>
                        Application Progress
                    </h2>

                    <p>
                        Track the current status of all student applications.
                    </p>

                </div>


                <div class="section-icon">
                    <i class="fa-solid fa-chart-column"></i>
                </div>

            </div>



            <div class="application-meta">


                <div>

                    <span class="meta-label">
                        SUBMITTED
                    </span>

                    <strong>
                        <?= $submittedCount ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        UNDER REVIEW
                    </span>

                    <strong>
                        <?= $underReviewCount ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        SHORTLISTED
                    </span>

                    <strong>
                        <?= $shortlistedCount ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        INTERVIEW
                    </span>

                    <strong>
                        <?= $interviewCount ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        SELECTED
                    </span>

                    <strong>
                        <?= $selectedCount ?>
                    </strong>

                </div>



                <div>

                    <span class="meta-label">
                        REJECTED
                    </span>

                    <strong>
                        <?= $rejectedCount ?>
                    </strong>

                </div>


            </div>

        </section>



        <!-- Applications List -->

        <section class="content-card">


            <div class="section-heading">

                <div>

                    <p class="section-label">
                        APPLICANTS
                    </p>

                    <h2>
                        All Applications
                    </h2>

                    <p>
                        View applicant information and manage the recruitment process.
                    </p>

                </div>


                <div class="section-icon">
                    <i class="fa-solid fa-users"></i>
                </div>

            </div>



            <?php if (!$applications): ?>


                <!-- Empty State -->

                <div class="empty-state">

                    <div class="empty-icon">
                        <i class="fa-solid fa-inbox"></i>
                    </div>

                    <h3>
                        No Applications Yet
                    </h3>

                    <p>
                        Applications submitted by students for your
                        opportunities will appear here.
                    </p>

                    <a
                        href="opportunities.php"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-briefcase"></i>

                        View Opportunities

                    </a>

                </div>


            <?php else: ?>


                <div class="applications-list">


                    <?php foreach ($applications as $application): ?>


                        <article class="application-item">


                            <!-- Applicant Avatar -->

                            <div class="application-avatar">

                                <?= strtoupper(
                                    substr(
                                        $application['full_name'],
                                        0,
                                        1
                                    )
                                ) ?>

                            </div>



                            <!-- Application Content -->

                            <div class="application-content">


                                <!-- Application Header -->

                                <div class="application-top">


                                    <div>

                                        <h3>

                                            <?= htmlspecialchars(
                                                $application['full_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </h3>


                                        <p class="application-opportunity">

                                            Applied for:

                                            <strong>

                                                <?= htmlspecialchars(
                                                    $application['opportunity_title'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </strong>

                                        </p>

                                    </div>



                                    <!-- Status Badge -->

                                    <span
                                        class="status-badge <?= htmlspecialchars(
                                            getStatusClass(
                                                $application['status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                        <i
                                            class="<?= htmlspecialchars(
                                                getStatusIcon(
                                                    $application['status']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        ></i>

                                        <?= htmlspecialchars(
                                            formatApplicationStatus(
                                                $application['status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>


                                </div>



                                <!-- Applicant Information -->

                                <div class="application-meta">


                                    <div>

                                        <span class="meta-label">
                                            EMAIL
                                        </span>

                                        <strong>

                                            <i class="fa-solid fa-envelope"></i>

                                            <?= htmlspecialchars(
                                                $application['email'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </div>



                                    <div>

                                        <span class="meta-label">
                                            UNIVERSITY
                                        </span>

                                        <strong>

                                            <i class="fa-solid fa-building-columns"></i>

                                            <?= htmlspecialchars(
                                                $application['university_name']
                                                    ?? 'Not specified',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </div>



                                    <div>

                                        <span class="meta-label">
                                            DEPARTMENT
                                        </span>

                                        <strong>

                                            <i class="fa-solid fa-graduation-cap"></i>

                                            <?= htmlspecialchars(
                                                $application['department']
                                                    ?? 'Not specified',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </div>



                                    <div>

                                        <span class="meta-label">
                                            APPLIED
                                        </span>

                                        <strong>

                                            <i class="fa-solid fa-calendar-day"></i>

                                            <?= htmlspecialchars(
                                                formatApplicationDate(
                                                    $application['applied_at']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>

                                    </div>



                                    <?php if (!empty($application['interview_id'])): ?>


                                        <div>

                                            <span class="meta-label">
                                                INTERVIEW
                                            </span>

                                            <strong>

                                                <i class="fa-solid fa-calendar-check"></i>

                                                <?= htmlspecialchars(
                                                    formatInterviewDate(
                                                        $application['interview_date']
                                                    ),
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </strong>

                                        </div>


                                    <?php endif; ?>


                                </div>



                                <!-- Actions -->

                                <div class="application-actions">


                                    <!-- Review Application -->

                                    <a
                                        href="update_application.php?id=<?= (int) $application['application_id'] ?>"
                                        class="btn btn-primary"
                                    >

                                        <i class="fa-solid fa-file-pen"></i>

                                        Review Application

                                    </a>



                                    <!-- View Resume -->

                                    <?php if (
                                        !empty(
                                            $application['resume_file_path']
                                        )
                                    ): ?>


                                        <a
                                            href="../<?= htmlspecialchars(
                                                $application['resume_file_path'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="btn btn-secondary"
                                        >

                                            <i class="fa-solid fa-file-pdf"></i>

                                            View Resume

                                        </a>


                                    <?php endif; ?>



                                    <!-- Schedule Interview -->
                                    <!-- IMPORTANT: application_id is passed -->

                                    <?php if (
                                        $application['status'] === 'shortlisted'
                                        && empty(
                                            $application['interview_id']
                                        )
                                    ): ?>


                                        <a
                                            href="schedule_interview.php?id=<?= (int) $application['application_id'] ?>"
                                            class="btn btn-primary"
                                        >

                                            <i class="fa-solid fa-calendar-plus"></i>

                                            Schedule Interview

                                        </a>


                                    <?php endif; ?>



                                    <!-- Manage Interview -->

                                    <?php if (
                                        !empty(
                                            $application['interview_id']
                                        )
                                    ): ?>


                                        <a
                                            href="manage_interview.php?id=<?= (int) $application['interview_id'] ?>"
                                            class="btn btn-secondary"
                                        >

                                            <i class="fa-solid fa-calendar-check"></i>

                                            Manage Interview

                                        </a>


                                    <?php endif; ?>


                                </div>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>



        <!-- Footer -->

        <footer class="page-footer">

            &copy; <?= date('Y') ?>

            CareerBridge — The Ultimate Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>