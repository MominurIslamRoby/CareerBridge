<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';


/*
|--------------------------------------------------------------------------
| AUTHORIZATION
|--------------------------------------------------------------------------
*/

requireRole('student');

$user = currentUser();

$userId = (int) ($user['id'] ?? 0);


/*
|--------------------------------------------------------------------------
| HELPER FUNCTION
|--------------------------------------------------------------------------
*/

function e(?string $value): string
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| USER DISPLAY DATA
|--------------------------------------------------------------------------
*/

$userName = $user['full_name'] ?? 'Student';

$userInitial = strtoupper(
    substr(
        $userName,
        0,
        1
    )
);


/*
|--------------------------------------------------------------------------
| GET STUDENT PROFILE
|--------------------------------------------------------------------------
*/

$studentStmt = $pdo->prepare(
    '
    SELECT
        student_id,
        university_name,
        department,
        academic_level
    FROM students
    WHERE user_id = ?
    LIMIT 1
    '
);

$studentStmt->execute([
    $userId
]);

$student = $studentStmt->fetch();

$studentId = $student
    ? (int) $student['student_id']
    : 0;


/*
|--------------------------------------------------------------------------
| DEFAULT STATISTICS
|--------------------------------------------------------------------------
*/

$totalApplications = 0;
$submittedApplications = 0;
$shortlistedApplications = 0;
$selectedApplications = 0;

$totalOpportunities = 0;


/*
|--------------------------------------------------------------------------
| APPLICATION STATISTICS
|--------------------------------------------------------------------------
*/

if ($studentId > 0) {

    $applicationStatsStmt = $pdo->prepare(
        '
        SELECT

            COUNT(*) AS total_applications,

            SUM(
                CASE
                    WHEN status = "submitted" THEN 1
                    ELSE 0
                END
            ) AS submitted_count,

            SUM(
                CASE
                    WHEN status IN ("shortlisted", "interview") THEN 1
                    ELSE 0
                END
            ) AS shortlisted_count,

            SUM(
                CASE
                    WHEN status = "selected" THEN 1
                    ELSE 0
                END
            ) AS selected_count

        FROM applications

        WHERE student_id = ?
        '
    );

    $applicationStatsStmt->execute([
        $studentId
    ]);

    $applicationStats = $applicationStatsStmt->fetch();

    if ($applicationStats) {

        $totalApplications = (int) (
            $applicationStats['total_applications']
            ?? 0
        );

        $submittedApplications = (int) (
            $applicationStats['submitted_count']
            ?? 0
        );

        $shortlistedApplications = (int) (
            $applicationStats['shortlisted_count']
            ?? 0
        );

        $selectedApplications = (int) (
            $applicationStats['selected_count']
            ?? 0
        );
    }
}


/*
|--------------------------------------------------------------------------
| OPPORTUNITY STATISTICS
|--------------------------------------------------------------------------
*/

$opportunityStatsStmt = $pdo->query(
    '
    SELECT
        COUNT(*) AS total_opportunities
    FROM opportunities
    WHERE status = "open"
    '
);

$opportunityStats = $opportunityStatsStmt->fetch();

if ($opportunityStats) {

    $totalOpportunities = (int) (
        $opportunityStats['total_opportunities']
        ?? 0
    );
}


/*
|--------------------------------------------------------------------------
| GET RECENT NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$notificationStmt = $pdo->prepare(
    '
    SELECT
        notification_id,
        title,
        message,
        type,
        is_read,
        created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 4
    '
);

$notificationStmt->execute([
    $userId
]);

$recentNotifications = $notificationStmt->fetchAll();


/*
|--------------------------------------------------------------------------
| UNREAD NOTIFICATION COUNT
|--------------------------------------------------------------------------
*/

$unreadNotificationStmt = $pdo->prepare(
    '
    SELECT COUNT(*) AS unread_count
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
    '
);

$unreadNotificationStmt->execute([
    $userId
]);

$unreadNotificationData =
    $unreadNotificationStmt->fetch();

$unreadNotifications = (int) (
    $unreadNotificationData['unread_count']
    ?? 0
);


/*
|--------------------------------------------------------------------------
| NOTIFICATION ICON HELPER
|--------------------------------------------------------------------------
*/

function getNotificationIconClass(string $type): string
{
    switch (strtolower($type)) {

        case 'application':
            return 'fa-solid fa-file-circle-check';

        case 'opportunity':
            return 'fa-solid fa-briefcase';

        case 'interview':
            return 'fa-solid fa-calendar-check';

        case 'selected':
            return 'fa-solid fa-circle-check';

        case 'rejected':
            return 'fa-solid fa-circle-xmark';

        case 'system':
        default:
            return 'fa-solid fa-bell';
    }
}


/*
|--------------------------------------------------------------------------
| FORMAT NOTIFICATION DATE
|--------------------------------------------------------------------------
*/

function formatNotificationDate(string $date): string
{
    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return 'Recently';
    }

    $today = strtotime('today');
    $yesterday = strtotime('yesterday');

    if ($timestamp >= $today) {
        return 'Today, ' . date('h:i A', $timestamp);
    }

    if ($timestamp >= $yesterday) {
        return 'Yesterday, ' . date('h:i A', $timestamp);
    }

    return date('d M Y', $timestamp);
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
        Student Dashboard | CareerBridge
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


    <!-- DASHBOARD SPECIFIC STYLES -->

    <style>

        /* =====================================
           DASHBOARD PAGE
        ====================================== */

        .dashboard-page {
            width: 100%;
        }


        /* =====================================
           STATISTICS
        ====================================== */

        .dashboard-page .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }


        .dashboard-page .stat-card {
            min-height: 130px;
            display: flex;
            align-items: center;
            gap: 18px;
        }


        .dashboard-page .stat-card h2 {
            margin: 8px 0 0;
            font-size: 26px;
        }


        .dashboard-page .stat-card p {
            margin: 0;
        }


        /* =====================================
           DASHBOARD GRID
        ====================================== */

        .dashboard-page .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 24px;
            align-items: start;
        }


        .dashboard-page .dashboard-column {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }


        /* =====================================
           CONTENT CARDS
        ====================================== */

        .dashboard-page .content-card {
            margin-bottom: 0;
        }


        .dashboard-page .section-heading {
            margin-bottom: 24px;
        }


        /* =====================================
           APPLICATION META
        ====================================== */

        .dashboard-page .application-meta {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 5px;
        }


        .dashboard-page .application-meta > div {
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }


        .dashboard-page .application-meta .meta-label {
            display: block;
            margin-bottom: 8px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
        }


        .dashboard-page .application-meta strong {
            display: block;
            color: #1e293b;
            font-size: 19px;
            line-height: 1.4;
            word-break: break-word;
        }


        /* =====================================
           FORM ACTIONS
        ====================================== */

        .dashboard-page .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
        }


        .dashboard-page .form-actions .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }


        /* =====================================
           QUICK ACCESS
        ====================================== */

        .dashboard-page .quick-access-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }


        .dashboard-page .quick-access-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            padding: 18px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            text-decoration: none;
            color: #1e293b;
            transition:
                transform 0.2s ease,
                box-shadow 0.2s ease,
                border-color 0.2s ease;
        }


        .dashboard-page .quick-access-card:hover {
            transform: translateY(-3px);
            border-color: #c7d2fe;
            box-shadow:
                0 8px 20px rgba(15, 23, 42, 0.08);
        }


        .dashboard-page .quick-access-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
            border-radius: 10px;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 17px;
        }


        .dashboard-page .quick-access-card strong {
            margin-bottom: 5px;
            font-size: 14px;
        }


        .dashboard-page .quick-access-card span {
            color: #64748b;
            font-size: 12px;
        }


        /* =====================================
           NOTIFICATIONS
        ====================================== */

        .dashboard-page .dashboard-notification-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }


        .dashboard-page .dashboard-notification-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
        }


        .dashboard-page .dashboard-notification-item.unread {
            background: #f8faff;
            border-color: #c7d2fe;
        }


        .dashboard-page .dashboard-notification-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #eef2ff;
            color: #4f46e5;
        }


        .dashboard-page .dashboard-notification-content {
            flex: 1;
            min-width: 0;
        }


        .dashboard-page .dashboard-notification-content h4 {
            margin: 0 0 6px;
            color: #1e293b;
            font-size: 14px;
        }


        .dashboard-page .dashboard-notification-content p {
            margin: 0 0 7px;
            color: #64748b;
            font-size: 13px;
            line-height: 1.5;
        }


        .dashboard-page .dashboard-notification-content small {
            color: #94a3b8;
            font-size: 11px;
        }


        .dashboard-page .notification-status-dot {
            width: 8px;
            height: 8px;
            flex-shrink: 0;
            margin-top: 6px;
            border-radius: 50%;
            background: #6366f1;
        }


        /* =====================================
           EMPTY STATE
        ====================================== */

        .dashboard-page .dashboard-empty-state {
            padding: 35px 20px;
            text-align: center;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            background: #f8fafc;
        }


        .dashboard-page .dashboard-empty-state .empty-icon {
            width: 58px;
            height: 58px;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #eef2ff;
            color: #4f46e5;
            font-size: 22px;
        }


        .dashboard-page .dashboard-empty-state h3 {
            margin: 0 0 8px;
            color: #1e293b;
            font-size: 17px;
        }


        .dashboard-page .dashboard-empty-state p {
            margin: 0 0 20px;
            color: #64748b;
            line-height: 1.6;
        }


        /* =====================================
           ACADEMIC INFORMATION
        ====================================== */

        .dashboard-page .academic-info-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }


        .dashboard-page .academic-info-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
        }


        .dashboard-page .academic-info-icon {
            width: 42px;
            height: 42px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            background: #eef2ff;
            color: #4f46e5;
        }


        .dashboard-page .academic-info-content {
            min-width: 0;
        }


        .dashboard-page .academic-info-content span {
            display: block;
            margin-bottom: 4px;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }


        .dashboard-page .academic-info-content strong {
            display: block;
            color: #1e293b;
            font-size: 14px;
            word-break: break-word;
        }


        /* =====================================
           RESPONSIVE
        ====================================== */

        @media (max-width: 1200px) {

            .dashboard-page .stats-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }


            .dashboard-page .dashboard-grid {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 700px) {

            .dashboard-page .application-meta {
                grid-template-columns: 1fr;
            }


            .dashboard-page .quick-access-grid {
                grid-template-columns: 1fr;
            }

        }


        @media (max-width: 600px) {

            .dashboard-page .stats-grid {
                grid-template-columns: 1fr;
            }


            .dashboard-page .form-actions {
                flex-direction: column;
            }


            .dashboard-page .form-actions .btn {
                width: 100%;
            }

        }

    </style>

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


        <!-- MENU LABEL -->

        <p class="menu-label">
            MAIN MENU
        </p>


        <!-- NAVIGATION -->

        <nav class="sidebar-nav">


            <a
                href="dashboard.php"
                class="active"
            >

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


            <a href="interviews.php">

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

    <main class="main-content dashboard-page">


        <!-- =====================================
             PAGE HEADER
        ====================================== -->

        <div class="page-header">


            <div>


                <p class="breadcrumb">
                    STUDENT PORTAL / DASHBOARD
                </p>


                <h1>
                    Student Dashboard
                </h1>


                <p class="page-subtitle">

                    Welcome back,
                    <strong>
                        <?= e($userName) ?>
                    </strong>

                    <br>

                    Manage your career journey and stay updated with new opportunities.

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
             STATISTICS
        ====================================== -->

        <section class="stats-grid">


            <!-- TOTAL APPLICATIONS -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-file-circle-check"></i>

                </div>


                <div>

                    <p>
                        Total Applications
                    </p>

                    <h2>
                        <?= $totalApplications ?>
                    </h2>

                </div>


            </div>



            <!-- SUBMITTED -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-paper-plane"></i>

                </div>


                <div>

                    <p>
                        Submitted
                    </p>

                    <h2>
                        <?= $submittedApplications ?>
                    </h2>

                </div>


            </div>



            <!-- SHORTLISTED -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-user-check"></i>

                </div>


                <div>

                    <p>
                        Shortlisted / Interview
                    </p>

                    <h2>
                        <?= $shortlistedApplications ?>
                    </h2>

                </div>


            </div>



            <!-- SELECTED -->

            <div class="stat-card">


                <div class="stat-icon">

                    <i class="fa-solid fa-circle-check"></i>

                </div>


                <div>

                    <p>
                        Selected
                    </p>

                    <h2>
                        <?= $selectedApplications ?>
                    </h2>

                </div>


            </div>


        </section>



        <!-- =====================================
             DASHBOARD GRID
        ====================================== -->

        <section class="dashboard-grid">


            <!-- =====================================
                 LEFT COLUMN
            ====================================== -->

            <div class="dashboard-column">


                <!-- APPLICATION SUMMARY -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                OVERVIEW
                            </p>


                            <h2>
                                Application Summary
                            </h2>


                            <p>
                                Track the current progress of your applications.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-chart-pie"></i>

                        </div>


                    </div>


                    <div class="application-meta">


                        <div>

                            <span class="meta-label">
                                TOTAL APPLICATIONS
                            </span>

                            <strong>
                                <?= $totalApplications ?>
                            </strong>

                        </div>



                        <div>

                            <span class="meta-label">
                                SUBMITTED
                            </span>

                            <strong>
                                <?= $submittedApplications ?>
                            </strong>

                        </div>



                        <div>

                            <span class="meta-label">
                                SHORTLISTED / INTERVIEW
                            </span>

                            <strong>
                                <?= $shortlistedApplications ?>
                            </strong>

                        </div>



                        <div>

                            <span class="meta-label">
                                SELECTED
                            </span>

                            <strong>
                                <?= $selectedApplications ?>
                            </strong>

                        </div>


                    </div>


                    <div class="form-actions">

                        <a
                            href="applications.php"
                            class="btn btn-secondary"
                        >

                            View My Applications

                            <i class="fa-solid fa-arrow-right"></i>

                        </a>

                    </div>


                </section>



                <!-- AVAILABLE OPPORTUNITIES -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                CAREER
                            </p>


                            <h2>
                                Available Opportunities
                            </h2>


                            <p>
                                Explore open internships and job opportunities.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-briefcase"></i>

                        </div>


                    </div>


                    <div class="application-meta">


                        <div>

                            <span class="meta-label">
                                OPEN OPPORTUNITIES
                            </span>

                            <strong>
                                <?= $totalOpportunities ?>
                            </strong>

                        </div>


                        <div>

                            <span class="meta-label">
                                OPPORTUNITY TYPES
                            </span>

                            <strong>
                                Jobs & Internships
                            </strong>

                        </div>


                    </div>


                    <div class="form-actions">

                        <a
                            href="opportunities.php"
                            class="btn btn-primary"
                        >

                            <i class="fa-solid fa-magnifying-glass"></i>

                            Browse Opportunities

                        </a>

                    </div>


                </section>



                <!-- QUICK ACCESS -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                QUICK ACCESS
                            </p>


                            <h2>
                                Manage Your Career
                            </h2>


                            <p>
                                Quickly access your essential career tools.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-bolt"></i>

                        </div>


                    </div>


                    <div class="quick-access-grid">


                        <a
                            href="profile.php"
                            class="quick-access-card"
                        >

                            <div class="quick-access-icon">

                                <i class="fa-solid fa-user"></i>

                            </div>

                            <strong>
                                Career Profile
                            </strong>

                            <span>
                                Update your information
                            </span>

                        </a>



                        <a
                            href="skills.php"
                            class="quick-access-card"
                        >

                            <div class="quick-access-icon">

                                <i class="fa-solid fa-bolt"></i>

                            </div>

                            <strong>
                                My Skills
                            </strong>

                            <span>
                                Manage your skills
                            </span>

                        </a>



                        <a
                            href="resume.php"
                            class="quick-access-card"
                        >

                            <div class="quick-access-icon">

                                <i class="fa-solid fa-file-lines"></i>

                            </div>

                            <strong>
                                Resume / CV
                            </strong>

                            <span>
                                Manage your resume
                            </span>

                        </a>


                    </div>


                </section>


            </div>



            <!-- =====================================
                 RIGHT COLUMN
            ====================================== -->

            <div class="dashboard-column">


                <!-- NOTIFICATIONS -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                UPDATES
                            </p>


                            <h2>
                                Recent Notifications
                            </h2>


                            <p>
                                Stay updated with important career activities.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-bell"></i>

                        </div>


                    </div>


                    <?php if (!$recentNotifications): ?>


                        <div class="dashboard-empty-state">


                            <div class="empty-icon">

                                <i class="fa-solid fa-bell-slash"></i>

                            </div>


                            <h3>
                                You're All Caught Up
                            </h3>


                            <p>
                                No notifications are available at the moment.
                            </p>


                        </div>


                    <?php else: ?>


                        <div class="dashboard-notification-list">


                            <?php foreach ($recentNotifications as $notification): ?>


                                <?php

                                $isUnread =
                                    (int) $notification['is_read'] === 0;

                                ?>


                                <article
                                    class="dashboard-notification-item <?= $isUnread
                                        ? 'unread'
                                        : ''
                                    ?>"
                                >


                                    <div class="dashboard-notification-icon">

                                        <i
                                            class="<?= e(
                                                getNotificationIconClass(
                                                    (string) (
                                                        $notification['type']
                                                        ?? 'system'
                                                    )
                                                )
                                            ) ?>"
                                        ></i>

                                    </div>


                                    <div class="dashboard-notification-content">


                                        <h4>

                                            <?= e(
                                                $notification['title']
                                                ?? 'Notification'
                                            ) ?>

                                        </h4>


                                        <p>

                                            <?= e(
                                                $notification['message']
                                                ?? ''
                                            ) ?>

                                        </p>


                                        <small>

                                            <?= e(
                                                formatNotificationDate(
                                                    (string) (
                                                        $notification['created_at']
                                                        ?? ''
                                                    )
                                                )
                                            ) ?>

                                        </small>


                                    </div>


                                    <?php if ($isUnread): ?>

                                        <span
                                            class="notification-status-dot"
                                            title="Unread notification"
                                        ></span>

                                    <?php endif; ?>


                                </article>


                            <?php endforeach; ?>


                        </div>


                    <?php endif; ?>


                    <div class="form-actions">

                        <a
                            href="notifications.php"
                            class="btn btn-secondary"
                        >

                            <i class="fa-solid fa-bell"></i>

                            View Notifications

                            <?php if ($unreadNotifications > 0): ?>

                                (<?= $unreadNotifications ?>)

                            <?php endif; ?>

                        </a>

                    </div>


                </section>



                <!-- ACADEMIC INFORMATION -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                PROFILE
                            </p>


                            <h2>
                                Academic Information
                            </h2>


                            <p>
                                Your current academic details.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-graduation-cap"></i>

                        </div>


                    </div>


                    <?php if ($student): ?>


                        <div class="academic-info-list">


                            <!-- UNIVERSITY -->

                            <div class="academic-info-item">


                                <div class="academic-info-icon">

                                    <i class="fa-solid fa-building-columns"></i>

                                </div>


                                <div class="academic-info-content">

                                    <span>
                                        UNIVERSITY
                                    </span>

                                    <strong>

                                        <?= e(
                                            $student['university_name']
                                            ?? 'Not provided'
                                        ) ?>

                                    </strong>

                                </div>


                            </div>



                            <!-- DEPARTMENT -->

                            <div class="academic-info-item">


                                <div class="academic-info-icon">

                                    <i class="fa-solid fa-book"></i>

                                </div>


                                <div class="academic-info-content">

                                    <span>
                                        DEPARTMENT
                                    </span>

                                    <strong>

                                        <?= e(
                                            $student['department']
                                            ?? 'Not provided'
                                        ) ?>

                                    </strong>

                                </div>


                            </div>



                            <!-- ACADEMIC LEVEL -->

                            <div class="academic-info-item">


                                <div class="academic-info-icon">

                                    <i class="fa-solid fa-layer-group"></i>

                                </div>


                                <div class="academic-info-content">

                                    <span>
                                        ACADEMIC LEVEL
                                    </span>

                                    <strong>

                                        <?= e(
                                            $student['academic_level']
                                            ?? 'Not provided'
                                        ) ?>

                                    </strong>

                                </div>


                            </div>


                        </div>


                        <div class="form-actions">

                            <a
                                href="profile.php"
                                class="btn btn-secondary"
                            >

                                Update Profile

                                <i class="fa-solid fa-arrow-right"></i>

                            </a>

                        </div>


                    <?php else: ?>


                        <div class="dashboard-empty-state">


                            <div class="empty-icon">

                                <i class="fa-solid fa-user-pen"></i>

                            </div>


                            <h3>
                                Profile Incomplete
                            </h3>


                            <p>
                                Complete your student profile to unlock
                                the full CareerBridge experience.
                            </p>


                            <a
                                href="profile.php"
                                class="btn btn-primary"
                            >

                                <i class="fa-solid fa-plus"></i>

                                Complete Profile

                            </a>


                        </div>


                    <?php endif; ?>


                </section>



                <!-- INTERVIEW MANAGEMENT -->

                <section class="content-card">


                    <div class="section-heading">


                        <div>

                            <p class="section-label">
                                INTERVIEWS
                            </p>


                            <h2>
                                Interview Management
                            </h2>


                            <p>
                                View and manage your scheduled interviews.
                            </p>

                        </div>


                        <div class="section-icon">

                            <i class="fa-solid fa-calendar-check"></i>

                        </div>


                    </div>


                    <div class="form-actions">

                        <a
                            href="interviews.php"
                            class="btn btn-primary"
                        >

                            <i class="fa-solid fa-calendar-check"></i>

                            View My Interviews

                        </a>

                    </div>


                </section>


            </div>


        </section>



        <!-- =====================================
             FOOTER
        ====================================== -->

        <footer class="page-footer">

            <span>

                &copy; <?= date('Y') ?>

                CareerBridge — University Career Management Platform

            </span>

        </footer>


    </main>


</div>


</body>

</html>