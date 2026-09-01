<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/* =========================================
   AUTHORIZATION
========================================= */

requireRole('student');

$user = currentUser();

$userId = (int) $user['id'];


/* =========================================
   HELPER FUNCTION
========================================= */

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================
   USER DISPLAY DATA
========================================= */

$userName = $user['full_name'] ?? 'Student';

$userInitial = strtoupper(
    substr(
        $userName,
        0,
        1
    )
);


/* =========================================
   GET STUDENT INFORMATION
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

$studentStmt->execute([$userId]);

$student = $studentStmt->fetch();

if (!$student) {

    http_response_code(404);

    exit('Student profile not found.');
}

$studentId = (int) $student['student_id'];


/* =========================================
   GET INTERVIEW STATISTICS
========================================= */

$statsStmt = $pdo->prepare(
    '
    SELECT

        COUNT(*) AS total_interviews,

        SUM(
            CASE
                WHEN i.status = "scheduled"
                THEN 1
                ELSE 0
            END
        ) AS scheduled_count,

        SUM(
            CASE
                WHEN i.status = "rescheduled"
                THEN 1
                ELSE 0
            END
        ) AS rescheduled_count,

        SUM(
            CASE
                WHEN i.status = "completed"
                THEN 1
                ELSE 0
            END
        ) AS completed_count,

        SUM(
            CASE
                WHEN i.status = "cancelled"
                THEN 1
                ELSE 0
            END
        ) AS cancelled_count

    FROM interviews i

    INNER JOIN applications a
        ON a.application_id = i.application_id

    WHERE a.student_id = ?
    '
);

$statsStmt->execute([$studentId]);

$stats = $statsStmt->fetch();


$totalInterviews = (int) (
    $stats['total_interviews'] ?? 0
);

$scheduledCount = (int) (
    $stats['scheduled_count'] ?? 0
);

$rescheduledCount = (int) (
    $stats['rescheduled_count'] ?? 0
);

$completedCount = (int) (
    $stats['completed_count'] ?? 0
);

$cancelledCount = (int) (
    $stats['cancelled_count'] ?? 0
);


/* =========================================
   GET STUDENT INTERVIEWS
========================================= */

$interviewsStmt = $pdo->prepare(
    '
    SELECT

        i.interview_id,
        i.interview_date,
        i.interview_mode,
        i.interview_location,
        i.meeting_link,
        i.notes,
        i.status AS interview_status,
        i.outcome,
        i.created_at,

        a.application_id,
        a.status AS application_status,

        o.opportunity_id,
        o.title AS opportunity_title,
        o.opportunity_type,
        o.location AS opportunity_location,

        e.company_name,
        e.industry,
        e.website

    FROM interviews i

    INNER JOIN applications a
        ON a.application_id = i.application_id

    INNER JOIN opportunities o
        ON o.opportunity_id = a.opportunity_id

    INNER JOIN employers e
        ON e.employer_id = o.employer_id

    WHERE a.student_id = ?

    ORDER BY
        CASE
            WHEN i.status IN (
                "scheduled",
                "rescheduled"
            )
            THEN 0
            ELSE 1
        END,

        i.interview_date ASC
    '
);

$interviewsStmt->execute([$studentId]);

$interviews = $interviewsStmt->fetchAll();


/* =========================================
   INTERVIEW STATUS CLASS HELPER
========================================= */

function getInterviewStatusClass(
    string $status
): string {

    switch ($status) {

        case 'scheduled':
            return 'status-interview';

        case 'rescheduled':
            return 'status-shortlisted';

        case 'completed':
            return 'status-selected';

        case 'cancelled':
            return 'status-rejected';

        default:
            return 'status-submitted';
    }
}


/* =========================================
   INTERVIEW STATUS ICON HELPER
========================================= */

function getInterviewStatusIcon(
    string $status
): string {

    switch ($status) {

        case 'scheduled':
            return 'fa-solid fa-calendar-check';

        case 'rescheduled':
            return 'fa-solid fa-rotate';

        case 'completed':
            return 'fa-solid fa-circle-check';

        case 'cancelled':
            return 'fa-solid fa-circle-xmark';

        default:
            return 'fa-solid fa-calendar';
    }
}


/* =========================================
   FORMAT INTERVIEW STATUS
========================================= */

function formatInterviewStatus(
    string $status
): string {

    return ucwords(
        str_replace(
            '_',
            ' ',
            $status
        )
    );
}


/* =========================================
   FORMAT INTERVIEW DATE
========================================= */

function formatInterviewDate(
    ?string $date
): string {

    if (empty($date)) {
        return 'Not scheduled';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Not scheduled';
    }

    return date(
        'd M Y, h:i A',
        $timestamp
    );
}


/* =========================================
   FORMAT INTERVIEW MODE
========================================= */

function formatInterviewMode(
    string $mode
): string {

    switch ($mode) {

        case 'online':
            return 'Online';

        case 'offline':
            return 'Offline';

        default:
            return ucfirst($mode);
    }
}


/* =========================================
   INTERVIEW MODE ICON
========================================= */

function getInterviewModeIcon(
    string $mode
): string {

    switch ($mode) {

        case 'online':
            return 'fa-solid fa-video';

        case 'offline':
            return 'fa-solid fa-location-dot';

        default:
            return 'fa-solid fa-briefcase';
    }
}


/* =========================================
   CHECK IF UPCOMING
========================================= */

function isUpcomingInterview(
    ?string $date,
    string $status
): bool {

    if (
        empty($date)
        || !in_array(
            $status,
            [
                'scheduled',
                'rescheduled'
            ],
            true
        )
    ) {
        return false;
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return false;
    }

    return $timestamp > time();
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
        My Interviews | CareerBridge
    </title>


    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <!-- MAIN STYLESHEET -->

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


        <!-- BRAND -->

        <div class="sidebar-brand">


            <div class="brand-logo">

                <img
                    src="../assets/images/CB Logo Transparent.png"
                    alt="CareerBridge Logo"
                >

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


            <a href="opportunities.php">

                <span>
                    <i class="fa-solid fa-briefcase"></i>
                </span>

                Opportunities

            </a>


            <a href="applications.php">

                <span>
                    <i class="fa-solid fa-clipboard-list"></i>
                </span>

                My Applications

            </a>


            <a
                href="interviews.php"
                class="active"
            >

                <span>
                    <i class="fa-solid fa-calendar-check"></i>
                </span>

                My Interviews

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
                    STUDENT PORTAL / MY INTERVIEWS
                </p>


                <h1>
                    My Interviews
                </h1>


                <p class="page-subtitle">
                    View and manage your scheduled interview opportunities.
                </p>

            </div>


            <!-- USER CARD -->

            <div class="user-card">


                <div class="user-avatar">

                    <?= e($userInitial) ?>

                </div>


                <div>

                    <strong>
                        <?= e($userName) ?>
                    </strong>


                    <span>
                        Student
                    </span>

                </div>


            </div>


        </div>



        <!-- =====================================
             INTERVIEW STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>


                <div>

                    <p>
                        Total Interviews
                    </p>

                    <h2>
                        <?= $totalInterviews ?>
                    </h2>

                </div>

            </div>



            <!-- SCHEDULED -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-calendar-check"></i>

                </div>


                <div>

                    <p>
                        Scheduled
                    </p>

                    <h2>
                        <?= $scheduledCount ?>
                    </h2>

                </div>

            </div>



            <!-- RESCHEDULED -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-rotate"></i>

                </div>


                <div>

                    <p>
                        Rescheduled
                    </p>

                    <h2>
                        <?= $rescheduledCount ?>
                    </h2>

                </div>

            </div>



            <!-- COMPLETED -->

            <div class="stat-card">

                <div class="stat-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div>

                    <p>
                        Completed
                    </p>

                    <h2>
                        <?= $completedCount ?>
                    </h2>

                </div>

            </div>


        </section>



        <!-- =====================================
             INTERVIEW OVERVIEW
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>

                    <p class="section-label">
                        INTERVIEW OVERVIEW
                    </p>


                    <h2>
                        Interview Progress
                    </h2>


                    <p>
                        Track the current status of your recruitment interviews.
                    </p>

                </div>


                <div class="section-icon">

                    <i class="fa-solid fa-chart-column"></i>

                </div>


            </div>


            <div class="application-meta">


                <div>

                    <span class="meta-label">
                        SCHEDULED
                    </span>

                    <strong>
                        <?= $scheduledCount ?>
                    </strong>

                </div>


                <div>

                    <span class="meta-label">
                        RESCHEDULED
                    </span>

                    <strong>
                        <?= $rescheduledCount ?>
                    </strong>

                </div>


                <div>

                    <span class="meta-label">
                        COMPLETED
                    </span>

                    <strong>
                        <?= $completedCount ?>
                    </strong>

                </div>


                <div>

                    <span class="meta-label">
                        CANCELLED
                    </span>

                    <strong>
                        <?= $cancelledCount ?>
                    </strong>

                </div>


            </div>


        </section>



        <!-- =====================================
             INTERVIEWS LIST
        ====================================== -->

        <section class="content-card">


            <div class="section-heading">


                <div>

                    <p class="section-label">
                        INTERVIEW SCHEDULE
                    </p>


                    <h2>
                        Your Interviews
                    </h2>


                    <p>
                        Review interview dates, meeting details, and instructions.
                    </p>

                </div>


                <div class="section-icon">

                    <i class="fa-solid fa-calendar-days"></i>

                </div>


            </div>



            <!-- EMPTY STATE -->

            <?php if (!$interviews): ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        <i class="fa-solid fa-calendar-xmark"></i>

                    </div>


                    <h3>
                        No Interviews Scheduled
                    </h3>


                    <p>
                        When an employer selects you for an interview,
                        the interview details will appear here.
                    </p>


                    <a
                        href="applications.php"
                        class="btn btn-primary"
                    >

                        <i class="fa-solid fa-clipboard-list"></i>

                        View My Applications

                    </a>


                </div>



            <!-- INTERVIEW LIST -->

            <?php else: ?>


                <div class="applications-list">


                    <?php foreach ($interviews as $interview): ?>


                        <?php

                        $isUpcoming = isUpcomingInterview(
                            $interview['interview_date'],
                            $interview['interview_status']
                        );

                        $statusClass = getInterviewStatusClass(
                            $interview['interview_status']
                        );

                        $statusIcon = getInterviewStatusIcon(
                            $interview['interview_status']
                        );

                        $modeIcon = getInterviewModeIcon(
                            $interview['interview_mode']
                        );

                        ?>


                        <article class="application-item">


                            <!-- INTERVIEW ICON -->

                            <div class="application-avatar">

                                <i class="fa-solid fa-calendar-check"></i>

                            </div>



                            <!-- INTERVIEW CONTENT -->

                            <div class="application-content">


                                <!-- TOP SECTION -->

                                <div class="application-top">


                                    <div>

                                        <h3>

                                            <?= e(
                                                $interview['opportunity_title']
                                            ) ?>

                                        </h3>


                                        <p class="application-opportunity">

                                            <i class="fa-solid fa-building"></i>

                                            <?= e(
                                                $interview['company_name']
                                            ) ?>


                                            <?php if (
                                                !empty(
                                                    $interview['industry']
                                                )
                                            ): ?>

                                                <span>
                                                    ·
                                                </span>

                                                <?= e(
                                                    $interview['industry']
                                                ) ?>

                                            <?php endif; ?>


                                        </p>


                                    </div>



                                    <!-- STATUS -->

                                    <span
                                        class="status-badge <?= e(
                                            $statusClass
                                        ) ?>"
                                    >

                                        <i class="<?= e(
                                            $statusIcon
                                        ) ?>"></i>

                                        <?= e(
                                            formatInterviewStatus(
                                                $interview['interview_status']
                                            )
                                        ) ?>

                                    </span>


                                </div>



                                <!-- UPCOMING INDICATOR -->

                                <?php if ($isUpcoming): ?>


                                    <div class="form-help">

                                        <i class="fa-solid fa-clock"></i>

                                        Upcoming Interview

                                    </div>


                                <?php endif; ?>



                                <!-- INTERVIEW META -->

                                <div class="application-meta">


                                    <!-- DATE -->

                                    <div>

                                        <span class="meta-label">

                                            <i class="fa-solid fa-calendar-days"></i>

                                            DATE & TIME

                                        </span>


                                        <strong>

                                            <?= e(
                                                formatInterviewDate(
                                                    $interview['interview_date']
                                                )
                                            ) ?>

                                        </strong>


                                    </div>



                                    <!-- MODE -->

                                    <div>

                                        <span class="meta-label">

                                            <i class="<?= e(
                                                $modeIcon
                                            ) ?>"></i>

                                            MODE

                                        </span>


                                        <strong>

                                            <?= e(
                                                formatInterviewMode(
                                                    $interview['interview_mode']
                                                )
                                            ) ?>

                                        </strong>


                                    </div>



                                    <!-- OPPORTUNITY TYPE -->

                                    <div>

                                        <span class="meta-label">

                                            <i class="fa-solid fa-briefcase"></i>

                                            OPPORTUNITY

                                        </span>


                                        <strong>

                                            <?= e(
                                                ucfirst(
                                                    $interview['opportunity_type']
                                                )
                                            ) ?>

                                        </strong>


                                    </div>



                                    <!-- APPLICATION STATUS -->

                                    <div>

                                        <span class="meta-label">

                                            <i class="fa-solid fa-file-circle-check"></i>

                                            APPLICATION

                                        </span>


                                        <strong>

                                            <?= e(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $interview['application_status']
                                                    )
                                                )
                                            ) ?>

                                        </strong>


                                    </div>


                                </div>



                                <!-- ONLINE INTERVIEW -->

                                <?php if (
                                    $interview['interview_mode'] === 'online'
                                    && !empty(
                                        $interview['meeting_link']
                                    )
                                ): ?>


                                    <div class="application-meta">


                                        <div>

                                            <span class="meta-label">

                                                <i class="fa-solid fa-video"></i>

                                                ONLINE MEETING

                                            </span>


                                            <strong>
                                                Meeting link provided
                                            </strong>


                                        </div>


                                    </div>


                                    <?php if (
                                        $interview['interview_status'] === 'scheduled'
                                        || $interview['interview_status'] === 'rescheduled'
                                    ): ?>


                                        <div class="application-actions">


                                            <a
                                                href="<?= e(
                                                    $interview['meeting_link']
                                                ) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-primary"
                                            >

                                                <i class="fa-solid fa-video"></i>

                                                Join Interview

                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>

                                            </a>


                                        </div>


                                    <?php endif; ?>


                                <?php endif; ?>



                                <!-- OFFLINE INTERVIEW -->

                                <?php if (
                                    $interview['interview_mode'] === 'offline'
                                    && !empty(
                                        $interview['interview_location']
                                    )
                                ): ?>


                                    <div class="application-meta">


                                        <div>

                                            <span class="meta-label">

                                                <i class="fa-solid fa-location-dot"></i>

                                                INTERVIEW LOCATION

                                            </span>


                                            <strong>

                                                <?= e(
                                                    $interview['interview_location']
                                                ) ?>

                                            </strong>


                                        </div>


                                    </div>


                                <?php endif; ?>



                                <!-- ADDITIONAL NOTES -->

                                <?php if (
                                    !empty(
                                        $interview['notes']
                                    )
                                ): ?>


                                    <div class="form-group form-full">


                                        <span class="meta-label">

                                            <i class="fa-solid fa-note-sticky"></i>

                                            ADDITIONAL INSTRUCTIONS

                                        </span>


                                        <p>

                                            <?= nl2br(
                                                e(
                                                    $interview['notes']
                                                )
                                            ) ?>

                                        </p>


                                    </div>


                                <?php endif; ?>



                                <!-- INTERVIEW OUTCOME -->

                                <?php if (
                                    !empty(
                                        $interview['outcome']
                                    )
                                ): ?>


                                    <div class="form-group form-full">


                                        <span class="meta-label">

                                            <i class="fa-solid fa-flag-checkered"></i>

                                            INTERVIEW OUTCOME

                                        </span>


                                        <p>

                                            <?= nl2br(
                                                e(
                                                    $interview['outcome']
                                                )
                                            ) ?>

                                        </p>


                                    </div>


                                <?php endif; ?>


                            </div>


                        </article>


                    <?php endforeach; ?>


                </div>


            <?php endif; ?>


        </section>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            &copy; <?= date('Y') ?>

            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>