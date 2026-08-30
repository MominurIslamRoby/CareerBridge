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


$totalInterviews =
    (int) ($stats['total_interviews'] ?? 0);

$scheduledCount =
    (int) ($stats['scheduled_count'] ?? 0);

$rescheduledCount =
    (int) ($stats['rescheduled_count'] ?? 0);

$completedCount =
    (int) ($stats['completed_count'] ?? 0);

$cancelledCount =
    (int) ($stats['cancelled_count'] ?? 0);


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
            return '📅';

        case 'rescheduled':
            return '🔄';

        case 'completed':
            return '✓';

        case 'cancelled':
            return '✕';

        default:
            return '📋';
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
            return '💻 Online';

        case 'offline':
            return '📍 Offline';

        default:
            return ucfirst($mode);
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


            <a href="opportunities.php">

                <span>💼</span>

                Opportunities

            </a>


            <a href="applications.php">

                <span>▤</span>

                My Applications

            </a>


            <a
                href="interviews.php"
                class="active"
            >

                <span>🎯</span>

                My Interviews

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
             INTERVIEW STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL -->

            <div class="stat-card">


                <div class="stat-icon">

                    🎯

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

                    📅

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

                    🔄

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

                    ✓

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



            <!-- CANCELLED -->

            <div class="stat-card">


                <div class="stat-icon">

                    ✕

                </div>


                <div>

                    <p>
                        Cancelled
                    </p>


                    <h2>

                        <?= $cancelledCount ?>

                    </h2>

                </div>


            </div>


        </section>



        <!-- =====================================
             INTERVIEW STATUS OVERVIEW
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

                        Track the status of your recruitment interviews.

                    </p>


                </div>


                <div class="section-icon">

                    📊

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

                    🎯

                </div>


            </div>



            <!-- =====================================
                 EMPTY STATE
            ====================================== -->

            <?php if (!$interviews): ?>


                <div class="empty-state">


                    <div class="empty-icon">

                        🎯

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

                        View My Applications →

                    </a>


                </div>



            <!-- =====================================
                 INTERVIEW LIST
            ====================================== -->

            <?php else: ?>


                <div class="applications-list">


                    <?php foreach ($interviews as $interview): ?>


                        <?php

                        $isUpcoming =
                            isUpcomingInterview(
                                $interview['interview_date'],
                                $interview['interview_status']
                            );

                        ?>


                        <article class="application-item">


                            <!-- INTERVIEW ICON -->

                            <div class="application-avatar">

                                🎯

                            </div>



                            <!-- INTERVIEW CONTENT -->

                            <div class="application-content">


                                <!-- TOP -->

                                <div class="application-top">


                                    <div>


                                        <h3>

                                            <?= htmlspecialchars(
                                                $interview['opportunity_title'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </h3>


                                        <p class="application-opportunity">

                                            <?= htmlspecialchars(
                                                $interview['company_name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                            <?php if (
                                                !empty(
                                                    $interview['industry']
                                                )
                                            ): ?>

                                                —

                                                <?= htmlspecialchars(
                                                    $interview['industry'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            <?php endif; ?>


                                        </p>


                                    </div>



                                    <!-- STATUS -->

                                    <span
                                        class="status-badge <?= htmlspecialchars(
                                            getInterviewStatusClass(
                                                $interview['interview_status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >

                                        <?= getInterviewStatusIcon(
                                            $interview['interview_status']
                                        ) ?>

                                        <?= htmlspecialchars(
                                            formatInterviewStatus(
                                                $interview['interview_status']
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </span>


                                </div>



                                <!-- UPCOMING INDICATOR -->

                                <?php if ($isUpcoming): ?>


                                    <div class="form-help">

                                        ⏰ Upcoming Interview

                                    </div>


                                <?php endif; ?>



                                <!-- INTERVIEW META -->

                                <div class="application-meta">


                                    <!-- DATE -->

                                    <div>


                                        <span class="meta-label">

                                            DATE & TIME

                                        </span>


                                        <strong>

                                            <?= htmlspecialchars(
                                                formatInterviewDate(
                                                    $interview['interview_date']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                    </div>



                                    <!-- MODE -->

                                    <div>


                                        <span class="meta-label">

                                            MODE

                                        </span>


                                        <strong>

                                            <?= htmlspecialchars(
                                                formatInterviewMode(
                                                    $interview['interview_mode']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                    </div>



                                    <!-- OPPORTUNITY TYPE -->

                                    <div>


                                        <span class="meta-label">

                                            OPPORTUNITY

                                        </span>


                                        <strong>

                                            <?= htmlspecialchars(
                                                ucfirst(
                                                    $interview['opportunity_type']
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                    </div>



                                    <!-- APPLICATION STATUS -->

                                    <div>


                                        <span class="meta-label">

                                            APPLICATION

                                        </span>


                                        <strong>

                                            <?= htmlspecialchars(
                                                ucwords(
                                                    str_replace(
                                                        '_',
                                                        ' ',
                                                        $interview['application_status']
                                                    )
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </strong>


                                    </div>


                                </div>



                                <!-- =====================================
                                     ONLINE INTERVIEW
                                ====================================== -->

                                <?php if (
                                    $interview['interview_mode'] === 'online'
                                    && !empty(
                                        $interview['meeting_link']
                                    )
                                ): ?>


                                    <div class="application-meta">


                                        <div>

                                            <span class="meta-label">

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
                                                href="<?= htmlspecialchars(
                                                    $interview['meeting_link'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-primary"
                                            >

                                                Join Interview →

                                            </a>


                                        </div>


                                    <?php endif; ?>


                                <?php endif; ?>



                                <!-- =====================================
                                     OFFLINE INTERVIEW
                                ====================================== -->

                                <?php if (
                                    $interview['interview_mode'] === 'offline'
                                    && !empty(
                                        $interview['interview_location']
                                    )
                                ): ?>


                                    <div class="application-meta">


                                        <div>

                                            <span class="meta-label">

                                                INTERVIEW LOCATION

                                            </span>


                                            <strong>

                                                <?= htmlspecialchars(
                                                    $interview['interview_location'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>

                                            </strong>


                                        </div>


                                    </div>


                                <?php endif; ?>



                                <!-- =====================================
                                     ADDITIONAL NOTES
                                ====================================== -->

                                <?php if (
                                    !empty(
                                        $interview['notes']
                                    )
                                ): ?>


                                    <div class="form-group form-full">


                                        <span class="meta-label">

                                            ADDITIONAL INSTRUCTIONS

                                        </span>


                                        <p>

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $interview['notes'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                            ) ?>

                                        </p>


                                    </div>


                                <?php endif; ?>



                                <!-- =====================================
                                     INTERVIEW OUTCOME
                                ====================================== -->

                                <?php if (
                                    !empty(
                                        $interview['outcome']
                                    )
                                ): ?>


                                    <div class="form-group form-full">


                                        <span class="meta-label">

                                            INTERVIEW OUTCOME

                                        </span>


                                        <p>

                                            <?= nl2br(
                                                htmlspecialchars(
                                                    $interview['outcome'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
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

            © <?= date('Y') ?>
            CareerBridge — University Career Management Platform

        </footer>


    </main>


</div>


</body>

</html>